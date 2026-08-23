<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Payments;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_REST_Request;
use wpdb;

use function BrotherTours\OperationsApi\error;
use function BrotherTours\OperationsApi\response;

/**
 * Pay Now on guest itinerary share pages (1.3.0).
 *
 * Two methods, both config-gated (option `btoa_payments_config`, set via the
 * dashboard/ops — no credentials in code):
 *  - bcel:   BCEL Landing Page Hosted Checkout v2 (Lao PDR). Signed HTML form,
 *            transient + auto-submitting bridge page at /?bt_pay={uuid}, then a
 *            signature-verified webhook marks the payment paid. Same field set
 *            and HMAC-SHA256/base64 signature as the client-supplied test.php
 *            and the wpistic-tour-manager BcelGateway.
 *  - crypto: wallet-address instructions (network + address + exact amount +
 *            payment reference). Non-custodial; confirmed on-chain manually.
 *
 * Amounts are ALWAYS computed server-side from the itinerary pricing row —
 * the client never sends an amount. Token URLs stay unguessable (32-hex).
 */
final class PaymentsController {

	private const DB_VERSION = '1.3.0';

	private const LIVE_ENDPOINT = 'https://bcel.la:9094/';
	private const TEST_ENDPOINT = 'https://bcel.la:9094/test';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'init', array( $this, 'maybe_install_table' ) );
		add_action( 'template_redirect', array( $this, 'bridge_page' ) );
	}

	public function maybe_install_table(): void {
		// Seed first (before the version gate) so the config lands even if the
		// operator drops the file a few minutes after the plugin update.
		$seed = trailingslashit( wp_upload_dir()['basedir'] ) . 'btoa-payments-seed.json';
		if ( file_exists( $seed ) ) {
			$cfg = json_decode( (string) file_get_contents( $seed ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			if ( is_array( $cfg ) && isset( $cfg['bcel']['secret_key'] ) && '' !== (string) $cfg['bcel']['secret_key'] ) {
				update_option( 'btoa_payments_config', $cfg, false );
			}
			@unlink( $seed ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}

		if ( get_option( 'btoa_payments_db_version' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			itinerary_id BIGINT UNSIGNED NOT NULL,
			token_hash CHAR(64) NOT NULL DEFAULT '',
			purpose VARCHAR(10) NOT NULL DEFAULT 'deposit',
			method VARCHAR(20) NOT NULL DEFAULT 'bcel',
			amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			currency VARCHAR(3) NOT NULL DEFAULT 'USD',
			reference VARCHAR(64) NOT NULL DEFAULT '',
			transaction_uuid VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			provider_ref VARCHAR(64) NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY reference (reference),
			KEY transaction_uuid (transaction_uuid),
			KEY itinerary_id (itinerary_id),
			KEY status (status)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'btoa_payments_db_version', self::DB_VERSION );
	}

	public function routes(): void {
		register_rest_route(
			BTOA_NAMESPACE,
			'/itinerary-pay/(?P<token>[a-f0-9]{32})',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/itineraries/webhook/bcel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'webhook' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/itineraries/(?P<id>\d+)/payments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_for_itinerary' ),
				'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Config                                                              */

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'btoa_itinerary_payments';
	}

	/** @return array<string,mixed> */
	private static function config(): array {
		$cfg = get_option( 'btoa_payments_config', array() );
		return is_array( $cfg ) ? $cfg : array();
	}

	private static function cfg_bcel( string $key, $default = '' ) {
		$bcel = self::config()['bcel'] ?? array();
		return is_array( $bcel ) ? ( $bcel[ $key ] ?? $default ) : $default;
	}

	private static function cfg_crypto( string $key, $default = '' ) {
		$crypto = self::config()['crypto'] ?? array();
		return is_array( $crypto ) ? ( $crypto[ $key ] ?? $default ) : $default;
	}

	private static function bcel_configured(): bool {
		return (int) self::cfg_bcel( 'enabled', 0 ) === 1
			&& '' !== (string) self::cfg_bcel( 'profile_id' )
			&& '' !== (string) self::cfg_bcel( 'access_key' )
			&& '' !== (string) self::cfg_bcel( 'secret_key' );
	}

	private static function crypto_configured(): bool {
		return (int) self::cfg_crypto( 'enabled', 0 ) === 1
			&& '' !== (string) self::cfg_crypto( 'wallet_address' );
	}

	private static function notify_email(): string {
		$email = (string) ( self::config()['notify_email'] ?? '' );
		return '' !== $email ? $email : 'enquiry@brothertours.com';
	}

	private static function endpoint(): string {
		return 'live' === (string) self::cfg_bcel( 'mode', 'test' ) ? self::LIVE_ENDPOINT : self::TEST_ENDPOINT;
	}

	/* ------------------------------------------------------------------ */
	/* Public: create a payment (guest-facing)                             */

	public function create( WP_REST_Request $request ) {
		$token   = (string) $request['token'];
		$purpose = (string) $request->get_param( 'purpose' );
		$method  = (string) $request->get_param( 'method' );
		if ( ! in_array( $purpose, array( 'deposit', 'full' ), true ) ) {
			return error( 'btoa_pay_bad_purpose', __( 'Invalid payment purpose.', 'brother-tours-operations-api' ), 400 );
		}
		if ( ! in_array( $method, array( 'bcel', 'crypto' ), true ) ) {
			return error( 'btoa_pay_bad_method', __( 'Invalid payment method.', 'brother-tours-operations-api' ), 400 );
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT id, title, guest_name, status, days, pricing, share_token FROM ' . self::itineraries_table() . ' WHERE share_token = %s', $token )
		); // phpcs:ignore
		if ( null === $row ) {
			return error( 'btoa_pay_not_found', __( 'This payment link is not available.', 'brother-tours-operations-api' ), 404 );
		}

		$pricing = json_decode( (string) $row->pricing, true );
		if ( ! is_array( $pricing ) ) {
			$pricing = array();
		}
		$total    = ( is_numeric( $pricing['perPerson'] ?? null ) && is_numeric( $pricing['groupSize'] ?? null ) ) ? (float) $pricing['perPerson'] * (float) $pricing['groupSize'] : null;
		$currency = strtoupper( (string) ( $pricing['currency'] ?? 'USD' ) );
		if ( null === $total || $total <= 0 ) {
			return error( 'btoa_pay_no_pricing', __( 'This itinerary has no pricing yet.', 'brother-tours-operations-api' ), 422 );
		}
		$deposit = round( $total * ( (float) ( $pricing['depositPercent'] ?? 30 ) / 100 ), 2 );
		$amount  = 'deposit' === $purpose ? $deposit : round( $total, 2 );

		$reference        = sprintf( 'BT-ITM-%d-%s-%s', (int) $row->id, 'deposit' === $purpose ? 'DEP' : 'FUL', strtoupper( wp_generate_password( 4, false, false ) ) );
		$transaction_uuid = sanitize_key( $reference ) . '-' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$now              = current_time( 'mysql', true );

		$wpdb->insert(
			self::table(),
			array(
				'itinerary_id'    => (int) $row->id,
				'token_hash'      => hash( 'sha256', $token ),
				'purpose'         => $purpose,
				'method'          => $method,
				'amount'          => $amount,
				'currency'        => $currency,
				'reference'       => $reference,
				'transaction_uuid'=> $transaction_uuid,
				'status'          => 'pending',
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		); // phpcs:ignore

		if ( 'crypto' === $method ) {
			if ( ! self::crypto_configured() ) {
				return error( 'btoa_crypto_not_configured', __( 'Crypto payments are not available yet — please use card payment.', 'brother-tours-operations-api' ), 503 );
			}
			self::notify(
				sprintf( '[%s] Crypto payment initiated — %s %s (%s)', 'INIT', $currency, number_format( (float) $amount, 2 ), $reference ),
				sprintf( "Guest started a crypto payment on itinerary #%d (%s).\nAmount: %s %s (%s)\nReference: %s\nWatch the wallet and confirm on-chain, then mark paid.", (int) $row->id, (string) $row->title, $currency, number_format( (float) $amount, 2 ), $purpose, $reference )
			);
			return response( array(
				'type'         => 'instructions',
				'reference'    => $reference,
				'amount'       => $amount,
				'currency'     => $currency,
				'wallet'       => (string) self::cfg_crypto( 'wallet_address' ),
				'network'      => (string) self::cfg_crypto( 'network', 'BEP-20 (Binance Smart Chain)' ),
				'note'         => (string) self::cfg_crypto( 'note' ),
			) );
		}

		if ( ! self::bcel_configured() ) {
			return error( 'btoa_bcel_not_configured', __( 'Card payments are being set up — please try again shortly.', 'brother-tours-operations-api' ), 503 );
		}

		$params = self::bcel_signed_params( $row, $reference, $transaction_uuid, $amount, $currency );

		set_transient(
			'btoa_bcel_pay_' . $transaction_uuid,
			array(
				'endpoint' => self::endpoint(),
				'params'   => $params,
				'reference'=> $reference,
				'amount'   => $amount,
				'currency' => $currency,
			),
			30 * MINUTE_IN_SECONDS
		);

		self::notify(
			sprintf( '[INIT] BCEL payment initiated — %s %s (%s)', $currency, number_format( (float) $amount, 2 ), $reference ),
			sprintf( "Guest started a BCEL payment on itinerary #%d (%s).\nAmount: %s %s (%s)\nReference: %s\nMode: %s", (int) $row->id, (string) $row->title, $currency, number_format( (float) $amount, 2 ), $purpose, $reference, (string) self::cfg_bcel( 'mode', 'test' ) )
		);

		return response( array(
			'type'     => 'redirect',
			'url'      => add_query_arg( 'bt_pay', rawurlencode( $transaction_uuid ), home_url( '/' ) ),
			'reference'=> $reference,
		) );
	}

	/** @param object $row Itinerary row. @return array<string,string> */
	private static function bcel_signed_params( $row, string $reference, string $transaction_uuid, float $amount, string $currency ): array {
		$name = preg_split( '/\s+/', trim( (string) $row->guest_name ), 2 );
		$params = array(
			'profile_id'                  => (string) self::cfg_bcel( 'profile_id' ),
			'access_key'                  => (string) self::cfg_bcel( 'access_key' ),
			'locale'                      => 'en',
			'lang'                        => 'en',
			'transaction_type'            => 'authorization',
			'transaction_uuid'            => $transaction_uuid,
			'reference_number'            => $reference,
			'device_fingerprint_id'       => (string) wp_rand( 100000, 999999 ),
			'amount'                      => number_format( $amount, 2, '.', '' ),
			'currency'                    => $currency,
			'signed_date_time'            => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'bill_to_forename'            => (string) ( $name[0] ?? 'Guest' ),
			'bill_to_surname'             => (string) ( $name[1] ?? 'Traveller' ),
			'bill_to_email'               => '',
			'bill_to_phone'               => '',
			'bill_to_address_city'        => 'Vientiane',
			'bill_to_address_state'       => 'Vientiane',
			'bill_to_address_line1'       => '-',
			'bill_to_address_line2'       => '-',
			'bill_to_address_postal_code' => '01000',
			'bill_to_address_country'     => 'la',
			'merchant_defined_data1'      => 'itinerary-payment',
			'merchant_defined_data2'      => substr( sprintf( '%s — %s', $row->title, $reference ), 0, 100 ),
		);
		$params['signed_field_names'] = implode( ',', array_keys( $params ) );
		$params['signature']          = self::sign( $params, $params['signed_field_names'], (string) self::cfg_bcel( 'secret_key' ) );
		return $params;
	}

	/** base64(hmac_sha256("f=v,f=v,...", secret)) over signed_field_names order — per BCEL guide/test.php. @param array<string,string> $params */
	private static function sign( array $params, string $signed_field_names, string $secret ): string {
		$pairs = array();
		foreach ( explode( ',', $signed_field_names ) as $field ) {
			$pairs[] = $field . '=' . ( $params[ $field ] ?? '' );
		}
		return base64_encode( hash_hmac( 'sha256', implode( ',', $pairs ), $secret, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/* ------------------------------------------------------------------ */
	/* BCEL bridge page: /?bt_pay={uuid} auto-submits the signed form      */

	public function bridge_page(): void {
		$uuid = isset( $_GET['bt_pay'] ) ? sanitize_text_field( wp_unslash( $_GET['bt_pay'] ) ) : '';
		if ( '' === $uuid || ! preg_match( '/^[a-z0-9-]{6,80}$/', $uuid ) ) {
			return;
		}
		$data = get_transient( 'btoa_bcel_pay_' . $uuid );
		if ( ! is_array( $data ) || empty( $data['params'] ) || empty( $data['endpoint'] ) ) {
			status_header( 410 );
			nocache_headers();
			exit( '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Link expired — Brother Tours</title></head><body style="font-family:system-ui;display:grid;place-items:center;min-height:100vh;background:#f7f5f0;color:#1c2b2f"><div style="text-align:center"><h1>This payment link expired</h1><p style="color:#5c6b6f">Please open your itinerary link again and start a new payment.</p></div></body></html>' );
		}
		delete_transient( 'btoa_bcel_pay_' . $uuid );

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		$endpoint = htmlspecialchars( (string) $data['endpoint'], ENT_QUOTES, 'UTF-8' );
		$money    = htmlspecialchars( (string) $data['currency'] . ' ' . number_format( (float) $data['amount'], 2 ), ENT_QUOTES, 'UTF-8' );
		echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><meta name=\"robots\" content=\"noindex,nofollow\"><title>Secure payment — Brother Tours</title><style>body{font-family:Inter,system-ui,sans-serif;background:#f7f5f0;color:#1c2b2f;display:grid;place-items:center;min-height:100vh;margin:0}.box{background:#fff;border:1px solid #e4dfd4;border-radius:14px;padding:2rem;text-align:center;box-shadow:0 4px 14px rgba(28,43,47,.05)}.amt{color:#b3924f;font-weight:700;font-size:1.4rem}button{background:#b3924f;color:#fff;border:0;border-radius:999px;padding:.7rem 1.8rem;font-size:1rem;font-weight:600;cursor:pointer;margin-top:.6rem}</style></head><body><div class=\"box\"><p>Redirecting to secure BCEL payment…</p><p class=\"amt\">{$money}</p><form id=\"f\" method=\"post\" action=\"{$endpoint}\">";
		foreach ( $data['params'] as $key => $val ) {
			$k = htmlspecialchars( (string) $key, ENT_QUOTES, 'UTF-8' );
			$v = htmlspecialchars( (string) $val, ENT_QUOTES, 'UTF-8' );
			echo "<input type=\"hidden\" name=\"{$k}\" value=\"{$v}\">";
		}
		echo '</form><button onclick="document.getElementById(\'f\').submit()">Continue to payment</button><p style="color:#5c6b6f;font-size:.8rem">Do not close this page.</p></div><script>setTimeout(function(){document.getElementById("f").submit();},600);</script></body></html>';
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Webhook: BCEL posts the decision here                               */

	public function webhook( WP_REST_Request $request ) {
		$raw = (string) $request->get_body();
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_parse_str( $raw, $parsed );
			$data = $parsed;
		}
		if ( ! is_array( $data ) || empty( $data ) ) {
			return response( array( 'ok' => true ) ); // Never leak state; never 500 to the bank.
		}

		$secret   = (string) self::cfg_bcel( 'secret_key' );
		$verified = false;
		if ( '' !== $secret && ! empty( $data['signature'] ) ) {
			$pairs = array();
			foreach ( explode( ',', (string) ( $data['signed_field_names'] ?? '' ) ) as $field ) {
				$pairs[] = $field . '=' . ( $data[ $field ] ?? '' );
			}
			$expected = base64_encode( hash_hmac( 'sha256', implode( ',', $pairs ), $secret, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$verified = hash_equals( $expected, (string) $data['signature'] );
		}

		$reference = (string) ( $data['req_reference_number'] ?? '' );
		$decision  = strtoupper( (string) ( $data['decision'] ?? 'unknown' ) );
		$paid      = $verified
			&& 'ACCEPT' === $decision
			&& '100' === (string) ( $data['reason_code'] ?? '' )
			&& '00' === (string) ( $data['auth_response'] ?? '' )
			&& '' !== (string) ( $data['auth_code'] ?? '' );

		if ( '' !== $reference ) {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE reference = %s ORDER BY id DESC LIMIT 1', $reference ) ); // phpcs:ignore
			if ( null !== $row ) {
				$wpdb->update(
					self::table(),
					array(
						'status'       => $paid ? 'paid' : 'failed',
						'provider_ref' => substr( (string) ( $data['transaction_id'] ?? '' ), 0, 64 ),
						'updated_at'   => current_time( 'mysql', true ),
					),
					array( 'id' => (int) $row->id )
				); // phpcs:ignore
				if ( $paid ) {
					self::notify(
						sprintf( 'PAYMENT RECEIVED — %s %s (%s)', $row->currency, number_format( (float) $row->amount, 2 ), $reference ),
						sprintf( "BCEL payment CONFIRMED for itinerary #%d.\nAmount: %s %s (%s)\nReference: %s\nBCEL transaction: %s\nPlease confirm the booking with the guest.", (int) $row->itinerary_id, $row->currency, number_format( (float) $row->amount, 2 ), $row->purpose, $reference, (string) ( $data['transaction_id'] ?? '' ) )
					);
				} else {
					self::notify(
						sprintf( 'Payment %s — %s (%s)', strtolower( $decision ), $reference, 'verified:' . ( $verified ? 'yes' : 'no' ) ),
						sprintf( "BCEL callback for reference %s: decision=%s reason_code=%s auth=%s verified=%s.", $reference, $decision, (string) ( $data['reason_code'] ?? '' ), (string) ( $data['auth_response'] ?? '' ), $verified ? 'yes' : 'no' )
					);
				}
			}
		}

		return response( array( 'ok' => true ) );
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard: payment list for an itinerary                            */

	public function list_for_itinerary( WP_REST_Request $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, purpose, method, amount, currency, reference, status, provider_ref, created_at, updated_at FROM ' . self::table() . ' WHERE itinerary_id = %d ORDER BY id DESC LIMIT 100', $id ) ); // phpcs:ignore
		return response( array( 'items' => array_map( static function ( $r ) {
			return array(
				'id' => (int) $r->id,
				'purpose' => (string) $r->purpose,
				'method' => (string) $r->method,
				'amount' => (float) $r->amount,
				'currency' => (string) $r->currency,
				'reference' => (string) $r->reference,
				'status' => (string) $r->status,
				'providerRef' => (string) ( $r->provider_ref ?? '' ),
				'createdAt' => (string) $r->created_at,
				'updatedAt' => (string) $r->updated_at,
			);
		}, is_array( $rows ) ? $rows : array() ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Guest page section (called from ItinerariesController::guest_view)  */

	public static function render_guest_section( string $token, int $itinerary_id, ?float $total, ?float $deposit, string $currency ): void {
		if ( null === $total || $total <= 0 ) {
			return;
		}
		$bcel   = self::bcel_configured();
		$crypto = self::crypto_configured();
		if ( ! $bcel && ! $crypto ) {
			return;
		}

		global $wpdb;
		$paid_deposit = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE itinerary_id = %d AND purpose = 'deposit' AND status = 'paid'", $itinerary_id ) ); // phpcs:ignore
		$paid_full    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE itinerary_id = %d AND purpose = 'full' AND status = 'paid'", $itinerary_id ) ); // phpcs:ignore

		$esc = static fn( string $v ) => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' );
		$api = esc_url_raw( rest_url( BTOA_NAMESPACE . '/itinerary-pay/' . $token ) );

		echo '<section class="card" id="pay-now"><h2 class="day"><span class="day-num">✓</span>Pay now, secure your trip</h2>';
		if ( $paid_full > 0 ) {
			echo '<p style="color:#2e7d32;font-weight:600">Payment received — thank you! Brother Tours will be in touch to confirm your booking.</p></section>';
			return;
		}
		if ( $paid_deposit > 0 ) {
			echo '<p style="color:#2e7d32;font-weight:600">Deposit received — your place is held. You can pay the remaining balance below or on arrival.</p>';
		}

		echo '<div id="pay-buttons" style="display:flex;flex-wrap:wrap;gap:.7rem;margin:.6rem 0 1rem">';
		if ( $bcel ) {
			echo '<button type="button" class="paybtn" data-purpose="deposit" style="' . self::btn() . '">Pay deposit · ' . $esc( $currency . ' ' . number_format( (float) $deposit, 0 ) ) . ' (card)</button>';
			echo '<button type="button" class="paybtn" data-purpose="full" style="' . self::btn() . '">Pay in full · ' . $esc( $currency . ' ' . number_format( (float) $total, 0 ) ) . ' (card)</button>';
		}
		if ( $crypto ) {
			echo '<button type="button" class="paybtn" data-purpose="deposit" data-method="crypto" style="' . self::btn( true ) . '">Pay deposit · crypto</button>';
			echo '<button type="button" class="paybtn" data-purpose="full" data-method="crypto" style="' . self::btn( true ) . '">Pay in full · crypto</button>';
		}
		echo '</div><p id="pay-status" style="font-size:.85rem;color:#5c6b6f"></p><div id="pay-instructions"></div>';
		echo '<script>(function(){var api=' . wp_json_encode( $api ) . ';document.querySelectorAll("#pay-now .paybtn").forEach(function(b){b.addEventListener("click",function(){var s=document.getElementById("pay-status"),i=document.getElementById("pay-instructions");s.textContent="Preparing secure payment…";i.innerHTML="";fetch(api,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({purpose:b.dataset.purpose,method:b.dataset.method||"bcel"})}).then(function(r){return r.json()}).then(function(d){if(d&&d.code){s.textContent=d.message||"Could not start payment — please try again.";return}if(d.type==="redirect"){s.textContent="Redirecting to secure payment…";window.location.href=d.url;return}s.textContent="Send the exact amount, then reply to your itinerary email — we confirm on-chain.";i.innerHTML="<div style=\u0027background:#fffdf5;border:1px solid #e4dfd4;border-radius:10px;padding:1rem;margin-top:.6rem;font-size:.9rem\u0027><p style=\u0027margin:0 0 .4rem\u0027><strong>Network:</strong> "+d.network+"</p><p style=\u0027margin:0 0 .4rem\u0027><strong>Amount:</strong> "+d.currency+" "+d.amount+"</p><p style=\u0027margin:0 0 .4rem\u0027><strong>Address:</strong> <code style=\u0027user-select:all;font-size:.85rem\u0027>"+d.wallet+"</code></p><p style=\u0027margin:0\u0027><strong>Reference:</strong> "+d.reference+"</p></div>";}).catch(function(){s.textContent="Network error — please try again.";});});});})();</script>';
		echo '</section>';
	}

	private static function btn( bool $alt = false ): string {
		return $alt
			? 'background:#fff;color:#b3924f;border:1.5px solid #b3924f;border-radius:999px;padding:.7rem 1.3rem;font-weight:600;font-size:.95rem;cursor:pointer'
			: 'background:#b3924f;color:#fff;border:0;border-radius:999px;padding:.7rem 1.3rem;font-weight:600;font-size:.95rem;cursor:pointer';
	}

	private static function itineraries_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'btoa_itineraries';
	}

	private static function notify( string $subject, string $body ): void {
		wp_mail(
			self::notify_email(),
			$subject,
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}
}

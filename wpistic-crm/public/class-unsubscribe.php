<?php
/**
 * Public unsubscribe endpoint — hit by the footer link in queued emails / SMS.
 *
 * Token is `wp_hash('g2a_unsub:'.customer_id.':'.channel)` — stable across
 * requests, no DB lookup needed to verify. GET shows confirmation page, POST
 * commits.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Unsubscribe {

	const QUERY_VAR = 'g2a_unsubscribe';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function register_query_var() {
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = self::QUERY_VAR;
				return $vars;
			}
		);
	}

	public static function build_url( $customer_id, $channel ) {
		$channel = 'sms' === $channel ? 'sms' : 'email';
		return add_query_arg(
			array(
				self::QUERY_VAR => 1,
				'customer'      => (int) $customer_id,
				'channel'       => $channel,
				'token'         => self::sign( (int) $customer_id, $channel ),
			),
			home_url( '/' )
		);
	}

	private static function sign( $customer_id, $channel ) {
		return wp_hash( sprintf( 'g2a_unsub:%d:%s', $customer_id, $channel ) );
	}

	private static function verify( $customer_id, $channel, $token ) {
		return hash_equals( self::sign( $customer_id, $channel ), (string) $token );
	}

	public static function maybe_render() {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$customer_id = (int) ( $_GET['customer'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$channel     = sanitize_key( (string) ( $_GET['channel'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token       = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $customer_id || ! in_array( $channel, array( 'email', 'sms' ), true ) || ! self::verify( $customer_id, $channel, $token ) ) {
			status_header( 403 );
			self::render_page( __( 'Invalid unsubscribe link', 'guns2ammo-crm' ), __( 'This unsubscribe link is missing required parameters or has been tampered with. Reply STOP to a text message, or contact us directly.', 'guns2ammo-crm' ), false );
			exit;
		}

		$customer = G2A_CRM_Customers_Repository::find( $customer_id );
		if ( ! $customer ) {
			status_header( 404 );
			self::render_page( __( 'Profile not found', 'guns2ammo-crm' ), __( 'We could not find a profile matching this link.', 'guns2ammo-crm' ), false );
			exit;
		}

		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] );

		if ( $is_post ) {
			self::do_unsubscribe( $customer, $channel );
			self::render_page(
				__( "You're unsubscribed", 'guns2ammo-crm' ),
				sprintf(
					/* translators: %s: channel */
					__( "We've removed you from %s communications. If you change your mind, just let staff know on your next visit.", 'guns2ammo-crm' ),
					$channel
				),
				true
			);
			exit;
		}

		// GET: show confirmation page.
		self::render_page(
			sprintf(
				/* translators: %s: channel */
				__( 'Unsubscribe from %s?', 'guns2ammo-crm' ),
				$channel
			),
			sprintf(
				/* translators: 1: customer name, 2: channel */
				__( 'Hi %1$s — click the button below to stop receiving %2$s messages from us. You can re-enable any time by asking staff.', 'guns2ammo-crm' ),
				$customer['first_name'] ?: $customer['email'] ?: __( 'there', 'guns2ammo-crm' ),
				$channel
			),
			false,
			true
		);
		exit;
	}

	private static function do_unsubscribe( $customer, $channel ) {
		global $wpdb;
		$now      = g2a_crm_now();
		$column   = 'email' === $channel ? 'email_opt_in' : 'sms_opt_in';
		$ts_col   = 'email' === $channel ? 'email_opt_out_at' : 'sms_opt_out_at';
		$wpdb->update(
			G2A_CRM_Customers_Repository::table(),
			array( $column => 0, $ts_col => $now ),
			array( 'id' => (int) $customer['id'] )
		);
		G2A_CRM_Audit_Log::record(
			'customer.unsubscribe',
			'customer',
			(int) $customer['id'],
			array( $column => (int) $customer[ $column ] ?? 1 ),
			array( $column => 0, 'channel' => $channel, 'source' => 'public_link' )
		);

		// Belt + suspenders: add to the global suppression list. The opt-in
		// flag on the customer record protects this customer; the suppression
		// list protects the same address if it shows up on a different
		// customer record (re-registrations, duplicates not yet merged).
		$address = 'email' === $channel ? (string) $customer['email'] : (string) $customer['phone'];
		if ( $address ) {
			G2A_CRM_Suppressions_Repository::add( $channel, $address, 'Customer used public unsubscribe link', 'unsubscribe' );
		}
	}

	private static function render_page( $title, $message, $success = false, $with_confirm = false ) {
		$business = (string) G2A_CRM_Settings::get( 'business_name', 'WPistic' );
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo esc_html( $title ); ?> — <?php echo esc_html( $business ); ?></title>
	<style>
		body { margin: 0; padding: 0; background: #080A0D; color: #F5F7FA; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
		.box { background: #11161C; border: 1px solid #2A333D; border-radius: 12px; padding: 40px 32px; max-width: 480px; text-align: center; }
		h1 { color: <?php echo $success ? '#33C27F' : '#D6A84F'; ?>; font-size: 22px; letter-spacing: 0.5px; margin: 0 0 12px; }
		p { color: #AAB4C0; font-size: 14px; line-height: 1.6; }
		.brand { color: #D6A84F; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; font-size: 11px; margin-bottom: 24px; }
		button { background: #D6A84F; color: #1A1409; border: none; padding: 14px 28px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 16px; }
		button:hover { background: #C09542; }
	</style>
</head>
<body>
	<div class="box">
		<div class="brand"><?php echo esc_html( $business ); ?></div>
		<h1><?php echo esc_html( $title ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>
		<?php if ( $with_confirm ) : ?>
			<form method="post">
				<button type="submit">Yes, unsubscribe me</button>
			</form>
		<?php endif; ?>
	</div>
</body>
</html>
		<?php
	}
}

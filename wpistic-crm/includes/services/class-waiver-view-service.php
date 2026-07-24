<?php
/**
 * Waiver print/PDF view.
 *
 * Phase 2 ships a print-friendly HTML view (browser's Save-as-PDF works on every
 * platform with zero dependencies). The waivers table stores the signed URL.
 * Swap in DOMPDF/TCPDF later by replacing render_html() — the public URL stays
 * the same.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Waiver_View_Service {

	const QUERY_VAR = 'g2a_waiver_view';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function register_query_var() {
		add_rewrite_endpoint( 'g2a-waiver', EP_ROOT );
	}

	public static function maybe_render() {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$id    = (int) $_GET[ self::QUERY_VAR ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $id || ! self::verify_token( $id, $token ) ) {
			// Permit logged-in staff with the right cap to view without a token.
			if ( ! current_user_can( 'g2a_crm_view_waivers' ) ) {
				status_header( 403 );
				exit( 'Forbidden' );
			}
		}

		$waiver = G2A_CRM_Waivers_Repository::find( $id );
		if ( ! $waiver ) {
			status_header( 404 );
			exit( 'Waiver not found' );
		}

		$customer = G2A_CRM_Customers_Repository::find( (int) $waiver['customer_id'] );

		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo self::render_html( $waiver, $customer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function print_view_url( $waiver_id ) {
		return add_query_arg(
			array(
				self::QUERY_VAR => (int) $waiver_id,
				'token'         => self::sign_token( (int) $waiver_id ),
			),
			home_url( '/' )
		);
	}

	private static function sign_token( $waiver_id ) {
		return wp_hash( 'g2a_waiver_view:' . $waiver_id );
	}

	private static function verify_token( $waiver_id, $token ) {
		return hash_equals( self::sign_token( $waiver_id ), (string) $token );
	}

	private static function render_html( array $waiver, $customer ) {
		$device       = $waiver['device_info'] ? json_decode( $waiver['device_info'], true ) : array();
		$typed_name   = $device['typed_name'] ?? '';
		$signature_path = $device['signature_path'] ?? '';
		$sig_data_url = '';
		if ( $signature_path && file_exists( $signature_path ) ) {
			$sig_data_url = 'data:image/png;base64,' . base64_encode( (string) file_get_contents( $signature_path ) );
		}

		$business_name = (string) G2A_CRM_Settings::get( 'business_name', 'WPistic' );

		ob_start();
		?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Waiver #<?php echo esc_html( $waiver['id'] ); ?> — <?php echo esc_html( $business_name ); ?></title>
	<style>
		* { box-sizing: border-box; }
		body { font-family: Georgia, serif; max-width: 780px; margin: 40px auto; padding: 0 24px; color: #111; line-height: 1.55; }
		header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
		h1 { margin: 0; font-size: 22px; letter-spacing: 0.5px; }
		h2 { font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin: 24px 0 8px; }
		.meta { font-size: 12px; color: #444; }
		.meta dl { display: grid; grid-template-columns: 180px 1fr; gap: 4px 12px; margin: 0; }
		.meta dt { color: #666; }
		.meta dd { margin: 0; }
		.body { font-size: 13px; }
		.body p { margin: 0 0 10px; }
		.sig { margin-top: 32px; border-top: 1px solid #aaa; padding-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
		.sig-block strong { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #555; margin-bottom: 6px; }
		.sig-img { max-width: 100%; height: 80px; object-fit: contain; border-bottom: 1px solid #111; }
		.sig-typed { font-family: "Brush Script MT", cursive; font-size: 28px; border-bottom: 1px solid #111; padding-bottom: 4px; min-height: 80px; display: flex; align-items: flex-end; }
		footer { margin-top: 40px; font-size: 11px; color: #666; border-top: 1px solid #ccc; padding-top: 12px; }
		.print-bar { background: #f5f5f5; padding: 12px; border: 1px solid #ddd; margin-bottom: 20px; text-align: right; }
		.print-bar button { padding: 8px 16px; cursor: pointer; }
		@media print { .print-bar { display: none; } body { margin: 0; } }
	</style>
</head>
<body>
	<div class="print-bar">
		<button onclick="window.print()">Print / Save as PDF</button>
	</div>

	<header>
		<div>
			<h1><?php echo esc_html( $business_name ); ?></h1>
			<div class="meta">Waiver of Liability and Release</div>
		</div>
		<div class="meta" style="text-align: right;">
			Waiver #<?php echo esc_html( $waiver['id'] ); ?><br />
			Type: <?php echo esc_html( $waiver['waiver_type'] ); ?>
		</div>
	</header>

	<section class="meta">
		<dl>
			<dt>Customer</dt>
			<dd><?php echo esc_html( trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) ) ); ?></dd>
			<dt>Email</dt>
			<dd><?php echo esc_html( $customer['email'] ?? '' ); ?></dd>
			<dt>Phone</dt>
			<dd><?php echo esc_html( $customer['phone'] ?? '' ); ?></dd>
			<dt>Date of birth</dt>
			<dd><?php echo esc_html( $customer['date_of_birth'] ?? '—' ); ?></dd>
			<dt>Signed (UTC)</dt>
			<dd><?php echo esc_html( $waiver['signed_at'] ); ?></dd>
			<dt>Expires (UTC)</dt>
			<dd><?php echo esc_html( $waiver['expires_at'] ?: 'No expiry' ); ?></dd>
			<dt>Status</dt>
			<dd><?php echo esc_html( $waiver['status'] ); ?></dd>
			<dt>IP at signing</dt>
			<dd><?php echo esc_html( $waiver['ip_address'] ); ?></dd>
		</dl>
	</section>

	<h2>Waiver Terms</h2>
	<section class="body">
		<p>I acknowledge that the use of firearms and the activities at <strong><?php echo esc_html( $business_name ); ?></strong> carry inherent risk of serious bodily injury, property damage, or death. I voluntarily accept and assume these risks.</p>
		<p>I confirm that I am legally permitted to handle firearms under all applicable federal, state, and local laws. I agree to follow all safety rules and instructions from <?php echo esc_html( $business_name ); ?> staff at all times while on the premises.</p>
		<p>I release and discharge <?php echo esc_html( $business_name ); ?>, its owners, employees, instructors, agents, and affiliated parties from any and all claims, demands, or causes of action arising out of my participation in range, training, retail, or related activities.</p>
		<p>I authorize <?php echo esc_html( $business_name ); ?> to retain a record of this waiver, including signing timestamp, IP address, and signature, for compliance and safety purposes.</p>
		<p><em>Specific terms, indemnification language, and state-required disclosures should be reviewed by your legal/compliance advisor before production use. This template is a starting point, not legal advice.</em></p>
	</section>

	<div class="sig">
		<div class="sig-block">
			<strong>Signature</strong>
			<?php if ( $sig_data_url ) : ?>
				<img class="sig-img" src="<?php echo esc_url( $sig_data_url ); ?>" alt="Signature" />
			<?php else : ?>
				<div class="sig-typed"><?php echo esc_html( $typed_name ); ?></div>
			<?php endif; ?>
		</div>
		<div class="sig-block">
			<strong>Printed name / Date</strong>
			<div class="sig-typed" style="font-family: inherit; font-size: 14px;">
				<?php echo esc_html( $typed_name ?: trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) ) ); ?><br />
				<?php echo esc_html( substr( (string) $waiver['signed_at'], 0, 10 ) ); ?>
			</div>
		</div>
	</div>

	<footer>
		Cryptographic record reference: <code><?php echo esc_html( $waiver['signature_hash'] ); ?></code>
	</footer>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}

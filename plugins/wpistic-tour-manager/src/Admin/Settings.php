<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Admin;

/**
 * Settings: general (currency, deposit default, pay-to-hold, from email) and the
 * four gateways (Stripe, PayPal, Bank/Wise, Binance). Saved via admin-post with a nonce.
 */
final class Settings {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_wpistic_tm_save_settings', array( $this, 'save' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'wpistic-tour-manager',
			__( 'Tour Manager Settings', 'wpistic-tour-manager' ),
			__( 'Settings', 'wpistic-tour-manager' ),
			'manage_options',
			'wpistic-tm-settings',
			array( $this, 'render' )
		);
	}

	/**
	 * Read a gateway config array.
	 *
	 * @return array<string, mixed>
	 */
	public static function gateway( string $id ): array {
		$value = get_option( 'wpistic_tm_' . $id, array() );
		return is_array( $value ) ? $value : array();
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$stripe  = self::gateway( 'stripe' );
		$paypal  = self::gateway( 'paypal' );
		$bank    = self::gateway( 'bank' );
		$binance = self::gateway( 'binance' );
		?>
		<div class="wrap wpistic-tm-settings">
			<h1><?php esc_html_e( 'Tour Manager Settings', 'wpistic-tour-manager' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpistic_tm_save_settings">
				<?php wp_nonce_field( 'wpistic_tm_settings' ); ?>

				<h2><?php esc_html_e( 'General', 'wpistic-tour-manager' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Default currency', 'wpistic-tour-manager' ); ?></th>
						<td><input type="text" name="wpistic_tm_currency" value="<?php echo esc_attr( get_option( 'wpistic_tm_currency', 'USD' ) ); ?>" class="small-text"></td></tr>
					<tr><th><?php esc_html_e( 'Default deposit type', 'wpistic-tour-manager' ); ?></th>
						<td>
							<select name="wpistic_tm_deposit_type">
								<?php $dt = get_option( 'wpistic_tm_deposit_type', 'percent' ); ?>
								<option value="percent" <?php selected( $dt, 'percent' ); ?>><?php esc_html_e( 'Percentage', 'wpistic-tour-manager' ); ?></option>
								<option value="fixed" <?php selected( $dt, 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'wpistic-tour-manager' ); ?></option>
							</select>
						</td></tr>
					<tr><th><?php esc_html_e( 'Default deposit value', 'wpistic-tour-manager' ); ?></th>
						<td><input type="text" name="wpistic_tm_deposit_value" value="<?php echo esc_attr( get_option( 'wpistic_tm_deposit_value', '30' ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'e.g. 30 (percent) or 500 (fixed)', 'wpistic-tour-manager' ); ?></span></td></tr>
					<tr><th><?php esc_html_e( 'Pay-to-hold a departure', 'wpistic-tour-manager' ); ?></th>
						<td><label><input type="checkbox" name="wpistic_tm_pay_to_hold" value="1" <?php checked( (int) get_option( 'wpistic_tm_pay_to_hold' ), 1 ); ?>> <?php esc_html_e( 'Allow self-pay deposit before a human confirms (off by default — brand rule).', 'wpistic-tour-manager' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'From email', 'wpistic-tour-manager' ); ?></th>
						<td><input type="email" name="wpistic_tm_from_email" value="<?php echo esc_attr( get_option( 'wpistic_tm_from_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td></tr>
				</table>

				<div class="wpistic-tm-gateway">
					<h2><?php esc_html_e( 'Stripe (cards)', 'wpistic-tour-manager' ); ?></h2>
					<table class="form-table">
						<?php $this->toggle_row( 'stripe', $stripe ); ?>
						<tr><th>Secret key</th><td><input type="password" name="wpistic_tm_stripe[secret_key]" value="<?php echo esc_attr( $stripe['secret_key'] ?? '' ); ?>" class="regular-text" autocomplete="off"></td></tr>
						<tr><th>Publishable key</th><td><input type="text" name="wpistic_tm_stripe[publishable_key]" value="<?php echo esc_attr( $stripe['publishable_key'] ?? '' ); ?>" class="regular-text"></td></tr>
						<tr><th>Webhook signing secret</th><td><input type="password" name="wpistic_tm_stripe[webhook_secret]" value="<?php echo esc_attr( $stripe['webhook_secret'] ?? '' ); ?>" class="regular-text" autocomplete="off"></td></tr>
					</table>
				</div>

				<div class="wpistic-tm-gateway">
					<h2><?php esc_html_e( 'PayPal', 'wpistic-tour-manager' ); ?></h2>
					<table class="form-table">
						<?php $this->toggle_row( 'paypal', $paypal ); ?>
						<tr><th>Client ID</th><td><input type="text" name="wpistic_tm_paypal[client_id]" value="<?php echo esc_attr( $paypal['client_id'] ?? '' ); ?>" class="regular-text"></td></tr>
						<tr><th>Secret</th><td><input type="password" name="wpistic_tm_paypal[secret]" value="<?php echo esc_attr( $paypal['secret'] ?? '' ); ?>" class="regular-text" autocomplete="off"></td></tr>
						<tr><th>Webhook ID</th><td><input type="text" name="wpistic_tm_paypal[webhook_id]" value="<?php echo esc_attr( $paypal['webhook_id'] ?? '' ); ?>" class="regular-text"></td></tr>
						<tr><th>Mode</th><td><?php $this->mode_select( 'paypal', $paypal ); ?></td></tr>
					</table>
				</div>

				<div class="wpistic-tm-gateway">
					<h2><?php esc_html_e( 'Bank transfer / Wise (offline)', 'wpistic-tour-manager' ); ?></h2>
					<table class="form-table">
						<?php $this->toggle_row( 'bank', $bank ); ?>
						<tr><th>Payment instructions</th><td><textarea name="wpistic_tm_bank[instructions]" rows="5" class="large-text"><?php echo esc_textarea( $bank['instructions'] ?? '' ); ?></textarea><p class="description"><?php esc_html_e( 'Required before Bank/Wise appears as a payment-link option.', 'wpistic-tour-manager' ); ?></p></td></tr>
					</table>
				</div>

				<div class="wpistic-tm-gateway">
					<h2><?php esc_html_e( 'Binance / Crypto', 'wpistic-tour-manager' ); ?></h2>
					<table class="form-table">
						<?php $this->toggle_row( 'binance', $binance ); ?>
						<tr><th>Mode</th><td>
							<select name="wpistic_tm_binance[mode]">
								<?php $bm = $binance['mode'] ?? 'address'; ?>
								<option value="address" <?php selected( $bm, 'address' ); ?>><?php esc_html_e( 'Wallet address (BEP-20, on-chain verify)', 'wpistic-tour-manager' ); ?></option>
								<option value="binance_pay" <?php selected( $bm, 'binance_pay' ); ?>><?php esc_html_e( 'Binance Pay merchant API', 'wpistic-tour-manager' ); ?></option>
							</select>
						</td></tr>
						<tr><th>Wallet address</th><td><input type="text" name="wpistic_tm_binance[wallet_address]" value="<?php echo esc_attr( $binance['wallet_address'] ?? '' ); ?>" class="regular-text"></td></tr>
						<tr><th>Merchant ID</th><td><input type="text" name="wpistic_tm_binance[merchant_id]" value="<?php echo esc_attr( $binance['merchant_id'] ?? '' ); ?>" class="regular-text"></td></tr>
						<tr><th>API key</th><td><input type="password" name="wpistic_tm_binance[api_key]" value="<?php echo esc_attr( $binance['api_key'] ?? '' ); ?>" class="regular-text" autocomplete="off"></td></tr>
						<tr><th>API secret</th><td><input type="password" name="wpistic_tm_binance[api_secret]" value="<?php echo esc_attr( $binance['api_secret'] ?? '' ); ?>" class="regular-text" autocomplete="off"></td></tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function toggle_row( string $id, array $config ): void {
		echo '<tr><th>' . esc_html__( 'Enabled', 'wpistic-tour-manager' ) . '</th><td><label><input type="checkbox" name="wpistic_tm_' . esc_attr( $id ) . '[enabled]" value="1" ' . checked( ! empty( $config['enabled'] ), true, false ) . '> ' . esc_html__( 'Enable this gateway', 'wpistic-tour-manager' ) . '</label></td></tr>';
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function mode_select( string $id, array $config ): void {
		$mode = $config['mode'] ?? 'sandbox';
		echo '<select name="wpistic_tm_' . esc_attr( $id ) . '[mode]">';
		echo '<option value="sandbox"' . selected( $mode, 'sandbox', false ) . '>' . esc_html__( 'Sandbox', 'wpistic-tour-manager' ) . '</option>';
		echo '<option value="live"' . selected( $mode, 'live', false ) . '>' . esc_html__( 'Live', 'wpistic-tour-manager' ) . '</option>';
		echo '</select>';
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'wpistic_tm_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpistic-tour-manager' ) );
		}

		$currency = strtoupper( sanitize_text_field( wp_unslash( $_POST['wpistic_tm_currency'] ?? 'USD' ) ) );
		if ( ! in_array( $currency, array( 'USD', 'EUR', 'BTC', 'USDT', 'BNB' ), true ) ) {
			$currency = 'USD';
		}

		$deposit_type = sanitize_key( wp_unslash( $_POST['wpistic_tm_deposit_type'] ?? 'percent' ) );
		if ( ! in_array( $deposit_type, array( 'fixed', 'percent' ), true ) ) {
			$deposit_type = 'percent';
		}

		$from_email = sanitize_email( wp_unslash( $_POST['wpistic_tm_from_email'] ?? '' ) );
		if ( ! is_email( $from_email ) ) {
			$from_email = (string) get_option( 'admin_email' );
		}

		update_option( 'wpistic_tm_currency', $currency );
		update_option( 'wpistic_tm_deposit_type', $deposit_type );
		update_option( 'wpistic_tm_deposit_value', sanitize_text_field( wp_unslash( $_POST['wpistic_tm_deposit_value'] ?? '30' ) ) );
		update_option( 'wpistic_tm_pay_to_hold', isset( $_POST['wpistic_tm_pay_to_hold'] ) ? 1 : 0 );
		update_option( 'wpistic_tm_from_email', $from_email );

		foreach ( array( 'stripe', 'paypal', 'bank', 'binance' ) as $gateway ) {
			$raw   = isset( $_POST[ 'wpistic_tm_' . $gateway ] ) && is_array( $_POST[ 'wpistic_tm_' . $gateway ] ) ? wp_unslash( $_POST[ 'wpistic_tm_' . $gateway ] ) : array(); // phpcs:ignore WordPress.Security.ValidationSanitization
			$clean = array();
			foreach ( $raw as $key => $value ) {
				$clean[ sanitize_key( $key ) ] = 'instructions' === $key ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
			}
			$clean['enabled'] = ! empty( $raw['enabled'] ) ? 1 : 0;
			update_option( 'wpistic_tm_' . $gateway, $clean );
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=wpistic-tm-settings' ) ) );
		exit;
	}
}

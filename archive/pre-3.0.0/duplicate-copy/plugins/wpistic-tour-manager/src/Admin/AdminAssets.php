<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Admin;

/**
 * Enqueues the dashboard's own CSS/JS bundle, and only on the plugin's own
 * admin screens (never sitewide in wp-admin) -- mirroring the hook-suffix
 * gate MetaBoxes::assets() already uses for the tour-editor screens.
 *
 * Also owns the per-user admin theme preference (light/dark/auto), stored in
 * user_meta so it survives across sessions and devices, exactly the way the
 * front-end theme toggle persists to localStorage but scoped server-side
 * since wp-admin pages are never statically cached the way front-end pages
 * can be -- there is no pre-paint "flash" to guard against here, the server
 * already knows the user's saved choice at render time.
 */
final class AdminAssets {

	public const META_KEY   = 'wpistic_tm_admin_theme';
	public const AJAX_ACTION = 'wpistic_tm_set_theme';
	public const NONCE      = 'wpistic_tm_theme';

	/** Hook suffixes of the plugin's own dashboard screens (admin_menu return values). */
	private const SCREEN_HOOKS = array(
		'toplevel_page_wpistic-tour-manager',
		'wpistic-tour-manager_page_wpistic-tm-bookings',
	);

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_set_theme' ) );
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, self::SCREEN_HOOKS, true ) ) {
			return;
		}

		wp_enqueue_style( 'wpistic-tm-dashboard', WPISTIC_TM_URL . 'assets/admin/css/dashboard.css', array(), WPISTIC_TM_VERSION );
		wp_enqueue_script( 'wpistic-tm-dashboard', WPISTIC_TM_URL . 'assets/admin/js/dashboard.js', array(), WPISTIC_TM_VERSION, true );
		wp_localize_script(
			'wpistic-tm-dashboard',
			'wpisticTmDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE ),
				'theme'   => self::current_theme(),
			)
		);
	}

	public function ajax_set_theme(): void {
		if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpistic-tour-manager' ) ), 403 );
		}

		$value = isset( $_POST['theme'] ) ? sanitize_key( wp_unslash( $_POST['theme'] ) ) : '';
		if ( ! in_array( $value, array( 'light', 'dark', 'auto' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid theme.', 'wpistic-tour-manager' ) ), 400 );
		}

		if ( 'auto' === $value ) {
			delete_user_meta( get_current_user_id(), self::META_KEY );
		} else {
			update_user_meta( get_current_user_id(), self::META_KEY, $value );
		}

		wp_send_json_success( array( 'theme' => $value ) );
	}

	/**
	 * The signed-in user's saved preference: 'light', 'dark', or '' (auto --
	 * follow the OS via the CSS `prefers-color-scheme` media query).
	 */
	public static function current_theme(): string {
		$value = (string) get_user_meta( get_current_user_id(), self::META_KEY, true );
		return in_array( $value, array( 'light', 'dark' ), true ) ? $value : '';
	}

	/**
	 * `data-theme="..."` attribute for the `.wpistic-tm-dashboard` wrapper, or
	 * an empty string when the user has no explicit preference yet (the CSS
	 * `@media (prefers-color-scheme: dark)` block then supplies the default).
	 */
	public static function theme_attr(): string {
		$theme = self::current_theme();
		return '' !== $theme ? ' data-theme="' . esc_attr( $theme ) . '"' : '';
	}

	/**
	 * Keyboard-operable theme toggle control, shared by every dashboard screen.
	 */
	public static function render_theme_toggle(): void {
		$current = self::current_theme();
		?>
		<div class="wpistic-tm-theme-toggle" role="group" aria-label="<?php esc_attr_e( 'Dashboard color theme', 'wpistic-tour-manager' ); ?>">
			<button type="button" class="wpistic-tm-theme-btn" data-theme-choice="light" aria-pressed="<?php echo esc_attr( 'light' === $current ? 'true' : 'false' ); ?>"><?php esc_html_e( 'Light', 'wpistic-tour-manager' ); ?></button>
			<button type="button" class="wpistic-tm-theme-btn" data-theme-choice="dark" aria-pressed="<?php echo esc_attr( 'dark' === $current ? 'true' : 'false' ); ?>"><?php esc_html_e( 'Dark', 'wpistic-tour-manager' ); ?></button>
			<button type="button" class="wpistic-tm-theme-btn" data-theme-choice="auto" aria-pressed="<?php echo esc_attr( '' === $current ? 'true' : 'false' ); ?>"><?php esc_html_e( 'Auto', 'wpistic-tour-manager' ); ?></button>
		</div>
		<?php
	}
}

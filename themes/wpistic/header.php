<?php
/**
 * Site header: no-flash theme bootstrap, utility bar, primary nav, light/dark toggle,
 * mobile slide-in menu, and a mobile "app view" bottom tab bar.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpistic_default_mode = sanitize_key( wpistic_opt( 'default_mode', 'light' ) );
if ( ! in_array( $wpistic_default_mode, array( 'light', 'dark' ), true ) ) {
	$wpistic_default_mode = 'light';
}
?><!doctype html>
<html <?php language_attributes(); ?> data-theme="<?php echo esc_attr( $wpistic_default_mode ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="<?php echo esc_attr( function_exists( 'wpistic_cfg_color' ) ? wpistic_cfg_color( 'color_navy', '#102A43' ) : '#102A43' ); ?>">
	<?php
	// No-flash: set the theme before paint. When the toggle is enabled, honor the saved
	// choice / system preference; when disabled, lock to the Theme Options default.
	$wpistic_toggle_on = (int) wpistic_opt( 'enable_toggle', 1 );
	if ( $wpistic_toggle_on ) {
		$wpistic_inline = "(function(){try{var d='" . esc_js( $wpistic_default_mode ) . "';var s=localStorage.getItem('wpistic-theme');var m=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;var t=s||(m?'dark':d);document.documentElement.setAttribute('data-theme',t);}catch(e){}})();";
	} else {
		$wpistic_inline = "document.documentElement.setAttribute('data-theme','" . esc_js( $wpistic_default_mode ) . "');";
	}
	echo '<script>' . $wpistic_inline . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline, fully built from esc_js.
	// No-JS fallback: reveal-on-scroll content must stay visible without JavaScript.
	echo '<noscript><style>.reveal{opacity:1 !important;transform:none !important}</style></noscript>';
	wp_head();
	?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'wpistic' ); ?></a>

<header class="site-head" id="site-head">
	<?php wpistic_util_bar(); ?>

	<nav class="nav" id="site-nav" aria-label="<?php esc_attr_e( 'Primary', 'wpistic' ); ?>">
		<?php wpistic_logo( 'nav-logo' ); ?>

		<div class="nav-right">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'nav-links',
					'fallback_cb'    => 'wpistic_primary_menu_fallback',
					'depth'          => 1,
				)
			);
			?>
			<a class="nav-cta" href="<?php echo esc_url( wpistic_cta_url() ); ?>"><?php echo esc_html( wpistic_cta_label() ); ?></a>
			<?php
			if ( $wpistic_toggle_on ) {
				wpistic_theme_toggle();
			}
			?>
			<button class="burger" aria-expanded="false" aria-controls="mobile-menu" aria-label="<?php esc_attr_e( 'Toggle menu', 'wpistic' ); ?>">
				<span></span><span></span><span></span>
			</button>
		</div>
	</nav>
</header>

<div class="mobile-menu" id="mobile-menu">
	<?php
	wp_nav_menu(
		array(
			'theme_location' => 'primary',
			'container'      => false,
			'menu_class'     => 'mobile-menu-list',
			'fallback_cb'    => 'wpistic_primary_menu_fallback',
			'depth'          => 1,
		)
	);
	?>
	<a class="btn btn-solid" href="<?php echo esc_url( wpistic_cta_url() ); ?>"><?php echo esc_html( wpistic_cta_label() ); ?> <span class="arr" aria-hidden="true">→</span></a>
	<div class="mobile-util">
		<a href="<?php echo esc_url( wpistic_whatsapp_url() ); ?>" rel="noopener">WhatsApp</a>
		<?php if ( $wpistic_toggle_on ) : ?>
			<button class="theme-toggle-text" type="button" data-theme-toggle><?php esc_html_e( 'Light / Dark', 'wpistic' ); ?></button>
		<?php endif; ?>
	</div>
</div>

<div class="head-spacer" aria-hidden="true"></div>

<main id="main" class="site-main">

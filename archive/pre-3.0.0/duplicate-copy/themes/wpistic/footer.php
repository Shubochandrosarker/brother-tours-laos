<?php
/**
 * Site footer: brand column (contact + newsletter), four link columns, social + legal,
 * mobile app bar, and cookie consent. Navy surface in both modes.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main>

<?php if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'footer' ) ) : ?>
<footer class="site-footer" role="contentinfo">
	<div class="wrap">
		<div class="footer-grid">
			<div class="footer-brand-col">
				<p class="footer-brand"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				<p class="footer-tagline">Born Here. Guide Here.</p>
				<p class="footer-desc"><?php echo esc_html( wpistic_opt( 'footer_blurb', 'A Lao company. Owned here, run here, guided here.' ) ); ?></p>
				<div class="footer-contact">
					<a href="<?php echo esc_url( 'mailto:' . wpistic_contact( 'email' ) ); ?>"><?php echo esc_html( wpistic_contact( 'email' ) ); ?></a><br>
					<a href="<?php echo esc_url( wpistic_whatsapp_url() ); ?>" rel="noopener"><?php echo esc_html( wpistic_contact( 'whatsapp_display' ) ); ?> · WhatsApp</a><br>
					<span><?php echo esc_html( wpistic_contact( 'office' ) ); ?></span>
				</div>
				<div class="footer-news">
					<?php
					// Newsletter: Tour Manager form (subscribes through Formistic), with a Formistic fallback.
					if ( shortcode_exists( 'wpistic_newsletter' ) ) {
						echo do_shortcode( '[wpistic_newsletter source="footer"]' );
					} elseif ( shortcode_exists( 'formistic_newsletter' ) ) {
						echo do_shortcode( '[formistic_newsletter source="footer"]' );
					} else {
						?>
						<label for="footer-news-email"><?php echo esc_html( wpistic_opt( 'newsletter_heading', 'Slow letters from Ken & the team' ) ); ?></label>
						<form class="footer-news-row" action="#" method="post">
							<input type="email" id="footer-news-email" name="email" placeholder="<?php esc_attr_e( 'your@email.com', 'wpistic' ); ?>" autocomplete="email">
							<button class="btn btn-solid" type="submit"><?php esc_html_e( 'Subscribe', 'wpistic' ); ?></button>
						</form>
						<?php
					}
					?>
				</div>
			</div>

			<?php
			$wpistic_footer_cols = array(
				'footer-journeys'     => __( 'Journeys', 'wpistic' ),
				'footer-destinations' => __( 'Destinations', 'wpistic' ),
				'footer-company'      => __( 'The Company', 'wpistic' ),
				'footer-practical'    => __( 'Practical', 'wpistic' ),
			);
			foreach ( $wpistic_footer_cols as $wpistic_loc => $wpistic_title ) :
				?>
				<div class="footer-col">
					<p class="footer-col-title"><?php echo esc_html( $wpistic_title ); ?></p>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => $wpistic_loc,
							'container'      => false,
							'menu_class'     => 'footer-links',
							'fallback_cb'    => 'wpistic_footer_menu_fallback',
							'depth'          => 1,
						)
					);
					?>
				</div>
				<?php
			endforeach;
			?>
		</div>

		<div class="footer-bottom">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?> · Vientiane, Lao PDR. <span class="tagline"><?php echo esc_html( wpistic_opt( 'copyright_extra', 'Lao-led. Globally understood.' ) ); ?></span></span>
			<span class="footer-social">
				<?php
				$wpistic_social = array(
					'instagram'   => wpistic_opt( 'social_instagram', '' ),
					'youtube'     => wpistic_opt( 'social_youtube', '' ),
					'tripadvisor' => wpistic_review_url( 'tripadvisor' ),
					'google'      => wpistic_review_url( 'google' ),
				);
				foreach ( $wpistic_social as $wpistic_net => $wpistic_link ) {
					if ( $wpistic_link ) {
						printf(
							'<a href="%1$s" rel="noopener">%2$s</a>',
							esc_url( $wpistic_link ),
							esc_html( ucfirst( $wpistic_net ) )
						);
					}
				}
				?>
				<?php
				$wpistic_privacy = get_page_by_path( 'privacy', OBJECT, 'page' );
				if ( ! $wpistic_privacy ) {
					$wpistic_privacy = get_page_by_path( 'privacy-policy', OBJECT, 'page' );
				}
				if ( $wpistic_privacy && 'publish' === get_post_status( $wpistic_privacy ) ) :
					?>
					<a href="<?php echo esc_url( get_permalink( $wpistic_privacy ) ); ?>"><?php esc_html_e( 'Privacy', 'wpistic' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/terms-and-conditions/' ) ); ?>"><?php esc_html_e( 'Terms', 'wpistic' ); ?></a>
			</span>
		</div>
	</div>
</footer>
<?php endif; ?>

<?php wpistic_app_bar(); ?>

<div class="cookie-consent" id="cookie-consent" hidden>
	<p><?php esc_html_e( 'We use cookies to understand how the site is used. You choose.', 'wpistic' ); ?></p>
	<div class="cookie-consent__actions">
		<button class="wpistic-cta" data-consent="accept"><?php esc_html_e( 'Accept', 'wpistic' ); ?></button>
		<button class="wpistic-ghost" data-consent="decline"><?php esc_html_e( 'Decline', 'wpistic' ); ?></button>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>

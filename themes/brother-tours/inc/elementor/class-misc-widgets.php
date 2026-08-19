<?php
/**
 * Brother Tours — Elementor widgets: Build My Trip CTA, Brother Tours
 * Reviews, Contact Information.
 *
 * @package BrotherTours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wpistic_Elementor_Widget_Base' ) ) {
	require_once get_template_directory() . '/inc/elementor/class-widget-base.php';
}

if ( ! class_exists( 'Wpistic_Elementor_Widget_Base' ) ) {
	// Elementor is not active; nothing below can be defined safely.
	return;
}

/* =============================================================================
 * 15. Build My Trip CTA
 * ========================================================================= */

class Brother_Tours_Widget_Build_My_Trip_Cta extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-build-my-trip-cta';
	}

	public function get_title() {
		return __( 'Build My Trip CTA', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-call-to-action';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);

		$this->add_control(
			'wpistic_context',
			array(
				'label'       => __( 'Pre-fill context (optional)', 'brother-tours' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'A tour or experience name pre-filled into the Build My Trip link, e.g. when this CTA closes a specific tour page. Leave empty for the generic CTA.', 'brother-tours' ),
			)
		);

		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		if ( ! function_exists( 'wpistic_build_my_trip_cta' ) ) {
			$this->render_empty_state( __( 'Build My Trip CTA is unavailable.', 'brother-tours' ) );
			return;
		}

		$settings = $this->get_settings_for_display();
		$context  = sanitize_text_field( (string) ( $settings['wpistic_context'] ?? '' ) );

		// The theme helper already renders full, brand-locked markup
		// (locked copy, both CTAs, correct escaping) -- this widget's only
		// job is to expose the one real per-instance variable to an editor.
		wpistic_build_my_trip_cta( $context );
	}
}

/* =============================================================================
 * 16. Brother Tours Reviews
 *
 * Text-only: two profile links plus an optional operator-supplied embed.
 * Never a star rating, a number, or AggregateRating schema -- the locked
 * brand rule holds until the verified-review threshold is reached (see
 * docs/launch-checklist.md).
 * ========================================================================= */

class Brother_Tours_Widget_Reviews extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-reviews';
	}

	public function get_title() {
		return __( 'Brother Tours Reviews', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-star';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);
		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		$google_url      = function_exists( 'wpistic_review_url' ) ? wpistic_review_url( 'google' ) : '';
		$tripadvisor_url = function_exists( 'wpistic_review_url' ) ? wpistic_review_url( 'tripadvisor' ) : '';
		$embed           = function_exists( 'wpistic_reviews_embed' ) ? wpistic_reviews_embed() : '';

		if ( ! $google_url && ! $tripadvisor_url && '' === trim( $embed ) ) {
			$this->render_admin_notice( __( 'No review profile links are configured yet. Set them in Appearance -> Customize -> Brother Tours -- Reviews.', 'brother-tours' ) );
			return;
		}
		?>
		<div class="bt-ew-reviews">
			<p class="bt-ew-reviews-line"><?php esc_html_e( 'Consistently top-rated on Google and TripAdvisor.', 'brother-tours' ); ?></p>
			<?php if ( function_exists( 'wpistic_review_links' ) ) : ?>
				<?php wpistic_review_links(); ?>
			<?php endif; ?>
			<?php if ( '' !== trim( $embed ) ) : ?>
				<div class="bt-ew-reviews-embed"><?php echo wp_kses_post( $embed ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}
}

/* =============================================================================
 * 18. Contact Information
 * ========================================================================= */

class Brother_Tours_Widget_Contact_Information extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-contact-information';
	}

	public function get_title() {
		return __( 'Contact Information', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-address-book';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);
		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		if ( ! function_exists( 'wpistic_contact' ) ) {
			$this->render_empty_state( __( 'Contact information is unavailable.', 'brother-tours' ) );
			return;
		}

		$email  = wpistic_contact( 'email' );
		$phone  = wpistic_contact( 'phone' );
		$office = wpistic_contact( 'office' );
		$hours  = wpistic_contact( 'hours' );
		$wa_url = function_exists( 'wpistic_whatsapp_url' ) ? wpistic_whatsapp_url() : '';

		$contact_url = function_exists( 'brother_tours_url' ) ? brother_tours_url( 'contact' ) : home_url( '/contact/' );
		?>
		<div class="bt-ew-contact">
			<?php if ( $office ) : ?><p class="bt-ew-contact-office"><?php echo esc_html( $office ); ?></p><?php endif; ?>
			<?php if ( $email ) : ?><p><a href="<?php echo esc_url( 'mailto:' . antispambot( $email ) ); ?>"><?php echo esc_html( $email ); ?></a></p><?php endif; ?>
			<?php if ( $phone ) : ?><p><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p><?php endif; ?>
			<?php if ( $wa_url ) : ?><p><a href="<?php echo esc_url( $wa_url ); ?>" rel="noopener"><?php esc_html_e( 'WhatsApp', 'brother-tours' ); ?></a></p><?php endif; ?>
			<?php if ( $hours ) : ?><p class="bt-ew-contact-hours"><?php echo esc_html( $hours ); ?></p><?php endif; ?>
			<p><a class="btn-link" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Contact page', 'brother-tours' ); ?> <span class="arr" aria-hidden="true">&rarr;</span></a></p>
		</div>
		<?php
	}
}

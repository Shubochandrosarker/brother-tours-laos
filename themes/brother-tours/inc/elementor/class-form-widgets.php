<?php
/**
 * Brother Tours — Elementor widgets: Request Availability, Formistic Form,
 * Newsletter Form.
 *
 * All three call into Formistic rather than rendering a form themselves --
 * Formistic owns rendering, validation, consent, spam protection and
 * storage (see docs/formistic-brother-tours-integration.md). Every call is
 * guarded with class_exists()/function_exists() so a site with Formistic
 * deactivated shows an editor-only notice instead of a fatal.
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
 * 13. Request Availability
 * ========================================================================= */

class Brother_Tours_Widget_Request_Availability extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-request-availability';
	}

	public function get_title() {
		return __( 'Request Availability', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);
		$this->register_source_control( 'wpistic_tour', __( 'Tour', 'brother-tours' ) );
		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		if ( ! class_exists( 'Wpistic_Formistic_BT_Forms' ) ) {
			$this->render_admin_notice( __( 'Formistic is not active, so the Request Availability form cannot render.', 'brother-tours' ) );
			return;
		}

		$settings = $this->get_settings_for_display();
		$tour_id  = $this->resolve_post_id( $settings );

		if ( ! $tour_id || 'wpistic_tour' !== get_post_type( $tour_id ) ) {
			$this->render_empty_state( __( 'Select a tour, or place this widget on a Tour page.', 'brother-tours' ) );
			return;
		}

		$html = Wpistic_Formistic_BT_Forms::render_request_availability( $tour_id, get_the_title( $tour_id ) );

		if ( '' === $html ) {
			$this->render_admin_notice( __( 'The Request Tour Availability form has not been created yet -- it is seeded automatically on Formistic activation.', 'brother-tours' ) );
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formistic's own render() output, already escaped at the source; render_request_availability() only substitutes two attribute values it esc_attr()s itself.
	}
}

/* =============================================================================
 * 14. Formistic Form
 * ========================================================================= */

class Brother_Tours_Widget_Formistic_Form extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-formistic-form';
	}

	public function get_title() {
		return __( 'Formistic Form', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);

		$this->add_control(
			'wpistic_form_id',
			array(
				'label'       => __( 'Form', 'brother-tours' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'label_block' => true,
				'default'     => '',
				'options'     => $this->form_options(),
			)
		);

		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	/**
	 * @return array<int|string,string>
	 */
	private function form_options() {
		$options = array( '' => __( '— Select a form —', 'brother-tours' ) );

		if ( ! class_exists( 'Wpistic_Formistic_Forms' ) ) {
			return $options;
		}

		$forms = get_posts(
			array(
				'post_type'        => Wpistic_Formistic_Forms::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 100,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $forms as $form ) {
			$options[ $form->ID ] = get_the_title( $form );
		}

		return $options;
	}

	protected function render() {
		if ( ! class_exists( 'Wpistic_Formistic_Forms' ) ) {
			$this->render_admin_notice( __( 'Formistic is not active, so this form cannot render.', 'brother-tours' ) );
			return;
		}

		$settings = $this->get_settings_for_display();
		$form_id  = absint( $settings['wpistic_form_id'] ?? 0 );

		if ( ! $form_id ) {
			$this->render_admin_notice( __( 'Select a form in the widget settings.', 'brother-tours' ) );
			return;
		}

		$post = get_post( $form_id );
		if ( ! $post || Wpistic_Formistic_Forms::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			$this->render_admin_notice( __( 'The selected form no longer exists or is not published.', 'brother-tours' ) );
			return;
		}

		echo do_shortcode( '[wpistic_form id="' . absint( $form_id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formistic's own render() output, already escaped at the source.
	}
}

/* =============================================================================
 * 17 (of 18). Newsletter Form
 *
 * Numbered per the master widget list; kept in this file because, like
 * Request Availability and Formistic Form, it renders through Formistic
 * rather than its own markup.
 * ========================================================================= */

class Brother_Tours_Widget_Newsletter_Form extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-newsletter-form';
	}

	public function get_title() {
		return __( 'Newsletter Form', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-mail';
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
		if ( ! class_exists( 'Wpistic_Formistic_BT_Forms' ) ) {
			$this->render_admin_notice( __( 'Formistic is not active, so the Newsletter form cannot render.', 'brother-tours' ) );
			return;
		}

		$form_id = Wpistic_Formistic_BT_Forms::form_id( Wpistic_Formistic_BT_Forms::NEWSLETTER );

		if ( ! $form_id ) {
			$this->render_admin_notice( __( 'The Newsletter form has not been created yet -- it is seeded automatically on Formistic activation.', 'brother-tours' ) );
			return;
		}

		echo do_shortcode( '[wpistic_form id="' . absint( $form_id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formistic's own render() output, already escaped at the source.
	}
}

<?php
/**
 * Shared Elementor widget base for WPistic-powered themes.
 *
 * Generic only -- no Brother Tours-specific copy or business logic lives
 * here. Concrete widgets are registered by a child theme (see
 * themes/brother-tours/inc/elementor/); this base exists so those widgets
 * don't each reimplement empty-state rendering, the "current post or manual
 * picker" pattern, or shared spacing controls.
 *
 * Loading order: `\Elementor\Widget_Base` only exists once Elementor itself
 * has booted, and a child theme's functions.php is required *before* its
 * parent's (WordPress core loads STYLESHEETPATH/functions.php ahead of
 * TEMPLATEPATH/functions.php), so which functions.php runs first cannot be
 * relied on to guarantee this class is already defined. Every concrete
 * widget file therefore requires this file defensively, immediately before
 * declaring a class that extends it -- see any file under
 * themes/brother-tours/inc/elementor/. This file itself only defines the
 * class when Elementor is present, so it is always safe to require.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wpistic_Elementor_Widget_Base' ) && class_exists( '\Elementor\Widget_Base' ) ) {

	/**
	 * Abstract base for WPistic / Brother Tours Elementor widgets.
	 */
	abstract class Wpistic_Elementor_Widget_Base extends \Elementor\Widget_Base {

		/**
		 * @return string[]
		 */
		public function get_categories() {
			return array( 'brother-tours' );
		}

		/**
		 * @return string[]
		 */
		public function get_keywords() {
			return array( 'brother tours', 'tour', 'wpistic' );
		}

		/**
		 * Shared stylesheet, registered (not enqueued) by the theme that owns
		 * it. Declaring it as a dependency here -- rather than enqueueing it
		 * unconditionally on every page -- is what keeps it off pages that
		 * never place one of these widgets; Elementor only pulls in a
		 * widget's get_style_depends() when that widget is actually rendered.
		 *
		 * @return string[]
		 */
		public function get_style_depends() {
			return wp_style_is( 'brother-tours-elementor-widgets', 'registered' )
				? array( 'brother-tours-elementor-widgets' )
				: array();
		}

		/**
		 * Resolve which post this widget instance should read from: a
		 * manually picked post if the control was set, otherwise the current
		 * post (so the widget "just works" when placed on the post's own
		 * template), otherwise 0.
		 *
		 * @param array<string,mixed> $settings    Widget settings.
		 * @param string              $control_key Control name holding the manual post id.
		 * @return int
		 */
		protected function resolve_post_id( array $settings, $control_key = 'wpistic_source_id' ) {
			$manual = isset( $settings[ $control_key ] ) ? absint( $settings[ $control_key ] ) : 0;
			if ( $manual ) {
				return $manual;
			}

			$current = get_the_ID();
			return $current ? absint( $current ) : 0;
		}

		/**
		 * Register the "which post" control shared by widgets that default to
		 * the current post but can also be placed on a page that is not that
		 * post type (e.g. Tour Hero dropped on a landing page).
		 *
		 * @param string $post_type   Post type queried for the manual picker.
		 * @param string $label       Human label, e.g. "Tour".
		 * @param string $control_key Control name for the manual id.
		 * @return void
		 */
		protected function register_source_control( $post_type, $label, $control_key = 'wpistic_source_id' ) {
			$this->add_control(
				$control_key,
				array(
					/* translators: %s: post type label, e.g. "Tour". */
					'label'       => sprintf( __( '%s (leave empty to use the current page)', 'wpistic' ), $label ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'label_block' => true,
					'default'     => '',
					'options'     => $this->post_options( $post_type ),
				)
			);
		}

		/**
		 * Build a Select2-ready id => title option list for a post type.
		 *
		 * @param string $post_type Post type slug.
		 * @param int    $limit     Maximum posts listed.
		 * @return array<int|string,string>
		 */
		protected function post_options( $post_type, $limit = 200 ) {
			$options = array( '' => __( '— Current page —', 'wpistic' ) );

			$posts = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => 'publish',
					'numberposts'      => (int) $limit,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			);

			foreach ( $posts as $post ) {
				$options[ $post->ID ] = get_the_title( $post );
			}

			return $options;
		}

		/**
		 * A consistent, brand-safe empty state. Never fabricated content --
		 * always a plain statement of what is missing.
		 *
		 * @param string $message Human-readable explanation.
		 * @return void
		 */
		protected function render_empty_state( $message ) {
			printf(
				'<div class="wpistic-ew-empty">%s</div>',
				esc_html( $message )
			);
		}

		/**
		 * An editor-only notice (e.g. "Formistic is not active"). Never shown
		 * to a visitor without edit_posts capability, and never a fatal.
		 *
		 * @param string $message Notice text.
		 * @return void
		 */
		protected function render_admin_notice( $message ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return;
			}
			printf(
				'<div class="wpistic-ew-admin-notice">%s</div>',
				esc_html( $message )
			);
		}

		/**
		 * Shared responsive spacing controls (space above / below the
		 * widget). Every widget in this set is a full block-level section, so
		 * this one axis covers the real variation an editor needs without
		 * decorative control bloat.
		 *
		 * @param string $selector CSS selector relative to {{WRAPPER}}.
		 * @return void
		 */
		protected function register_spacing_controls( $selector = '{{WRAPPER}}' ) {
			$this->start_controls_section(
				'wpistic_section_spacing',
				array(
					'label' => __( 'Spacing', 'wpistic' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_responsive_control(
				'wpistic_spacing_top',
				array(
					'label'      => __( 'Space above', 'wpistic' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array( 'px' => array( 'min' => 0, 'max' => 160 ) ),
					'selectors'  => array(
						$selector => 'margin-top: {{SIZE}}{{UNIT}};',
					),
				)
			);

			$this->add_responsive_control(
				'wpistic_spacing_bottom',
				array(
					'label'      => __( 'Space below', 'wpistic' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array( 'px' => array( 'min' => 0, 'max' => 160 ) ),
					'selectors'  => array(
						$selector => 'margin-bottom: {{SIZE}}{{UNIT}};',
					),
				)
			);

			$this->end_controls_section();
		}
	}
}

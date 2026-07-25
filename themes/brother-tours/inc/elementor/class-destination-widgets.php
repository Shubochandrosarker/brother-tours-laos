<?php
/**
 * Brother Tours — Elementor widgets: Destination Hero, Destination
 * Experiences.
 *
 * Meta key spellings copied verbatim from themes/wpistic/single-destination.php,
 * the template that already reads them in production. The destination <->
 * experience relationship is the `wpistic_parent_destination` post meta on
 * `wpistic_experience` (see PostTypes\ContentTypes::experience_permalink()),
 * not a taxonomy term match.
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
 * 11. Destination Hero
 * ========================================================================= */

class Brother_Tours_Widget_Destination_Hero extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-destination-hero';
	}

	public function get_title() {
		return __( 'Destination Hero', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-slider-full-screen';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);
		$this->register_source_control( 'wpistic_destination', __( 'Destination', 'brother-tours' ) );
		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		$settings        = $this->get_settings_for_display();
		$destination_id  = $this->resolve_post_id( $settings );

		if ( ! $destination_id || 'wpistic_destination' !== get_post_type( $destination_id ) ) {
			$this->render_empty_state( __( 'Select a destination, or place this widget on a Destination page.', 'brother-tours' ) );
			return;
		}

		$slug = get_post_field( 'post_name', $destination_id );
		$hero = has_post_thumbnail( $destination_id )
			? get_the_post_thumbnail_url( $destination_id, 'bt-hero' )
			: ( function_exists( 'wpistic_demo_img' ) ? wpistic_demo_img( 'dest-' . $slug ) : '' );
		$tag  = (string) get_post_meta( $destination_id, 'wpistic_region_tag', true );
		?>
		<section class="page-hero">
			<?php if ( $hero ) : ?>
				<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $hero ); ?>" alt="" fetchpriority="high"></div>
			<?php endif; ?>
			<div class="wrap">
				<?php if ( $tag ) : ?><span class="eyebrow"><?php echo esc_html( $tag ); ?></span><?php endif; ?>
				<h1><?php echo esc_html( get_the_title( $destination_id ) ); ?></h1>
			</div>
		</section>
		<?php
	}
}

/* =============================================================================
 * 12. Destination Experiences
 * ========================================================================= */

class Brother_Tours_Widget_Destination_Experiences extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-destination-experiences';
	}

	public function get_title() {
		return __( 'Destination Experiences', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-posts-grid';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);
		$this->register_source_control( 'wpistic_destination', __( 'Destination', 'brother-tours' ) );
		$this->add_control(
			'wpistic_count',
			array(
				'label'   => __( 'Number of experiences', 'brother-tours' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 24,
				'default' => 6,
			)
		);
		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		$settings       = $this->get_settings_for_display();
		$destination_id = $this->resolve_post_id( $settings );

		if ( ! $destination_id || 'wpistic_destination' !== get_post_type( $destination_id ) ) {
			$this->render_empty_state( __( 'Select a destination, or place this widget on a Destination page.', 'brother-tours' ) );
			return;
		}

		$count = max( 1, (int) ( $settings['wpistic_count'] ?? 6 ) );

		$experiences = get_posts(
			array(
				'post_type'        => 'wpistic_experience',
				'post_status'      => 'publish',
				'numberposts'      => $count,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'wpistic_parent_destination',
						'value' => $destination_id,
					),
				),
			)
		);

		if ( ! $experiences ) {
			$this->render_empty_state( __( 'No experiences are linked to this destination yet.', 'brother-tours' ) );
			return;
		}
		?>
		<div class="tour-grid">
			<?php foreach ( $experiences as $experience ) : ?>
				<article class="tour-card">
					<a href="<?php echo esc_url( get_permalink( $experience ) ); ?>">
						<?php if ( has_post_thumbnail( $experience ) ) : ?>
							<div class="tour-card__img"><?php echo get_the_post_thumbnail( $experience, 'bt-gallery', array( 'loading' => 'lazy', 'alt' => '' ) ); ?></div>
						<?php endif; ?>
						<div class="tour-card__body">
							<h3><?php echo esc_html( get_the_title( $experience ) ); ?></h3>
							<?php if ( get_the_excerpt( $experience ) ) : ?>
								<p class="tour-card__blurb"><?php echo esc_html( get_the_excerpt( $experience ) ); ?></p>
							<?php endif; ?>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

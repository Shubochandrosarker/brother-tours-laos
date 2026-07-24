<?php
/**
 * Brother Tours — Elementor widgets: Tour Hero, Tour Grid, Tour Search and
 * Filters, Tour Facts, Tour Pricing, Tour Itinerary, Included and Excluded,
 * Tour Gallery, Tour FAQ, Related Tours.
 *
 * Every widget reads real `wpistic_tour` post meta or calls an existing
 * theme/plugin helper (wpistic_tour_card(), wpistic_price_line(), the
 * `[wpistic_booking_widget]` shortcode, ...) rather than duplicating that
 * logic. Meta key spellings are copied verbatim from
 * themes/wpistic/single-tour.php, which is the template that already reads
 * them in production.
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
 * 1. Tour Hero
 * ========================================================================= */

class Brother_Tours_Widget_Tour_Hero extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-tour-hero';
	}

	public function get_title() {
		return __( 'Tour Hero', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-slider-full-screen';
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
		$settings = $this->get_settings_for_display();
		$tour_id  = $this->resolve_post_id( $settings );

		if ( ! $tour_id || 'wpistic_tour' !== get_post_type( $tour_id ) ) {
			$this->render_empty_state( __( 'Select a tour, or place this widget on a Tour page.', 'brother-tours' ) );
			return;
		}

		$title    = get_the_title( $tour_id );
		$duration = (string) get_post_meta( $tour_id, 'wpistic_duration', true );
		$from     = get_post_meta( $tour_id, 'wpistic_from_price', true );
		$hero_url = has_post_thumbnail( $tour_id ) ? get_the_post_thumbnail_url( $tour_id, 'bt-hero' ) : '';
		$regions  = wp_get_post_terms( $tour_id, 'region', array( 'fields' => 'names' ) );
		$region   = ( is_array( $regions ) && $regions ) ? (string) $regions[0] : '';
		?>
		<section class="page-hero">
			<?php if ( $hero_url ) : ?>
				<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $hero_url ); ?>" alt="" fetchpriority="high"></div>
			<?php endif; ?>
			<div class="wrap">
				<?php if ( $region || $duration ) : ?>
					<div class="hero-tags">
						<?php if ( $region ) : ?><span class="tag"><?php echo esc_html( $region ); ?></span><?php endif; ?>
						<?php if ( $duration ) : ?><span class="tag"><?php echo esc_html( $duration ); ?></span><?php endif; ?>
					</div>
				<?php endif; ?>
				<h1><?php echo esc_html( $title ); ?></h1>
				<div class="tour-meta-row">
					<?php if ( $duration ) : ?><span><?php echo esc_html( $duration ); ?></span><span class="dot" aria-hidden="true"></span><?php endif; ?>
					<span class="price"><?php echo esc_html( wpistic_price_line( (string) $from ) ); ?></span>
				</div>
			</div>
		</section>
		<?php
	}
}

/* =============================================================================
 * 2. Tour Grid
 * ========================================================================= */

class Brother_Tours_Widget_Tour_Grid extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-tour-grid';
	}

	public function get_title() {
		return __( 'Tour Grid', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	/** Filter-param taxonomy keys, mirroring brother_tours_filter_tours(). */
	private function taxonomy_map() {
		return array(
			''              => __( 'All tours', 'brother-tours' ),
			'tour_category'    => __( 'Tour Category', 'brother-tours' ),
			'tour_destination' => __( 'Tour Destination', 'brother-tours' ),
			'tour_difficulty'  => __( 'Difficulty', 'brother-tours' ),
		);
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);

		$this->add_control(
			'wpistic_taxonomy',
			array(
				'label'   => __( 'Filter by', 'brother-tours' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $this->taxonomy_map(),
			)
		);

		$this->add_control(
			'wpistic_term',
			array(
				'label'       => __( 'Term slug', 'brother-tours' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'The term slug within the taxonomy chosen above, e.g. "signature-journeys". Ignored when "All tours" is selected.', 'brother-tours' ),
				'condition'   => array( 'wpistic_taxonomy!' => '' ),
			)
		);

		$this->add_control(
			'wpistic_count',
			array(
				'label'   => __( 'Number of tours', 'brother-tours' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 24,
				'default' => 6,
			)
		);

		$this->add_responsive_control(
			'wpistic_columns',
			array(
				'label'   => __( 'Columns', 'brother-tours' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options' => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
			)
		);

		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		if ( ! function_exists( 'wpistic_tour_card' ) ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$taxonomy = sanitize_key( $settings['wpistic_taxonomy'] ?? '' );
		$term     = sanitize_title( $settings['wpistic_term'] ?? '' );
		$count    = max( 1, (int) ( $settings['wpistic_count'] ?? 6 ) );

		$args = array(
			'post_type'        => 'wpistic_tour',
			'post_status'      => 'publish',
			'posts_per_page'   => $count,
			'orderby'          => 'menu_order date',
			'order'            => 'ASC',
			'suppress_filters' => false,
		);

		if ( $taxonomy && $term && array_key_exists( $taxonomy, $this->taxonomy_map() ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $term,
				),
			);
		}

		$tours = get_posts( $args );

		if ( ! $tours ) {
			$this->render_empty_state( __( 'No tours match this filter yet.', 'brother-tours' ) );
			return;
		}

		$columns    = isset( $settings['wpistic_columns'] ) ? (int) $settings['wpistic_columns'] : 3;
		$cols_class = 2 === $columns ? ' cols-2' : ( 4 === $columns ? ' cols-4' : '' );
		?>
		<div class="tour-grid<?php echo esc_attr( $cols_class ); ?>">
			<?php
			foreach ( $tours as $tour ) {
				$regions = wp_get_post_terms( $tour->ID, 'region', array( 'fields' => 'names' ) );
				wpistic_tour_card(
					array(
						'name'     => get_the_title( $tour ),
						'url'      => get_permalink( $tour ),
						'region'   => ( is_array( $regions ) && $regions ) ? (string) $regions[0] : '',
						'meta'     => get_post_meta( $tour->ID, 'wpistic_duration', true ),
						'blurb'    => get_the_excerpt( $tour ),
						'tags'     => array_filter( array( get_post_meta( $tour->ID, 'wpistic_departures_label', true ) ) ),
						'price'    => wpistic_price_line( get_post_meta( $tour->ID, 'wpistic_from_price', true ) ),
						'image_id' => get_post_thumbnail_id( $tour ),
					)
				);
			}
			?>
		</div>
		<?php
	}
}

/* =============================================================================
 * 3. Tour Search and Filters
 * ========================================================================= */

class Brother_Tours_Widget_Tour_Search_Filters extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-tour-search-filters';
	}

	public function get_title() {
		return __( 'Tour Search and Filters', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-search';
	}

	/** GET param => [taxonomy, label], exactly as brother_tours_filter_tours() consumes. */
	private function fields() {
		return array(
			'category'    => array( 'tour_category', __( 'Tour category', 'brother-tours' ) ),
			'destination' => array( 'tour_destination', __( 'Destination', 'brother-tours' ) ),
			'difficulty'  => array( 'tour_difficulty', __( 'Difficulty', 'brother-tours' ) ),
			'duration'    => array( 'tour_duration_range', __( 'Duration', 'brother-tours' ) ),
			'region'      => array( 'region', __( 'Region', 'brother-tours' ) ),
			'style'       => array( 'travel_style', __( 'Travel style', 'brother-tours' ) ),
			'season'      => array( 'tour_season', __( 'Best season', 'brother-tours' ) ),
		);
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);

		$labels = array();
		foreach ( $this->fields() as $param => $field ) {
			$labels[ $param ] = $field[1];
		}

		$this->add_control(
			'wpistic_fields',
			array(
				'label'    => __( 'Filters to show', 'brother-tours' ),
				'type'     => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options'  => $labels,
				'default'  => array_keys( $labels ),
			)
		);

		$this->add_control(
			'wpistic_submit_label',
			array(
				'label'   => __( 'Submit button label', 'brother-tours' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Apply', 'brother-tours' ),
			)
		);

		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$active   = (array) ( $settings['wpistic_fields'] ?? array_keys( $this->fields() ) );
		$fields   = array_intersect_key( $this->fields(), array_flip( $active ) );

		if ( ! $fields ) {
			$this->render_empty_state( __( 'Select at least one filter in the widget settings.', 'brother-tours' ) );
			return;
		}

		$archive_url = get_post_type_archive_link( 'wpistic_tour' );
		$archive_url = $archive_url ? $archive_url : home_url( '/tours/' );
		?>
		<form class="tours-toolbar bt-ew-tour-filters" method="get" action="<?php echo esc_url( $archive_url ); ?>">
			<div class="wrap">
				<?php
				foreach ( $fields as $param => $field ) :
					list( $taxonomy, $label ) = $field;
					$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
					if ( is_wp_error( $terms ) || ! $terms ) {
						continue;
					}
					// Read-only, shareable filter links; there is no form submission to nonce.
					$current = isset( $_GET[ $param ] ) ? sanitize_title( wp_unslash( $_GET[ $param ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					?>
					<div class="filter-field">
						<label for="bt-ew-filter-<?php echo esc_attr( $param ); ?>"><?php echo esc_html( $label ); ?></label>
						<select id="bt-ew-filter-<?php echo esc_attr( $param ); ?>" name="<?php echo esc_attr( $param ); ?>">
							<option value=""><?php echo esc_html( sprintf( /* translators: %s: filter label, e.g. "Region". */ __( 'Any %s', 'brother-tours' ), strtolower( $label ) ) ); ?></option>
							<?php foreach ( $terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"<?php selected( $current, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endforeach; ?>
				<button class="btn btn-navy" type="submit"><?php echo esc_html( $settings['wpistic_submit_label'] ?? __( 'Apply', 'brother-tours' ) ); ?></button>
			</div>
		</form>
		<?php
	}
}

/* =============================================================================
 * 4. Tour Facts
 * ========================================================================= */

class Brother_Tours_Widget_Tour_Facts extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-tour-facts';
	}

	public function get_title() {
		return __( 'Tour Facts', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-bullet-list';
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
		$settings = $this->get_settings_for_display();
		$tour_id  = $this->resolve_post_id( $settings );

		if ( ! $tour_id || 'wpistic_tour' !== get_post_type( $tour_id ) ) {
			$this->render_empty_state( __( 'Select a tour, or place this widget on a Tour page.', 'brother-tours' ) );
			return;
		}

		// Same six facts, same meta keys, as themes/wpistic/single-tour.php's
		// glance band -- but a fact is only shown here when the tour actually
		// has a value for it, rather than falling back to sample copy.
		$facts = array(
			array( __( 'Duration', 'brother-tours' ), get_post_meta( $tour_id, 'wpistic_duration', true ) ),
			array( __( 'Start', 'brother-tours' ), get_post_meta( $tour_id, 'wpistic_start', true ) ),
			array( __( 'End', 'brother-tours' ), get_post_meta( $tour_id, 'wpistic_end', true ) ),
			array( __( 'Style', 'brother-tours' ), get_post_meta( $tour_id, 'wpistic_style', true ) ),
			array( __( 'Group', 'brother-tours' ), get_post_meta( $tour_id, 'wpistic_group_size', true ) ),
			array( __( 'Best season', 'brother-tours' ), get_post_meta( $tour_id, 'wpistic_season', true ) ),
		);
		$facts = array_values( array_filter( $facts, static fn( $f ) => '' !== trim( (string) $f[1] ) ) );

		if ( ! $facts ) {
			$this->render_empty_state( __( 'This tour has no at-a-glance facts yet.', 'brother-tours' ) );
			return;
		}
		?>
		<div class="bt-ew-facts">
			<?php foreach ( $facts as $fact ) : ?>
				<div class="glance-item">
					<p class="glance-label"><?php echo esc_html( $fact[0] ); ?></p>
					<p class="glance-value"><?php echo esc_html( $fact[1] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

/* =============================================================================
 * 5. Tour Pricing
 * ========================================================================= */

class Brother_Tours_Widget_Tour_Pricing extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-tour-pricing';
	}

	public function get_title() {
		return __( 'Tour Pricing', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-price-table';
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
		$settings = $this->get_settings_for_display();
		$tour_id  = $this->resolve_post_id( $settings );

		if ( ! $tour_id || 'wpistic_tour' !== get_post_type( $tour_id ) ) {
			$this->render_empty_state( __( 'Select a tour, or place this widget on a Tour page.', 'brother-tours' ) );
			return;
		}

		$from = get_post_meta( $tour_id, 'wpistic_from_price', true );
		$cap  = (string) get_post_meta( $tour_id, 'wpistic_departures_label', true );
		?>
		<div class="bt-ew-pricing">
			<p class="bt-ew-pricing-price"><?php echo esc_html( wpistic_price_line( (string) $from ) ); ?></p>
			<?php if ( $cap ) : ?>
				<p class="bt-ew-pricing-cap"><?php echo esc_html( $cap ); ?></p>
			<?php endif; ?>
			<?php
			if ( shortcode_exists( 'wpistic_booking_widget' ) ) {
				echo do_shortcode( '[wpistic_booking_widget id="' . esc_attr( $tour_id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output is trusted plugin markup.
			} else {
				$title = get_the_title( $tour_id );
				?>
				<a class="btn btn-navy" href="<?php echo esc_url( add_query_arg( 'tour', rawurlencode( $title ), wpistic_cta_url() ) ); ?>"><?php esc_html_e( 'Request Itinerary', 'brother-tours' ); ?></a>
				<?php
			}
			?>
		</div>
		<?php
	}
}

/* =============================================================================
 * 6. Tour Itinerary
 * ========================================================================= */

class Brother_Tours_Widget_Tour_Itinerary extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-tour-itinerary';
	}

	public function get_title() {
		return __( 'Tour Itinerary', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-time-line';
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
		$settings = $this->get_settings_for_display();
		$tour_id  = $this->resolve_post_id( $settings );

		if ( ! $tour_id || 'wpistic_tour' !== get_post_type( $tour_id ) ) {
			$this->render_empty_state( __( 'Select a tour, or place this widget on a Tour page.', 'brother-tours' ) );
			return;
		}

		$days = get_post_meta( $tour_id, 'wpistic_itinerary', true );

		if ( ! is_array( $days ) || ! $days ) {
			$this->render_empty_state( __( 'This tour has no day-by-day itinerary yet.', 'brother-tours' ) );
			return;
		}
		?>
		<div class="itinerary">
			<?php foreach ( $days as $n => $day ) : ?>
				<details class="itin-day"<?php echo 0 === $n ? ' open' : ''; ?>>
					<summary>
						<span class="itin-num"><?php echo esc_html( sprintf( /* translators: %d: day number. */ __( 'Day %d', 'brother-tours' ), $n + 1 ) ); ?></span>
						<span class="itin-title"><?php echo esc_html( is_array( $day ) ? ( $day['title'] ?? '' ) : '' ); ?></span>
					</summary>
					<p class="itin-body"><?php echo esc_html( is_array( $day ) ? ( $day['body'] ?? '' ) : '' ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

/* =============================================================================
 * 7. Included and Excluded
 *
 * Gap: no `wpistic_inclusions` / `wpistic_exclusions` meta exists yet --
 * themes/wpistic/single-tour.php currently hardcodes one static list for
 * every tour. Rather than fabricating brand claims per tour, or adding a
 * competing meta box under plugins/wpistic-tour-manager/src/Admin/ (off
 * limits -- the admin dashboard is being rebuilt there in parallel), this
 * widget defines the contract (`wpistic_inclusions` / `wpistic_exclusions`,
 * arrays of strings) and reads it if present. Until an editor UI writes
 * those keys, it shows an editor-only notice instead of guessing.
 * ========================================================================= */

class Brother_Tours_Widget_Included_Excluded extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-tour-included-excluded';
	}

	public function get_title() {
		return __( 'Included and Excluded', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-checkbox';
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
		$settings = $this->get_settings_for_display();
		$tour_id  = $this->resolve_post_id( $settings );

		if ( ! $tour_id || 'wpistic_tour' !== get_post_type( $tour_id ) ) {
			$this->render_empty_state( __( 'Select a tour, or place this widget on a Tour page.', 'brother-tours' ) );
			return;
		}

		$included = get_post_meta( $tour_id, 'wpistic_inclusions', true );
		$excluded = get_post_meta( $tour_id, 'wpistic_exclusions', true );
		$included = is_array( $included ) ? array_filter( array_map( 'trim', $included ) ) : array();
		$excluded = is_array( $excluded ) ? array_filter( array_map( 'trim', $excluded ) ) : array();

		if ( ! $included && ! $excluded ) {
			$this->render_admin_notice( __( 'No inclusions or exclusions are set for this tour yet. Add "wpistic_inclusions" / "wpistic_exclusions" post meta (arrays of strings) to show this block.', 'brother-tours' ) );
			return;
		}
		?>
		<div class="incl-grid">
			<?php if ( $included ) : ?>
				<div class="incl-list">
					<?php foreach ( $included as $line ) : ?>
						<div class="row"><span class="incl-mark">&#10003;</span><?php echo esc_html( $line ); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $excluded ) : ?>
				<div class="incl-list">
					<?php foreach ( $excluded as $line ) : ?>
						<div class="row"><span class="incl-mark no">&#8211;</span><?php echo esc_html( $line ); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

/* =============================================================================
 * 8. Tour Gallery
 * ========================================================================= */

class Brother_Tours_Widget_Tour_Gallery extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-tour-gallery';
	}

	public function get_title() {
		return __( 'Tour Gallery', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-gallery-masonry';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);
		$this->register_source_control( 'wpistic_tour', __( 'Tour', 'brother-tours' ) );
		$this->add_control(
			'wpistic_count',
			array(
				'label'   => __( 'Maximum images', 'brother-tours' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 24,
				'default' => 8,
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'wpistic_section_layout',
			array(
				'label' => __( 'Layout', 'brother-tours' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'wpistic_gal_columns',
			array(
				'label'     => __( 'Columns', 'brother-tours' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '4',
				'options'   => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'selectors' => array(
					'{{WRAPPER}} .gallery-grid' => '--bt-ew-gal-cols: {{VALUE}};',
				),
			)
		);
		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$tour_id  = $this->resolve_post_id( $settings );

		if ( ! $tour_id || 'wpistic_tour' !== get_post_type( $tour_id ) ) {
			$this->render_empty_state( __( 'Select a tour, or place this widget on a Tour page.', 'brother-tours' ) );
			return;
		}

		$gallery = get_post_meta( $tour_id, 'wpistic_gallery', true );
		$count   = max( 1, (int) ( $settings['wpistic_count'] ?? 8 ) );

		if ( ! is_array( $gallery ) || ! $gallery ) {
			$this->render_empty_state( __( 'This tour has no gallery images yet.', 'brother-tours' ) );
			return;
		}
		?>
		<div class="gallery-grid">
			<?php foreach ( array_slice( $gallery, 0, $count ) as $attachment_id ) : ?>
				<div class="cell"><?php echo wp_get_attachment_image( absint( $attachment_id ), 'bt-gallery', false, array( 'loading' => 'lazy' ) ); ?></div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

/* =============================================================================
 * 9. Tour FAQ
 *
 * Gap: no `wpistic_faq` meta exists yet -- single-tour.php currently
 * hardcodes three generic questions for every tour. Same judgment call as
 * Included/Excluded above: define the `wpistic_faq` contract (array of
 * {q, a} pairs) and show an editor-only notice until it is populated,
 * rather than repeating one canned FAQ set as if it were per-tour content.
 * ========================================================================= */

class Brother_Tours_Widget_Tour_Faq extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-tour-faq';
	}

	public function get_title() {
		return __( 'Tour FAQ', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-toggle';
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
		$settings = $this->get_settings_for_display();
		$tour_id  = $this->resolve_post_id( $settings );

		if ( ! $tour_id || 'wpistic_tour' !== get_post_type( $tour_id ) ) {
			$this->render_empty_state( __( 'Select a tour, or place this widget on a Tour page.', 'brother-tours' ) );
			return;
		}

		$faq = get_post_meta( $tour_id, 'wpistic_faq', true );
		$faq = is_array( $faq ) ? array_values( array_filter( $faq, static fn( $row ) => is_array( $row ) && ! empty( $row['q'] ) ) ) : array();

		if ( ! $faq ) {
			$this->render_admin_notice( __( 'No FAQ entries are set for this tour yet. Add "wpistic_faq" post meta (an array of {q, a} pairs) to show this block.', 'brother-tours' ) );
			return;
		}
		?>
		<div class="faq">
			<?php foreach ( $faq as $i => $row ) : ?>
				<details class="faq-item"<?php echo 0 === $i ? ' open' : ''; ?>>
					<summary class="faq-q"><?php echo esc_html( $row['q'] ); ?></summary>
					<div class="faq-a"><?php echo esc_html( $row['a'] ?? '' ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

/* =============================================================================
 * 10. Related Tours
 * ========================================================================= */

class Brother_Tours_Widget_Related_Tours extends Wpistic_Elementor_Widget_Base {

	public function get_name() {
		return 'bt-related-tours';
	}

	public function get_title() {
		return __( 'Related Tours', 'brother-tours' );
	}

	public function get_icon() {
		return 'eicon-posts-grid';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wpistic_section_content',
			array( 'label' => __( 'Content', 'brother-tours' ) )
		);
		$this->register_source_control( 'wpistic_tour', __( 'Tour', 'brother-tours' ) );
		$this->add_control(
			'wpistic_count',
			array(
				'label'   => __( 'Number of tours', 'brother-tours' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 12,
				'default' => 3,
			)
		);
		$this->end_controls_section();

		$this->register_spacing_controls();
	}

	protected function render() {
		if ( ! function_exists( 'wpistic_tour_card' ) ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$tour_id  = $this->resolve_post_id( $settings );

		if ( ! $tour_id || 'wpistic_tour' !== get_post_type( $tour_id ) ) {
			$this->render_empty_state( __( 'Select a tour, or place this widget on a Tour page.', 'brother-tours' ) );
			return;
		}

		$count = max( 1, (int) ( $settings['wpistic_count'] ?? 3 ) );

		// Prefer tours sharing a tour_category term; fall back to any other
		// tour so the widget is never empty on a site with only one category.
		$terms   = wp_get_post_terms( $tour_id, 'tour_category', array( 'fields' => 'ids' ) );
		$related = array();

		if ( ! is_wp_error( $terms ) && $terms ) {
			$related = get_posts(
				array(
					'post_type'        => 'wpistic_tour',
					'post_status'      => 'publish',
					'numberposts'      => $count,
					'post__not_in'     => array( $tour_id ),
					'orderby'          => 'rand',
					'suppress_filters' => false,
					'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'tour_category',
							'field'    => 'term_id',
							'terms'    => $terms,
						),
					),
				)
			);
		}

		if ( count( $related ) < $count ) {
			$fill = get_posts(
				array(
					'post_type'        => 'wpistic_tour',
					'post_status'      => 'publish',
					'numberposts'      => $count - count( $related ),
					'post__not_in'     => array_merge( array( $tour_id ), wp_list_pluck( $related, 'ID' ) ),
					'orderby'          => 'rand',
					'suppress_filters' => false,
				)
			);
			$related = array_merge( $related, $fill );
		}

		if ( ! $related ) {
			$this->render_empty_state( __( 'No other tours are published yet.', 'brother-tours' ) );
			return;
		}
		?>
		<div class="tour-grid">
			<?php
			foreach ( $related as $tour ) {
				$regions = wp_get_post_terms( $tour->ID, 'region', array( 'fields' => 'names' ) );
				wpistic_tour_card(
					array(
						'name'     => get_the_title( $tour ),
						'url'      => get_permalink( $tour ),
						'region'   => ( is_array( $regions ) && $regions ) ? (string) $regions[0] : '',
						'meta'     => get_post_meta( $tour->ID, 'wpistic_duration', true ),
						'blurb'    => get_the_excerpt( $tour ),
						'tags'     => array_filter( array( get_post_meta( $tour->ID, 'wpistic_departures_label', true ) ) ),
						'price'    => wpistic_price_line( get_post_meta( $tour->ID, 'wpistic_from_price', true ) ),
						'image_id' => get_post_thumbnail_id( $tour ),
					)
				);
			}
			?>
		</div>
		<?php
	}
}

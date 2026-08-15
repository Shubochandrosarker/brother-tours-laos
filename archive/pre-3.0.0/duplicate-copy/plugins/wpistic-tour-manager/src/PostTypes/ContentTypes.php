<?php

declare(strict_types=1);

namespace Wpistic\TourManager\PostTypes;

/**
 * Registers the tour content model: CPTs, taxonomies, and the nested
 * /destinations/{destination}/{experience}/ routing.
 */
final class ContentTypes {

	public function register(): void {
		add_action( 'init', array( $this, 'post_types' ) );
		add_action( 'init', array( $this, 'taxonomies' ) );
		add_action( 'init', array( $this, 'experience_rewrites' ), 11 );
		add_filter( 'post_type_link', array( $this, 'experience_permalink' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_tour_archive' ) );
		add_action( 'init', array( $this, 'register_rest_meta' ), 20 );
	}

	public function post_types(): void {
		register_post_type(
			'wpistic_tour',
			$this->args(
				__( 'Tour', 'wpistic-tour-manager' ),
				__( 'Tours', 'wpistic-tour-manager' ),
				array(
					'menu_icon'   => 'dashicons-palmtree',
					'has_archive' => 'tours',
					'rewrite'     => array( 'slug' => 'tour', 'with_front' => false ),
					'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				)
			)
		);

		register_post_type(
			'wpistic_destination',
			$this->args(
				__( 'Destination', 'wpistic-tour-manager' ),
				__( 'Destinations', 'wpistic-tour-manager' ),
				array(
					'menu_icon'   => 'dashicons-location-alt',
					'has_archive' => 'destinations',
					'rewrite'     => array( 'slug' => 'destinations', 'with_front' => false ),
					'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				)
			)
		);

		register_post_type(
			'wpistic_experience',
			$this->args(
				__( 'Experience', 'wpistic-tour-manager' ),
				__( 'Experiences', 'wpistic-tour-manager' ),
				array(
					'menu_icon'   => 'dashicons-camera',
					'has_archive' => false,
					'rewrite'     => false, // routed manually under a destination.
					'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				)
			)
		);

		register_post_type(
			'wpistic_departure',
			$this->args(
				__( 'Departure', 'wpistic-tour-manager' ),
				__( 'Departures', 'wpistic-tour-manager' ),
				array(
					'public'             => false,
					'show_ui'            => true,
					'show_in_menu'       => 'edit.php?post_type=wpistic_tour',
					'publicly_queryable' => false,
					'exclude_from_search' => true,
					'has_archive'        => false,
					'rewrite'            => false,
					'supports'           => array( 'title' ),
					'show_in_rest'       => false,
				)
			)
		);
	}

	public function taxonomies(): void {
		register_taxonomy(
			'country',
			array( 'wpistic_tour', 'wpistic_destination' ),
			$this->tax_args( __( 'Country', 'wpistic-tour-manager' ), __( 'Countries', 'wpistic-tour-manager' ), 'country' )
		);

		register_taxonomy(
			'region',
			array( 'wpistic_tour', 'wpistic_destination', 'wpistic_experience' ),
			$this->tax_args( __( 'Region', 'wpistic-tour-manager' ), __( 'Regions', 'wpistic-tour-manager' ), 'region' )
		);

		register_taxonomy(
			'travel_style',
			array( 'wpistic_tour' ),
			$this->tax_args( __( 'Travel Style', 'wpistic-tour-manager' ), __( 'Travel Styles', 'wpistic-tour-manager' ), 'travel-style' )
		);

		$taxonomies = array(
			'tour_category'       => array( __( 'Tour Category', 'wpistic-tour-manager' ), __( 'Tour Categories', 'wpistic-tour-manager' ), 'tour-category' ),
			'tour_destination'    => array( __( 'Tour Destination', 'wpistic-tour-manager' ), __( 'Tour Destinations', 'wpistic-tour-manager' ), 'tour-destination' ),
			'tour_duration_range' => array( __( 'Duration Range', 'wpistic-tour-manager' ), __( 'Duration Ranges', 'wpistic-tour-manager' ), 'tour-duration' ),
			'tour_difficulty'     => array( __( 'Difficulty', 'wpistic-tour-manager' ), __( 'Difficulties', 'wpistic-tour-manager' ), 'tour-difficulty' ),
			'tour_season'         => array( __( 'Best Season', 'wpistic-tour-manager' ), __( 'Best Seasons', 'wpistic-tour-manager' ), 'tour-season' ),
		);
		foreach ( $taxonomies as $taxonomy => $labels ) {
			register_taxonomy( $taxonomy, array( 'wpistic_tour' ), $this->tax_args( $labels[0], $labels[1], $labels[2] ) );
		}
	}

	/** Apply accessible, shareable GET filters to the main tours archive query. */
	public function filter_tour_archive( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'wpistic_tour' ) ) {
			return;
		}
		$map = array(
			'region'      => 'region',
			'style'       => 'travel_style',
			'category'    => 'tour_category',
			'destination' => 'tour_destination',
			'duration'    => 'tour_duration_range',
			'difficulty'  => 'tour_difficulty',
			'season'      => 'tour_season',
		);
		$tax_query = array();
		foreach ( $map as $parameter => $taxonomy ) {
			$value = isset( $_GET[ $parameter ] ) ? sanitize_title( wp_unslash( $_GET[ $parameter ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public archive filter.
			if ( $value ) {
				$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $value );
			}
		}
		if ( $tax_query ) {
			$tax_query['relation'] = 'AND';
			$query->set( 'tax_query', $tax_query );
		}
		$query->set( 'posts_per_page', 12 );
	}

	/** Expose non-sensitive tour facts read-only for future app integrations. */
	public function register_rest_meta(): void {
		$keys = array( 'wpistic_subtitle', 'wpistic_short_summary', 'wpistic_duration', 'wpistic_start_location', 'wpistic_end_location', 'wpistic_group_size', 'wpistic_minimum_age', 'wpistic_season', 'wpistic_accommodation', 'wpistic_transport', 'wpistic_from_price', 'wpistic_pricing_type', 'wpistic_availability' );
		foreach ( $keys as $key ) {
			register_post_meta( 'wpistic_tour', $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => '__return_true' ) );
		}
	}

	public function experience_rewrites(): void {
		add_rewrite_tag( '%wpistic_destination%', '([^/]+)' );
		add_rewrite_rule(
			'^destinations/([^/]+)/([^/]+)/?$',
			'index.php?wpistic_experience=$matches[2]',
			'top'
		);
	}

	/**
	 * Build /destinations/{destination}/{experience}/ links for experiences.
	 *
	 * @param string   $link The post URL.
	 * @param \WP_Post $post The post.
	 * @return string
	 */
	public function experience_permalink( $link, $post ) {
		if ( ! $post instanceof \WP_Post || 'wpistic_experience' !== $post->post_type ) {
			return $link;
		}

		$destination_id = (int) get_post_meta( $post->ID, 'wpistic_parent_destination', true );
		$destination    = $destination_id ? get_post_field( 'post_name', $destination_id ) : 'experiences';

		return home_url( '/destinations/' . $destination . '/' . $post->post_name . '/' );
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function args( string $singular, string $plural, array $overrides ): array {
		$labels = array(
			'name'               => $plural,
			'singular_name'      => $singular,
			/* translators: %s: post type singular name. */
			'add_new_item'       => sprintf( __( 'Add New %s', 'wpistic-tour-manager' ), $singular ),
			/* translators: %s: post type singular name. */
			'edit_item'          => sprintf( __( 'Edit %s', 'wpistic-tour-manager' ), $singular ),
			'all_items'          => $plural,
			'menu_name'          => $plural,
		);

		return array_merge(
			array(
				'labels'       => $labels,
				'public'       => true,
				'show_in_rest' => true,
				'menu_position' => 26,
			),
			$overrides
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function tax_args( string $singular, string $plural, string $slug ): array {
		return array(
			'labels'            => array(
				'name'          => $plural,
				'singular_name' => $singular,
				'menu_name'     => $plural,
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => $slug, 'with_front' => false ),
		);
	}
}

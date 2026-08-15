<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

final class Seo {
	public function register(): void {
		add_filter( 'pre_get_document_title', array( $this, 'title' ), 20 );
		add_action( 'wp_head', array( $this, 'head' ), 2 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'seoistic/tour_data', array( $this, 'tour_data' ), 20, 2 );
		add_filter( 'seoistic/destination_data', array( $this, 'destination_data' ), 20, 2 );
	}

	public function title( string $title ): string {
		if ( $this->primary_owner_active() ) {
			return $title;
		}
		$custom = $this->current_meta( 'bt_seo_title' );
		return $custom ?: $title;
	}

	public function head(): void {
		if ( is_admin() || $this->primary_owner_active() ) {
			return;
		}
		$description = $this->current_meta( 'bt_seo_description' );
		$canonical   = $this->current_meta( 'bt_seo_canonical' ) ?: ( is_singular() ? get_permalink() : '' );
		if ( $description ) {
			printf( '<meta name="description" content="%s">\n', esc_attr( wp_strip_all_tags( $description ) ) );
			printf( '<meta property="og:description" content="%s">\n', esc_attr( wp_strip_all_tags( $description ) ) );
		}
		if ( $canonical ) {
			printf( '<link rel="canonical" href="%s">\n', esc_url( $canonical ) );
		}
	}

	/** @param array<string,bool> $robots @return array<string,bool> */
	public function robots( array $robots ): array {
		if ( Sitemap::is_staging() ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
		}
		return $robots;
	}

	/** @param mixed $data @return array<string,mixed> */
	public function tour_data( mixed $data, mixed $post_id ): array {
		$post_id = absint( $post_id );
		$data    = (array) $data;
		$duration = (string) get_post_meta( $post_id, 'wpistic_duration', true );
		$currency = strtoupper( (string) get_post_meta( $post_id, 'bt_price_currency', true ) );
		if ( $duration ) { $data['duration'] = $duration; }
		if ( $currency && preg_match( '/^[A-Z]{3}$/', $currency ) ) { $data['priceCurrency'] = $currency; }
		$styles = array_values( array_filter( array_map( 'sanitize_text_field', (array) wp_get_post_terms( $post_id, 'travel_style', array( 'fields' => 'names' ) ) ) ) );
		if ( $styles ) {
			$data['touristType'] = $styles;
		}
		return $data;
	}

	/** @param mixed $data @return array<string,mixed> */
	public function destination_data( mixed $data, mixed $post_id ): array {
		$post_id = absint( $post_id );
		$data    = (array) $data;
		$best    = (string) get_post_meta( $post_id, 'bt_best_time', true );
		$tips    = (string) get_post_meta( $post_id, 'bt_local_tips', true );
		if ( $best ) { $data['bestTimeToVisit'] = $best; }
		if ( $tips ) { $data['description'] = trim( (string) ( $data['description'] ?? '' ) . ' ' . wp_strip_all_tags( $tips ) ); }
		return $data;
	}

	private function primary_owner_active(): bool {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		$active_plugins = array_merge( $active_plugins, array_keys( $network_plugins ) );
		$active = function_exists( 'seoistic' ) || class_exists( 'SEOistic' ) || (bool) array_filter( $active_plugins, static fn( string $plugin ): bool => str_ends_with( $plugin, '/seoistic.php' ) || 'seoistic.php' === $plugin );
		return (bool) apply_filters( 'bt_cs_primary_seo_owner_active', $active );
	}

	private function current_meta( string $key ): string {
		if ( ! is_singular() ) { return ''; }
		return trim( (string) get_post_meta( get_queried_object_id(), $key, true ) );
	}
}

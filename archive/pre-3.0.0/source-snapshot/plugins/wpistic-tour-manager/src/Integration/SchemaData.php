<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Integration;

/**
 * Exposes tour content as structured data via filters. SEOISTIC (Phase 3) consumes
 * these and emits the JSON-LD — the Tour Manager never prints schema itself, keeping
 * SEO ownership in one place.
 */
final class SchemaData {

	public function register(): void {
		add_filter( 'seoistic/tour_data', array( $this, 'tour' ), 10, 2 );
		add_filter( 'seoistic/destination_data', array( $this, 'destination' ), 10, 2 );
		add_filter( 'seoistic/experience_data', array( $this, 'experience' ), 10, 2 );
	}

	/**
	 * @param mixed $data
	 * @return array<string, mixed>
	 */
	public function tour( $data, $post_id ) {
		$post_id = (int) $post_id;
		$data    = (array) $data;
		unset( $data['offers'] );
		$schema  = array_merge(
			$data,
			array(
				'@type'       => 'TouristTrip',
				'name'        => get_the_title( $post_id ),
				'description' => get_the_excerpt( $post_id ),
				'url'         => get_permalink( $post_id ),
			)
		);

		/*
		 * Commercial schema must mirror visible, authoritative data. An empty or
		 * non-numeric price is an inquiry-only tour, so no Offer is emitted. The
		 * plugin deliberately omits availability: a departure/inventory source has
		 * to provide that signal rather than this integration guessing it.
		 */
		$price    = trim( (string) get_post_meta( $post_id, 'wpistic_from_price', true ) );
		$currency = strtoupper( trim( (string) get_option( 'wpistic_tm_currency', '' ) ) );
		if ( is_numeric( $price ) && (float) $price > 0 && 1 === preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'priceCurrency' => $currency,
				'price'         => $price,
				'url'           => get_permalink( $post_id ),
			);
		}

		return $schema;
	}

	/**
	 * @param mixed $data
	 * @return array<string, mixed>
	 */
	public function destination( $data, $post_id ) {
		$post_id = (int) $post_id;
		return array_merge(
			(array) $data,
			array(
				'@type'       => 'TouristDestination',
				'name'        => get_the_title( $post_id ),
				'description' => get_the_excerpt( $post_id ),
				'url'         => get_permalink( $post_id ),
			)
		);
	}

	/**
	 * @param mixed $data
	 * @return array<string, mixed>
	 */
	public function experience( $data, $post_id ) {
		$post_id = (int) $post_id;
		return array_merge(
			(array) $data,
			array(
				'@type'       => 'TouristAttraction',
				'name'        => get_the_title( $post_id ),
				'description' => get_the_excerpt( $post_id ),
				'url'         => get_permalink( $post_id ),
			)
		);
	}
}

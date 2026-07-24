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
		return array_merge(
			(array) $data,
			array(
				'@type'       => 'TouristTrip',
				'name'        => get_the_title( $post_id ),
				'description' => get_the_excerpt( $post_id ),
				'url'         => get_permalink( $post_id ),
				'offers'      => array(
					'@type'         => 'Offer',
					'priceCurrency' => (string) get_option( 'wpistic_tm_currency', 'USD' ),
					'price'         => (string) get_post_meta( $post_id, 'wpistic_from_price', true ),
					'availability'  => 'https://schema.org/LimitedAvailability',
					'url'           => get_permalink( $post_id ),
				),
			)
		);
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

<?php

declare(strict_types=1);

namespace BrotherTours\ResourceDownloads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single source of truth for downloadable resources.
 *
 * Every resource carries the same normalised metadata, so a future
 * /travel-guides/resources/ library can enumerate them without this plugin
 * changing shape. Adding a resource is a filter, not a code edit.
 *
 * A resource with an empty pdf_url is treated as NOT READY: it never renders a
 * CTA and never opens a popup. That is deliberate — a download button pointing
 * at nothing is worse than no button, and the PDFs are produced separately from
 * this system.
 */
final class Registry {

	/**
	 * Resource definitions keyed by resource id.
	 *
	 * `pdf_url` and `cover_image` are intentionally empty here. They are filled
	 * per-environment via the `btrd_resources` filter (or an options screen
	 * later) so that no staging or developer URL can ever be committed.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		$resources = array(
			'adventure-planner' => array(
				'resource_name'     => __( 'Laos Adventure Travel Planner', 'brother-tours-resource-downloads' ),
				'resource_category' => 'adventure',
				'canonical_page'    => '/adventure-tours/',
				'trip_interest'     => 'adventure',
				'popup_headline'    => __( 'Planning an adventure in Laos?', 'brother-tours-resource-downloads' ),
				'description'       => __( 'Regions, seasons and a practical packing checklist, written by the team that guides these routes.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'Best adventure regions', 'brother-tours-resource-downloads' ),
					__( 'Seasonal planning', 'brother-tours-resource-downloads' ),
					__( 'Packing checklist', 'brother-tours-resource-downloads' ),
					__( 'Suggested journey ideas', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'Download the adventure planner', 'brother-tours-resource-downloads' ),
				'secondary_action'  => 'view',
			),
			'central-laos-guide' => array(
				'resource_name'     => __( 'Central Laos Travel Guide', 'brother-tours-resource-downloads' ),
				'resource_category' => 'destination',
				'canonical_page'    => '/central-laos/',
				'trip_interest'     => 'central_laos',
				'popup_headline'    => __( 'Exploring Central Laos?', 'brother-tours-resource-downloads' ),
				'description'       => __( 'Where to go between Vientiane, Vang Vieng and Kong Lor, and how long to give it.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'Where to go', 'brother-tours-resource-downloads' ),
					__( 'How many days', 'brother-tours-resource-downloads' ),
					__( 'Route planning', 'brother-tours-resource-downloads' ),
					__( 'Local travel tips', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'Download the Central Laos guide', 'brother-tours-resource-downloads' ),
				'secondary_action'  => 'view',
			),
			'founder-hosted-guide' => array(
				'resource_name'     => __( 'The Founder-Hosted Laos Experience', 'brother-tours-resource-downloads' ),
				'resource_category' => 'signature',
				'canonical_page'    => '/founder-hosted-signature-journeys/',
				'trip_interest'     => 'founder_hosted',
				'popup_headline'    => __( 'Discover founder-hosted Laos', 'brother-tours-resource-downloads' ),
				'description'       => __( 'What changes when Ken hosts the journey himself, and who this style of travel suits.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'Travel with Ken', 'brother-tours-resource-downloads' ),
					__( 'Local insight', 'brother-tours-resource-downloads' ),
					__( 'Private journey style', 'brother-tours-resource-downloads' ),
					__( 'Personal planning', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'View the founder-hosted guide', 'brother-tours-resource-downloads' ),
				'secondary_action'  => 'view',
			),
			'honeymoon-guide' => array(
				'resource_name'     => __( 'Laos Honeymoon Planning Guide', 'brother-tours-resource-downloads' ),
				'resource_category' => 'honeymoon',
				'canonical_page'    => '/honeymoon-packages/',
				'trip_interest'     => 'honeymoon',
				'popup_headline'    => __( 'Planning your honeymoon in Laos?', 'brother-tours-resource-downloads' ),
				'description'       => __( 'Romantic regions, sensible pacing and a planning checklist for two people, not a package.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'Romantic destinations', 'brother-tours-resource-downloads' ),
					__( 'Suggested trip length', 'brother-tours-resource-downloads' ),
					__( 'Private experiences', 'brother-tours-resource-downloads' ),
					__( 'Honeymoon checklist', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'Download the honeymoon guide', 'brother-tours-resource-downloads' ),
				'secondary_action'  => 'view',
			),
			'indochina-planner' => array(
				'resource_name'     => __( 'Laos + Indochina Journey Planner', 'brother-tours-resource-downloads' ),
				'resource_category' => 'multi-country',
				'canonical_page'    => '/indochina-tours/',
				'trip_interest'     => 'multi_country',
				'popup_headline'    => __( 'Planning a multi-country journey?', 'brother-tours-resource-downloads' ),
				'description'       => __( 'Laos-first routing, realistic trip lengths and what border days actually cost you.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'Route combinations', 'brother-tours-resource-downloads' ),
					__( 'Trip duration', 'brother-tours-resource-downloads' ),
					__( 'Border planning', 'brother-tours-resource-downloads' ),
					__( 'Laos-first expertise', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'Download the Indochina planner', 'brother-tours-resource-downloads' ),
				'secondary_action'  => 'view',
			),
			'signature-guide' => array(
				'resource_name'     => __( 'Brother Tours Signature Laos Guide', 'brother-tours-resource-downloads' ),
				'resource_category' => 'signature',
				'canonical_page'    => '/laos-signature-tours/',
				'trip_interest'     => 'signature',
				'popup_headline'    => __( 'Find your signature Laos journey', 'brother-tours-resource-downloads' ),
				'description'       => __( 'North, central and south compared, with route ideas and how to choose between them.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'Signature experiences', 'brother-tours-resource-downloads' ),
					__( 'Regional highlights', 'brother-tours-resource-downloads' ),
					__( 'Route ideas', 'brother-tours-resource-downloads' ),
					__( 'Private planning', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'Download the signature guide', 'brother-tours-resource-downloads' ),
				'secondary_action'  => 'view',
			),
			'lcr-guide' => array(
				'resource_name'     => __( 'Lao-China Railway E-Ticket Guide', 'brother-tours-resource-downloads' ),
				'resource_category' => 'practical',
				'canonical_page'    => '/lcr-e-ticket-guide/',
				'trip_interest'     => 'railway_assistance',
				'popup_headline'    => __( 'Keep the railway guide offline', 'brother-tours-resource-downloads' ),
				'description'       => __( 'The whole boarding process in one file, for when the station has no signal.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'QR ticket instructions', 'brother-tours-resource-downloads' ),
					__( 'Prohibited items', 'brother-tours-resource-downloads' ),
					__( 'Boarding process', 'brother-tours-resource-downloads' ),
					__( 'Travel tips', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'Download the railway guide', 'brother-tours-resource-downloads' ),
				// Print earns its place here: travellers genuinely carry this one
				// to the station. Inspirational guides get "view" instead.
				'secondary_action'  => 'print',
			),
			'luxury-guide' => array(
				'resource_name'     => __( 'Private Luxury Travel in Laos', 'brother-tours-resource-downloads' ),
				'resource_category' => 'luxury',
				'canonical_page'    => '/luxury-laos-tours/',
				'trip_interest'     => 'luxury',
				'popup_headline'    => __( 'Considering a private luxury journey?', 'brother-tours-resource-downloads' ),
				'description'       => __( 'What unhurried, private travel in Laos actually involves, and how it is planned.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'Boutique stays', 'brother-tours-resource-downloads' ),
					__( 'Private travel', 'brother-tours-resource-downloads' ),
					__( 'Personal planning', 'brother-tours-resource-downloads' ),
					__( 'Journey inspiration', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'Download the luxury guide', 'brother-tours-resource-downloads' ),
				'secondary_action'  => 'view',
			),
			'student-group-planner' => array(
				'resource_name'     => __( 'Laos Student Group Learning Planner', 'brother-tours-resource-downloads' ),
				'resource_category' => 'education',
				'canonical_page'    => '/student-group-learning/',
				'trip_interest'     => 'student_group',
				'popup_headline'    => __( 'Planning educational travel to Laos?', 'brother-tours-resource-downloads' ),
				'description'       => __( 'Learning themes, group logistics and a planning checklist for faculty organisers.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'Learning themes', 'brother-tours-resource-downloads' ),
					__( 'Group logistics', 'brother-tours-resource-downloads' ),
					__( 'Suggested activities', 'brother-tours-resource-downloads' ),
					__( 'Planning checklist', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'Download the student group planner', 'brother-tours-resource-downloads' ),
				'secondary_action'  => 'print',
			),
			'journey-calendar' => array(
				'resource_name'     => __( 'When to Visit Laos — Journey Planning Calendar', 'brother-tours-resource-downloads' ),
				'resource_category' => 'planning',
				'canonical_page'    => '/upcoming-tours/',
				'trip_interest'     => 'general',
				'popup_headline'    => __( 'Not sure when to visit Laos?', 'brother-tours-resource-downloads' ),
				'description'       => __( 'Month by month, region by region — what the weather and the rivers are actually doing.', 'brother-tours-resource-downloads' ),
				'benefits'          => array(
					__( 'Month-by-month guide', 'brother-tours-resource-downloads' ),
					__( 'Regional seasons', 'brother-tours-resource-downloads' ),
					__( 'Activity timing', 'brother-tours-resource-downloads' ),
					__( 'Journey planning', 'brother-tours-resource-downloads' ),
				),
				'cta_label'         => __( 'Download the travel calendar', 'brother-tours-resource-downloads' ),
				'secondary_action'  => 'view',
			),
		);

		$defaults = array(
			'resource_id'       => '',
			'resource_name'     => '',
			'resource_category' => 'general',
			'pdf_url'           => '',
			'pdf_filename'      => '',
			'cover_image'       => '',
			'canonical_page'    => '',
			'updated_date'      => '',
			'description'       => '',
			'benefits'          => array(),
			'popup_headline'    => '',
			'cta_label'         => __( 'Download free guide', 'brother-tours-resource-downloads' ),
			'secondary_action'  => 'view',
			'trip_interest'     => 'general',
		);

		foreach ( $resources as $id => $resource ) {
			$resources[ $id ] = array_merge( $defaults, $resource, array( 'resource_id' => $id ) );
		}

		/**
		 * Filters the resource registry.
		 *
		 * This is where pdf_url, cover_image and updated_date are supplied, so
		 * production media URLs live in configuration rather than in this file.
		 *
		 * @param array<string, array<string, mixed>> $resources
		 */
		$resources = (array) apply_filters( 'btrd_resources', $resources );

		return array_map( array( self::class, 'sanitize' ), $resources );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		$all = self::all();
		return $all[ $id ] ?? null;
	}

	/**
	 * A resource is publishable only once it has a real file behind it.
	 *
	 * @param array<string, mixed> $resource
	 */
	public static function is_ready( array $resource ): bool {
		return '' !== (string) ( $resource['pdf_url'] ?? '' );
	}

	/**
	 * Resolves the resource for the current request, if any.
	 *
	 * Matches on canonical_page against the current path, so a landing page does
	 * not have to declare its resource twice.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function for_current_request(): ?array {
		$path = '/' . trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ) . '/';
		foreach ( self::all() as $resource ) {
			$canonical = (string) $resource['canonical_page'];
			if ( '' !== $canonical && untrailingslashit( $canonical ) === untrailingslashit( $path ) ) {
				return self::is_ready( $resource ) ? $resource : null;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $resource
	 * @return array<string, mixed>
	 */
	private static function sanitize( array $resource ): array {
		return array(
			'resource_id'       => sanitize_key( (string) ( $resource['resource_id'] ?? '' ) ),
			'resource_name'     => sanitize_text_field( (string) ( $resource['resource_name'] ?? '' ) ),
			'resource_category' => sanitize_key( (string) ( $resource['resource_category'] ?? 'general' ) ),
			'pdf_url'           => esc_url_raw( (string) ( $resource['pdf_url'] ?? '' ) ),
			'pdf_filename'      => sanitize_file_name( (string) ( $resource['pdf_filename'] ?? '' ) ),
			'cover_image'       => esc_url_raw( (string) ( $resource['cover_image'] ?? '' ) ),
			'canonical_page'    => (string) ( $resource['canonical_page'] ?? '' ),
			'updated_date'      => sanitize_text_field( (string) ( $resource['updated_date'] ?? '' ) ),
			'description'       => sanitize_text_field( (string) ( $resource['description'] ?? '' ) ),
			'benefits'          => array_values( array_map( 'sanitize_text_field', (array) ( $resource['benefits'] ?? array() ) ) ),
			'popup_headline'    => sanitize_text_field( (string) ( $resource['popup_headline'] ?? '' ) ),
			'cta_label'         => sanitize_text_field( (string) ( $resource['cta_label'] ?? '' ) ),
			'secondary_action'  => in_array( $resource['secondary_action'] ?? 'view', array( 'view', 'print', 'none' ), true )
				? (string) $resource['secondary_action']
				: 'view',
			'trip_interest'     => sanitize_key( (string) ( $resource['trip_interest'] ?? 'general' ) ),
		);
	}
}

<?php
/**
 * Non-commercial presentation copy used by templates before reviewed content exists.
 *
 * Tour, destination, review, and article records are deliberately not fabricated.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The five signature journeys. Accent word marked for the single italic-gold <em>.
 *
 * @return array<int, array<string, string>>
 */
function wpistic_sample_tours() {
	return array();
}

/**
 * The six featured destinations.
 *
 * @return array<int, array<string, string>>
 */
function wpistic_sample_destinations() {
	return array();
}

/**
 * The three-line philosophy.
 *
 * @return array<int, array<string, string>>
 */
function wpistic_sample_philosophy() {
	return array(
		array( 'n' => '01', 'title' => 'Human <em>First</em>', 'body' => 'The people you travel with are the reason a place is worth visiting at all.' ),
		array( 'n' => '02', 'title' => 'Experience <em>Second</em>', 'body' => 'A guide reads the itinerary. A host reads the day, and changes the plan when the day asks for it.' ),
		array( 'n' => '03', 'title' => 'Destination <em>Third</em>', 'body' => 'The country is the setting. The morning a weaver lets you sit at her loom is the journey.' ),
	);
}

/**
 * Non-fabricated trust points shown until verified review embeds are configured.
 * No ratings, no counts, no aggregate review schema. Real quotes should come only from
 * the Google/TripAdvisor embed or confirmed review profile links.
 *
 * @return array<int, array<string, string>>
 */
function wpistic_sample_reviews() {
	return array();
}

/**
 * Sample journal teasers.
 *
 * @return array<int, array<string, string>>
 */
function wpistic_sample_journal() {
	return array();
}

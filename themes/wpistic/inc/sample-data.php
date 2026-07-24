<?php
/**
 * Sample content so every template renders before live Tour Manager content exists.
 * Templates prefer real queries and fall back to these launch-safe entries.
 *
 * All copy follows the brand rules: no banned words, locked phrases respected,
 * per-tour capacity only, and no fabricated review numbers or prices.
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
	return array(
		array(
			'name'   => 'Spine of the <em>Country</em>',
			'slug'   => 'spine-of-the-country',
			'meta'   => '14 days - Laos, north to south',
			'cap'    => '8 departures / year',
			'blurb'  => 'North to south across Laos, by river and road, at the pace the country keeps.',
		),
		array(
			'name'   => 'Northern <em>Rivers</em>',
			'slug'   => 'northern-rivers',
			'meta'   => 'Upper Mekong and Nam Ou',
			'cap'    => '8 departures / year',
			'blurb'  => 'The upper Mekong and its tributaries, villages reached by boat, not by bus.',
		),
		array(
			'name'   => 'Told <em>Carefully</em>',
			'slug'   => 'told-carefully',
			'meta'   => 'Xieng Khouang and the plateau',
			'cap'    => '6 departures / year',
			'blurb'  => 'The places where Laos keeps its harder history, walked with people who hold it.',
		),
		array(
			'name'   => 'Aboriginal Tribal <em>Loop</em>',
			'slug'   => 'aboriginal-tribal-loop',
			'meta'   => 'Highlands - green season',
			'cap'    => '6 departures / year - Jun to Sep',
			'blurb'  => 'Highland weaving houses and tribal villages, in the green season only.',
		),
		array(
			'name'   => 'Women of the <em>Mekong</em>',
			'slug'   => 'women-of-the-mekong',
			'meta'   => 'Markets, looms, kitchens',
			'cap'    => '4 departures / year',
			'blurb'  => 'The river told through the women who work it: markets, looms, and kitchens.',
		),
	);
}

/**
 * The six featured destinations.
 *
 * @return array<int, array<string, string>>
 */
function wpistic_sample_destinations() {
	return array(
		array( 'name' => 'Luang Prabang', 'slug' => 'luang-prabang', 'tag' => 'North', 'desc' => 'Morning alms, river light, and the old royal capital.' ),
		array( 'name' => 'Nong Khiaw', 'slug' => 'nong-khiaw', 'tag' => 'North', 'desc' => 'Limestone walls over a slow bend of the Nam Ou.' ),
		array( 'name' => 'Vang Vieng', 'slug' => 'vang-vieng', 'tag' => 'Central', 'desc' => 'Karst country, quieter than its reputation.' ),
		array( 'name' => 'Plain of Jars', 'slug' => 'plain-of-jars', 'tag' => 'Xieng Khouang', 'desc' => 'Iron-age stone vessels across the plateau.' ),
		array( 'name' => 'Pakse', 'slug' => 'pakse-bolaven-plateau', 'tag' => 'South', 'desc' => 'The southern gateway to coffee country and the Bolaven.' ),
		array( 'name' => '4,000 Islands', 'slug' => '4000-islands', 'tag' => 'South', 'desc' => 'Where the Mekong braids and the day slows to the current.' ),
	);
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
 * No ratings, no counts, no AggregateRating. Real quotes should come only from
 * the Google/TripAdvisor embed or confirmed review profile links.
 *
 * @return array<int, array<string, string>>
 */
function wpistic_sample_reviews() {
	return array(
		array( 'body' => 'Every journey is reviewed by Ken before it reaches the guest.', 'attr' => 'Founder-set standard' ),
		array( 'body' => 'Deposits are requested only after the route, dates, and guest needs are confirmed.', 'attr' => 'Human-confirmed booking' ),
		array( 'body' => 'Private inquiries, agent requests, and custom trips all land in the same operations portal.', 'attr' => 'One team workflow' ),
	);
}

/**
 * Sample journal teasers.
 *
 * @return array<int, array<string, string>>
 */
function wpistic_sample_journal() {
	return array(
		array( 'title' => 'When to visit Laos, month by month', 'cat' => 'Travel Guides', 'meta' => '7 min read' ),
		array( 'title' => 'Almsgiving in Luang Prabang, done respectfully', 'cat' => 'Culture', 'meta' => '6 min read' ),
		array( 'title' => 'Getting to Nong Khiaw by boat and road', 'cat' => 'Destination Guides', 'meta' => '5 min read' ),
	);
}

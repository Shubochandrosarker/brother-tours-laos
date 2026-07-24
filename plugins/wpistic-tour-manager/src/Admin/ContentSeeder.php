<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Admin;

/**
 * Installs the Brother Tours launch catalog from approved legacy slugs.
 * The action is idempotent: existing posts are updated by slug instead of duplicated.
 */
final class ContentSeeder {

	private const ACTION = 'wpistic_tm_seed_catalog';
	private const NONCE  = 'wpistic_tm_seed_catalog';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'seed' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'wpistic-tour-manager',
			__( 'Brother Tours Catalog', 'wpistic-tour-manager' ),
			__( 'Content Seeder', 'wpistic-tour-manager' ),
			'manage_options',
			'wpistic-tm-content-seeder',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$seeded_at = (string) get_option( 'wpistic_tm_catalog_seeded_at', '' );
		$count     = isset( $_GET['seeded'] ) ? absint( $_GET['seeded'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Brother Tours Catalog', 'wpistic-tour-manager' ); ?></h1>
			<?php if ( $count ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Catalog installed or updated: %d records processed.', 'wpistic-tour-manager' ), $count ) ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Creates the launch destinations and 38 tour pages using the live Brother Tours URL slugs. Existing posts with the same slug are updated, not duplicated.', 'wpistic-tour-manager' ); ?></p>
			<?php if ( '' !== $seeded_at ) : ?>
				<p><strong><?php esc_html_e( 'Last seeded:', 'wpistic-tour-manager' ); ?></strong> <?php echo esc_html( $seeded_at ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<?php submit_button( __( 'Install / Update Brother Tours Catalog', 'wpistic-tour-manager' ), 'primary' ); ?>
			</form>
		</div>
		<?php
	}

	public function seed(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( self::NONCE ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpistic-tour-manager' ) );
		}

		$processed = 0;
		$dest_ids  = array();
		foreach ( $this->destinations() as $destination ) {
			$dest_ids[ $destination['key'] ] = $this->upsert_destination( $destination );
			++$processed;
		}

		foreach ( $this->tours() as $tour ) {
			$this->upsert_tour( $tour, $dest_ids );
			++$processed;
		}

		update_option( 'wpistic_tm_catalog_seeded_at', current_time( 'mysql' ) );
		update_option( 'wpistic_tm_flush', 1 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'wpistic-tm-content-seeder',
					'seeded' => $processed,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @param array<string, mixed> $destination
	 */
	private function upsert_destination( array $destination ): int {
		$post_id = $this->post_id_by_slug( (string) $destination['slug'], 'wpistic_destination' );
		$content = '<p>' . esc_html( (string) $destination['content'] ) . '</p>';
		$args    = array(
			'post_type'    => 'wpistic_destination',
			'post_status'  => 'publish',
			'post_title'   => (string) $destination['title'],
			'post_name'    => (string) $destination['slug'],
			'post_excerpt' => (string) $destination['one_line'],
			'post_content' => $content,
		);

		if ( $post_id ) {
			$args['ID'] = $post_id;
			wp_update_post( wp_slash( $args ) );
		} else {
			$post_id = wp_insert_post( wp_slash( $args ) );
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_post_meta( (int) $post_id, 'wpistic_one_line', (string) $destination['one_line'] );
		wp_set_object_terms( (int) $post_id, array( 'Laos' ), 'country', false );
		wp_set_object_terms( (int) $post_id, (array) $destination['regions'], 'region', false );

		return (int) $post_id;
	}

	/**
	 * @param array<string, mixed> $tour
	 * @param array<string, int>   $dest_ids
	 */
	private function upsert_tour( array $tour, array $dest_ids ): int {
		$post_id = $this->post_id_by_slug( (string) $tour['slug'], 'wpistic_tour' );
		$content = $this->tour_content( $tour );
		$args    = array(
			'post_type'    => 'wpistic_tour',
			'post_status'  => 'publish',
			'post_title'   => (string) $tour['title'],
			'post_name'    => (string) $tour['slug'],
			'post_excerpt' => (string) $tour['excerpt'],
			'post_content' => $content,
		);

		if ( $post_id ) {
			$args['ID'] = $post_id;
			wp_update_post( wp_slash( $args ) );
		} else {
			$post_id = wp_insert_post( wp_slash( $args ) );
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$meta = array(
			'wpistic_accent_word'      => (string) $tour['accent'],
			'wpistic_duration'         => (string) $tour['duration'],
			'wpistic_group_size'       => (string) $tour['group_size'],
			'wpistic_season'           => 'All year',
			'wpistic_departures_label' => (string) $tour['departures'],
			'wpistic_from_price'       => (string) $tour['price'],
			'wpistic_inclusions'       => $this->default_inclusions(),
			'wpistic_exclusions'       => $this->default_exclusions(),
			'wpistic_faq'              => $this->default_faq(),
			'wpistic_itinerary'        => $this->itinerary_rows( $tour ),
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( (int) $post_id, $key, $value );
		}

		wp_set_object_terms( (int) $post_id, array( 'Laos' ), 'country', false );
		wp_set_object_terms( (int) $post_id, (array) $tour['regions'], 'region', false );
		wp_set_object_terms( (int) $post_id, (array) $tour['styles'], 'travel_style', false );

		delete_post_meta( (int) $post_id, 'wpistic_related_destination' );
		foreach ( (array) $tour['destinations'] as $destination_key ) {
			if ( ! empty( $dest_ids[ $destination_key ] ) ) {
				add_post_meta( (int) $post_id, 'wpistic_related_destination', (int) $dest_ids[ $destination_key ], false );
			}
		}

		return (int) $post_id;
	}

	/**
	 * @param array<string, mixed> $tour
	 */
	private function tour_content( array $tour ): string {
		$paragraphs = array(
			(string) $tour['excerpt'],
			'This private Laos tour is configured for inquiry-led booking, flexible date planning, and human confirmation before final payment. Brother Tours can adjust hotels, pacing, transport, and guide arrangements after the request is reviewed.',
			'Use the booking form on this page to request availability, ask for a custom version, or reserve a preferred travel window.',
		);

		return '<p>' . implode( '</p><p>', array_map( 'esc_html', $paragraphs ) ) . '</p>';
	}

	/**
	 * @param array<string, mixed> $tour
	 * @return array<int, array<string, string>>
	 */
	private function itinerary_rows( array $tour ): array {
		return array(
			array(
				'title' => 'Trip overview',
				'body'  => (string) $tour['itinerary'],
			),
			array(
				'title' => 'Custom confirmation',
				'body'  => 'After your inquiry, Brother Tours confirms routing, guide availability, accommodation level, transport timing, and payment instructions for the selected travel date.',
			),
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function default_inclusions(): array {
		return array(
			'Local Brother Tours planning support',
			'Private guide arrangements where included in the confirmed proposal',
			'Ground transport arranged according to the final itinerary',
			'Activity coordination and local supplier confirmation',
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function default_exclusions(): array {
		return array(
			'International flights',
			'Visa fees and travel insurance',
			'Personal expenses and optional upgrades',
			'Meals or entrance fees not listed in the confirmed proposal',
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function default_faq(): array {
		return array(
			array(
				'q' => 'Can this tour run on private dates?',
				'a' => 'Yes. Most Brother Tours programs are arranged by request and confirmed after checking guide, transport, and accommodation availability.',
			),
			array(
				'q' => 'Can the itinerary be customized?',
				'a' => 'Yes. Send your travel dates, group size, hotel preference, and pace. The team will prepare a confirmed proposal before deposit payment.',
			),
		);
	}

	private function post_id_by_slug( string $slug, string $post_type ): int {
		$post = get_page_by_path( $slug, OBJECT, $post_type );
		return $post instanceof \WP_Post ? (int) $post->ID : 0;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function destinations(): array {
		return array(
			array( 'key' => 'vientiane', 'slug' => 'vientiane', 'title' => 'Vientiane', 'one_line' => 'Capital temples, riverside food, markets, and easy first-day Laos orientation.', 'regions' => array( 'Central Laos' ), 'content' => 'Vientiane is the capital base for temple visits, food walks, local markets, and overland connections toward Vang Vieng, Muang Fueng, and Konglor Cave.' ),
			array( 'key' => 'luang-prabang', 'slug' => 'luang-prabang', 'title' => 'Luang Prabang', 'one_line' => 'UNESCO heritage, Mekong cruises, waterfalls, village visits, and slow cultural days.', 'regions' => array( 'Northern Laos' ), 'content' => 'Luang Prabang anchors many Brother Tours cultural programs with heritage streets, Kuang Si waterfall, Pak Ou Cave, Mekong villages, and hands-on local experiences.' ),
			array( 'key' => 'vang-vieng', 'slug' => 'vang-vieng', 'title' => 'Vang Vieng', 'one_line' => 'Limestone landscapes, blue lagoons, caves, kayaking, cycling, and soft adventure.', 'regions' => array( 'Central Laos' ), 'content' => 'Vang Vieng works well for active travelers who want caves, river time, rural landscapes, cycling, viewpoints, and adventure days between Vientiane and Luang Prabang.' ),
			array( 'key' => 'nong-khiaw', 'slug' => 'nong-khiaw', 'title' => 'Nong Khiaw', 'one_line' => 'Nam Ou river scenery, viewpoints, boat journeys, and village-based travel.', 'regions' => array( 'Northern Laos' ), 'content' => 'Nong Khiaw is a quiet northern base for scenic river travel, viewpoints, local villages, and routes toward Muang Ngoi or deeper northern Laos programs.' ),
			array( 'key' => 'luang-namtha', 'slug' => 'luang-namtha', 'title' => 'Luang Namtha', 'one_line' => 'Northern trekking, protected forest, and ethnic community experiences.', 'regions' => array( 'Northern Laos' ), 'content' => 'Luang Namtha is used for forest trekking, village stays, and northern cultural programs that focus on community-led outdoor travel.' ),
			array( 'key' => 'pakse-bolaven', 'slug' => 'pakse-bolaven-plateau', 'title' => 'Pakse and Bolaven Plateau', 'one_line' => 'Southern coffee country, waterfalls, Wat Phou, and cooler plateau landscapes.', 'regions' => array( 'Southern Laos' ), 'content' => 'Pakse and the Bolaven Plateau connect southern Laos highlights including coffee farms, waterfalls, villages, Wat Phou, and onward routes to the Mekong islands.' ),
			array( 'key' => 'four-thousand-islands', 'slug' => 'four-thousand-islands', 'title' => '4,000 Islands', 'one_line' => 'Mekong island cycling, waterfalls, slow river days, and southern village life.', 'regions' => array( 'Southern Laos' ), 'content' => 'The 4,000 Islands region is used for relaxed Mekong travel, cycling, river views, village time, and southern extensions from Pakse.' ),
			array( 'key' => 'phongsaly', 'slug' => 'phongsaly', 'title' => 'Phongsaly', 'one_line' => 'Remote northern mountain travel, tea landscapes, and tribal culture.', 'regions' => array( 'Northern Laos' ), 'content' => 'Phongsaly supports remote northern adventures, tribal routes, upland scenery, and longer programs for travelers who want less-visited Laos.' ),
			array( 'key' => 'konglor', 'slug' => 'konglor-cave', 'title' => 'Konglor Cave', 'one_line' => 'A dramatic cave river journey and rural central Laos extension.', 'regions' => array( 'Central Laos' ), 'content' => 'Konglor Cave is a central Laos highlight for travelers adding a cave boat journey, village landscapes, and a more adventurous overland route.' ),
			array( 'key' => 'mekong', 'slug' => 'mekong-river', 'title' => 'Mekong River', 'one_line' => 'Boat journeys, river villages, Pak Ou Cave, and slow Laos travel.', 'regions' => array( 'Northern Laos', 'Southern Laos' ), 'content' => 'The Mekong River appears across Brother Tours programs from Luang Prabang cave cruises to southern island cycling and river-based cultural days.' ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function tours(): array {
		$defaults = array( 'departures' => 'Private dates on request', 'group_size' => 'Private', 'price' => '', 'regions' => array( 'Laos' ), 'styles' => array( 'Culture' ), 'destinations' => array( 'luang-prabang' ) );
		$tours = array(
			array( 'slug' => 'laos-highlight-weekend-escape', 'title' => 'Laos Highlight Weekend Escape', 'duration' => '6D/5N', 'price' => '595', 'group_size' => 'Max 10', 'regions' => array( 'Northern Laos', 'Central Laos' ), 'styles' => array( 'Culture', 'Weekend Escape' ), 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng' ), 'accent' => 'Highlight', 'excerpt' => 'A compact Laos route linking Vientiane, Luang Prabang, and Vang Vieng for travelers who want temples, river scenery, caves, waterfalls, and a balanced first Laos experience.', 'itinerary' => 'Arrive in Vientiane, continue to Luang Prabang for heritage and Mekong experiences, then travel through Vang Vieng landscapes before departure.' ),
			array( 'slug' => '6-day-laos-wonder-escape-vientiane-vangvieng', 'title' => '6 Day Laos Wonder Escape: Vientiane and Vang Vieng', 'duration' => '6 days', 'destinations' => array( 'vientiane', 'vang-vieng' ), 'regions' => array( 'Central Laos' ), 'styles' => array( 'Culture', 'Adventure' ), 'accent' => 'Wonder', 'excerpt' => 'A six-day central Laos trip built around Vientiane culture, Vang Vieng limestone scenery, caves, lagoons, and relaxed countryside travel.', 'itinerary' => 'Begin with Vientiane landmarks and local food, then continue to Vang Vieng for cave visits, blue lagoons, viewpoints, and riverside downtime.' ),
			array( 'slug' => '7-day-cultural-homestay-discovery-adventure', 'title' => '7 Day Cultural Homestay Discovery Adventure', 'duration' => '7 days', 'destinations' => array( 'luang-prabang', 'nong-khiaw' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Culture', 'Homestay' ), 'accent' => 'Homestay', 'excerpt' => 'A northern Laos journey focused on village hospitality, cultural exchange, scenic routes, and low-impact local travel.', 'itinerary' => 'Travel from Luang Prabang toward northern villages, with guided cultural activities, local meals, and time in a community homestay setting.' ),
			array( 'slug' => 'founder-hosted-laos-cultural-tours', 'title' => 'Founder Hosted Laos Cultural Tours', 'duration' => 'Custom', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng' ), 'regions' => array( 'Northern Laos', 'Central Laos' ), 'styles' => array( 'Culture', 'Founder Hosted' ), 'accent' => 'Founder', 'excerpt' => 'A custom cultural program hosted or personally shaped by Brother Tours leadership for travelers who want deeper context and direct local interpretation.', 'itinerary' => 'The final route is tailored after inquiry and may combine city culture, temple visits, village stops, food, markets, and local conversations.' ),
			array( 'slug' => '7-day-mekong-explorer-with-cultural', 'title' => '7 Day Mekong Explorer with Culture', 'duration' => '7 days', 'destinations' => array( 'luang-prabang', 'mekong' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Mekong', 'Culture' ), 'accent' => 'Mekong', 'excerpt' => 'A Mekong-focused cultural trip combining river time, Luang Prabang heritage, cave visits, villages, markets, and slower local travel.', 'itinerary' => 'Use Luang Prabang as the cultural base, then add Mekong boat experiences, village visits, Pak Ou Cave, and flexible heritage days.' ),
			array( 'slug' => '8-day-lao-forest-tribe-trek-and-cultural-highlights-tour', 'title' => '8 Day Lao Forest Tribe Trek and Cultural Highlights Tour', 'duration' => '8 days', 'destinations' => array( 'luang-namtha', 'luang-prabang' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Trekking', 'Culture' ), 'accent' => 'Forest', 'excerpt' => 'An eight-day northern Laos program for forest trekking, ethnic community encounters, and cultural highlights before or after Luang Prabang.', 'itinerary' => 'Combine Luang Prabang orientation with a northern trekking route around forest, village, and mountain landscapes.' ),
			array( 'slug' => '7-day-laos-tribal-experience-adventure-hike-luang-namtha', 'title' => '7 Day Laos Tribal Experience and Luang Namtha Hike', 'duration' => '7 days', 'destinations' => array( 'luang-namtha' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Trekking', 'Tribal Culture' ), 'accent' => 'Tribal', 'excerpt' => 'A Luang Namtha-based hiking and tribal culture itinerary for travelers seeking northern landscapes, village contact, and active days.', 'itinerary' => 'Focus on Luang Namtha trekking routes, protected forest areas, local village visits, and guided cultural interpretation.' ),
			array( 'slug' => '1-day-mekong-cruise-pak-cave-and-whisky-village', 'title' => '1 Day Mekong Cruise, Pak Ou Cave and Whisky Village', 'duration' => '1 day', 'destinations' => array( 'luang-prabang', 'mekong' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Mekong', 'Day Tour' ), 'accent' => 'Mekong', 'excerpt' => 'A Luang Prabang day tour by boat to Pak Ou Cave with Mekong scenery and a village stop connected to local craft and rice whisky traditions.', 'itinerary' => 'Depart from Luang Prabang by boat, visit Pak Ou Cave, stop at a riverside village, and return with time for sunset or evening plans.' ),
			array( 'slug' => '4-day-vientiane-highlights-and-konglor-cave-adventure', 'title' => '4 Day Vientiane Highlights and Konglor Cave Adventure', 'duration' => '4 days', 'destinations' => array( 'vientiane', 'konglor' ), 'regions' => array( 'Central Laos' ), 'styles' => array( 'Culture', 'Adventure' ), 'accent' => 'Konglor', 'excerpt' => 'A four-day central Laos trip pairing Vientiane culture with an adventurous overland extension to Konglor Cave.', 'itinerary' => 'Visit Vientiane landmarks, travel through rural central Laos, experience Konglor Cave by boat, then return or continue onward.' ),
			array( 'slug' => 'laos-weekend-escape-heritage-adventure', 'title' => 'Laos Weekend Escape: Heritage and Adventure', 'duration' => 'Weekend', 'destinations' => array( 'luang-prabang', 'vang-vieng' ), 'regions' => array( 'Northern Laos', 'Central Laos' ), 'styles' => array( 'Weekend Escape', 'Adventure' ), 'accent' => 'Weekend', 'excerpt' => 'A short Laos program for travelers combining heritage atmosphere with a light adventure component and efficient private logistics.', 'itinerary' => 'Build the weekend around Luang Prabang heritage, Vang Vieng scenery, or a custom mix depending on flight times and preferred pace.' ),
			array( 'slug' => '14-day-laos-private-expedition', 'title' => '14 Day Laos Private Expedition', 'duration' => '14 days', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng', 'nong-khiaw', 'pakse-bolaven', 'four-thousand-islands' ), 'regions' => array( 'Northern Laos', 'Central Laos', 'Southern Laos' ), 'styles' => array( 'Private Expedition', 'Culture' ), 'accent' => 'Expedition', 'excerpt' => 'A two-week private Laos journey linking the country north to south with heritage cities, river landscapes, caves, waterfalls, and village encounters.', 'itinerary' => 'Move from Vientiane and Vang Vieng to Luang Prabang and northern river areas, then continue south toward Pakse, Bolaven Plateau, and the Mekong islands.' ),
			array( 'slug' => 'gibbon-experience-laos-adventure-tour', 'title' => 'Gibbon Experience Laos Adventure Tour', 'duration' => 'Custom', 'destinations' => array( 'luang-namtha' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Adventure', 'Nature' ), 'accent' => 'Gibbon', 'excerpt' => 'An adventure extension built around northern Laos forest travel and access planning for travelers interested in canopy and conservation experiences.', 'itinerary' => 'Brother Tours confirms the route, transfer timing, conservation experience availability, and any overnight arrangements after inquiry.' ),
			array( 'slug' => 'laos-family-adventure-tour', 'title' => 'Laos Family Adventure Tour', 'duration' => 'Custom', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng' ), 'regions' => array( 'Northern Laos', 'Central Laos' ), 'styles' => array( 'Family', 'Adventure' ), 'accent' => 'Family', 'excerpt' => 'A family-friendly Laos trip balancing culture, nature, light activities, private transport, and flexible pacing for children or mixed-age groups.', 'itinerary' => 'Typical routes combine Luang Prabang, Vang Vieng, and Vientiane with adjusted activity levels, hotel style, and transfer timing.' ),
			array( 'slug' => 'essence-of-luang-prabang-in-2-days', 'title' => 'Essence of Luang Prabang in 2 Days', 'duration' => '2 days', 'destinations' => array( 'luang-prabang' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Culture', 'Short Break' ), 'accent' => 'Essence', 'excerpt' => 'A focused two-day Luang Prabang program covering heritage streets, temple culture, local markets, Mekong atmosphere, and nearby nature highlights.', 'itinerary' => 'Use two days for Luang Prabang heritage, food or market time, Mekong viewpoints, and an optional waterfall or cave extension.' ),
			array( 'slug' => '12-day-exclusive-laos-tour-southeast-asia', 'title' => '12 Day Exclusive Laos Tour in Southeast Asia', 'duration' => '12 days', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng', 'pakse-bolaven' ), 'regions' => array( 'Northern Laos', 'Central Laos', 'Southern Laos' ), 'styles' => array( 'Private Expedition', 'Culture' ), 'accent' => 'Exclusive', 'excerpt' => 'A private Laos route for travelers who want a polished multi-region trip with guided culture, scenic transport, and customizable hotel standards.', 'itinerary' => 'Connect central and northern Laos highlights with optional southern extensions based on preferred travel pace and comfort level.' ),
			array( 'slug' => '1-day-vangvieng-adventure-wonders', 'title' => '1 Day Vang Vieng Adventure Wonders', 'duration' => '1 day', 'destinations' => array( 'vang-vieng' ), 'regions' => array( 'Central Laos' ), 'styles' => array( 'Adventure', 'Day Tour' ), 'accent' => 'Adventure', 'excerpt' => 'A one-day Vang Vieng adventure combining limestone scenery, caves, blue lagoons, river activities, or viewpoints depending on season and pace.', 'itinerary' => 'Choose a practical day route around caves, lagoon time, countryside views, kayaking or soft adventure activities, then return to town.' ),
			array( 'slug' => '4-day-adventure-vientiane-muang-fueng-vang-vieng-highlights', 'title' => '4 Day Vientiane, Muang Fueng and Vang Vieng Adventure Highlights', 'duration' => '4 days', 'destinations' => array( 'vientiane', 'vang-vieng' ), 'regions' => array( 'Central Laos' ), 'styles' => array( 'Adventure', 'Culture' ), 'accent' => 'Adventure', 'excerpt' => 'A central Laos adventure route linking Vientiane, quieter Muang Fueng landscapes, and Vang Vieng highlights.', 'itinerary' => 'Start in Vientiane, continue through rural central Laos, include Muang Fueng scenery, and finish with Vang Vieng caves, lagoons, or viewpoints.' ),
			array( 'slug' => 'paksong-eco-trail-coffee-waterfalls-tribal-villages', 'title' => 'Paksong Eco Trail: Coffee, Waterfalls and Tribal Villages', 'duration' => 'Custom', 'destinations' => array( 'pakse-bolaven' ), 'regions' => array( 'Southern Laos' ), 'styles' => array( 'Eco', 'Culture' ), 'accent' => 'Paksong', 'excerpt' => 'A Bolaven Plateau eco route around Paksong with coffee landscapes, waterfalls, village visits, and cooler highland scenery.', 'itinerary' => 'Use Pakse or Paksong as the base, then visit coffee farms, waterfalls, plateau villages, and local viewpoints with private transport.' ),
			array( 'slug' => '5-day-4-night-southern-laos-adventure', 'title' => '5 Day 4 Night Southern Laos Adventure', 'duration' => '5 days / 4 nights', 'destinations' => array( 'pakse-bolaven', 'four-thousand-islands' ), 'regions' => array( 'Southern Laos' ), 'styles' => array( 'Adventure', 'Culture' ), 'accent' => 'Southern', 'excerpt' => 'A five-day southern Laos route covering Pakse, Bolaven Plateau waterfalls, coffee country, Wat Phou options, and Mekong island travel.', 'itinerary' => 'Combine Pakse arrival, plateau touring, waterfall stops, southern village time, and a Mekong island extension before departure.' ),
			array( 'slug' => '1-day-tour-highlight-of-vientiane', 'title' => '1 Day Tour Highlight of Vientiane', 'duration' => '1 day', 'destinations' => array( 'vientiane' ), 'regions' => array( 'Central Laos' ), 'styles' => array( 'Culture', 'Day Tour' ), 'accent' => 'Vientiane', 'excerpt' => 'A guided Vientiane day tour covering the capital landmarks, temples, local stories, riverside atmosphere, and practical city orientation.', 'itinerary' => 'Visit Vientiane highlights with a flexible route around temples, monuments, markets, riverfront time, and local food stops.' ),
			array( 'slug' => '1-day-vang-vieng-travel', 'title' => '1 Day Vang Vieng Travel', 'duration' => '1 day', 'destinations' => array( 'vang-vieng' ), 'regions' => array( 'Central Laos' ), 'styles' => array( 'Day Tour', 'Adventure' ), 'accent' => 'Vang Vieng', 'excerpt' => 'A one-day Vang Vieng sightseeing and soft adventure plan for caves, countryside, lagoons, river views, and scenic stops.', 'itinerary' => 'Plan the day around the best seasonal mix of cave visits, lagoon time, viewpoints, rural roads, and easy local experiences.' ),
			array( 'slug' => 'enchanting-laos-5-day-and-4-night', 'title' => 'Enchanting Laos 5 Day and 4 Night', 'duration' => '5 days / 4 nights', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng' ), 'regions' => array( 'Northern Laos', 'Central Laos' ), 'styles' => array( 'Culture', 'Short Break' ), 'accent' => 'Enchanting', 'excerpt' => 'A five-day Laos introduction combining heritage, scenery, temples, waterfalls, caves, and smooth private logistics.', 'itinerary' => 'Use a compact route through Vientiane, Vang Vieng, and Luang Prabang, adjusted to flight times and desired activity level.' ),
			array( 'slug' => '1-day-luang-prabang-local-discovery', 'title' => '1 Day Luang Prabang Local Discovery', 'duration' => '1 day', 'destinations' => array( 'luang-prabang' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Culture', 'Day Tour' ), 'accent' => 'Luang Prabang', 'excerpt' => 'A one-day Luang Prabang local discovery with heritage neighborhoods, temple culture, local markets, riverside scenery, and optional waterfall time.', 'itinerary' => 'Build the day around UNESCO heritage, village or market stops, river views, and a nature extension if time allows.' ),
			array( 'slug' => 'untouched-adventure-in-phongsaly-a-7d-6n', 'title' => 'Phongsaly Adventure 7D/6N', 'duration' => '7D/6N', 'destinations' => array( 'phongsaly' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Adventure', 'Tribal Culture' ), 'accent' => 'Phongsaly', 'excerpt' => 'A remote Phongsaly journey for mountain landscapes, tea country, tribal culture, and less-visited northern Laos routes.', 'itinerary' => 'Travel into Phongsaly with guided local planning, mountain viewpoints, community visits, and flexible pacing for remote road conditions.' ),
			array( 'slug' => '3-day-local-life-experience', 'title' => '3 Day Local Life Experience', 'duration' => '3 days', 'destinations' => array( 'luang-prabang', 'nong-khiaw' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Culture', 'Local Life' ), 'accent' => 'Local Life', 'excerpt' => 'A three-day local life program designed around village visits, food, craft, local transport, and slow cultural exchange.', 'itinerary' => 'Spend three days with guided local experiences, village or family-hosted activities, markets, meals, and short scenic transfers.' ),
			array( 'slug' => '1-day-mekong-magic-don-det-don-khon-cycling-adventure', 'title' => '1 Day Mekong Magic: Don Det and Don Khon Cycling Adventure', 'duration' => '1 day', 'destinations' => array( 'four-thousand-islands' ), 'regions' => array( 'Southern Laos' ), 'styles' => array( 'Mekong', 'Cycling' ), 'accent' => 'Mekong', 'excerpt' => 'A southern Laos cycling day around Don Det and Don Khon with Mekong scenery, island paths, village life, and waterfall options.', 'itinerary' => 'Cycle quiet island routes, stop for Mekong viewpoints and village scenes, and include waterfall or historic railway sights as timing allows.' ),
			array( 'slug' => '6-day-laos-local-life-and-lesser-known-places', 'title' => '6 Day Laos Local Life and Lesser-Known Places', 'duration' => '6 days', 'price' => '695', 'group_size' => 'Max 8', 'destinations' => array( 'luang-prabang', 'nong-khiaw' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Culture', 'Local Life' ), 'accent' => 'Local Life', 'excerpt' => 'A six-day northern Laos trip from Luang Prabang toward Nong Khiaw, built for travelers who want village contact, river scenery, and deeper local experiences.', 'itinerary' => 'Start from Luang Prabang or Vientiane airport, continue through Luang Prabang cultural days and Nong Khiaw landscapes, then add local food, markets, and community-led activities.' ),
			array( 'slug' => 'southern-laos-delights-in-3-days', 'title' => 'Southern Laos Delights in 3 Days', 'duration' => '3 days', 'destinations' => array( 'pakse-bolaven' ), 'regions' => array( 'Southern Laos' ), 'styles' => array( 'Culture', 'Short Break' ), 'accent' => 'Southern', 'excerpt' => 'A short southern Laos trip for Pakse, Bolaven Plateau waterfalls, coffee landscapes, local villages, and a practical taste of the south.', 'itinerary' => 'Base the trip around Pakse with day routes to the plateau, waterfalls, coffee stops, Wat Phou options, and local food.' ),
			array( 'slug' => '1-day-hiking-hmong-village-and-kuangsi-waterfall', 'title' => '1 Day Hiking, Hmong Village and Kuang Si Waterfall', 'duration' => '1 day', 'destinations' => array( 'luang-prabang' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Hiking', 'Day Tour' ), 'accent' => 'Kuang Si', 'excerpt' => 'A Luang Prabang day tour combining a guided hike, Hmong village context, and time at Kuang Si Waterfall.', 'itinerary' => 'Leave Luang Prabang for a village and hiking route, then continue to Kuang Si Waterfall before returning to town.' ),
			array( 'slug' => '1-day-ancient-wonders-of-pakse-wat-phou', 'title' => '1 Day Pakse and Wat Phou Ancient Wonders', 'duration' => '1 day', 'destinations' => array( 'pakse-bolaven' ), 'regions' => array( 'Southern Laos' ), 'styles' => array( 'Culture', 'Day Tour' ), 'accent' => 'Wat Phou', 'excerpt' => 'A southern Laos day tour from Pakse to Wat Phou and nearby cultural landscapes.', 'itinerary' => 'Visit Wat Phou with guide context, add local stops around Champasak or Pakse, and return by private transport.' ),
			array( 'slug' => 'tribal-tour-of-northern-laos-7d-6n', 'title' => 'Tribal Tour of Northern Laos 7D/6N', 'duration' => '7D/6N', 'destinations' => array( 'luang-namtha', 'phongsaly' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Tribal Culture', 'Adventure' ), 'accent' => 'Tribal', 'excerpt' => 'A seven-day northern Laos tribal culture route for community visits, mountain scenery, local markets, and remote road travel.', 'itinerary' => 'Plan a northern loop around tribal communities, mountain scenery, cultural stops, and flexible transfers based on seasonal road conditions.' ),
			array( 'slug' => 'lao-odyssey-three-weeks-of-learning-and-exploration', 'title' => 'Lao Odyssey: Three Weeks of Learning and Exploration', 'duration' => '3 weeks', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng', 'nong-khiaw', 'pakse-bolaven', 'four-thousand-islands' ), 'regions' => array( 'Northern Laos', 'Central Laos', 'Southern Laos' ), 'styles' => array( 'Learning', 'Culture' ), 'accent' => 'Odyssey', 'excerpt' => 'A three-week learning-focused Laos journey for culture, language exposure, local life, nature, and reflective slow travel.', 'itinerary' => 'Build a cross-country learning route with extended time in key regions, guided cultural sessions, language exposure, village visits, and nature days.' ),
			array( 'slug' => 'lao-two-weeks-of-adventure-and-cultural-exchange', 'title' => 'Two Weeks of Adventure and Cultural Exchange', 'duration' => '2 weeks', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng', 'nong-khiaw' ), 'regions' => array( 'Northern Laos', 'Central Laos' ), 'styles' => array( 'Adventure', 'Cultural Exchange' ), 'accent' => 'Two Weeks', 'excerpt' => 'A two-week Laos program combining adventure, cultural exchange, local learning, and flexible private routing.', 'itinerary' => 'Use two weeks for a balanced route through Vientiane, Vang Vieng, Luang Prabang, and northern scenery with community-focused activities.' ),
			array( 'slug' => 'lao-explorer-a-week-of-adventure-and-culture', 'title' => 'Lao Explorer: A Week of Adventure and Culture', 'duration' => '1 week', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng' ), 'regions' => array( 'Northern Laos', 'Central Laos' ), 'styles' => array( 'Adventure', 'Culture' ), 'accent' => 'Explorer', 'excerpt' => 'A one-week Laos route for first-time travelers who want culture, landscapes, soft adventure, and efficient private logistics.', 'itinerary' => 'Connect Vientiane, Vang Vieng, and Luang Prabang with guided highlights, caves or lagoons, waterfalls, markets, and temple culture.' ),
			array( 'slug' => 'lao-expedition-4-week-of-adventure-and-cultural-discovery', 'title' => 'Lao Expedition: 4 Weeks of Adventure and Cultural Discovery', 'duration' => '4 weeks', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng', 'nong-khiaw', 'luang-namtha', 'phongsaly', 'pakse-bolaven', 'four-thousand-islands' ), 'regions' => array( 'Northern Laos', 'Central Laos', 'Southern Laos' ), 'styles' => array( 'Private Expedition', 'Learning' ), 'accent' => 'Expedition', 'excerpt' => 'A four-week Laos expedition for slow travel, remote regions, cultural learning, trekking, river landscapes, and southern extensions.', 'itinerary' => 'Design a full-country route with enough time for north, central, and south Laos, including remote travel days, village-based activities, and rest stops.' ),
			array( 'slug' => 'cultural-learning-adventure-5d-4n', 'title' => 'Cultural Learning Adventure 5D/4N', 'duration' => '5D/4N', 'destinations' => array( 'luang-prabang' ), 'regions' => array( 'Northern Laos' ), 'styles' => array( 'Learning', 'Culture' ), 'accent' => 'Learning', 'excerpt' => 'A five-day cultural learning program built around Luang Prabang, local people, food, language exposure, craft, and heritage context.', 'itinerary' => 'Use Luang Prabang as a base for structured cultural learning, markets, temples, local activities, and a nearby village or waterfall extension.' ),
			array( 'slug' => 'unveiling-lao-charms-6d-5n', 'title' => 'Unveiling Lao Charms 6D/5N', 'duration' => '6D/5N', 'destinations' => array( 'vientiane', 'luang-prabang', 'vang-vieng' ), 'regions' => array( 'Northern Laos', 'Central Laos' ), 'styles' => array( 'Culture', 'Short Break' ), 'accent' => 'Lao Charms', 'excerpt' => 'A six-day Laos route combining major cultural highlights, scenic valleys, waterfalls, temples, and relaxed private touring.', 'itinerary' => 'Link Vientiane, Vang Vieng, and Luang Prabang with efficient transfers and a mix of heritage, river, cave, and waterfall experiences.' ),
			array( 'slug' => 'half-day-lao-language-experience', 'title' => 'Half Day Lao Language Experience', 'duration' => 'Half day', 'destinations' => array( 'luang-prabang', 'vientiane' ), 'regions' => array( 'Northern Laos', 'Central Laos' ), 'styles' => array( 'Learning', 'Culture' ), 'accent' => 'Language', 'excerpt' => 'A short Lao language and cultural orientation session for travelers who want practical phrases, etiquette, and local context before deeper travel.', 'itinerary' => 'Meet a local host or guide for practical Lao phrases, pronunciation, cultural etiquette, and useful travel scenarios.' ),
		);

		return array_map(
			static function ( array $tour ) use ( $defaults ): array {
				return array_merge( $defaults, $tour );
			},
			$tours
		);
	}
}

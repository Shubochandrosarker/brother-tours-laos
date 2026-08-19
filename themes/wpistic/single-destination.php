<?php
/**
 * Single destination (pillar page) — PDF "Destination Template".
 *
 * Navy masthead, "The Region" intro + region-at-a-glance card, best-places grid,
 * best tours, field notes + FAQ, CTA. Reads Tour Manager meta when present
 * (Phase 2); otherwise brand-safe sample structure with bundled demo imagery.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'single' ) ) {
	get_footer();
	return;
}

while ( have_posts() ) :
	the_post();

	$wpistic_id   = get_the_ID();
	$wpistic_slug = get_post_field( 'post_name', $wpistic_id );
	$wpistic_hero = has_post_thumbnail() ? get_the_post_thumbnail_url( $wpistic_id, 'bt-hero' ) : wpistic_demo_img( 'dest-' . $wpistic_slug );
	$wpistic_tag  = get_post_meta( $wpistic_id, 'wpistic_region_tag', true );

	$wpistic_glance = get_post_meta( $wpistic_id, 'wpistic_region_glance', true );
	if ( ! is_array( $wpistic_glance ) || empty( $wpistic_glance ) ) {
		$wpistic_glance = array(
			'Best season'        => 'Nov – Feb (cool dry)',
			'Recommended length' => '7 – 14 days',
			'Pace'               => 'Slow & cultural',
			'Best for'           => 'Couples, slow travel, photography',
			'Hub city'           => 'Luang Prabang (LPQ)',
		);
	}

	$wpistic_places = get_post_meta( $wpistic_id, 'wpistic_places', true );
	if ( ! is_array( $wpistic_places ) || empty( $wpistic_places ) ) {
		$wpistic_places = array(
			array( 'name' => 'Luang Prabang', 'desc' => 'UNESCO heritage city — morning alms, the old royal palace.', 'img' => 'dest-luang-prabang' ),
			array( 'name' => 'Nong Khiaw', 'desc' => 'Karst-valley village — viewpoints and kayak country.', 'img' => 'dest-nong-khiaw' ),
			array( 'name' => 'Vang Vieng', 'desc' => 'Limestone lagoons, quieter than its reputation.', 'img' => 'dest-vang-vieng' ),
			array( 'name' => 'Plain of Jars', 'desc' => 'Iron-age stone vessels across the Xieng Khouang plateau.', 'img' => 'dest-plain-of-jars' ),
			array( 'name' => 'Pakse & the Bolaven', 'desc' => 'Coffee plateau, waterfalls, and the road south.', 'img' => 'dest-pakse' ),
			array( 'name' => 'Vientiane', 'desc' => 'The riverside capital — temples and a slow evening market.', 'img' => 'dest-vientiane' ),
			array( 'name' => '4,000 Islands', 'desc' => 'Where the Mekong braids — Don Det, Don Khone, the falls.', 'img' => 'dest-4000-islands' ),
			array( 'name' => 'The Nam Ou', 'desc' => 'A slow boat threading the river between karst walls.', 'img' => 'dest-nong-khiaw' ),
		);
	}
	?>
	<section class="page-hero">
		<?php if ( $wpistic_hero ) : ?>
			<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
		<?php endif; ?>
		<div class="wrap">
			<?php wpistic_breadcrumbs(); ?>
			<span class="eyebrow"><?php echo esc_html( $wpistic_tag ? $wpistic_tag : __( 'Destination', 'wpistic' ) ); ?></span>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="lede"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section">
		<div class="wrap">
			<div class="region-layout">
				<div>
					<div class="section-head"><span class="eyebrow">The Region</span><h2 class="sec-h2">Where Laos becomes <em>slow</em>.</h2></div>
					<div class="prose"><?php the_content(); ?></div>
				</div>
				<aside class="glance-card" aria-label="<?php esc_attr_e( 'Region at a glance', 'wpistic' ); ?>">
					<h3><?php esc_html_e( 'Region at a glance', 'wpistic' ); ?></h3>
					<?php foreach ( $wpistic_glance as $wpistic_k => $wpistic_v ) : ?>
						<div class="glance-row"><span class="k"><?php echo esc_html( $wpistic_k ); ?></span><span class="v"><?php echo esc_html( $wpistic_v ); ?></span></div>
					<?php endforeach; ?>
				</aside>
			</div>
		</div>
	</section>

	<section class="section section-sand">
		<div class="wrap">
			<div class="section-head center">
				<span class="eyebrow center">Best places to visit</span>
				<h2 class="sec-h2">Places that stay with <em>you</em>.</h2>
			</div>
			<div class="places-grid">
				<?php foreach ( $wpistic_places as $wpistic_place ) : ?>
					<div class="place-card reveal">
						<div class="place-media">
							<?php
							$wpistic_pimg = isset( $wpistic_place['img'] ) ? wpistic_demo_img( $wpistic_place['img'] ) : '';
							if ( $wpistic_pimg ) {
								printf( '<img src="%s" alt="%s" loading="lazy">', esc_url( $wpistic_pimg ), esc_attr( $wpistic_place['name'] ?? '' ) );
							} else {
								echo '<span class="place-media-fallback" aria-hidden="true"></span>';
							}
							?>
						</div>
						<p class="place-name"><?php echo esc_html( $wpistic_place['name'] ?? '' ); ?></p>
						<p class="place-desc"><?php echo esc_html( $wpistic_place['desc'] ?? '' ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php
	// Best tours in this region — real relations in Phase 2; sample fallback now.
	$wpistic_region_tours = get_posts( array( 'post_type' => 'wpistic_tour', 'numberposts' => 3 ) );
	?>
	<section class="section">
		<div class="wrap">
			<div class="section-head"><span class="eyebrow">Journeys here</span><h2 class="sec-h2">Private journeys through this <em>region</em>.</h2></div>
			<div class="tour-grid">
				<?php
				if ( $wpistic_region_tours ) :
					foreach ( $wpistic_region_tours as $wpistic_rt ) :
						wpistic_tour_card(
							array(
								'name'     => get_the_title( $wpistic_rt ),
								'url'      => get_permalink( $wpistic_rt ),
								'region'   => $wpistic_tag ? $wpistic_tag : 'Laos',
								'meta'     => get_post_meta( $wpistic_rt->ID, 'wpistic_duration', true ),
								'blurb'    => get_the_excerpt( $wpistic_rt ),
								'tags'     => array_filter( array( get_post_meta( $wpistic_rt->ID, 'wpistic_departures_label', true ) ) ),
								'price'    => wpistic_price_line( get_post_meta( $wpistic_rt->ID, 'wpistic_from_price', true ) ),
								'image_id' => get_post_thumbnail_id( $wpistic_rt ),
							)
						);
					endforeach;
				else :
					$wpistic_fb = array( 'tour-1', 'tour-2', 'tour-3' );
					foreach ( array_slice( wpistic_sample_tours(), 0, 3 ) as $wpistic_i => $wpistic_tour ) :
						wpistic_tour_card(
							array(
								'name'      => $wpistic_tour['name'],
								'url'       => home_url( '/tours/' . $wpistic_tour['slug'] . '/' ),
								'region'    => $wpistic_tag ? $wpistic_tag : 'Laos',
								'meta'      => $wpistic_tour['meta'],
								'blurb'     => $wpistic_tour['blurb'],
								'tags'      => array( $wpistic_tour['cap'] ),
								'price'     => wpistic_price_line( '' ),
								'image_url' => wpistic_demo_img( $wpistic_fb[ $wpistic_i ] ),
							)
						);
					endforeach;
				endif;
				?>
			</div>
		</div>
	</section>

	<section class="section section-sand">
		<div class="wrap">
			<div class="split-grid lean">
				<div>
					<div class="section-head"><span class="eyebrow">Field notes</span><h2 class="sec-h2">Travel tips from our <em>guides</em>.</h2></div>
					<?php
					$wpistic_notes = array(
						array( 'Pack a light layer.', 'November mornings can drop to 12 °C in the highlands; days warm to 24 °C.' ),
						array( 'Book the slow boat ahead.', 'Private river boats fill weeks ahead in the cool-dry months.' ),
						array( 'Respect the alms ceremony.', 'Stand quiet and at a distance. We brief you privately the morning of.' ),
						array( 'Carry small kip notes.', 'Village markets rarely break large notes.' ),
					);
					/**
					 * Filter the per-destination field notes.
					 * @param array $wpistic_notes Array of [ title, body ] pairs.
					 * @param int   $post_id       Destination post ID.
					 */
					$wpistic_notes = apply_filters( 'wpistic_destination_field_notes', $wpistic_notes, get_the_ID() );
					foreach ( $wpistic_notes as $wpistic_ni => $wpistic_note ) :
						?>
						<div class="note-row">
							<span class="hl-num"><?php echo esc_html( sprintf( '%02d', $wpistic_ni + 1 ) ); ?></span>
							<span class="hl-text"><strong><?php echo esc_html( $wpistic_note[0] ); ?></strong><?php echo esc_html( $wpistic_note[1] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<div>
					<div class="section-head"><span class="eyebrow">Good to know</span><h2 class="sec-h2">Travelers frequently <em>ask</em>.</h2></div>
					<?php
					/**
					 * Filter the destination FAQ block.
					 * Return an array of [ 'q' => ..., 'a' => ... ] to replace the defaults,
					 * or an empty array to hide the section.
					 */
					$wpistic_faq = apply_filters( 'wpistic_destination_faq', array(), get_the_ID() );
					if ( ! empty( $wpistic_faq ) && is_array( $wpistic_faq ) ) :
						echo '<div class="faq">';
						$wpistic_first = true;
						foreach ( $wpistic_faq as $wpistic_item ) {
							printf(
								'<details class="faq-item"%1$s><summary class="faq-q">%2$s</summary><div class="faq-a">%3$s</div></details>',
								$wpistic_first ? ' open' : '',
								esc_html( $wpistic_item['q'] ),
								esc_html( $wpistic_item['a'] )
							);
							$wpistic_first = false;
						}
						echo '</div>';
					else :
						?>
						<div class="faq">
						<details class="faq-item" open><summary class="faq-q">Is this region safe for travelers?</summary><div class="faq-a">Yes. Laos is a calm, welcoming country. Your host is with you throughout.</div></details>
						<details class="faq-item"><summary class="faq-q">How many days do I need here?</summary><div class="faq-a">Seven to fourteen days lets the region unfold at its own pace.</div></details>
						<details class="faq-item"><summary class="faq-q">What is the best month to visit?</summary><div class="faq-a">November to February is cool and dry; the green season brings fewer travelers and lower light.</div></details>
						<details class="faq-item"><summary class="faq-q">Can I combine it with another region?</summary><div class="faq-a">Yes — we design north-to-south journeys and cross-border routes on request.</div></details>
					</div>
						<?php
					endif;
					?>
				</div>
			</div>
		</div>
	</section>

	<?php wpistic_build_my_trip_cta( get_the_title() ); ?>
	<?php
endwhile;

get_footer();

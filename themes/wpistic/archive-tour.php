<?php
/**
 * Tour archive (/tours) — PDF "Filterable Grid".
 *
 * Navy masthead + filter toolbar + Refine sidebar + Journey-of-the-Month +
 * editorial tour cards. Filter controls are presentational here; they wire to
 * the `country` / `region` / `travel_style` taxonomies when the Tour Manager
 * plugin is active (Phase 2). Copy is brand-safe (locked H1, no fabricated stats).
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'archive' ) ) {
	get_footer();
	return;
}

$wpistic_count = ( is_post_type_archive() && isset( $GLOBALS['wp_query'] ) ) ? (int) $GLOBALS['wp_query']->found_posts : 0;
?>

<section class="page-hero">
	<div class="wrap">
		<?php wpistic_breadcrumbs(); ?>
		<span class="eyebrow">Signature Journeys</span>
		<h1>Each runs a fixed number of times each <em>year</em>.</h1>
		<p class="lede">By design. Small numbers, hosted personally, held to one standard.</p>
	</div>
</section>

<!-- Filter toolbar (wires to taxonomies when Tour Manager is active) -->
<form class="tours-toolbar" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'wpistic_tour' ) ? get_post_type_archive_link( 'wpistic_tour' ) : home_url( '/tours/' ) ); ?>">
	<div class="wrap">
		<?php
		$wpistic_filters = array(
			'category'    => array( 'Tour category', array_merge( array( 'Any category' ), wp_list_pluck( get_terms( array( 'taxonomy' => 'tour_category', 'hide_empty' => true ) ), 'name' ) ) ),
			'destination' => array( 'Destination', array_merge( array( 'Any destination' ), wp_list_pluck( get_terms( array( 'taxonomy' => 'tour_destination', 'hide_empty' => true ) ), 'name' ) ) ),
			'difficulty'  => array( 'Difficulty', array_merge( array( 'Any difficulty' ), wp_list_pluck( get_terms( array( 'taxonomy' => 'tour_difficulty', 'hide_empty' => true ) ), 'name' ) ) ),
			'duration' => array( 'Duration', array( 'Any length', '1–3 days', '4–6 days', '7–10 days', '11+ days' ) ),
			'region'   => array( 'Region', array( 'All Laos', 'Northern Laos', 'Central Laos', 'Southern Laos', 'Cross-country' ) ),
			'style'    => array( 'Travel style', array( 'Any', 'Slow & cultural', 'Active / trekking', 'Founder-led', 'Family' ) ),
			'season'   => array( 'Best season', array( 'Any month', 'Nov–Feb · cool dry', 'Mar–Apr · warm dry', 'May–Oct · green' ) ),
		);
		foreach ( $wpistic_filters as $wpistic_key => $wpistic_field ) :
			?>
			<div class="filter-field">
				<label for="filter-<?php echo esc_attr( $wpistic_key ); ?>"><?php echo esc_html( $wpistic_field[0] ); ?></label>
				<select id="filter-<?php echo esc_attr( $wpistic_key ); ?>" name="<?php echo esc_attr( $wpistic_key ); ?>">
					<?php foreach ( $wpistic_field[1] as $wpistic_i => $wpistic_opt ) : ?>
						<option value="<?php echo esc_attr( 0 === $wpistic_i ? '' : sanitize_title( $wpistic_opt ) ); ?>"<?php selected( isset( $_GET[ $wpistic_key ] ) ? sanitize_title( wp_unslash( $_GET[ $wpistic_key ] ) ) : '', sanitize_title( $wpistic_opt ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>><?php echo esc_html( $wpistic_opt ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endforeach; ?>
		<button class="btn btn-navy" type="submit"><?php esc_html_e( 'Apply', 'wpistic' ); ?></button>
	</div>
</form>

<section class="section">
	<div class="wrap">
		<div class="tools-row">
			<div class="active-filters" aria-label="<?php esc_attr_e( 'Active filters', 'wpistic' ); ?>">
				<span class="af"><?php esc_html_e( 'All Laos', 'wpistic' ); ?> <button type="button" aria-label="<?php esc_attr_e( 'Remove filter', 'wpistic' ); ?>">×</button></span>
				<a class="clear" href="<?php echo esc_url( home_url( '/tours/' ) ); ?>"><?php esc_html_e( 'Clear all', 'wpistic' ); ?></a>
			</div>
			<div class="tools-meta">
				<?php if ( $wpistic_count ) : ?>
					<span><strong><?php echo esc_html( number_format_i18n( $wpistic_count ) ); ?></strong> <?php esc_html_e( 'journeys', 'wpistic' ); ?></span>
				<?php endif; ?>
				<span><?php esc_html_e( 'Sort: Recommended', 'wpistic' ); ?></span>
			</div>
		</div>

		<div class="archive-layout">
			<aside class="refine" aria-label="<?php esc_attr_e( 'Refine journeys', 'wpistic' ); ?>">
				<div class="refine-group">
					<p class="refine-title"><?php esc_html_e( 'Duration', 'wpistic' ); ?></p>
					<?php foreach ( array( '1–3 days', '4–6 days', '7–10 days', '11+ days' ) as $wpistic_d ) : ?>
						<label class="refine-opt"><input type="checkbox"> <?php echo esc_html( $wpistic_d ); ?></label>
					<?php endforeach; ?>
				</div>
				<div class="refine-group">
					<p class="refine-title"><?php esc_html_e( 'Region', 'wpistic' ); ?></p>
					<?php foreach ( array( 'Northern Laos', 'Central Laos', 'Southern Laos', 'Cross-country' ) as $wpistic_r ) : ?>
						<label class="refine-opt"><input type="checkbox"> <?php echo esc_html( $wpistic_r ); ?></label>
					<?php endforeach; ?>
				</div>
				<div class="refine-group">
					<p class="refine-title"><?php esc_html_e( 'Best for', 'wpistic' ); ?></p>
					<?php foreach ( array( 'Couples', 'Families', 'Slow travel', 'Founder-led' ) as $wpistic_b ) : ?>
						<label class="refine-opt"><input type="checkbox"> <?php echo esc_html( $wpistic_b ); ?></label>
					<?php endforeach; ?>
				</div>
				<div class="refine-group">
					<p class="refine-title"><?php esc_html_e( 'Best season', 'wpistic' ); ?></p>
					<?php foreach ( array( 'Nov–Feb · cool dry', 'Mar–Apr · warm dry', 'May–Oct · green' ) as $wpistic_s ) : ?>
						<label class="refine-opt"><input type="checkbox"> <?php echo esc_html( $wpistic_s ); ?></label>
					<?php endforeach; ?>
				</div>
				<div class="match-card">
					<h3><?php esc_html_e( "Can't decide?", 'wpistic' ); ?></h3>
					<p><?php esc_html_e( 'Tell us the shape of your trip and we will design it around you.', 'wpistic' ); ?></p>
					<a class="btn btn-solid" href="<?php echo esc_url( wpistic_cta_url() ); ?>"><?php echo esc_html( wpistic_cta_label() ); ?></a>
				</div>
			</aside>

			<div class="archive-main">
				<?php
				// Journey of the month — brand-safe (per-tour scarcity, no fabricated counts).
				$wpistic_feature = null;
				if ( have_posts() ) {
					$wpistic_first = get_posts( array( 'post_type' => 'wpistic_tour', 'numberposts' => 1 ) );
					if ( $wpistic_first ) {
						$wpistic_feature = $wpistic_first[0];
					}
				}
				$wpistic_feature_url   = $wpistic_feature ? get_permalink( $wpistic_feature ) : home_url( '/tours/spine-of-the-country/' );
				$wpistic_feature_title = $wpistic_feature ? get_the_title( $wpistic_feature ) : 'Spine of the Country';
				?>
				<div class="jom reveal">
					<div>
						<p class="jom-eyebrow"><?php esc_html_e( 'Journey of the month', 'wpistic' ); ?></p>
						<h2 class="jom-title"><?php echo esc_html( $wpistic_feature_title ); ?></h2>
						<p class="jom-meta"><?php esc_html_e( 'Founder-led · a fixed number of departures each year, by design.', 'wpistic' ); ?></p>
					</div>
					<a class="btn btn-solid" href="<?php echo esc_url( $wpistic_feature_url ); ?>"><?php esc_html_e( 'View journey', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></a>
				</div>

				<div class="tour-grid cols-2">
					<?php
					if ( have_posts() ) :
						while ( have_posts() ) :
							the_post();
							wpistic_tour_card(
								array(
									'name'     => get_the_title(),
									'url'      => get_permalink(),
									'region'   => 'Laos',
									'meta'     => get_post_meta( get_the_ID(), 'wpistic_duration', true ),
									'blurb'    => get_the_excerpt(),
									'tags'     => array_filter( array( get_post_meta( get_the_ID(), 'wpistic_departures_label', true ) ) ),
									'price'    => wpistic_price_line( get_post_meta( get_the_ID(), 'wpistic_from_price', true ) ),
									'image_id' => get_post_thumbnail_id(),
								)
							);
						endwhile;
					elseif ( wpistic_tour_filters_active() ) :
						/*
						 * A filter matched nothing. Falling through to the sample
						 * journeys here would show every tour and read as though
						 * the filter had been ignored.
						 */
						?>
						<p class="tours-empty">
							<?php esc_html_e( 'No journeys match that combination.', 'wpistic' ); ?>
							<a href="<?php echo esc_url( get_post_type_archive_link( 'wpistic_tour' ) ); ?>"><?php esc_html_e( 'Clear filters', 'wpistic' ); ?></a>
						</p>
						<?php
					else :
						$wpistic_fb = array( 'tour-1', 'tour-2', 'tour-3', 'tour-4', 'tour-5' );
						foreach ( wpistic_sample_tours() as $wpistic_i => $wpistic_tour ) :
							wpistic_tour_card(
								array(
									'name'      => $wpistic_tour['name'],
									'url'       => home_url( '/tours/' . $wpistic_tour['slug'] . '/' ),
									'region'    => 'Laos',
									'meta'      => $wpistic_tour['meta'],
									'blurb'     => $wpistic_tour['blurb'],
									'tags'      => array( $wpistic_tour['cap'] ),
									'price'     => wpistic_price_line( '' ),
									'image_url' => wpistic_demo_img( $wpistic_fb[ $wpistic_i % count( $wpistic_fb ) ] ),
								)
							);
						endforeach;
					endif;
					?>
				</div>

				<?php
				if ( function_exists( 'the_posts_pagination' ) && have_posts() ) {
					the_posts_pagination(
						array(
							'mid_size'  => 1,
							'prev_text' => __( '← Prev', 'wpistic' ),
							'next_text' => __( 'Next →', 'wpistic' ),
						)
					);
				}
				?>
			</div>
		</div>
	</div>
</section>

<?php wpistic_build_my_trip_cta(); ?>

<?php
get_footer();

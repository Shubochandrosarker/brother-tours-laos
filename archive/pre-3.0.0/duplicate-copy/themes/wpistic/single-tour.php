<?php
/**
 * Single tour — PDF "Single Tour Detail".
 *
 * Navy masthead, 6-up glance band, overview, highlights, day-by-day accordion,
 * what's included/not, route strip, gallery, FAQ, and an enriched sticky booking
 * aside (host card + dual CTAs). Reads Tour Manager meta when present, otherwise
 * shows brand-safe sample structure. No fabricated ratings or counts.
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

	$wpistic_id    = get_the_ID();
	$wpistic_cap   = get_post_meta( $wpistic_id, 'wpistic_departures_label', true );
	$wpistic_from  = get_post_meta( $wpistic_id, 'wpistic_from_price', true );
	$wpistic_dur   = get_post_meta( $wpistic_id, 'wpistic_duration', true );
	$wpistic_start  = (string) get_post_meta( $wpistic_id, 'wpistic_start_location', true );
	$wpistic_end    = (string) get_post_meta( $wpistic_id, 'wpistic_end_location', true );
	$wpistic_styles = wp_get_post_terms( $wpistic_id, 'travel_style', array( 'fields' => 'names' ) );
	$wpistic_style  = is_wp_error( $wpistic_styles ) ? '' : implode( ', ', $wpistic_styles );
	$wpistic_glance = array(
		array( 'label' => 'Duration', 'value' => $wpistic_dur ),
		array( 'label' => 'Start', 'value' => $wpistic_start ),
		array( 'label' => 'End', 'value' => $wpistic_end ),
		array( 'label' => 'Style', 'value' => $wpistic_style ),
		array( 'label' => 'Group', 'value' => get_post_meta( $wpistic_id, 'wpistic_group_size', true ) ),
		array( 'label' => 'Best season', 'value' => get_post_meta( $wpistic_id, 'wpistic_season', true ) ),
	);
	$wpistic_glance = array_values( array_filter( $wpistic_glance, static fn( array $item ): bool => '' !== trim( (string) $item['value'] ) ) );
	?>
	<?php $wpistic_tour_hero = has_post_thumbnail() ? get_the_post_thumbnail_url( $wpistic_id, 'bt-hero' ) : ''; ?>
	<section class="page-hero">
		<?php if ( $wpistic_tour_hero ) : ?>
			<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_tour_hero ); ?>" alt="" fetchpriority="high"></div>
		<?php endif; ?>
		<div class="wrap">
			<?php wpistic_breadcrumbs(); ?>
			<?php if ( ! is_wp_error( $wpistic_styles ) && $wpistic_styles ) : ?>
				<div class="hero-tags"><?php foreach ( $wpistic_styles as $wpistic_style_name ) : ?><span class="tag"><?php echo esc_html( $wpistic_style_name ); ?></span><?php endforeach; ?></div>
			<?php endif; ?>
			<h1><?php the_title(); ?></h1>
			<div class="tour-meta-row">
				<?php if ( $wpistic_dur ) : ?><span><?php echo esc_html( $wpistic_dur ); ?></span><span class="dot" aria-hidden="true"></span><?php endif; ?>
				<?php if ( '' !== $wpistic_start && '' !== $wpistic_end ) : ?><span><?php echo esc_html( $wpistic_start . ' → ' . $wpistic_end ); ?></span><span class="dot" aria-hidden="true"></span><?php endif; ?>
				<span class="price"><?php echo esc_html( wpistic_price_line( $wpistic_from ) ); ?></span>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="wrap">
			<div class="tour-layout">
				<div class="tour-main">
					<?php if ( $wpistic_glance ) : ?><div class="glance six">
						<?php foreach ( $wpistic_glance as $wpistic_g ) : ?>
							<div class="glance-item">
								<p class="glance-label"><?php echo esc_html( $wpistic_g['label'] ); ?></p>
								<p class="glance-value"><?php echo esc_html( $wpistic_g['value'] ); ?></p>
							</div>
						<?php endforeach; ?>
					</div><?php endif; ?>

					<?php if ( get_the_content() ) : ?>
						<h2 class="block-title"><em>Overview</em>.</h2>
						<div class="prose u-mb-l"><?php the_content(); ?></div>
					<?php endif; ?>

					<?php
					$wpistic_highlights = get_post_meta( $wpistic_id, 'wpistic_highlights', true );
					$wpistic_highlights = is_array( $wpistic_highlights ) ? array_filter( $wpistic_highlights ) : array();
					?>
					<?php if ( $wpistic_highlights ) : ?>
					<h2 class="block-title">Moments you'll <em>remember</em>.</h2>
					<div class="highlights">
						<?php foreach ( $wpistic_highlights as $wpistic_hi => $wpistic_h ) : ?>
							<div class="hl-item">
								<span class="hl-num"><?php echo esc_html( sprintf( '%02d', $wpistic_hi + 1 ) ); ?></span>
								<span class="hl-text"><?php echo esc_html( is_array( $wpistic_h ) ? ( $wpistic_h['text'] ?? '' ) : $wpistic_h ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>

					<?php $wpistic_days = get_post_meta( $wpistic_id, 'wpistic_itinerary', true ); ?>
					<?php if ( is_array( $wpistic_days ) && $wpistic_days ) : ?>
					<h2 class="block-title">Day by <em>day</em>.</h2>
					<div class="itinerary">
						<?php
						foreach ( $wpistic_days as $wpistic_n => $wpistic_day ) :
							?>
							<details class="itin-day"<?php echo 0 === $wpistic_n ? ' open' : ''; ?>>
								<summary>
									<span class="itin-num">Day <?php echo esc_html( $wpistic_n + 1 ); ?></span>
									<span class="itin-title"><?php echo esc_html( $wpistic_day['title'] ?? '' ); ?></span>
								</summary>
								<p class="itin-body"><?php echo esc_html( $wpistic_day['body'] ?? '' ); ?></p>
							</details>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>

					<?php
					$wpistic_inclusions = get_post_meta( $wpistic_id, 'wpistic_inclusions', true );
					$wpistic_exclusions = get_post_meta( $wpistic_id, 'wpistic_exclusions', true );
					$wpistic_inclusions = is_array( $wpistic_inclusions ) ? array_filter( $wpistic_inclusions ) : array();
					$wpistic_exclusions = is_array( $wpistic_exclusions ) ? array_filter( $wpistic_exclusions ) : array();
					?>
					<?php if ( $wpistic_inclusions || $wpistic_exclusions ) : ?>
					<h2 class="block-title">What's <em>included</em>.</h2>
					<div class="incl-grid">
						<?php if ( $wpistic_inclusions ) : ?><div class="incl-list"><?php foreach ( $wpistic_inclusions as $wpistic_item ) : ?><div class="row"><span class="incl-mark">✓</span><?php echo esc_html( $wpistic_item ); ?></div><?php endforeach; ?></div><?php endif; ?>
						<?php if ( $wpistic_exclusions ) : ?><div class="incl-list"><?php foreach ( $wpistic_exclusions as $wpistic_item ) : ?><div class="row"><span class="incl-mark no">–</span><?php echo esc_html( $wpistic_item ); ?></div><?php endforeach; ?></div><?php endif; ?>
					</div>
					<?php endif; ?>

					<?php
					$wpistic_route_list = array_values( array_filter( array( $wpistic_start, $wpistic_end ) ) );
					?>
					<?php if ( count( $wpistic_route_list ) > 1 ) : ?>
					<h2 class="block-title">The <em>route</em>.</h2>
					<div class="route-strip">
						<?php
						$wpistic_last = count( $wpistic_route_list ) - 1;
						foreach ( $wpistic_route_list as $wpistic_ri => $wpistic_place ) :
							echo '<span>' . esc_html( $wpistic_place ) . '</span>';
							if ( $wpistic_ri < $wpistic_last ) {
								echo '<span class="arr" aria-hidden="true">→</span>';
							}
						endforeach;
						?>
					</div>
					<?php endif; ?>

					<?php $wpistic_gallery = get_post_meta( $wpistic_id, 'wpistic_gallery', true ); ?>
					<?php if ( is_array( $wpistic_gallery ) && $wpistic_gallery ) : ?>
					<h2 class="block-title"><em>Gallery</em>.</h2>
					<div class="gallery-grid">
						<?php foreach ( array_slice( $wpistic_gallery, 0, 8 ) as $wpistic_gid ) : ?>
							<div class="cell"><?php echo wp_get_attachment_image( absint( $wpistic_gid ), 'bt-gallery', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>

					<?php $wpistic_faq = get_post_meta( $wpistic_id, 'wpistic_faq', true ); ?>
					<?php if ( is_array( $wpistic_faq ) && $wpistic_faq ) : ?>
					<h2 class="block-title">Common <em>questions</em>.</h2>
					<div class="faq">
						<?php foreach ( $wpistic_faq as $wpistic_index => $wpistic_item ) : ?><details class="faq-item"<?php echo 0 === $wpistic_index ? ' open' : ''; ?>><summary class="faq-q"><?php echo esc_html( $wpistic_item['q'] ?? '' ); ?></summary><div class="faq-a"><?php echo esc_html( $wpistic_item['a'] ?? '' ); ?></div></details><?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>

				<aside class="booking-aside">
					<p class="price"><?php echo esc_html( wpistic_price_line( $wpistic_from ) ); ?></p>
					<?php if ( $wpistic_cap ) : ?><p class="cap"><?php echo esc_html( $wpistic_cap ); ?></p><?php endif; ?>

					<?php
					if ( shortcode_exists( 'wpistic_booking_widget' ) ) {
						echo do_shortcode( '[wpistic_booking_widget id="' . esc_attr( $wpistic_id ) . '"]' );
					} else {
						?>
						<div class="ba-actions">
							<a class="btn btn-navy" href="<?php echo esc_url( add_query_arg( 'tour', rawurlencode( get_the_title() ), wpistic_cta_url() ) ); ?>"><?php esc_html_e( 'Request Itinerary', 'wpistic' ); ?></a>
							<a class="btn btn-green" href="<?php echo esc_url( wpistic_whatsapp_url( 'Hello — I would like to ask about the ' . get_the_title() . ' journey.' ) ); ?>" rel="noopener"><?php esc_html_e( 'Ask on WhatsApp', 'wpistic' ); ?></a>
						</div>
						<?php
					}
					?>

					<?php $wpistic_host_name = trim( (string) wpistic_opt( 'hero_host_name', '' ) ); ?>
					<?php if ( $wpistic_host_name ) : ?><div class="ba-host">
						<span class="ba-host-avatar">
							<?php
							$wpistic_host_id = absint( wpistic_opt( 'hero_host_image', 0 ) );
							if ( $wpistic_host_id ) {
								echo wp_get_attachment_image( $wpistic_host_id, 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) );
							}
							?>
						</span>
						<span>
							<span class="ba-host-name"><?php echo esc_html( $wpistic_host_name ); ?></span><br>
							<span class="ba-host-role"><?php esc_html_e( 'Brother Tours team', 'wpistic' ); ?></span>
						</span>
					</div><?php endif; ?>

					<p class="secondary">
						<?php esc_html_e( 'Not quite right?', 'wpistic' ); ?><br>
						<a class="btn-link" href="<?php echo esc_url( add_query_arg( 'tour', rawurlencode( get_the_title() ), wpistic_cta_url() ) ); ?>"><?php esc_html_e( 'Design your own journey', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></a>
					</p>
				</aside>
			</div>
		</div>
	</section>

	<?php
	$wpistic_related = get_posts(
		array(
			'post_type'    => 'wpistic_tour',
			'numberposts'  => 3,
			'post__not_in' => array( $wpistic_id ),
			'orderby'      => 'rand',
		)
	);
	if ( $wpistic_related ) :
		?>
		<section class="section section-sand">
			<div class="wrap">
				<div class="section-head"><span class="eyebrow">More journeys</span><h2 class="sec-h2">Related <em>journeys</em>.</h2></div>
				<div class="tour-grid">
					<?php
					foreach ( $wpistic_related as $wpistic_rt ) :
						wpistic_tour_card(
							array(
								'name'     => get_the_title( $wpistic_rt ),
								'url'      => get_permalink( $wpistic_rt ),
								'region'   => 'Laos',
								'meta'     => get_post_meta( $wpistic_rt->ID, 'wpistic_duration', true ),
								'blurb'    => get_the_excerpt( $wpistic_rt ),
								'tags'     => array_filter( array( get_post_meta( $wpistic_rt->ID, 'wpistic_departures_label', true ) ) ),
								'price'    => wpistic_price_line( get_post_meta( $wpistic_rt->ID, 'wpistic_from_price', true ) ),
								'image_id' => get_post_thumbnail_id( $wpistic_rt ),
							)
						);
					endforeach;
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php wpistic_build_my_trip_cta( get_the_title() ); ?>
	<?php
endwhile;

get_footer();

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

while ( have_posts() ) :
	the_post();

	$wpistic_id    = get_the_ID();
	$wpistic_cap   = get_post_meta( $wpistic_id, 'wpistic_departures_label', true );
	$wpistic_from  = get_post_meta( $wpistic_id, 'wpistic_from_price', true );
	$wpistic_dur   = get_post_meta( $wpistic_id, 'wpistic_duration', true );
	$wpistic_route = get_post_meta( $wpistic_id, 'wpistic_route', true );
	$wpistic_glance = array(
		array( 'label' => 'Duration', 'value' => $wpistic_dur ? $wpistic_dur : '—' ),
		array( 'label' => 'Start', 'value' => get_post_meta( $wpistic_id, 'wpistic_start', true ) ?: 'Vientiane' ),
		array( 'label' => 'End', 'value' => get_post_meta( $wpistic_id, 'wpistic_end', true ) ?: 'Luang Prabang' ),
		array( 'label' => 'Style', 'value' => get_post_meta( $wpistic_id, 'wpistic_style', true ) ?: 'Private, hosted' ),
		array( 'label' => 'Group', 'value' => get_post_meta( $wpistic_id, 'wpistic_group_size', true ) ?: 'Private' ),
		array( 'label' => 'Best season', 'value' => get_post_meta( $wpistic_id, 'wpistic_season', true ) ?: 'Nov – Feb' ),
	);
	?>
	<?php $wpistic_tour_hero = has_post_thumbnail() ? get_the_post_thumbnail_url( $wpistic_id, 'bt-hero' ) : wpistic_demo_img( 'hero-southern' ); ?>
	<section class="page-hero">
		<?php if ( $wpistic_tour_hero ) : ?>
			<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_tour_hero ); ?>" alt="" fetchpriority="high"></div>
		<?php endif; ?>
		<div class="wrap">
			<?php wpistic_breadcrumbs(); ?>
			<div class="hero-tags">
				<span class="tag">Private</span>
				<span class="tag">Founder-led</span>
			</div>
			<h1><?php the_title(); ?></h1>
			<div class="tour-meta-row">
				<?php if ( $wpistic_dur ) : ?><span><?php echo esc_html( $wpistic_dur ); ?></span><span class="dot" aria-hidden="true"></span><?php endif; ?>
				<span><?php echo esc_html( $wpistic_glance[1]['value'] . ' → ' . $wpistic_glance[2]['value'] ); ?></span>
				<span class="dot" aria-hidden="true"></span>
				<span class="price"><?php echo esc_html( wpistic_price_line( $wpistic_from ) ); ?></span>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="wrap">
			<div class="tour-layout">
				<div class="tour-main">
					<div class="glance six">
						<?php foreach ( $wpistic_glance as $wpistic_g ) : ?>
							<div class="glance-item">
								<p class="glance-label"><?php echo esc_html( $wpistic_g['label'] ); ?></p>
								<p class="glance-value"><?php echo esc_html( $wpistic_g['value'] ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( get_the_content() ) : ?>
						<h2 class="block-title"><em>Overview</em>.</h2>
						<div class="prose u-mb-l"><?php the_content(); ?></div>
					<?php endif; ?>

					<?php
					$wpistic_highlights = get_post_meta( $wpistic_id, 'wpistic_highlights', true );
					if ( ! is_array( $wpistic_highlights ) || empty( $wpistic_highlights ) ) {
						$wpistic_highlights = array(
							'A private welcome from your host on the first evening.',
							'Two slow days on the river, by boat where the road would rush you.',
							'A named village homestay, with a weaving lesson at the loom.',
							'A cooking class in a family kitchen, not a hotel.',
							'Morning light over the karst country, before the day begins.',
							'A farewell meal your host plans around you.',
						);
					}
					?>
					<h2 class="block-title">Moments you'll <em>remember</em>.</h2>
					<div class="highlights">
						<?php foreach ( $wpistic_highlights as $wpistic_hi => $wpistic_h ) : ?>
							<div class="hl-item">
								<span class="hl-num"><?php echo esc_html( sprintf( '%02d', $wpistic_hi + 1 ) ); ?></span>
								<span class="hl-text"><?php echo esc_html( is_array( $wpistic_h ) ? ( $wpistic_h['text'] ?? '' ) : $wpistic_h ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>

					<h2 class="block-title">Day by <em>day</em>.</h2>
					<div class="itinerary">
						<?php
						$wpistic_days = get_post_meta( $wpistic_id, 'wpistic_itinerary', true );
						if ( ! is_array( $wpistic_days ) || empty( $wpistic_days ) ) {
							$wpistic_days = array(
								array( 'title' => 'Arrival in Vientiane', 'body' => 'Met by your host. An easy first evening by the river.' ),
								array( 'title' => 'North by road and water', 'body' => 'The country opens up — villages reached by boat, not by bus.' ),
								array( 'title' => 'Into the highlands', 'body' => 'Weaving houses, named hosts, a meal in a family kitchen.' ),
							);
						}
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

					<h2 class="block-title">What's <em>included</em>.</h2>
					<div class="incl-grid">
						<div class="incl-list">
							<div class="row"><span class="incl-mark">✓</span>Licensed Lao host throughout</div>
							<div class="row"><span class="incl-mark">✓</span>Named hotels and river lodges</div>
							<div class="row"><span class="incl-mark">✓</span>Private transfers and boats</div>
							<div class="row"><span class="incl-mark">✓</span>Meals named in your itinerary</div>
						</div>
						<div class="incl-list">
							<div class="row"><span class="incl-mark no">–</span>International flights</div>
							<div class="row"><span class="incl-mark no">–</span>Travel insurance</div>
							<div class="row"><span class="incl-mark no">–</span>Personal spending</div>
						</div>
					</div>

					<?php
					$wpistic_route_list = is_array( $wpistic_route ) && $wpistic_route ? $wpistic_route : array( 'Vientiane', 'Vang Vieng', 'Luang Prabang', 'Nong Khiaw' );
					?>
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

					<h2 class="block-title"><em>Gallery</em>.</h2>
					<div class="gallery-grid">
						<?php
						$wpistic_gallery = get_post_meta( $wpistic_id, 'wpistic_gallery', true );
						if ( is_array( $wpistic_gallery ) && $wpistic_gallery ) {
							foreach ( array_slice( $wpistic_gallery, 0, 8 ) as $wpistic_gid ) {
								echo '<div class="cell">' . wp_get_attachment_image( absint( $wpistic_gid ), 'bt-gallery', false, array( 'loading' => 'lazy' ) ) . '</div>';
							}
						} else {
							for ( $wpistic_i = 0; $wpistic_i < 8; $wpistic_i++ ) {
								echo '<div class="cell"></div>';
							}
						}
						?>
					</div>

					<h2 class="block-title">Common <em>questions</em>.</h2>
					<div class="faq">
						<details class="faq-item" open><summary class="faq-q">Is this a private journey?</summary><div class="faq-a">Yes. Every Brother Tours journey is private and hosted personally.</div></details>
						<details class="faq-item"><summary class="faq-q">How is the price set?</summary><div class="faq-a">Price confirmed on request — it follows the journey we design with you, never a budget box.</div></details>
						<details class="faq-item"><summary class="faq-q">How many times does this run?</summary><div class="faq-a">Each journey runs a fixed number of times each year. By design.</div></details>
					</div>
				</div>

				<aside class="booking-aside">
					<p class="price"><?php echo esc_html( wpistic_price_line( $wpistic_from ) ); ?></p>
					<p class="cap"><?php echo esc_html( $wpistic_cap ? $wpistic_cap : __( 'Each journey runs a fixed number of times each year. By design.', 'wpistic' ) ); ?></p>

					<?php
					if ( shortcode_exists( 'wpistic_booking_widget' ) ) {
						echo do_shortcode( '[wpistic_booking_widget id="' . esc_attr( $wpistic_id ) . '"]' );
					} else {
						?>
						<div class="ba-actions">
							<a class="btn btn-navy" href="<?php echo esc_url( add_query_arg( 'tour', rawurlencode( get_the_title() ), home_url( '/plan-my-laos-trip/' ) ) ); ?>"><?php esc_html_e( 'Request Itinerary', 'wpistic' ); ?></a>
							<a class="btn btn-green" href="<?php echo esc_url( wpistic_whatsapp_url( 'Hello — I would like to ask about the ' . get_the_title() . ' journey.' ) ); ?>" rel="noopener"><?php esc_html_e( 'Ask on WhatsApp', 'wpistic' ); ?></a>
						</div>
						<div class="ba-check">
							<div class="row"><?php esc_html_e( 'Free cancellation up to 30 days', 'wpistic' ); ?></div>
							<div class="row"><?php esc_html_e( 'No payment until your itinerary is approved', 'wpistic' ); ?></div>
							<div class="row"><?php esc_html_e( 'Private — never shared with strangers', 'wpistic' ); ?></div>
							<div class="row"><?php esc_html_e( 'Founder-led, Ken-reviewed', 'wpistic' ); ?></div>
						</div>
						<?php
					}
					?>

					<div class="ba-host">
						<span class="ba-host-avatar">
							<?php
							$wpistic_host_id = absint( wpistic_opt( 'hero_host_image', 0 ) );
							if ( $wpistic_host_id ) {
								echo wp_get_attachment_image( $wpistic_host_id, 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) );
							}
							?>
						</span>
						<span>
							<span class="ba-host-name"><?php echo esc_html( wpistic_opt( 'hero_host_name', 'Ken FJ Her' ) ); ?></span><br>
							<span class="ba-host-role"><?php esc_html_e( 'Your host · usually replies within a day', 'wpistic' ); ?></span>
						</span>
					</div>

					<p class="secondary">
						<?php esc_html_e( 'Not quite right?', 'wpistic' ); ?><br>
						<a class="btn-link" href="<?php echo esc_url( add_query_arg( 'tour', rawurlencode( get_the_title() ), home_url( '/plan-my-laos-trip/' ) ) ); ?>"><?php esc_html_e( 'Design your own journey', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></a>
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

<?php
/**
 * Homepage variant V3 — "Magazine Spread" (PDF board 03).
 *
 * Loaded by front-page.php when Theme Options → home_variant = v3. Body only.
 * Brand-safe content + locked phrases; no fabricated review counts/stars.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpistic_cover = wpistic_demo_img( 'hero-southern' );
$wpistic_tours = wpistic_sample_tours();
$wpistic_img   = array( 'tour-1', 'tour-2', 'tour-3', 'tour-4', 'tour-5' );
?>

<!-- V3 · MAGAZINE MASTHEAD -->
<section class="mag-hero">
	<div class="wrap">
		<div class="mag-topline">
			<span>A private Laos tour company · Founded 2018</span>
			<span>Lao-led. Globally understood.</span>
		</div>
		<div class="mag-grid">
			<div>
				<h1 class="mag-h1">Experience Laos Through the People Who Call It <em>Home</em>.</h1>
				<p class="mag-lede">Private journeys crafted by Lao guides who know the stories behind every temple, river, village, and mountain.</p>
				<div class="hero-btns">
					<a class="btn btn-solid" href="<?php echo esc_url( home_url( '/plan-my-laos-trip/' ) ); ?>">Plan My Laos Trip <span class="arr" aria-hidden="true">→</span></a>
					<a class="btn btn-ghost on-dark" href="<?php echo esc_url( home_url( '/tours/' ) ); ?>">See Our Journeys</a>
				</div>
				<div class="mag-stats">
					<div class="mag-stat"><span class="n">2010</span><span class="l">Licensed Lao guide</span></div>
					<div class="mag-stat"><span class="n">2018</span><span class="l">Founded</span></div>
					<div class="mag-stat"><span class="n">Private</span><span class="l">Every journey hosted</span></div>
					<div class="mag-stat"><span class="n">Vientiane</span><span class="l">Lao-owned</span></div>
				</div>
			</div>
			<div class="mag-cover">
				<?php if ( $wpistic_cover ) { printf( '<img src="%s" alt="" fetchpriority="high">', esc_url( $wpistic_cover ) ); } ?>
			</div>
		</div>
	</div>
</section>

<!-- V3 · FEATURE: THE COLLECTION -->
<section class="section section-sand reveal">
	<div class="wrap">
		<div class="sec-head-row">
			<div class="section-head">
				<span class="eyebrow">Feature · The Collection</span>
				<h2 class="sec-h2">Hand-crafted journeys, privately <em>hosted</em>.</h2>
			</div>
			<a class="btn-link" href="<?php echo esc_url( home_url( '/tours/' ) ); ?>"><?php esc_html_e( 'View all journeys', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></a>
		</div>
		<div class="mag-feature">
			<div class="mag-feature-main">
				<?php
				if ( isset( $wpistic_tours[0] ) ) {
					wpistic_tour_card(
						array(
							'name'      => $wpistic_tours[0]['name'],
							'url'       => home_url( '/tours/' . $wpistic_tours[0]['slug'] . '/' ),
							'region'    => 'Editor’s pick · Laos',
							'meta'      => $wpistic_tours[0]['meta'],
							'blurb'     => $wpistic_tours[0]['blurb'],
							'tags'      => array( $wpistic_tours[0]['cap'] ),
							'price'     => wpistic_price_line( '' ),
							'image_url' => wpistic_demo_img( 'tour-1' ),
						)
					);
				}
				?>
			</div>
			<div class="mag-feature-side">
				<?php
				foreach ( array_slice( $wpistic_tours, 1, 2 ) as $wpistic_i => $wpistic_tour ) :
					wpistic_tour_card(
						array(
							'name'      => $wpistic_tour['name'],
							'url'       => home_url( '/tours/' . $wpistic_tour['slug'] . '/' ),
							'region'    => 'Laos',
							'meta'      => $wpistic_tour['meta'],
							'blurb'     => $wpistic_tour['blurb'],
							'tags'      => array( $wpistic_tour['cap'] ),
							'price'     => wpistic_price_line( '' ),
							'image_url' => wpistic_demo_img( $wpistic_img[ ( $wpistic_i + 1 ) % count( $wpistic_img ) ] ),
						)
					);
				endforeach;
				?>
			</div>
		</div>
	</div>
</section>

<!-- V3 · WHY (seven reasons) -->
<section class="section reveal">
	<div class="wrap">
		<div class="section-head">
			<span class="eyebrow">Why Brother Tours</span>
			<h2 class="sec-h2">Reasons our guests come <em>back</em>.</h2>
		</div>
		<div class="mag-reasons">
			<?php
			$wpistic_reasons = array(
				array( 'Local, never outsourced', 'Lao-owned, Lao-staffed, Lao-licensed.' ),
				array( 'Founder-set, team-delivered', 'Ken sets the standard; the team carries it.' ),
				array( 'Private & flexible', "Your journey moves at your pace, not a coach's." ),
				array( 'Responsible tourism', 'Community partnerships and fair guide wages.' ),
				array( 'Multilingual hosts', 'English first, with more languages on request.' ),
				array( 'Fast to reach', 'A reply from Vientiane within a day.' ),
				array( 'One point of contact', 'Visa, driver, hotel, ticketing — handled.' ),
			);
			foreach ( $wpistic_reasons as $wpistic_ri => $wpistic_reason ) :
				?>
				<div class="manifesto-item">
					<span class="manifesto-num"><?php echo esc_html( sprintf( '%02d', $wpistic_ri + 1 ) ); ?></span>
					<div>
						<h3><?php echo esc_html( $wpistic_reason[0] ); ?></h3>
						<p><?php echo esc_html( $wpistic_reason[1] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- V3 · WHERE WILL YOU GO -->
<section class="section section-sand reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">Where will you go?</span>
			<h2 class="sec-h2">Six regions, one <em>country</em>.</h2>
		</div>
		<div class="dest-grid">
			<?php foreach ( wpistic_sample_destinations() as $wpistic_dest ) : ?>
				<a class="dest-card reveal" href="<?php echo esc_url( home_url( '/destinations/' . $wpistic_dest['slug'] . '/' ) ); ?>">
					<?php $wpistic_di = wpistic_demo_img( 'dest-' . $wpistic_dest['slug'] ); if ( $wpistic_di ) { printf( '<img src="%s" alt="" loading="lazy">', esc_url( $wpistic_di ) ); } ?>
					<span class="dest-tag"><?php echo esc_html( $wpistic_dest['tag'] ); ?></span>
					<span class="dest-name"><?php echo esc_html( $wpistic_dest['name'] ); ?></span>
					<span class="dest-desc"><?php echo esc_html( $wpistic_dest['desc'] ); ?></span>
					<span class="dest-link"><?php esc_html_e( 'See the region', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- V3 · REVIEWS -->
<section class="section reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">What travelers say</span>
			<h2 class="sec-h2">Read what guests have <em>written</em>.</h2>
			<p class="sec-lead">Consistently top-rated on Google and TripAdvisor.</p>
		</div>
		<div class="reviews-grid">
			<?php foreach ( wpistic_sample_reviews() as $wpistic_review ) : ?>
				<div class="review-card reveal">
					<p class="review-quote"><?php echo esc_html( $wpistic_review['body'] ); ?></p>
					<p class="review-attr"><?php echo esc_html( $wpistic_review['attr'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php wpistic_build_my_trip_cta(); ?>

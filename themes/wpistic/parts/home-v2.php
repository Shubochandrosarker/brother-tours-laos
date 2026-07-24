<?php
/**
 * Homepage variant V2 — "Asymmetric Story-Led" (PDF board 02).
 *
 * Loaded by front-page.php when Theme Options → home_variant = v2. Body only
 * (header/footer come from front-page.php). Brand-safe content + locked phrases.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpistic_main = wpistic_demo_img( 'hero-laos' );
$wpistic_ken  = wpistic_demo_img( 'ken-portrait' );
?>

<!-- V2 · ASYMMETRIC HERO -->
<section class="hero-v2">
	<div class="wrap">
		<div class="hero-v2-content">
			<span class="eyebrow">Vientiane · Since 2018</span>
			<h1 class="hero-v2-h1">Experience Laos Through the People Who Call It <em>Home</em>.</h1>
			<p class="hero-v2-lede">Lao-led. Globally understood.</p>
			<p class="hero-v2-sub">Private, hosted journeys designed around you — built and led from the ground in Laos.</p>
			<div class="hero-btns">
				<a class="btn btn-solid" href="<?php echo esc_url( home_url( '/plan-my-laos-trip/' ) ); ?>">Plan My Laos Trip <span class="arr" aria-hidden="true">→</span></a>
				<a class="btn btn-ghost" href="<?php echo esc_url( home_url( '/tours/' ) ); ?>">See Our Journeys</a>
			</div>
			<div class="hero-v2-stats">
				<div class="hero-v2-stat"><span class="n">2010</span><span class="l">Licensed Lao guide</span></div>
				<div class="hero-v2-stat"><span class="n">2018</span><span class="l">Brother Tours founded</span></div>
				<div class="hero-v2-stat"><span class="n">Private</span><span class="l">Every journey hosted</span></div>
			</div>
		</div>
		<div class="hero-v2-media">
			<div class="hero-v2-main">
				<?php if ( $wpistic_main ) { printf( '<img src="%s" alt="" fetchpriority="high">', esc_url( $wpistic_main ) ); } ?>
				<span class="hero-v2-coords">21°55′N · 102°08′E</span>
			</div>
			<aside class="hero-host" aria-label="<?php esc_attr_e( 'Your host', 'wpistic' ); ?>">
				<div class="hero-host-img">
					<?php if ( $wpistic_ken ) { printf( '<img src="%s" alt="%s" loading="lazy">', esc_url( $wpistic_ken ), esc_attr( wpistic_opt( 'hero_host_name', 'Ken FJ Her' ) ) ); } ?>
					<span class="hero-host-tag"><?php esc_html_e( 'Your host', 'wpistic' ); ?></span>
				</div>
				<div class="hero-host-body">
					<p class="hero-host-name"><?php echo esc_html( wpistic_opt( 'hero_host_name', 'Ken FJ Her' ) ); ?></p>
					<p class="hero-host-role"><?php echo esc_html( wpistic_opt( 'hero_host_role', 'Founder · Lead Host' ) ); ?></p>
				</div>
			</aside>
		</div>
	</div>
</section>

<!-- V2 · FROM THE FOUNDER (letter) -->
<section class="letter section reveal">
	<div class="wrap">
		<div class="letter-portrait">
			<?php if ( $wpistic_ken ) { printf( '<img src="%s" alt="Ken FJ Her, Founder of Brother Tours" loading="lazy">', esc_url( $wpistic_ken ) ); } ?>
		</div>
		<div>
			<span class="eyebrow">From the founder</span>
			<p class="letter-quote">Not a guide. A <em>host</em>.</p>
			<p class="letter-body">Ken was born and raised in Laos. He earned his National Tour Guide license in 2010 and founded Brother Tours in 2018, built on what those years taught him: that a country is its people first, and that a guest is owed more than a route.</p>
			<p class="letter-body">We do not sell journeys. We share the country we were born in.</p>
			<p class="letter-sign">Born Here. Guide Here.</p>
		</div>
	</div>
</section>

<!-- V2 · THE COLLECTION -->
<section class="section reveal">
	<div class="wrap">
		<div class="sec-head-row">
			<div class="section-head">
				<span class="eyebrow">The Collection</span>
				<h2 class="sec-h2">Each runs a fixed number of times each <em>year</em>.</h2>
				<p class="sec-lead">By design. Small numbers, hosted personally, held to one standard.</p>
			</div>
			<a class="btn-link" href="<?php echo esc_url( home_url( '/tours/' ) ); ?>"><?php esc_html_e( 'View all journeys', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></a>
		</div>
		<div class="tour-grid">
			<?php
			$wpistic_img = array( 'tour-1', 'tour-2', 'tour-3', 'tour-4', 'tour-5' );
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
						'image_url' => wpistic_demo_img( $wpistic_img[ $wpistic_i % count( $wpistic_img ) ] ),
					)
				);
			endforeach;
			?>
		</div>
	</div>
</section>

<!-- V2 · THREE LAOSES -->
<section class="section section-sand reveal">
	<div class="wrap">
		<div class="section-head">
			<span class="eyebrow">Where We Go</span>
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

<!-- V2 · REVIEWS -->
<section class="section reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">Guest Reviews</span>
			<h2 class="sec-h2">What our guests have <em>said</em>.</h2>
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

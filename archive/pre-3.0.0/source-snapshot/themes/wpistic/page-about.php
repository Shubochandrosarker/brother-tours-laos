<?php
/**
 * Template Name: About
 *
 * PDF "Founder Story". Brand-safe: founder bio through work only — no ethnicity,
 * monastic background, or personal heritage; no fabricated awards/press.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$wpistic_hero = wpistic_demo_img( 'hero-laos' );
$wpistic_ken  = wpistic_demo_img( 'ken-portrait' );
?>

<!-- 1 · HERO -->
<section class="page-hero">
	<?php if ( $wpistic_hero ) : ?>
		<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
	<?php endif; ?>
	<div class="wrap">
		<?php wpistic_breadcrumbs(); ?>
		<span class="eyebrow">About Brother Tours</span>
		<h1>Laos is not a destination. It is the people who take you <em>there</em>.</h1>
		<p class="lede">Lao-led. Globally understood.</p>
	</div>
</section>

<!-- 2 · COMPANY INTRO -->
<section class="section reveal">
	<div class="wrap">
		<div class="prose u-narrow">
			<h2>A simple order of <em>things</em>.</h2>
			<p>Brother Tours was founded in 2018, on a simple order of things. The people come first. The experience comes second. The destination comes third.</p>
			<p>Most tour companies reverse that. They sell a place, fit an experience around it, and the people are whoever happens to be standing there. We built Brother Tours the other way around — because the people are the reason a place is worth visiting at all.</p>
			<p>We are a Lao company. Owned here, run here, guided here.</p>
		</div>
	</div>
</section>

<!-- 3 · FOUNDER -->
<section class="section section-sand reveal">
	<div class="wrap">
		<div class="founder-grid">
			<div class="founder-img-wrap">
				<span class="founder-corner-tl" aria-hidden="true"></span>
				<span class="founder-corner-br" aria-hidden="true"></span>
				<div class="founder-portrait">
					<?php if ( $wpistic_ken ) : ?>
						<img src="<?php echo esc_url( $wpistic_ken ); ?>" alt="Ken FJ Her, Founder of Brother Tours" loading="lazy">
					<?php else : ?>
						<div class="founder-portrait-fill"><span class="founder-initials">BT</span></div>
					<?php endif; ?>
					<div class="founder-cap">
						<p class="founder-cap-name">Ken FJ Her</p>
						<p class="founder-cap-role">Founder · Licensed Lao National Tour Guide</p>
					</div>
				</div>
			</div>
			<div class="founder-copy">
				<span class="eyebrow">The Founder</span>
				<p class="founder-pull">Founder-set. Team-<em>delivered</em>.</p>
				<p class="founder-body">Ken was born and raised in Laos. He came to this work the long way — first as a guide, walking the country road by road and season by season.</p>
				<p class="founder-body">In 2010 he earned his National Tour Guide license. By 2018 he had seen enough of how Laos was usually sold — quickly, thinly, as a list of sights — to want to do it differently. He founded Brother Tours that year.</p>
				<p class="founder-cred">Today Brother Tours is founder-set and team-delivered. Ken sets the standard. A team of licensed Lao hosts, trained in his way of working, carries it.</p>
				<p class="founder-signature">Born Here. Guide Here.</p>
			</div>
		</div>
	</div>
</section>

<!-- 4 · THE HOST -->
<section class="section reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">Our People</span>
			<h2 class="sec-h2">Not a guide. A <em>host</em>.</h2>
		</div>
		<div class="prose is-center u-narrow">
			<p>A guide reads the itinerary. A host reads the day. The person who travels with you helps where help is wanted, shares the stories that make a place make sense, and finds the openings that an itinerary cannot plan for.</p>
			<p>Lao, licensed, and trusted personally by Ken.</p>
		</div>
	</div>
</section>

<!-- 5 · STANDARDS -->
<section class="section section-sand reveal">
	<div class="wrap">
		<div class="section-head">
			<span class="eyebrow">The Standard</span>
			<h2 class="sec-h2">Named hosts. Named hotels. Named <em>meals</em>.</h2>
		</div>
		<div class="standards">
			<div class="standard"><h3>Named hosts</h3><p>Every journey is carried by a licensed Lao host, trained in Ken's way of working.</p></div>
			<div class="standard"><h3>Named hotels</h3><p>We name the places you sleep — heritage houses, river lodges — before you confirm.</p></div>
			<div class="standard"><h3>Named meals</h3><p>The kitchens, and the families behind them, written into your itinerary.</p></div>
		</div>
	</div>
</section>

<!-- 6 · MANIFESTO -->
<section class="section reveal">
	<div class="wrap">
		<div class="split-grid lean">
			<div class="section-head">
				<span class="eyebrow">Manifesto</span>
				<h2 class="sec-h2">What we <em>believe</em>.</h2>
				<p class="sec-lead">Five sentences pinned above the office door. They are not policy. They are how we host.</p>
			</div>
			<div>
				<?php
				$wpistic_manifesto = array(
					array( 'Welcome travelers as guests.', 'Not customers. The difference shows in every cup of coffee we pour.' ),
					array( 'Travel slow.', 'Seven nights minimum. Boats over buses where the road would rush you.' ),
					array( 'Pay locally.', 'Our guides, drivers, and partners are Lao-owned, paid above the national hospitality average.' ),
					array( 'Tell the truth.', 'If a tour will not work for you, we say so. If a season is wrong, we delay you. If a price is fair, we hold it.' ),
					array( 'Leave Laos better.', 'Community, conservation, and culture — funded as a percentage of every booking.' ),
				);
				foreach ( $wpistic_manifesto as $wpistic_mi => $wpistic_m ) :
					?>
					<div class="manifesto-item">
						<span class="manifesto-num"><?php echo esc_html( sprintf( '%02d', $wpistic_mi + 1 ) ); ?></span>
						<div>
							<h3><?php echo esc_html( $wpistic_m[0] ); ?></h3>
							<p><?php echo esc_html( $wpistic_m[1] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>

<!-- 7 · WHY -->
<section class="section section-sand reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">Why Brother Tours</span>
			<h2 class="sec-h2">Human first. Experience second. Destination <em>third</em>.</h2>
		</div>
		<p class="why-statement">We do not sell journeys. We share the country we were <em>born</em> in.</p>
	</div>
</section>

<!-- 8 · TRUST -->
<section class="section reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">Guest Reviews</span>
			<h2 class="sec-h2">Read what guests have <em>written</em>.</h2>
			<p class="sec-lead">Read guest notes on Google and TripAdvisor.</p>
		</div>
		<?php wpistic_review_links(); ?>
	</div>
</section>

<!-- 9 · CTA -->
<?php wpistic_build_my_trip_cta(); ?>

<?php
get_footer();

<?php
/**
 * Template Name: Sustainability
 *
 * PDF "Receipts & Projects". Commitment stats + four pillars + live projects.
 * Figures are editable placeholders (meta-overridable); brand-safe framing —
 * these are commitments/receipts, not guest counts or review numbers.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$wpistic_hero = wpistic_demo_img( 'dest-nong-khiaw' );

$wpistic_pillars = array(
	array( 'eyebrow' => 'I · Community', 'title' => 'We hire Laotians, and we pay them well.', 'body' => 'Every guide, driver, planner and host on our team is a Lao national. Salaries are benchmarked against the national hospitality average and adjusted upward.', 'stats' => array( '100% Lao team', 'Above national hospitality wage', 'Tips pooled and shared' ), 'img' => 'tour-3' ),
	array( 'eyebrow' => 'II · Villages', 'title' => 'Upland villages, partnered for the long term.', 'body' => 'Our community fund pays directly into upland communities. Funds are decided by a village council, not by us, and the receipts are published in our travelers\' letter.', 'stats' => array( 'Direct — no NGO middleman', 'Council-decided spend', 'Annual published receipts' ), 'img' => 'dest-nong-khiaw' ),
	array( 'eyebrow' => 'III · Climate', 'title' => 'Slow travel by design — and a forest in Bokeo.', 'body' => 'We design itineraries around boats and trains where possible. Every booking funds native hardwood trees planted in our reforestation parcel, audited by the provincial forestry office.', 'stats' => array( 'Native teak, rosewood & mai sak', 'Boats & trains over flights', 'Independently audited' ), 'img' => 'tour-2' ),
	array( 'eyebrow' => 'IV · Heritage', 'title' => 'Quiet at the alms. Permission at the temple.', 'body' => 'We brief every guest privately on the protocols of the dawn alms ceremony, of temple visits, and of village photography. We say no to visits where our presence would harm the place.', 'stats' => array( 'Private briefing — every tour', 'No-photo zones honored', 'Permission first' ), 'img' => 'dest-luang-prabang' ),
);

$wpistic_projects = array(
	array( 'name' => 'Village literacy program', 'desc' => 'Books, salaries, and a small library extension for an upland village. Phongsali · North.', 'img' => 'dest-plain-of-jars' ),
	array( 'name' => 'Hardwood reforestation', 'desc' => 'A restoration parcel of native teak, rosewood and mai sak under long-term care. Bokeo · North.', 'img' => 'tour-2' ),
	array( 'name' => 'Weaving collective', 'desc' => 'Looms, cotton stocks, and direct-to-traveler retail. Bolaven · South.', 'img' => 'dest-pakse' ),
);
?>

<section class="page-hero">
	<?php if ( $wpistic_hero ) : ?>
		<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
	<?php endif; ?>
	<div class="wrap">
		<?php wpistic_breadcrumbs(); ?>
		<span class="eyebrow">Our commitment</span>
		<h1>Travel that leaves Laos <em>better</em>.</h1>
		<p class="lede">A defined percentage of every booking goes to community, conservation, and culture across Laos. Below, the receipts.</p>
	</div>
</section>

<section class="stat-band">
	<div class="wrap">
		<?php
		$wpistic_facts = array(
			array( 'num' => 'Every booking', 'label' => 'Funds the community fund' ),
			array( 'num' => 'Upland', 'label' => 'Villages partnered' ),
			array( 'num' => 'Native', 'label' => 'Hardwood reforestation' ),
			array( 'num' => '100%', 'label' => 'Lao-owned, Lao-staffed' ),
		);
		foreach ( $wpistic_facts as $wpistic_f ) :
			?>
			<div class="stat"><span class="stat-num"><?php echo esc_html( $wpistic_f['num'] ); ?></span><span class="stat-label"><?php echo esc_html( $wpistic_f['label'] ); ?></span></div>
		<?php endforeach; ?>
	</div>
</section>

<section class="section reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">The four pillars</span>
			<h2 class="sec-h2">How responsible looks, in <em>practice</em>.</h2>
		</div>
		<div class="pillars">
			<?php foreach ( $wpistic_pillars as $wpistic_p ) : ?>
				<article class="pillar reveal">
					<div class="pillar-media">
						<?php $wpistic_pi = wpistic_demo_img( $wpistic_p['img'] ); if ( $wpistic_pi ) { printf( '<img src="%s" alt="" loading="lazy">', esc_url( $wpistic_pi ) ); } ?>
					</div>
					<div class="pillar-body">
						<p class="pillar-eyebrow"><?php echo esc_html( $wpistic_p['eyebrow'] ); ?></p>
						<h3 class="pillar-title"><?php echo esc_html( $wpistic_p['title'] ); ?></h3>
						<p><?php echo esc_html( $wpistic_p['body'] ); ?></p>
						<div class="pillar-stats">
							<?php foreach ( $wpistic_p['stats'] as $wpistic_s ) : ?>
								<span><strong><?php echo esc_html( $wpistic_s ); ?></strong></span>
							<?php endforeach; ?>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="section section-sand reveal">
	<div class="wrap">
		<div class="section-head"><span class="eyebrow">Live projects</span><h2 class="sec-h2">What your booking <em>supports</em>.</h2></div>
		<div class="places-grid cols-3">
			<?php foreach ( $wpistic_projects as $wpistic_proj ) : ?>
				<div class="place-card reveal">
					<div class="place-media">
						<?php $wpistic_pri = wpistic_demo_img( $wpistic_proj['img'] ); if ( $wpistic_pri ) { printf( '<img src="%s" alt="" loading="lazy">', esc_url( $wpistic_pri ) ); } ?>
					</div>
					<p class="place-name"><?php echo esc_html( $wpistic_proj['name'] ); ?></p>
					<p class="place-desc"><?php echo esc_html( $wpistic_proj['desc'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="section reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">The traveler promise</span>
			<h2 class="sec-h2">Travel you can be proud <em>of</em>.</h2>
		</div>
		<p class="why-statement">Every booking funds a documented Lao project. Every guide, driver and host is paid Lao wages above the national average. Every itinerary is designed to leave a place quieter, cleaner, kinder than it was when you arrived.</p>
	</div>
</section>

<?php wpistic_build_my_trip_cta(); ?>

<?php
get_footer();

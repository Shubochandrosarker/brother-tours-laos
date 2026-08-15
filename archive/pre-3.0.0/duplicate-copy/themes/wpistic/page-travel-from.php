<?php
/**
 * Template Name: Country Edition (e.g. USA)
 *
 * PDF "Country Localized". A localized landing for travelers from one country.
 * Brand-safe: NO company-wide guest numbers, NO fabricated review counts/stars.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$wpistic_hero = wpistic_demo_img( 'hero-laos' );

$wpistic_diff = array(
	array( 'title' => 'US-hours support', 'body' => 'A senior planner is available 9am–12pm ET, Mon–Fri. Outside hours, WhatsApp replies within 24 hours.' ),
	array( 'title' => 'Bangkok / Hanoi connection', 'body' => 'We coordinate the regional flight or train into Laos so you do not arrive jet-lagged and lost. A team member meets you.' ),
	array( 'title' => 'USD pricing, US payment', 'body' => 'Quotes in USD. Secure US checkout. No foreign-card surprise fees.' ),
	array( 'title' => 'Visa, handled', 'body' => 'Lao e-visa included free for tour clients. We submit, track, and forward — no embassy queue.' ),
	array( 'title' => 'Travel insurance partner', 'body' => 'We refer you to an A-rated US insurer with Laos coverage that actually pays out. Optional.' ),
	array( 'title' => 'Same hosts, every trip', 'body' => 'You will know your guide and planner by name before you board. No call-center handoffs.' ),
);

$wpistic_routes = array(
	array( 'label' => 'From the East Coast', 'stops' => array( 'New York', 'Bangkok', 'Vientiane' ), 'note' => '~22 hrs · 1 stop' ),
	array( 'label' => 'From the West Coast', 'stops' => array( 'Los Angeles', 'Tokyo', 'Hanoi', 'Vientiane' ), 'note' => '~24 hrs · 2 stops' ),
	array( 'label' => 'Via Seoul', 'stops' => array( 'San Francisco', 'Seoul', 'Vientiane' ), 'note' => '~21 hrs · 1 stop' ),
);
?>

<section class="page-hero">
	<?php if ( $wpistic_hero ) : ?>
		<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
	<?php endif; ?>
	<div class="wrap">
		<?php wpistic_breadcrumbs(); ?>
		<span class="eyebrow">For travelers from the United States</span>
		<h1>Laos, designed for the American <em>traveler</em>.</h1>
		<p class="lede">Direct from Bangkok or Hanoi. Visa, flights, and in-country logistics — handled by a Lao-owned company in Vientiane.</p>
		<div class="hero-actions">
			<a class="btn btn-solid" href="<?php echo esc_url( wpistic_cta_url() ); ?>"><?php echo esc_html( wpistic_cta_label() ); ?> <span class="arr" aria-hidden="true">→</span></a>
			<a class="btn btn-ghost on-dark" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Schedule a US-hours call</a>
		</div>
		<p class="facts">
			<span>ET-hours support · 9am–12pm ET</span>
			<span>USD pricing · no FX surprises</span>
			<span>Visa handled</span>
		</p>
	</div>
</section>

<section class="section reveal">
	<div class="wrap">
		<div class="section-head"><span class="eyebrow">What's different for US travelers</span><h2 class="sec-h2">We removed the friction of planning Laos from half a world <em>away</em>.</h2></div>
		<div class="steps-grid">
			<?php foreach ( $wpistic_diff as $wpistic_di => $wpistic_d ) : ?>
				<div class="step reveal">
					<p class="step-num"><?php echo esc_html( sprintf( '%02d', $wpistic_di + 1 ) ); ?></p>
					<h3 class="step-title"><?php echo esc_html( $wpistic_d['title'] ); ?></h3>
					<p class="step-body"><?php echo esc_html( $wpistic_d['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="section section-sand reveal">
	<div class="wrap">
		<div class="section-head"><span class="eyebrow">Top picks</span><h2 class="sec-h2">Journeys our American guests come back <em>for</em>.</h2></div>
		<div class="tour-grid">
			<?php
			$wpistic_us_img = array( 'tour-1', 'tour-3', 'tour-5' );
			foreach ( array_slice( wpistic_sample_tours(), 0, 3 ) as $wpistic_i => $wpistic_tour ) :
				wpistic_tour_card(
					array(
						'name'      => $wpistic_tour['name'],
						'url'       => home_url( '/tour/' . $wpistic_tour['slug'] . '/' ),
						'region'    => 'Laos',
						'meta'      => $wpistic_tour['meta'],
						'blurb'     => $wpistic_tour['blurb'],
						'tags'      => array( $wpistic_tour['cap'] ),
						'price'     => wpistic_price_line( '' ),
						'image_url' => wpistic_demo_img( $wpistic_us_img[ $wpistic_i ] ),
					)
				);
			endforeach;
			?>
		</div>
	</div>
</section>

<section class="section reveal">
	<div class="wrap">
		<div class="section-head"><span class="eyebrow">Getting there, honestly</span><h2 class="sec-h2">There are no direct flights from the US to Laos. We turn that into a <em>feature</em>.</h2><p class="sec-lead">We coordinate a one-night stop on either side — built into the itinerary, not bolted on — so you arrive rested.</p></div>
		<div class="routes">
			<?php foreach ( $wpistic_routes as $wpistic_r ) : ?>
				<div class="route-block">
					<p class="route-label"><?php echo esc_html( $wpistic_r['label'] ); ?> · <?php echo esc_html( $wpistic_r['note'] ); ?></p>
					<div class="route-strip">
						<?php
						$wpistic_last = count( $wpistic_r['stops'] ) - 1;
						foreach ( $wpistic_r['stops'] as $wpistic_si => $wpistic_stop ) {
							echo '<span>' . esc_html( $wpistic_stop ) . '</span>';
							if ( $wpistic_si < $wpistic_last ) {
								echo '<span class="arr" aria-hidden="true">→</span>';
							}
						}
						?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="section section-sand reveal">
	<div class="wrap">
		<div class="section-head center"><span class="eyebrow center">From our American guests</span><h2 class="sec-h2">In their <em>words</em>.</h2><p class="sec-lead">Read guest notes on Google and TripAdvisor.</p></div>
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

<section class="section reveal">
	<div class="wrap u-narrow">
		<div class="section-head"><span class="eyebrow">FAQ · US edition</span><h2 class="sec-h2">Practical, for travelers from the <em>States</em>.</h2></div>
		<div class="faq">
			<details class="faq-item" open><summary class="faq-q">Do US passport holders need a visa for Laos?</summary><div class="faq-a">Yes — a 30-day e-visa, processed in about three days. We submit it for you, free, when you book a journey.</div></details>
			<details class="faq-item"><summary class="faq-q">What is the best month to visit Laos from the US?</summary><div class="faq-a">November to February is cool and dry. The green season brings fewer travelers and softer light.</div></details>
			<details class="faq-item"><summary class="faq-q">Can we combine Laos with Thailand or Vietnam?</summary><div class="faq-a">Yes — we design cross-border routes through Bangkok, Chiang Mai, or Hanoi on request.</div></details>
			<details class="faq-item"><summary class="faq-q">How does payment work for US clients?</summary><div class="faq-a">Quotes in USD, secure US checkout, and no foreign-card surprise fees. No payment until your itinerary is approved.</div></details>
			<details class="faq-item"><summary class="faq-q">Is Laos safe for American travelers?</summary><div class="faq-a">Laos is a calm, welcoming country. Your host is with you throughout, and we are reachable on WhatsApp at any hour.</div></details>
		</div>
	</div>
</section>

<?php wpistic_build_my_trip_cta(); ?>

<?php
get_footer();

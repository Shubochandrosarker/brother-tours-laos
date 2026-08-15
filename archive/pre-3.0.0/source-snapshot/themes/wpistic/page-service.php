<?php
/**
 * Template Name: Service Page
 *
 * PDF "Service Template" (e.g. Laos Visa): navy masthead + facts, what's included,
 * how it works, a pricing table, and an FAQ. All sections are meta-driven with
 * brand-safe Laos-Visa defaults, so each service page is editable from its editor.
 * (Service fees are published flat fees — distinct from the tour pricing rule.)
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$wpistic_id   = get_the_ID();
	$wpistic_hero = has_post_thumbnail() ? get_the_post_thumbnail_url( $wpistic_id, 'bt-hero' ) : wpistic_demo_img( 'dest-vientiane' );

	$wpistic_facts = get_post_meta( $wpistic_id, 'wpistic_service_facts', true );
	if ( ! is_array( $wpistic_facts ) || empty( $wpistic_facts ) ) {
		$wpistic_facts = array( '$35 e-visa fee', '3 days processing', '30 days stay' );
	}

	$wpistic_includes = get_post_meta( $wpistic_id, 'wpistic_service_includes', true );
	if ( ! is_array( $wpistic_includes ) || empty( $wpistic_includes ) ) {
		$wpistic_includes = array(
			array( 'title' => 'Document review', 'body' => 'Passport, photo specs and address checks before submission.' ),
			array( 'title' => 'e-Visa application', 'body' => 'We submit your application through the official Lao portal.' ),
			array( 'title' => 'Processing tracking', 'body' => 'Daily updates from us until your e-visa lands in your inbox.' ),
			array( 'title' => 'Entry briefing', 'body' => 'Which checkpoint to use, what to expect, what to carry.' ),
			array( 'title' => 'On-arrival support', 'body' => 'A team member reachable on WhatsApp during your crossing.' ),
			array( 'title' => 'Multi-entry guidance', 'body' => 'For travelers combining Laos with Thailand, Vietnam or Cambodia.' ),
		);
	}

	$wpistic_steps = get_post_meta( $wpistic_id, 'wpistic_service_steps', true );
	if ( ! is_array( $wpistic_steps ) || empty( $wpistic_steps ) ) {
		$wpistic_steps = array(
			array( 'title' => 'Request', 'body' => 'Tell us your dates and passport country.' ),
			array( 'title' => 'Documents', 'body' => 'Upload your passport scan and photo to our secure form.' ),
			array( 'title' => 'Submission', 'body' => 'We submit and pay the government fee.' ),
			array( 'title' => 'Approval', 'body' => 'Your e-visa arrives — we forward it to you.' ),
			array( 'title' => 'Arrive in Laos', 'body' => 'Print, present, get stamped. Welcome.' ),
		);
	}

	$wpistic_pricing = get_post_meta( $wpistic_id, 'wpistic_service_pricing', true );
	if ( ! is_array( $wpistic_pricing ) || empty( $wpistic_pricing ) ) {
		$wpistic_pricing = array(
			array( 'name' => 'Standard e-visa (3 days)', 'amt' => '$35' ),
			array( 'name' => 'Express e-visa (24 hours)', 'amt' => '$60' ),
			array( 'name' => 'Multi-entry guidance', 'amt' => 'Included' ),
			array( 'name' => 'On-arrival visa support', 'amt' => '$25' ),
			array( 'name' => 'Visa for tour clients', 'amt' => 'Included free' ),
		);
	}
	?>
	<section class="page-hero">
		<?php if ( $wpistic_hero ) : ?>
			<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
		<?php endif; ?>
		<div class="wrap">
			<?php wpistic_breadcrumbs(); ?>
			<span class="eyebrow">Service</span>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="lede"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
			<div class="hero-actions">
				<a class="btn btn-solid" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Request a Quote', 'wpistic' ); ?></a>
				<a class="btn btn-ghost on-dark" href="<?php echo esc_url( wpistic_whatsapp_url() ); ?>" rel="noopener"><?php esc_html_e( 'Ask on WhatsApp', 'wpistic' ); ?></a>
			</div>
			<?php if ( $wpistic_facts ) : ?>
				<p class="facts"><?php foreach ( $wpistic_facts as $wpistic_f ) : ?><span><?php echo esc_html( $wpistic_f ); ?></span><?php endforeach; ?></p>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( get_the_content() ) : ?>
		<section class="section"><div class="wrap"><div class="prose u-narrow"><?php the_content(); ?></div></div></section>
	<?php endif; ?>

	<section class="section section-sand">
		<div class="wrap">
			<div class="section-head"><span class="eyebrow">What's included</span><h2 class="sec-h2">In this <em>service</em>.</h2></div>
			<div class="steps-grid">
				<?php foreach ( $wpistic_includes as $wpistic_ii => $wpistic_inc ) : ?>
					<div class="step reveal">
						<p class="step-num"><?php echo esc_html( sprintf( '%02d', $wpistic_ii + 1 ) ); ?></p>
						<h3 class="step-title"><?php echo esc_html( $wpistic_inc['title'] ?? '' ); ?></h3>
						<p class="step-body"><?php echo esc_html( $wpistic_inc['body'] ?? '' ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="wrap">
			<div class="section-head center"><span class="eyebrow center">How it works</span><h2 class="sec-h2">A few steps. About thirty <em>minutes</em>.</h2></div>
			<div class="process">
				<?php foreach ( $wpistic_steps as $wpistic_si => $wpistic_step ) : ?>
					<div class="process-step">
						<span class="process-num"><?php echo esc_html( $wpistic_si + 1 ); ?></span>
						<h3><?php echo esc_html( $wpistic_step['title'] ?? '' ); ?></h3>
						<p><?php echo esc_html( $wpistic_step['body'] ?? '' ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section section-sand">
		<div class="wrap">
			<div class="split-grid lean">
				<div class="section-head">
					<span class="eyebrow">Pricing</span>
					<h2 class="sec-h2">Clear fees, no <em>surprises</em>.</h2>
					<p class="sec-lead"><?php esc_html_e( 'Published flat fees. Visa is included free when you book a journey with us.', 'wpistic' ); ?></p>
				</div>
				<div class="pricing-table">
					<?php foreach ( $wpistic_pricing as $wpistic_p ) : ?>
						<div class="pricing-row"><span class="name"><?php echo esc_html( $wpistic_p['name'] ?? '' ); ?></span><span class="amt"><?php echo esc_html( $wpistic_p['amt'] ?? '' ); ?></span></div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="wrap u-narrow">
			<div class="section-head"><span class="eyebrow">Good to know</span><h2 class="sec-h2">Questions, <em>answered</em>.</h2></div>
			<div class="faq">
				<details class="faq-item" open><summary class="faq-q">Which passports can apply for a Lao e-visa?</summary><div class="faq-a">Travelers from 80+ countries. Tell us your passport and we'll confirm the right route for you.</div></details>
				<details class="faq-item"><summary class="faq-q">Can I extend my visa once I'm in Laos?</summary><div class="faq-a">Yes. We can advise on extensions and handle the paperwork for tour clients.</div></details>
				<details class="faq-item"><summary class="faq-q">What if I arrive at a non-e-visa checkpoint?</summary><div class="faq-a">We brief you on the right crossing in advance and prepare on-arrival paperwork as a backup.</div></details>
				<details class="faq-item"><summary class="faq-q">How early should I apply?</summary><div class="faq-a">Two weeks ahead is comfortable. Express processing is available within 24 hours.</div></details>
			</div>
		</div>
	</section>

	<?php wpistic_build_my_trip_cta(); ?>
	<?php
endwhile;

get_footer();

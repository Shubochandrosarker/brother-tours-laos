<?php
/**
 * Template Name: Build My Trip
 *
 * PDF "Custom Trip Builder". Three-step intro + the shape-of-trip form. No budget
 * field, by design. Submits through Tour Manager when active, with a static fallback.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpistic_tour_context = isset( $_GET['tour'] ) ? sanitize_text_field( wp_unslash( $_GET['tour'] ) ) : '';

get_header();
$wpistic_hero = wpistic_demo_img( 'hero-southern' );
?>

<section class="page-hero">
	<?php if ( $wpistic_hero ) : ?>
		<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
	<?php endif; ?>
	<div class="wrap">
		<?php wpistic_breadcrumbs(); ?>
		<span class="eyebrow">The custom journey</span>
		<h1>Your private Laos journey, designed around <em>you</em>.</h1>
		<p class="lede">We design your journey, your way.</p>
	</div>
</section>

<section class="section">
	<div class="wrap">
		<div class="process">
			<div class="process-step">
				<span class="process-num">1</span>
				<h3>Tell us your dream trip</h3>
				<p>Dates, group, what you love. Five minutes.</p>
			</div>
			<div class="process-step">
				<span class="process-num">2</span>
				<h3>We design your itinerary</h3>
				<p>Drafts within a day, tailored to you.</p>
			</div>
			<div class="process-step">
				<span class="process-num">3</span>
				<h3>Approve &amp; travel</h3>
				<p>Edit anything until the morning you arrive.</p>
			</div>
		</div>
	</div>
</section>

<section class="section section-sand reveal">
	<div class="wrap">
		<div class="split-grid lean">
			<div class="prose">
				<h2>Tell us your <em>shape</em> of trip.</h2>
				<p>Tell us what you're imagining. We build a journey around it, send it to you to read, and refine it together until it fits. No pressure, no upselling.</p>
				<p>Most travelers find seven to fourteen days is the right length to see Laos properly — long enough to slow down, short enough to stay sharp.</p>
				<p><strong>No payment until you approve the itinerary.</strong></p>
			</div>

			<div class="form-wrap">
				<h2 class="form-title">Tell us your shape</h2>
				<p class="form-sub">Tell us the kind of experience you have in mind.</p>
				<?php
				if ( shortcode_exists( 'wpistic_build_my_trip_form' ) ) {
					echo do_shortcode( '[wpistic_build_my_trip_form]' );
				} else {
					?>
					<form class="bmt-form" method="post" action="">
						<?php if ( '' !== $wpistic_tour_context ) : ?>
							<input type="hidden" name="tour_context" value="<?php echo esc_attr( $wpistic_tour_context ); ?>">
						<?php endif; ?>

						<fieldset>
							<legend>When &amp; how long</legend>
							<div class="field-row">
								<div class="field"><label for="b-dates">Travel dates (or flexible)</label><input type="text" id="b-dates" name="dates" placeholder="e.g. flexible, Nov 2026"></div>
								<div class="field"><label for="b-duration">Trip length</label><input type="text" id="b-duration" name="duration" placeholder="e.g. 10 nights"></div>
							</div>
						</fieldset>

						<fieldset>
							<legend>Who is travelling</legend>
							<div class="field-row">
								<div class="field"><label for="b-adults">Adults</label><input type="number" id="b-adults" name="adults" min="1" value="2"></div>
								<div class="field"><label for="b-children">Children (ages)</label><input type="text" id="b-children" name="children" placeholder="e.g. 2 (ages 8, 11)"></div>
							</div>
							<div class="field">
								<label for="b-hotel">Hotel preference</label>
								<select id="b-hotel" name="hotel">
									<option value="">No preference</option>
									<option>3-star</option><option>4-star</option><option>5-star</option><option>Heritage</option>
								</select>
							</div>
						</fieldset>

						<fieldset>
							<legend>What you're imagining</legend>
							<div class="field"><label for="b-interests">Interests</label><input type="text" id="b-interests" name="interests" placeholder="culture, river, food, family, photography…"></div>
							<div class="field"><label for="b-notes">Anything else we should know</label><textarea id="b-notes" name="notes" placeholder="Dietary needs, accessibility, anniversaries…"></textarea></div>
						</fieldset>

						<fieldset>
							<legend>Where to reach you</legend>
							<div class="field-row">
								<div class="field"><label for="b-name">Name</label><input type="text" id="b-name" name="name" autocomplete="name"></div>
								<div class="field"><label for="b-country">Country</label><input type="text" id="b-country" name="country" autocomplete="country-name"></div>
							</div>
							<div class="field-row">
								<div class="field"><label for="b-email">Email</label><input type="email" id="b-email" name="email" autocomplete="email"></div>
								<div class="field"><label for="b-phone">Phone</label><input type="tel" id="b-phone" name="phone"></div>
							</div>
						</fieldset>

						<button class="btn btn-solid" type="submit">Send my request <span class="arr" aria-hidden="true">→</span></button>
						<p class="form-note">No budget box, by design. The booking is human-confirmed.</p>
					</form>
					<?php
				}
				?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();

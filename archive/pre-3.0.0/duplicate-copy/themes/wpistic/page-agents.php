<?php
/**
 * Template Name: Travel Agents
 *
 * B2B partnership page — navy masthead + partnership intro beside an agent form
 * flagged [AGENT] to the team through Tour Manager.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$wpistic_hero = wpistic_demo_img( 'tour-3' );
?>

<section class="page-hero">
	<?php if ( $wpistic_hero ) : ?>
		<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
	<?php endif; ?>
	<div class="wrap">
		<?php wpistic_breadcrumbs(); ?>
		<span class="eyebrow">Travel Agents · B2B</span>
		<h1>For Travel <em>Agents</em>.</h1>
		<p class="lede">Lao-led journeys, handled as a partner.</p>
	</div>
</section>

<section class="section reveal">
	<div class="wrap">
		<div class="split-grid lean">
			<div class="prose">
				<h2>Partner with the people on the <em>ground</em>.</h2>
				<p>We work with a small number of agents and operators who place clients with us directly in Laos. You keep the relationship; we carry the ground.</p>
				<p>Net rates, named hosts, and a single point of contact in Vientiane. Tell us about your clients and the regions you sell, and we will set up the partnership.</p>
			</div>
			<div class="form-wrap">
				<h2 class="form-title">Partner with us</h2>
				<p class="form-sub">Flagged <code>[AGENT]</code> to our team.</p>
				<?php
				if ( shortcode_exists( 'wpistic_agent_form' ) ) {
					echo do_shortcode( '[wpistic_agent_form]' );
				} else {
					?>
					<form class="bmt-form" method="post" action="">
						<div class="field"><label for="a-org">Organization</label><input type="text" id="a-org" name="org" autocomplete="organization"></div>
						<div class="field-row">
							<div class="field"><label for="a-name">Contact</label><input type="text" id="a-name" name="name" autocomplete="name"></div>
							<div class="field"><label for="a-email">Email</label><input type="email" id="a-email" name="email" autocomplete="email"></div>
						</div>
						<div class="field"><label for="a-regions">Regions of interest</label><input type="text" id="a-regions" name="regions" placeholder="Northern Laos, cross-country…"></div>
						<div class="field"><label for="a-details">Details</label><textarea id="a-details" name="details"></textarea></div>
						<button class="btn btn-solid" type="submit">Send inquiry <span class="arr" aria-hidden="true">→</span></button>
						<p class="form-note">Flagged to the Brother Tours team for partner follow-up.</p>
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

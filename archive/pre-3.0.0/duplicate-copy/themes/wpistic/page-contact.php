<?php
/**
 * Template Name: Contact
 *
 * PDF "Inquiry Page" — navy masthead + a message form beside direct-contact details.
 * Uses Formistic when present, otherwise Tour Manager captures the inquiry.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$wpistic_hero = wpistic_demo_img( 'dest-vientiane' );
?>

<section class="page-hero">
	<?php if ( $wpistic_hero ) : ?>
		<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
	<?php endif; ?>
	<div class="wrap">
		<?php wpistic_breadcrumbs(); ?>
		<span class="eyebrow">Get in touch</span>
		<h1>Talk to us in <em>Vientiane</em>.</h1>
		<p class="lede">A clear path from first message to first morning.</p>
	</div>
</section>

<section class="section reveal">
	<div class="wrap">
		<div class="split-grid lean">
			<div class="form-wrap">
				<h2 class="form-title">Send us a note</h2>
				<p class="form-sub">A senior planner replies within 24 hours, from Vientiane.</p>
				<?php
				if ( shortcode_exists( 'formistic_contact' ) ) {
					echo do_shortcode( '[formistic_contact]' );
				} elseif ( shortcode_exists( 'wpistic_contact_form' ) ) {
					echo do_shortcode( '[wpistic_contact_form]' );
				} else {
					?>
					<form class="bmt-form" method="post" action="">
						<div class="field"><label for="c-name">Your name</label><input type="text" id="c-name" name="name" autocomplete="name"></div>
						<div class="field-row">
							<div class="field"><label for="c-email">Email</label><input type="email" id="c-email" name="email" autocomplete="email"></div>
							<div class="field"><label for="c-phone">WhatsApp (optional)</label><input type="tel" id="c-phone" name="phone"></div>
						</div>
						<div class="field"><label for="c-msg">Tell us about your trip</label><textarea id="c-msg" name="message" placeholder="Hi Ken — we'd love a slow trip through the north…"></textarea></div>
						<button class="btn btn-solid" type="submit">Send to Ken <span class="arr" aria-hidden="true">→</span></button>
						<p class="form-note">Replies usually within 24 hours. No payment until your itinerary is approved.</p>
					</form>
					<?php
				}
				?>
			</div>
			<div class="contact-info">
				<div class="item"><p class="label">WhatsApp</p><a href="<?php echo esc_url( wpistic_whatsapp_url() ); ?>" rel="noopener"><?php echo esc_html( wpistic_contact( 'whatsapp_display' ) ); ?></a></div>
				<div class="item"><p class="label">Email</p><a href="<?php echo esc_url( 'mailto:' . wpistic_contact( 'email' ) ); ?>"><?php echo esc_html( wpistic_contact( 'email' ) ); ?></a></div>
				<div class="item"><p class="label">Office</p><?php echo esc_html( wpistic_contact( 'office' ) ); ?></div>
				<div class="item"><p class="label">Office hours</p><?php echo esc_html( wpistic_contact( 'hours' ) ); ?><br>WhatsApp answered evenings &amp; Sundays too.</div>
				<div class="item"><p class="label">Reply time</p>Within 24 hours, from Vientiane.</div>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();

<?php
/**
 * Template Name: Thank You
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="center-stage on-navy">
	<span class="eyebrow center">Received</span>
	<h1>Thank <em>you</em>.</h1>
	<p>Your request is with our team in Vientiane. We reply within 24 hours — designed to you, no pressure, no upselling.</p>
	<div class="final-btns">
		<a class="btn btn-solid" href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to home</a>
		<a class="btn btn-ghost on-dark" href="<?php echo esc_url( home_url( '/laos-travel-guide/' ) ); ?>">Read the travel guide</a>
	</div>
</section>

<?php
get_footer();

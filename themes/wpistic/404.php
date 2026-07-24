<?php
/**
 * 404 — every path resolves somewhere useful (the migration rule, at the theme level).
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="center-stage on-navy">
	<span class="eyebrow center">404</span>
	<h1>Not <em>found</em>.</h1>
	<p>The page you were looking for isn't here. The journeys still are.</p>
	<div class="final-btns">
		<a class="btn btn-solid" href="<?php echo esc_url( home_url( '/tours/' ) ); ?>">See our journeys <span class="arr" aria-hidden="true">→</span></a>
		<a class="btn btn-ghost on-dark" href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to home</a>
	</div>
</section>

<?php
get_footer();

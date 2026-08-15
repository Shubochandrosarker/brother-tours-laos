<?php
/**
 * Default page template (legal pages: Privacy, Terms, Cancellation, and generic pages).
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero">
		<div class="wrap">
			<?php wpistic_breadcrumbs(); ?>
			<h1><?php the_title(); ?></h1>
		</div>
	</section>

	<section class="section">
		<div class="wrap">
			<div class="prose entry-content u-narrow"><?php the_content(); ?></div>
		</div>
	</section>
	<?php
endwhile;

get_footer();

<?php
/**
 * Fallback template. Specific templates (front-page, single-tour, etc.) come in Phase 1 continuation.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="wrap site-content">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'entry' ); ?>>
				<h1 class="entry__title"><?php the_title(); ?></h1>
				<div class="entry__content">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		endwhile;

		the_posts_pagination();
	else :
		?>
		<p><?php esc_html_e( 'Nothing here yet.', 'wpistic' ); ?></p>
		<?php
	endif;
	?>
</div>
<?php
get_footer();

<?php
/**
 * Journal post (BlogPosting). 15-element layout; Person/BlogPosting JSON-LD from SEOISTIC.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'single' ) ) {
	get_footer();
	return;
}

while ( have_posts() ) :
	the_post();
	$wpistic_cats = get_the_category();
	$wpistic_hero = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'bt-hero' ) : wpistic_demo_img( 'journal-1' );
	?>
	<article <?php post_class(); ?>>
		<section class="page-hero">
			<?php if ( $wpistic_hero ) : ?>
				<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
			<?php endif; ?>
			<div class="wrap">
				<?php wpistic_breadcrumbs(); ?>
				<span class="eyebrow"><?php echo esc_html( $wpistic_cats ? $wpistic_cats[0]->name : __( 'Journal', 'wpistic' ) ); ?></span>
				<h1><?php the_title(); ?></h1>
				<p class="journal-meta">
					<?php echo esc_html( get_the_date() ); ?> · <?php echo esc_html( get_the_author() ); ?>
					<?php if ( get_the_modified_date() !== get_the_date() ) : ?>
						· <?php printf( esc_html__( 'Updated %s', 'wpistic' ), esc_html( get_the_modified_date() ) ); ?>
					<?php endif; ?>
				</p>
			</div>
		</section>

		<section class="section">
			<div class="wrap u-narrow">
				<?php if ( has_excerpt() ) : ?>
					<p class="entry-standfirst"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="entry-feat"><?php the_post_thumbnail( 'bt-hero', array( 'loading' => 'lazy' ) ); ?></div>
				<?php endif; ?>

				<div class="prose entry-content"><?php the_content(); ?></div>

				<div class="author-block">
					<div class="author-avatar"><?php echo get_avatar( get_the_author_meta( 'ID' ), 54 ); ?></div>
					<div>
						<p class="author-name"><?php the_author(); ?></p>
						<p class="author-role">Brother Tours · Vientiane</p>
					</div>
				</div>
			</div>
		</section>

		<?php wpistic_build_my_trip_cta(); ?>
	</article>
	<?php
endwhile;

get_footer();

<?php
/**
 * Single experience (nested under a destination) — PDF "Experience" layout.
 *
 * Navy masthead, editorial prose, optional gallery, and a Build-My-Trip CTA.
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

	$wpistic_id   = get_the_ID();
	// Vary the no-thumbnail fallback so neighbouring experiences don't share one image.
	$wpistic_pool = array( 'dest-luang-prabang', 'dest-nong-khiaw', 'dest-vang-vieng', 'dest-pakse', 'dest-vientiane' );
	$wpistic_hero = has_post_thumbnail() ? get_the_post_thumbnail_url( $wpistic_id, 'bt-hero' ) : wpistic_demo_img( $wpistic_pool[ $wpistic_id % count( $wpistic_pool ) ] );
	?>
	<section class="page-hero">
		<?php if ( $wpistic_hero ) : ?>
			<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
		<?php endif; ?>
		<div class="wrap">
			<?php wpistic_breadcrumbs(); ?>
			<span class="eyebrow">Experience</span>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="lede"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section">
		<div class="wrap">
			<div class="prose u-narrow"><?php the_content(); ?></div>

			<?php
			$wpistic_gallery = get_post_meta( $wpistic_id, 'wpistic_gallery', true );
			if ( is_array( $wpistic_gallery ) && $wpistic_gallery ) :
				?>
				<div class="gallery-grid u-mt-l">
					<?php foreach ( array_slice( $wpistic_gallery, 0, 8 ) as $wpistic_gid ) : ?>
						<div class="cell"><?php echo wp_get_attachment_image( absint( $wpistic_gid ), 'bt-gallery', false, array( 'loading' => 'lazy' ) ); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php wpistic_build_my_trip_cta( get_the_title() ); ?>
	<?php
endwhile;

get_footer();

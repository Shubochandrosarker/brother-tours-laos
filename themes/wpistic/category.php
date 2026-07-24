<?php
/**
 * Journal category archive.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$wpistic_hero = wpistic_demo_img( 'journal-2' );
?>

<section class="page-hero">
	<?php if ( $wpistic_hero ) : ?>
		<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
	<?php endif; ?>
	<div class="wrap">
		<?php wpistic_breadcrumbs(); ?>
		<span class="eyebrow">Travel Guide</span>
		<h1><?php single_cat_title(); ?></h1>
		<?php
		$wpistic_desc = category_description();
		if ( $wpistic_desc ) :
			?>
			<div class="lede"><?php echo wp_kses_post( $wpistic_desc ); ?></div>
		<?php endif; ?>
	</div>
</section>

<section class="section">
	<div class="wrap">
		<div class="journal-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				$wpistic_cats = get_the_category();
				?>
				<a class="journal-card reveal" href="<?php the_permalink(); ?>">
					<div class="journal-thumb">
						<?php
						if ( has_post_thumbnail() ) {
							the_post_thumbnail( 'bt-teaser', array( 'loading' => 'lazy' ) );
						}
						?>
					</div>
					<span class="journal-cat"><?php echo esc_html( $wpistic_cats ? $wpistic_cats[0]->name : '' ); ?></span>
					<h2 class="journal-title"><?php the_title(); ?></h2>
					<span class="journal-meta"><?php echo esc_html( get_the_date() ); ?></span>
				</a>
			<?php endwhile; ?>
		</div>
		<div class="pagination"><?php echo wp_kses_post( paginate_links() ); ?></div>
	</div>
</section>

<?php
get_footer();

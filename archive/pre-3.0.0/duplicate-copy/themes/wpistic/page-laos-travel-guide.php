<?php
/**
 * Template Name: Laos Travel Guide
 * Description: Editorial Laos planning guides and field notes.
 * @package WPistic
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$guide_query = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 12, 'paged' => max( 1, get_query_var( 'paged' ) ), 'ignore_sticky_posts' => false ) );
?>
<section class="page-hero"><div class="wrap"><?php wpistic_breadcrumbs(); ?><span class="eyebrow"><?php esc_html_e( 'The Field Notes', 'wpistic' ); ?></span><h1><?php esc_html_e( 'Laos travel guide and journal.', 'wpistic' ); ?></h1><p class="lede"><?php esc_html_e( 'Practical planning, culture, food and local stories written to help you choose the right Laos journey.', 'wpistic' ); ?></p></div></section>
<section class="section"><div class="wrap">
	<?php $guide_categories = get_categories( array( 'hide_empty' => true, 'number' => 8 ) ); if ( $guide_categories ) : ?><nav class="chip-row journal-cats" aria-label="<?php esc_attr_e( 'Travel guide categories', 'wpistic' ); ?>"><?php foreach ( $guide_categories as $guide_category ) : ?><a class="chip" href="<?php echo esc_url( get_category_link( $guide_category ) ); ?>"><?php echo esc_html( $guide_category->name ); ?></a><?php endforeach; ?></nav><?php endif; ?>
	<div class="journal-grid"><?php if ( $guide_query->have_posts() ) : while ( $guide_query->have_posts() ) : $guide_query->the_post(); $cats = get_the_category(); ?><a class="journal-card reveal" href="<?php the_permalink(); ?>"><div class="journal-thumb"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'bt-teaser', array( 'loading' => 'lazy' ) ); } ?></div><span class="journal-cat"><?php echo esc_html( $cats ? $cats[0]->name : __( 'Laos Guide', 'wpistic' ) ); ?></span><h2 class="journal-title"><?php the_title(); ?></h2><span class="journal-meta"><?php echo esc_html( get_the_date() ); ?></span></a><?php endwhile; else : ?><div class="no-results"><h2><?php esc_html_e( 'No travel guides are published yet.', 'wpistic' ); ?></h2><p><?php esc_html_e( 'Publish verified Laos planning articles before launch; no placeholder posts are displayed.', 'wpistic' ); ?></p></div><?php endif; ?></div>
	<?php echo wp_kses_post( paginate_links( array( 'total' => $guide_query->max_num_pages, 'current' => max( 1, get_query_var( 'paged' ) ) ) ) ); wp_reset_postdata(); ?>
</div></section>
<?php wpistic_build_my_trip_cta(); get_footer();

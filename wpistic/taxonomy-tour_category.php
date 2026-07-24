<?php
/** Tour-category landing page. @package WPistic */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header(); $term = get_queried_object();
?>
<section class="page-hero"><div class="wrap"><?php wpistic_breadcrumbs(); ?><span class="eyebrow"><?php esc_html_e( 'Tour collection', 'wpistic' ); ?></span><h1><?php single_term_title(); ?></h1><?php if ( term_description() ) : ?><div class="lede"><?php echo wp_kses_post( term_description() ); ?></div><?php endif; ?></div></section>
<section class="section"><div class="wrap"><div class="section-head"><span class="eyebrow"><?php esc_html_e( 'Private Laos journeys', 'wpistic' ); ?></span><h2 class="sec-h2"><?php esc_html_e( 'Tours in this collection.', 'wpistic' ); ?></h2></div><div class="tour-grid"><?php if ( have_posts() ) : while ( have_posts() ) : the_post(); $regions = wp_get_post_terms( get_the_ID(), 'region', array( 'fields' => 'names' ) ); wpistic_tour_card( array( 'name' => get_the_title(), 'url' => get_permalink(), 'region' => implode( ', ', $regions ), 'meta' => get_post_meta( get_the_ID(), 'wpistic_duration', true ), 'blurb' => get_the_excerpt(), 'price' => wpistic_price_line( get_post_meta( get_the_ID(), 'wpistic_from_price', true ) ), 'image_id' => get_post_thumbnail_id() ) ); endwhile; else : ?><p><?php esc_html_e( 'No complete, published tours are assigned to this collection.', 'wpistic' ); ?></p><?php endif; ?></div><?php the_posts_pagination(); ?></div></section>
<?php wpistic_build_my_trip_cta( $term->name ?? '' ); get_footer();

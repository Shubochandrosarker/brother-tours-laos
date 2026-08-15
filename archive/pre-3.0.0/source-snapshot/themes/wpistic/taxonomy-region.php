<?php
/** Regional destination and tour landing page. @package WPistic */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header(); $term = get_queried_object();
?>
<section class="page-hero"><div class="wrap"><?php wpistic_breadcrumbs(); ?><span class="eyebrow"><?php esc_html_e( 'Region', 'wpistic' ); ?></span><h1><?php single_term_title(); ?></h1><?php if ( term_description() ) : ?><div class="lede"><?php echo wp_kses_post( term_description() ); ?></div><?php endif; ?></div></section>
<section class="section"><div class="wrap"><div class="section-head"><span class="eyebrow"><?php esc_html_e( 'Explore', 'wpistic' ); ?></span><h2 class="sec-h2"><?php esc_html_e( 'Places and journeys in this region.', 'wpistic' ); ?></h2></div><div class="tour-grid"><?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?><article class="service-card reveal"><a href="<?php the_permalink(); ?>"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'bt-card', array( 'loading' => 'lazy' ) ); } ?><div class="service-body"><h2 class="service-title"><?php the_title(); ?></h2><p><?php echo esc_html( get_the_excerpt() ); ?></p><span class="btn-link"><?php esc_html_e( 'Explore', 'wpistic' ); ?> &rarr;</span></div></a></article><?php endwhile; else : ?><p><?php esc_html_e( 'No complete content is assigned to this region yet.', 'wpistic' ); ?></p><?php endif; ?></div><?php the_posts_pagination(); ?></div></section>
<?php wpistic_build_my_trip_cta( $term->name ?? '' ); get_footer();

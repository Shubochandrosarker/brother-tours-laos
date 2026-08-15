<?php
/** Destination discovery archive. @package WPistic */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'archive' ) ) {
	get_footer();
	return;
}
?>
<section class="page-hero"><div class="wrap"><?php wpistic_breadcrumbs(); ?><span class="eyebrow"><?php esc_html_e( 'Laos destinations', 'wpistic' ); ?></span><h1><?php esc_html_e( 'Destinations across northern, central and southern Laos.', 'wpistic' ); ?></h1><p class="lede"><?php esc_html_e( 'Choose a place to understand its character, practical details and the private tours that visit it.', 'wpistic' ); ?></p></div></section>
<section class="section"><div class="wrap"><nav class="active-filters" aria-label="<?php esc_attr_e( 'Laos regions', 'wpistic' ); ?>"><?php $regions = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => true ) ); if ( ! is_wp_error( $regions ) ) { foreach ( $regions as $region ) { echo '<a class="af" href="' . esc_url( get_term_link( $region ) ) . '">' . esc_html( $region->name ) . '</a>'; } } ?></nav>
<div class="dest-grid"><?php if ( have_posts() ) : while ( have_posts() ) : the_post(); $names = wp_get_post_terms( get_the_ID(), 'region', array( 'fields' => 'names' ) ); ?><article class="dest-card reveal"><a href="<?php the_permalink(); ?>"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'bt-card', array( 'loading' => 'lazy' ) ); } ?><div class="dest-card__body"><span class="eyebrow"><?php echo esc_html( $names[0] ?? __( 'Laos', 'wpistic' ) ); ?></span><h2><?php the_title(); ?></h2><p><?php echo esc_html( get_the_excerpt() ); ?></p><span class="btn-link"><?php esc_html_e( 'See destination', 'wpistic' ); ?> &rarr;</span></div></a></article><?php endwhile; else : ?><div class="no-results"><h2><?php esc_html_e( 'No destinations are published yet.', 'wpistic' ); ?></h2><p><?php esc_html_e( 'Run the verified catalog import before launch.', 'wpistic' ); ?></p></div><?php endif; ?></div><?php the_posts_pagination(); ?></div></section>
<?php wpistic_build_my_trip_cta(); get_footer();

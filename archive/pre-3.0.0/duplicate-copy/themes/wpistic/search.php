<?php
/** Accessible site search results. @package WPistic */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<section class="page-hero"><div class="wrap"><?php wpistic_breadcrumbs(); ?><span class="eyebrow"><?php esc_html_e( 'Search Brother Tours', 'wpistic' ); ?></span><h1><?php printf( esc_html__( 'Results for “%s”', 'wpistic' ), esc_html( get_search_query() ) ); ?></h1><?php get_search_form(); ?></div></section>
<section class="section"><div class="wrap"><div class="journal-grid"><?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?><article class="journal-card reveal"><a href="<?php the_permalink(); ?>"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'bt-card', array( 'loading' => 'lazy' ) ); } ?><div class="journal-card__body"><h2><?php the_title(); ?></h2><p><?php echo esc_html( get_the_excerpt() ); ?></p><span class="btn-link"><?php esc_html_e( 'Read more', 'wpistic' ); ?> &rarr;</span></div></a></article><?php endwhile; else : ?><div class="no-results"><h2><?php esc_html_e( 'Nothing matched that search.', 'wpistic' ); ?></h2><p><?php esc_html_e( 'Try a destination, tour style or Laos travel topic.', 'wpistic' ); ?></p><?php get_search_form(); ?></div><?php endif; ?></div><?php the_posts_pagination(); ?></div></section>
<?php get_footer();

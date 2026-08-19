<?php
/**
 * Template Name: Tour Landing
 *
 * Curated tour-collection landing page (Luxury, Honeymoon, Adventure, Highlight,
 * Founder-Hosted). Built 15 Aug 2026 — the meta contract existed but no template
 * consumed it, so these pages fell back to page.php and rendered no tours.
 *
 * Layout: hero (eyebrow + accented H1 + excerpt lede) → tour grid → long-form prose
 * → CTA. Products first, supporting copy below, which is what this page type needs.
 *
 * Meta contract:
 *   _wpistic_p_filter_tax   taxonomy slug (e.g. travel_style)
 *   _wpistic_p_filter_term  term slug within it
 *   _wpistic_p_eyebrow      label above the H1
 *   _wpistic_p_heading      H1; {{double braces}} render as the accent
 *
 * @package BrotherTours
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$page_id = get_the_ID();
$tax     = get_post_meta( $page_id, '_wpistic_p_filter_tax', true );
$term_sl = get_post_meta( $page_id, '_wpistic_p_filter_term', true );
$eyebrow = get_post_meta( $page_id, '_wpistic_p_eyebrow', true );
$heading = get_post_meta( $page_id, '_wpistic_p_heading', true );

$heading_html = $heading
	? str_replace( array( '&lt;em&gt;', '&lt;/em&gt;' ), array( '<em>', '</em>' ),
		preg_replace( '/\{\{(.+?)\}\}/s', '&lt;em&gt;$1&lt;/em&gt;', esc_html( $heading ) ) )
	: esc_html( get_the_title() );

$term = ( $tax && $term_sl && taxonomy_exists( $tax ) ) ? get_term_by( 'slug', $term_sl, $tax ) : null;
?>
<section class="page-hero">
	<div class="wrap">
		<?php if ( function_exists( 'wpistic_breadcrumbs' ) ) { wpistic_breadcrumbs(); } ?>
		<?php if ( $eyebrow ) : ?><span class="eyebrow"><?php echo esc_html( $eyebrow ); ?></span><?php endif; ?>
		<h1><?php echo wp_kses( $heading_html, array( 'em' => array() ) ); ?></h1>
		<?php
		$lede = get_the_excerpt( $page_id );
		if ( $lede ) {
			echo '<p class="lede">' . esc_html( $lede ) . '</p>';
		}
		?>
	</div>
</section>

<section class="section">
	<div class="wrap">
		<?php
		$tours = null;
		$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		if ( $term && ! is_wp_error( $term ) ) {
			$tours = new WP_Query( array(
				'post_type'           => 'wpistic_tour',
				'post_status'         => 'publish',
				'posts_per_page'      => 12,
				'paged'               => $paged,
				'ignore_sticky_posts' => true,
				'tax_query'           => array( array(
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => $term_sl,
				) ),
			) );
		}

		if ( $tours && $tours->have_posts() ) :
			?>
			<div class="section-head">
				<span class="eyebrow"><?php esc_html_e( 'Private Laos journeys', 'wpistic' ); ?></span>
				<h2 class="sec-h2"><?php esc_html_e( 'Journeys in this collection.', 'wpistic' ); ?></h2>
			</div>
			<div class="tour-grid">
				<?php
				while ( $tours->have_posts() ) :
					$tours->the_post();
					$regions = wp_get_post_terms( get_the_ID(), 'region', array( 'fields' => 'names' ) );
					if ( is_wp_error( $regions ) ) { $regions = array(); }
					wpistic_tour_card( array(
						'name'     => get_the_title(),
						'url'      => get_permalink(),
						'region'   => implode( ', ', $regions ),
						'meta'     => get_post_meta( get_the_ID(), 'wpistic_duration', true ),
						'blurb'    => get_the_excerpt(),
						'price'    => wpistic_price_line( get_post_meta( get_the_ID(), 'wpistic_from_price', true ) ),
						'image_id' => get_post_thumbnail_id(),
					) );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
			<?php
			$links = paginate_links( array( 'total' => $tours->max_num_pages, 'current' => $paged ) );
			if ( $links ) :
				echo '<nav class="pagination">' . wp_kses_post( $links ) . '</nav>';
			endif;
		else :
			?>
			<div class="section-head">
				<span class="eyebrow"><?php esc_html_e( 'Designed to order', 'wpistic' ); ?></span>
				<h2 class="sec-h2"><?php esc_html_e( 'Every journey here is built to your brief.', 'wpistic' ); ?></h2>
				<p class="lede"><?php esc_html_e( 'Tell us your dates, your pace and who is travelling. Our team in Vientiane will design this trip around you.', 'wpistic' ); ?></p>
				<p><a class="btn btn-solid" href="<?php echo esc_url( home_url( '/build-my-trip/' ) ); ?>"><?php esc_html_e( 'Build My Trip', 'wpistic' ); ?></a></p>
			</div>
			<?php
		endif;
		?>
	</div>
</section>

<?php
// Long-form copy below the grid.
while ( have_posts() ) :
	the_post();
	if ( trim( wp_strip_all_tags( get_the_content() ) ) !== '' ) :
		?>
		<section class="section section-sand">
			<div class="wrap">
				<div class="prose prose-narrow"><?php the_content(); ?></div>
			</div>
		</section>
		<?php
	endif;
endwhile;

if ( function_exists( 'wpistic_build_my_trip_cta' ) ) {
	wpistic_build_my_trip_cta( $term && ! is_wp_error( $term ) ? $term->name : get_the_title( $page_id ) );
}
get_footer();
<?php
/**
 * Journal index (the posts page) — PDF "Travel Guide / Blog Archive".
 *
 * Navy masthead + category chips + a featured article + the teaser grid.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$wpistic_hero = wpistic_demo_img( 'hero-laos' );
?>

<section class="page-hero">
	<?php if ( $wpistic_hero ) : ?>
		<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
	<?php endif; ?>
	<div class="wrap">
		<?php wpistic_breadcrumbs(); ?>
		<span class="eyebrow">The Field Notes</span>
		<h1>Laos travel <em>guide</em> &amp; journal.</h1>
		<p class="lede">Guides, culture, food, and local stories — written by the people who live here.</p>
	</div>
</section>

<section class="section">
	<div class="wrap">
		<?php
		$wpistic_cats = get_categories( array( 'hide_empty' => true, 'number' => 8 ) );
		if ( $wpistic_cats ) :
			?>
			<div class="chip-row journal-cats">
				<a class="chip active" href="<?php echo esc_url( home_url( '/laos-travel-guide/' ) ); ?>"><?php esc_html_e( 'All', 'wpistic' ); ?></a>
				<?php foreach ( $wpistic_cats as $wpistic_cat ) : ?>
					<a class="chip" href="<?php echo esc_url( get_category_link( $wpistic_cat ) ); ?>"><?php echo esc_html( $wpistic_cat->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		if ( have_posts() ) :
			the_post();
			// Featured (first) article.
			$wpistic_fcats = get_the_category();
			$wpistic_fimg  = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'bt-hero' ) : wpistic_demo_img( 'journal-1' );
			?>
			<a class="journal-featured reveal" href="<?php the_permalink(); ?>">
				<span class="jf-media"><?php if ( $wpistic_fimg ) { printf( '<img src="%s" alt="" loading="lazy">', esc_url( $wpistic_fimg ) ); } ?></span>
				<span class="jf-body">
					<span class="jf-cat"><?php echo esc_html( $wpistic_fcats ? __( 'Featured · ', 'wpistic' ) . $wpistic_fcats[0]->name : __( 'Featured', 'wpistic' ) ); ?></span>
					<h2 class="jf-title"><?php the_title(); ?></h2>
					<?php if ( has_excerpt() ) : ?>
						<span class="jf-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></span>
					<?php endif; ?>
					<span class="jf-meta"><?php echo esc_html( get_the_date() ); ?> · <?php echo esc_html( get_the_author() ); ?></span>
					<span class="btn-link"><?php esc_html_e( 'Read the essay', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></span>
				</span>
			</a>

			<div class="journal-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					$wpistic_pcats = get_the_category();
					?>
					<a class="journal-card reveal" href="<?php the_permalink(); ?>">
						<div class="journal-thumb">
							<?php
							if ( has_post_thumbnail() ) {
								the_post_thumbnail( 'bt-teaser', array( 'loading' => 'lazy' ) );
							}
							?>
						</div>
						<span class="journal-cat"><?php echo esc_html( $wpistic_pcats ? $wpistic_pcats[0]->name : __( 'Journal', 'wpistic' ) ); ?></span>
						<h2 class="journal-title"><?php the_title(); ?></h2>
						<span class="journal-meta"><?php echo esc_html( get_the_date() ); ?></span>
					</a>
				<?php endwhile; ?>
			</div>
			<div class="pagination"><?php echo wp_kses_post( paginate_links() ); ?></div>
		<?php else : ?>
			<div class="journal-grid">
				<?php
				$wpistic_jimg = array( 'journal-1', 'journal-2', 'tour-5' );
				foreach ( wpistic_sample_journal() as $wpistic_jx => $wpistic_j ) :
					$wpistic_jsrc = wpistic_demo_img( $wpistic_jimg[ $wpistic_jx % count( $wpistic_jimg ) ] );
					?>
					<div class="journal-card">
						<div class="journal-thumb"><?php if ( $wpistic_jsrc ) { printf( '<img src="%s" alt="" loading="lazy">', esc_url( $wpistic_jsrc ) ); } ?></div>
						<span class="journal-cat"><?php echo esc_html( $wpistic_j['cat'] ); ?></span>
						<h2 class="journal-title"><?php echo esc_html( $wpistic_j['title'] ); ?></h2>
						<span class="journal-meta"><?php echo esc_html( $wpistic_j['meta'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();

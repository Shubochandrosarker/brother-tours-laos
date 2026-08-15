<?php
/**
 * Template Name: Services Landing
 *
 * PDF "One company, six services." A navy masthead + a grid of service cards.
 * Brand-safe copy; imagery from the demo set or the
 * page's own Featured Image, all swappable in the Media Library.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$wpistic_hero = has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'bt-hero' ) : wpistic_demo_img( 'hero-laos' );

	$wpistic_services = array(
		array( 'slug' => 'laos-visa', 'eyebrow' => 'Visa & entry', 'title' => 'Laos Visa Service', 'blurb' => 'e-Visa and on-arrival paperwork, with the right border crossing for your passport.', 'img' => 'dest-vientiane' ),
		array( 'slug' => 'private-guide', 'eyebrow' => 'Hosting', 'title' => 'Private Tour Guide', 'blurb' => 'Licensed Lao hosts, matched to your trip and with you the whole way.', 'img' => 'tour-3' ),
		array( 'slug' => 'driver-transport', 'eyebrow' => 'On the ground', 'title' => 'Driver & Transport', 'blurb' => 'Private car, van, slow boat — never shared, always your pace.', 'img' => 'service-driver' ),
		array( 'slug' => 'accommodation', 'eyebrow' => 'Where you sleep', 'title' => 'Accommodation', 'blurb' => 'Named hotels and river lodges, seen in person by our team.', 'img' => 'service-stay' ),
		array( 'slug' => 'tickets', 'eyebrow' => 'Getting around', 'title' => 'Train & Air Tickets', 'blurb' => 'The Laos–China railway and domestic flights, booked and handled.', 'img' => 'service-railway' ),
		array( 'slug' => 'rentals', 'eyebrow' => 'Roam a little', 'title' => 'Rentals', 'blurb' => 'Bicycles, kayaks, and more — arranged where you stay.', 'img' => 'tour-2' ),
	);
	?>
	<section class="page-hero">
		<?php if ( $wpistic_hero ) : ?>
			<div class="page-hero__bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero ); ?>" alt="" fetchpriority="high"></div>
		<?php endif; ?>
		<div class="wrap">
			<?php wpistic_breadcrumbs(); ?>
			<span class="eyebrow">Services</span>
			<h1><?php echo esc_html( get_the_title() ); ?></h1>
			<p class="lede"><?php echo esc_html( has_excerpt() ? get_the_excerpt() : __( 'Everything your Laos trip needs, under one point of contact.', 'wpistic' ) ); ?></p>
		</div>
	</section>

	<?php if ( get_the_content() ) : ?>
		<section class="section">
			<div class="wrap"><div class="prose u-narrow"><?php the_content(); ?></div></div>
		</section>
	<?php endif; ?>

	<section class="section<?php echo get_the_content() ? ' section-sand' : ''; ?>">
		<div class="wrap">
			<div class="services-grid">
				<?php foreach ( $wpistic_services as $wpistic_s ) : ?>
					<article class="service-card reveal">
						<a class="service-media" href="<?php echo esc_url( home_url( '/services/' . $wpistic_s['slug'] . '/' ) ); ?>" aria-label="<?php echo esc_attr( $wpistic_s['title'] ); ?>">
							<?php
							$wpistic_img = wpistic_demo_img( $wpistic_s['img'] );
							if ( $wpistic_img ) {
								printf( '<img src="%s" alt="" loading="lazy">', esc_url( $wpistic_img ) );
							}
							?>
						</a>
						<div class="service-body">
							<p class="service-eyebrow"><?php echo esc_html( $wpistic_s['eyebrow'] ); ?></p>
							<h2 class="service-title"><?php echo esc_html( $wpistic_s['title'] ); ?></h2>
							<p class="service-blurb"><?php echo esc_html( $wpistic_s['blurb'] ); ?></p>
							<a class="btn-link" href="<?php echo esc_url( home_url( '/services/' . $wpistic_s['slug'] . '/' ) ); ?>"><?php esc_html_e( 'Learn more', 'wpistic' ); ?><span class="screen-reader-text"> <?php echo esc_html( $wpistic_s['title'] ); ?></span> <span class="arr" aria-hidden="true">→</span></a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php wpistic_build_my_trip_cta(); ?>
	<?php
endwhile;

get_footer();

<?php
/**
 * Homepage — V1 "Editorial Cinematic" (PDF design dossier).
 *
 * Design follows the 2026 dossier; all copy is the brand-safe store content
 * (locked phrases verbatim, per-tour scarcity, no fabricated review numbers).
 * The client switches V1/V2/V3 in Theme Options → Brand & Layout.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Alternate homepage layouts load from parts/ when chosen in Theme Options.
$wpistic_variant = sanitize_key( wpistic_opt( 'home_variant', 'v1' ) );

// Admins can preview any layout without changing the saved setting: /?bt_variant=v2
// Read-only display switch, gated by capability — no state change, so no nonce needed.
if ( current_user_can( 'edit_theme_options' ) && isset( $_GET['bt_variant'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$wpistic_preview = sanitize_key( wp_unslash( $_GET['bt_variant'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( in_array( $wpistic_preview, array( 'v1', 'v2', 'v3' ), true ) ) {
		$wpistic_variant = $wpistic_preview;
	}
}

if ( 'v1' !== $wpistic_variant && locate_template( 'parts/home-' . $wpistic_variant . '.php' ) ) {
	// V2 opens on a light editorial hero, so the fixed header needs the solid (dark-text)
	// treatment; the hero's own top padding clears it, so suppress the head spacer too.
	if ( 'v2' === $wpistic_variant ) {
		add_filter( 'wpistic_has_dark_hero', '__return_false' );
		add_filter(
			'body_class',
			static function ( $classes ) {
				$classes[] = 'home-v2';
				return $classes;
			}
		);
	}
	get_header();
	get_template_part( 'parts/home', $wpistic_variant );
	get_footer();
	return;
}

get_header();

$wpistic_hero_id  = absint( wpistic_opt( 'home_hero_image', 0 ) );
$wpistic_hero_src = $wpistic_hero_id ? wp_get_attachment_image_url( $wpistic_hero_id, 'bt-hero' ) : wpistic_demo_img( 'hero-laos' );
$wpistic_host_id  = absint( wpistic_opt( 'hero_host_image', 0 ) );
$wpistic_host_src = $wpistic_host_id ? wp_get_attachment_image_url( $wpistic_host_id, 'bt-portrait' ) : wpistic_demo_img( 'ken-portrait' );
?>

<!-- S1 · HERO -->
<section class="hero">
	<?php if ( $wpistic_hero_src ) : ?>
		<div class="hero-bg" aria-hidden="true"><img src="<?php echo esc_url( $wpistic_hero_src ); ?>" alt="" fetchpriority="high"></div>
	<?php endif; ?>
	<div class="wrap">
		<div class="hero-content">
			<span class="eyebrow on-dark">Vientiane · Since 2018</span>
			<h1 class="hero-h1">Experience Laos Through the People Who Call It <em>Home</em>.</h1>
			<p class="hero-tagline">Lao-led. Globally understood.</p>
			<p class="hero-sub">Private, hosted journeys designed around you — built and led from the ground in Laos.</p>
			<div class="hero-btns">
				<a class="btn btn-solid" href="<?php echo esc_url( wpistic_cta_url() ); ?>"><?php echo esc_html( wpistic_cta_label() ); ?> <span class="arr" aria-hidden="true">→</span></a>
				<a class="btn btn-ghost on-dark" href="<?php echo esc_url( home_url( '/tours/' ) ); ?>">See Our Journeys</a>
			</div>
			<p class="hero-trust">
				<span>Born in Laos</span>
				<span>Licensed since 2010</span>
				<span>Private custom journeys</span>
			</p>
		</div>
	</div>

	<aside class="hero-host" aria-label="<?php esc_attr_e( 'Your host', 'wpistic' ); ?>">
		<div class="hero-host-img">
			<?php if ( $wpistic_host_src ) : ?>
				<img src="<?php echo esc_url( $wpistic_host_src ); ?>" alt="<?php echo esc_attr( wpistic_opt( 'hero_host_name', 'Ken FJ Her' ) ); ?>" loading="lazy">
			<?php endif; ?>
			<span class="hero-host-tag"><?php esc_html_e( 'Your host', 'wpistic' ); ?></span>
		</div>
		<div class="hero-host-body">
			<p class="hero-host-name"><?php echo esc_html( wpistic_opt( 'hero_host_name', 'Ken FJ Her' ) ); ?></p>
			<p class="hero-host-role"><?php echo esc_html( wpistic_opt( 'hero_host_role', 'Founder · Lead Host' ) ); ?></p>
			<p class="hero-host-quote">“<?php echo esc_html( wpistic_opt( 'hero_host_quote', 'I host the Laos I know — road by road, season by season.' ) ); ?>”</p>
		</div>
	</aside>

	<div class="hero-scroll" aria-hidden="true">
		<span class="hero-scroll-line"></span>
		<span class="hero-scroll-label">Scroll</span>
	</div>
</section>

<!-- S1b · STAT BAND (brand-safe facts — no fabricated counts/ratings) -->
<section class="stat-band">
	<div class="wrap">
		<?php
		$wpistic_facts = array(
			array( 'num' => '2010', 'label' => 'Licensed Lao guide' ),
			array( 'num' => '2018', 'label' => 'Brother Tours founded' ),
			array( 'num' => 'Private', 'label' => 'Every journey hosted' ),
			array( 'num' => 'Lao-led', 'label' => 'Owned & guided here' ),
			array( 'num' => 'Vientiane', 'label' => 'Where we are based' ),
		);
		foreach ( $wpistic_facts as $wpistic_fact ) :
			?>
			<div class="stat">
				<span class="stat-num"><?php echo esc_html( $wpistic_fact['num'] ); ?></span>
				<span class="stat-label"><?php echo esc_html( $wpistic_fact['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<!-- S2 · FOUNDER -->
<section class="section reveal">
	<div class="wrap">
		<div class="founder-grid">
			<div class="founder-img-wrap">
				<span class="founder-corner-tl" aria-hidden="true"></span>
				<span class="founder-corner-br" aria-hidden="true"></span>
				<div class="founder-portrait">
					<?php
					$wpistic_ken = wpistic_demo_img( 'ken-portrait' );
					if ( $wpistic_ken ) :
						?>
						<img src="<?php echo esc_url( $wpistic_ken ); ?>" alt="Ken FJ Her, Founder of Brother Tours" loading="lazy">
					<?php else : ?>
						<div class="founder-portrait-fill"><span class="founder-initials">BT</span></div>
					<?php endif; ?>
					<div class="founder-cap">
						<p class="founder-cap-name">Ken FJ Her</p>
						<p class="founder-cap-role">Founder · Licensed Lao National Tour Guide</p>
					</div>
				</div>
			</div>
			<div class="founder-copy">
				<span class="eyebrow">The Founder</span>
				<p class="founder-pull">Not a guide. A <em>host</em>.</p>
				<p class="founder-body">Ken was born and raised in Laos. He came to this work the long way — first as a guide, walking the country road by road and season by season.</p>
				<p class="founder-body">In 2010 he earned his National Tour Guide license. By 2018 he had founded Brother Tours, built on what those years taught him: that a country is its people first, and that a guest is owed more than a route.</p>
				<p class="founder-cred">Founder-set. Team-delivered. Licensed Lao National Tour Guide since 2010 · Brother Tours founded 2018.</p>
				<p class="founder-signature">Born Here. Guide Here.</p>
				<p><a class="btn-link" href="<?php echo esc_url( home_url( '/about/' ) ); ?>">Read the full story <span class="arr" aria-hidden="true">→</span></a></p>
			</div>
		</div>
	</div>
</section>

<!-- S3 · FEATURED DESTINATIONS -->
<section class="section section-sand reveal">
	<div class="wrap">
		<div class="section-head">
			<span class="eyebrow">Where We Go</span>
			<h2 class="sec-h2">Six regions, one <em>country</em>.</h2>
			<p class="sec-lead">Laos past the photographed corners of it — the river villages, the plateau, the highland weaving houses.</p>
		</div>
		<div class="dest-grid">
			<?php
			foreach ( wpistic_home_destinations( 6 ) as $wpistic_dest ) :
				?>
				<a class="dest-card reveal" href="<?php echo esc_url( $wpistic_dest['url'] ); ?>">
					<?php
					if ( ! empty( $wpistic_dest['image_url'] ) ) {
						printf(
							'<img src="%1$s" alt="%2$s" loading="lazy" decoding="async">',
							esc_url( $wpistic_dest['image_url'] ),
							/* translators: %s: destination name. */
							esc_attr( sprintf( __( '%s, Laos', 'wpistic' ), $wpistic_dest['name'] ) )
						);
					}
					?>
					<span class="dest-tag"><?php echo esc_html( $wpistic_dest['tag'] ); ?></span>
					<span class="dest-name"><?php echo esc_html( $wpistic_dest['name'] ); ?></span>
					<span class="dest-desc"><?php echo esc_html( $wpistic_dest['desc'] ); ?></span>
					<span class="dest-link"><?php esc_html_e( 'See the region', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- S4 · SIGNATURE TOURS (The Collection) -->
<section class="section reveal">
	<div class="wrap">
		<div class="sec-head-row">
			<div class="section-head">
				<span class="eyebrow">Signature Journeys</span>
				<h2 class="sec-h2">Each journey runs a fixed number of times each <em>year</em>. By design.</h2>
				<p class="sec-lead">Small numbers, hosted personally, held to one standard.</p>
			</div>
			<a class="btn-link" href="<?php echo esc_url( home_url( '/tours/' ) ); ?>"><?php esc_html_e( 'View all journeys', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></a>
		</div>
		<div class="tour-grid">
			<?php
			foreach ( wpistic_home_tours( 6 ) as $wpistic_tour ) :
				wpistic_tour_card( $wpistic_tour );
			endforeach;
			?>
		</div>
	</div>
</section>

<!-- S5 · WHY BROTHER TOURS -->
<section class="section section-sand reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">Why Brother Tours</span>
			<h2 class="sec-h2">Human first. Experience second. Destination <em>third</em>.</h2>
		</div>
		<div class="why-grid">
			<?php foreach ( wpistic_sample_philosophy() as $wpistic_p ) : ?>
				<div class="why-card reveal">
					<span class="why-card-ghost"><?php echo esc_html( $wpistic_p['n'] ); ?></span>
					<h3 class="why-card-title"><?php echo wp_kses( $wpistic_p['title'], array( 'em' => array() ) ); ?></h3>
					<p class="why-card-body"><?php echo esc_html( $wpistic_p['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="why-statement">We do not sell journeys. We share the country we were <em>born</em> in.</p>
	</div>
</section>

<!-- S6 · GUEST REVIEWS -->
<section class="section reveal">
	<div class="wrap">
		<div class="section-head center">
			<span class="eyebrow center">Guest Reviews</span>
			<h2 class="sec-h2">What our guests have <em>said</em>.</h2>
			<p class="sec-lead">Read guest notes on Google and TripAdvisor.</p>
		</div>
		<?php
		$wpistic_embed = function_exists( 'wpistic_reviews_embed' ) ? wpistic_reviews_embed() : '';
		if ( '' !== $wpistic_embed ) {
			echo '<div class="reviews-embed">' . wp_kses_post( $wpistic_embed ) . '</div>';
		}
		?>
		<div class="reviews-grid">
			<?php foreach ( wpistic_sample_reviews() as $wpistic_review ) : ?>
				<div class="review-card reveal">
					<p class="review-quote"><?php echo esc_html( $wpistic_review['body'] ); ?></p>
					<p class="review-attr"><?php echo esc_html( $wpistic_review['attr'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php wpistic_review_links(); ?>
	</div>
</section>

<!-- S7 · LATEST FROM THE JOURNAL -->
<section class="section section-sand reveal">
	<div class="wrap">
		<div class="sec-head-row">
			<div class="section-head">
				<span class="eyebrow">The Journal</span>
				<h2 class="sec-h2">Latest from the <em>Journal</em>.</h2>
			</div>
			<a class="btn-link" href="<?php echo esc_url( home_url( '/travel-guide/' ) ); ?>"><?php esc_html_e( 'All field notes', 'wpistic' ); ?> <span class="arr" aria-hidden="true">→</span></a>
		</div>
		<div class="journal-grid">
			<?php
			$wpistic_recent = get_posts( array( 'numberposts' => 3 ) );
			if ( $wpistic_recent ) :
				foreach ( $wpistic_recent as $wpistic_post ) :
					$wpistic_cats = get_the_category( $wpistic_post->ID );
					?>
					<a class="journal-card reveal" href="<?php echo esc_url( get_permalink( $wpistic_post ) ); ?>">
						<div class="journal-thumb">
							<?php
							if ( has_post_thumbnail( $wpistic_post ) ) {
								echo get_the_post_thumbnail( $wpistic_post, 'bt-teaser', array( 'loading' => 'lazy' ) );
							}
							?>
						</div>
						<span class="journal-cat"><?php echo esc_html( $wpistic_cats ? $wpistic_cats[0]->name : __( 'Journal', 'wpistic' ) ); ?></span>
						<h3 class="journal-title"><?php echo esc_html( get_the_title( $wpistic_post ) ); ?></h3>
						<span class="journal-meta"><?php echo esc_html( get_the_date( '', $wpistic_post ) ); ?></span>
					</a>
					<?php
				endforeach;
			else :
				$wpistic_jimg = array( 'journal-1', 'journal-2', 'tour-5' );
				foreach ( wpistic_sample_journal() as $wpistic_jx => $wpistic_j ) :
					$wpistic_jsrc = wpistic_demo_img( $wpistic_jimg[ $wpistic_jx % count( $wpistic_jimg ) ] );
					?>
					<a class="journal-card reveal" href="<?php echo esc_url( home_url( '/travel-guide/' ) ); ?>">
						<div class="journal-thumb"><?php if ( $wpistic_jsrc ) { printf( '<img src="%s" alt="" loading="lazy">', esc_url( $wpistic_jsrc ) ); } ?></div>
						<span class="journal-cat"><?php echo esc_html( $wpistic_j['cat'] ); ?></span>
						<h3 class="journal-title"><?php echo esc_html( $wpistic_j['title'] ); ?></h3>
						<span class="journal-meta"><?php echo esc_html( $wpistic_j['meta'] ); ?></span>
					</a>
					<?php
				endforeach;
			endif;
			?>
		</div>
	</div>
</section>

<!-- S8 · BUILD MY TRIP -->
<?php wpistic_build_my_trip_cta(); ?>

<?php
get_footer();

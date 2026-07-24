<?php
/**
 * Template tags: small presentation helpers used across templates.
 *
 * SEO/JSON-LD is owned by the SEOISTIC plugin; these helpers only render markup.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render an eyebrow label.
 *
 * @param string $label   Eyebrow text.
 * @param bool   $center  Center the eyebrow.
 * @return void
 */
function wpistic_eyebrow( $label, $center = false ) {
	printf(
		'<span class="eyebrow%1$s">%2$s</span>',
		$center ? ' center' : '',
		esc_html( $label )
	);
}

/**
 * Format the brand-compliant price line.
 *
 * @param string $from     Amount (already formatted, no currency). Empty = on request.
 * @param string $currency Currency code.
 * @return string
 */
function wpistic_price_line( $from = '', $currency = 'USD' ) {
	$from = trim( (string) $from );

	if ( '' === $from ) {
		return __( 'Price confirmed on request', 'wpistic' );
	}

	return sprintf(
		/* translators: 1: currency code, 2: amount. */
		__( 'From %1$s %2$s per person · price confirmed on request', 'wpistic' ),
		$currency,
		$from
	);
}

/**
 * Render a simple visual breadcrumb trail. (BreadcrumbList JSON-LD comes from SEOISTIC.)
 *
 * @return void
 */
function wpistic_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}

	$items = array( array( 'label' => __( 'Home', 'wpistic' ), 'url' => home_url( '/' ) ) );

	if ( is_singular() ) {
		$items[] = array( 'label' => get_the_title(), 'url' => '' );
	} elseif ( is_post_type_archive() ) {
		$items[] = array( 'label' => post_type_archive_title( '', false ), 'url' => '' );
	} elseif ( is_category() || is_archive() ) {
		$items[] = array( 'label' => wp_strip_all_tags( get_the_archive_title() ), 'url' => '' );
	} elseif ( is_search() ) {
		$items[] = array( 'label' => __( 'Search', 'wpistic' ), 'url' => '' );
	}

	echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'wpistic' ) . '"><div class="wrap">';

	$last = count( $items ) - 1;
	foreach ( $items as $index => $item ) {
		if ( $index === $last || empty( $item['url'] ) ) {
			echo '<span class="breadcrumbs__current">' . esc_html( $item['label'] ) . '</span>';
		} else {
			echo '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
			echo '<span class="breadcrumbs__sep" aria-hidden="true">/</span>';
		}
	}

	echo '</div></nav>';
}

/**
 * Render a single Build My Trip call-to-action section.
 *
 * @param string $context Optional tour/experience name pre-filled into the CTA link.
 * @return void
 */
function wpistic_build_my_trip_cta( $context = '' ) {
	$url = wpistic_cta_url();
	if ( '' !== $context ) {
		$url = add_query_arg( 'tour', rawurlencode( $context ), $url );
	}
	?>
	<section class="final reveal">
		<div class="wrap">
			<span class="eyebrow center">The Invitation</span>
			<h2 class="final-h2">Tell us what you're <em>imagining</em>.</h2>
			<p class="final-sub">We design your journey, your way. 24-hour reply from Vientiane — no pressure, no upselling.</p>
			<div class="final-btns">
				<a class="btn btn-solid" href="<?php echo esc_url( $url ); ?>">Build My Trip <span class="arr" aria-hidden="true">→</span></a>
				<a class="btn btn-ghost on-dark" href="<?php echo esc_url( wpistic_whatsapp_url() ); ?>" rel="noopener">WhatsApp Brother Tours</a>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Review profile URL for a network (set in the Customizer).
 *
 * @param string $network 'google' | 'tripadvisor'.
 * @return string
 */
function wpistic_review_url( $network ) {
	$map = array(
		'google'      => 'wpistic_google_reviews_url',
		'tripadvisor' => 'wpistic_tripadvisor_url',
	);
	return isset( $map[ $network ] ) ? (string) get_theme_mod( $map[ $network ], '' ) : '';
}

/**
 * Render configured external review profile links. Empty Customizer fields do
 * not produce dead buttons.
 *
 * @return void
 */
function wpistic_review_links() {
	$links = array(
		'google'      => array(
			'url'   => wpistic_review_url( 'google' ),
			'label' => __( 'Read our Google reviews', 'wpistic' ),
		),
		'tripadvisor' => array(
			'url'   => wpistic_review_url( 'tripadvisor' ),
			'label' => __( 'Read our TripAdvisor reviews', 'wpistic' ),
		),
	);

	$links = array_filter(
		$links,
		static function ( $link ) {
			return ! empty( $link['url'] );
		}
	);

	if ( empty( $links ) ) {
		return;
	}

	echo '<div class="reviews-links">';
	foreach ( $links as $link ) {
		echo '<a class="btn btn-ghost" href="' . esc_url( $link['url'] ) . '" rel="noopener">' . esc_html( $link['label'] ) . '</a>';
	}
	echo '</div>';
}

/**
 * Optional Google/TripAdvisor review widget embed (set in the Customizer).
 *
 * @return string
 */
function wpistic_reviews_embed() {
	return (string) get_theme_mod( 'wpistic_reviews_embed', '' );
}

/**
 * Theme option accessor — a thin wrapper over get_theme_mod with brand defaults.
 *
 * @param string $key     Option key (without the wpistic_ prefix is fine; we normalize).
 * @param mixed  $default Fallback value.
 * @return mixed
 */
function wpistic_opt( $key, $default = '' ) {
	$key = 0 === strpos( $key, 'wpistic_' ) ? $key : 'wpistic_' . $key;
	$val = get_theme_mod( $key, $default );
	return ( '' === $val || null === $val ) ? $default : $val;
}

/**
 * URL for a bundled demo image (assets/demo/{name}.webp), or '' if absent.
 * Used as a tasteful default so the design ships looking real; the client
 * overrides any image via Featured Image or Theme Options (Media Library).
 *
 * @param string $name Demo image slug (e.g. 'hero-laos', 'dest-luang-prabang', 'tour-2').
 * @return string
 */
function wpistic_demo_img( $name ) {
	$name = sanitize_file_name( $name );
	$rel  = '/assets/demo/' . $name . '.webp';
	return is_readable( WPISTIC_DIR . $rel ) ? WPISTIC_URI . $rel : '';
}

/**
 * Brand contact defaults (overridable in the Customizer).
 *
 * @param string $key whatsapp|whatsapp_display|email|phone|office|hours|locale.
 * @return string
 */
function wpistic_contact( $key ) {
	$defaults = array(
		'whatsapp'         => '8562055989894',
		'whatsapp_display' => '+856 20 55989894',
		'email'            => 'enquiry@brothertours.com',
		'phone'            => '+856 21 000 000',
		'office'           => 'Brother Tours Sole Co., Ltd. · Vientiane, Lao PDR',
		'hours'            => 'Mon–Sat · 8:00–19:00 ICT (UTC+7)',
		'locale'           => 'EN / USD',
	);
	$default = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	return (string) wpistic_opt( 'contact_' . $key, $default );
}

/**
 * WhatsApp click-to-chat URL.
 *
 * @param string $text Optional prefilled message.
 * @return string
 */
function wpistic_whatsapp_url( $text = '' ) {
	$num = preg_replace( '/\D+/', '', wpistic_contact( 'whatsapp' ) );
	$url = 'https://wa.me/' . $num;
	if ( '' !== $text ) {
		$url = add_query_arg( 'text', rawurlencode( $text ), $url );
	}
	return $url;
}

/**
 * Render the site logo. Defaults to the Cormorant text wordmark (which inherits
 * colour so it reads correctly over the dark hero and on light scrolled surfaces).
 * The client can set an optional transparent logo image in Theme Options.
 *
 * @param string $class Wrapper class.
 * @return void
 */
function wpistic_logo( $class = 'nav-logo' ) {
	$logo = absint( wpistic_opt( 'logo', 0 ) );
	$home = esc_url( home_url( '/' ) );
	$name = get_bloginfo( 'name' );

	if ( $logo ) {
		echo '<a class="' . esc_attr( $class ) . '" href="' . $home . '" rel="home">';
		echo wp_get_attachment_image( $logo, 'full', false, array( 'alt' => $name ) );
		echo '</a>';
		return;
	}

	if ( has_custom_logo() ) {
		echo '<div class="' . esc_attr( $class ) . '">';
		the_custom_logo();
		echo '</div>';
		return;
	}

	echo '<a class="' . esc_attr( $class ) . '" href="' . $home . '" rel="home">' . esc_html( $name ) . '</a>';
}

/**
 * Render the light/dark theme toggle button.
 *
 * @return void
 */
function wpistic_theme_toggle() {
	?>
	<button class="theme-toggle" type="button" aria-label="<?php esc_attr_e( 'Toggle light or dark mode', 'wpistic' ); ?>" aria-pressed="false" data-theme-toggle>
		<svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/></svg>
		<svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.3 6.3 0 0 0 10.5 10.5z"/></svg>
	</button>
	<?php
}

/**
 * Render the utility bar (top of header): locale, WhatsApp, primary CTA.
 *
 * @return void
 */
function wpistic_util_bar() {
	?>
	<div class="util-bar">
		<span class="util-locale"><?php echo esc_html( wpistic_contact( 'locale' ) ); ?></span>
		<a class="util-wa" href="<?php echo esc_url( wpistic_whatsapp_url() ); ?>" rel="noopener">WhatsApp</a>
		<a class="util-cta" href="<?php echo esc_url( wpistic_cta_url() ); ?>"><?php echo esc_html( wpistic_cta_label() ); ?></a>
	</div>
	<?php
}

/**
 * Render the fixed mobile bottom tab bar ("app view" feel).
 *
 * @return void
 */
function wpistic_app_bar() {
	$here = static function ( $cond ) {
		return $cond ? ' is-current' : '';
	};
	?>
	<nav class="app-bar" aria-label="<?php esc_attr_e( 'Quick navigation', 'wpistic' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="<?php echo esc_attr( trim( $here( is_front_page() ) ) ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 11l9-7 9 7M5 10v10h14V10"/></svg>
			<?php esc_html_e( 'Home', 'wpistic' ); ?>
		</a>
		<a href="<?php echo esc_url( home_url( '/tours/' ) ); ?>" class="<?php echo esc_attr( trim( $here( is_post_type_archive( 'wpistic_tour' ) || is_singular( 'wpistic_tour' ) ) ) ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="1.4" fill="currentColor"/><circle cx="14" cy="12" r="1.4" fill="currentColor"/><circle cx="10" cy="18" r="1.4" fill="currentColor"/></svg>
			<?php esc_html_e( 'Tours', 'wpistic' ); ?>
		</a>
		<a href="<?php echo esc_url( wpistic_cta_url() ); ?>" class="app-cta">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
			<?php esc_html_e( 'Build', 'wpistic' ); ?>
		</a>
		<a href="<?php echo esc_url( wpistic_whatsapp_url() ); ?>" rel="noopener">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 21l2.2-5.2A8.5 8.5 0 1 1 21 11.5z"/></svg>
			<?php esc_html_e( 'Chat', 'wpistic' ); ?>
		</a>
	</nav>
	<?php
}

/**
 * Render an editorial tour card. Accepts real Tour Manager data or sample-data arrays.
 *
 * @param array $args { name(html), url, region(eyebrow), meta, badge, blurb, tags(array),
 *                      price(html line), image_id|image_url, fallback('','clay','navy') }.
 * @return void
 */
function wpistic_tour_card( $args = array() ) {
	$a = wp_parse_args(
		$args,
		array(
			'name'     => '',
			'url'      => '#',
			'region'   => '',
			'meta'     => '',
			'badge'    => '',
			'blurb'    => '',
			'tags'     => array(),
			'price'    => wpistic_price_line( '' ),
			'image_id' => 0,
			'image_url' => '',
			'fallback' => '',
		)
	);
	?>
	<article class="tour-card reveal">
		<a class="tc-media" href="<?php echo esc_url( $a['url'] ); ?>" aria-label="<?php echo esc_attr( wp_strip_all_tags( $a['name'] ) ); ?>">
			<?php
			if ( $a['image_id'] ) {
				echo wp_get_attachment_image( absint( $a['image_id'] ), 'bt-card', false, array( 'loading' => 'lazy' ) );
			} elseif ( $a['image_url'] ) {
				printf( '<img src="%s" alt="" loading="lazy">', esc_url( $a['image_url'] ) );
			} else {
				printf( '<span class="tc-media-fallback %s" aria-hidden="true"></span>', esc_attr( $a['fallback'] ) );
			}
			if ( $a['region'] ) {
				echo '<span class="tc-region">' . esc_html( $a['region'] ) . '</span>';
			}
			if ( $a['badge'] ) {
				echo '<span class="tc-badge">' . esc_html( $a['badge'] ) . '</span>';
			}
			?>
		</a>
		<div class="tc-body">
			<h3 class="tc-title"><?php echo wp_kses( $a['name'], array( 'em' => array() ) ); ?></h3>
			<?php if ( $a['meta'] ) : ?>
				<p class="tc-meta"><?php echo esc_html( $a['meta'] ); ?></p>
			<?php endif; ?>
			<?php if ( $a['blurb'] ) : ?>
				<p class="tc-blurb"><?php echo esc_html( $a['blurb'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $a['tags'] ) ) : ?>
				<div class="tc-tags">
					<?php foreach ( (array) $a['tags'] as $tag ) : ?>
						<span class="tag"><?php echo esc_html( $tag ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="tc-foot">
				<span class="tc-price">
					<span class="note"><?php echo esc_html( $a['price'] ); ?></span>
				</span>
				<span class="tc-actions">
					<a class="btn-link" href="<?php echo esc_url( $a['url'] ); ?>"><?php esc_html_e( 'Details', 'wpistic' ); ?><span class="screen-reader-text"> <?php echo esc_html( wp_strip_all_tags( $a['name'] ) ); ?></span> <span class="arr" aria-hidden="true">→</span></a>
				</span>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Fallback primary menu (used until the client assigns a menu to the Primary location).
 *
 * @return void
 */
function wpistic_primary_menu_fallback() {
	/**
	 * Filter the fallback navigation shown when no `primary` menu is assigned.
	 *
	 * A child theme with a locked information architecture -- Brother Tours has
	 * one -- can supply its own path => label map here, so a site that has not
	 * yet built a menu in the admin still renders the intended navigation.
	 *
	 * @param array<string,string> $items Path => label.
	 */
	$items = (array) apply_filters(
		'wpistic/primary_menu_fallback',
		array(
			'/destinations/'  => __( 'Destinations', 'wpistic' ),
			'/tours/'         => __( 'Tours', 'wpistic' ),
			'/about/'         => __( 'About', 'wpistic' ),
			'/laos-travel-guide/' => __( 'Travel Guide', 'wpistic' ),
			'/contact/'       => __( 'Contact', 'wpistic' ),
		)
	);
	echo '<ul class="nav-links">';
	foreach ( $items as $path => $label ) {
		printf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( home_url( $path ) ),
			esc_html( $label )
		);
	}
	echo '</ul>';
}

/** Render useful footer links until custom WordPress menus are assigned. */
function wpistic_footer_menu_fallback( $args ) {
	$location = isset( $args->theme_location ) ? $args->theme_location : '';
	$menus = array(
		'footer-journeys' => array( '/tours/' => __( 'All Tours', 'wpistic' ), '/tour-category/signature-tours/' => __( 'Signature Tours', 'wpistic' ), '/tour-category/one-day-tours/' => __( 'One-Day Tours', 'wpistic' ), '/tour-category/adventure-tours/' => __( 'Adventure Tours', 'wpistic' ), '/plan-my-laos-trip/' => __( 'Custom Tours', 'wpistic' ) ),
		'footer-destinations' => array( '/destinations/' => __( 'All Destinations', 'wpistic' ), '/region/northern-laos/' => __( 'Northern Laos', 'wpistic' ), '/region/central-laos/' => __( 'Central Laos', 'wpistic' ), '/region/southern-laos/' => __( 'Southern Laos', 'wpistic' ) ),
		'footer-company' => array( '/about/' => __( 'About', 'wpistic' ), '/laos-travel-guide/' => __( 'Travel Guide', 'wpistic' ), '/contact/' => __( 'Contact', 'wpistic' ), '/plan-my-laos-trip/' => __( 'Plan My Trip', 'wpistic' ) ),
		'footer-practical' => array( '/booking-conditions/' => __( 'Booking Conditions', 'wpistic' ), '/cancellation-policy/' => __( 'Cancellation Policy', 'wpistic' ), '/privacy-policy/' => __( 'Privacy Policy', 'wpistic' ), '/terms-and-conditions/' => __( 'Terms and Conditions', 'wpistic' ) ),
	);
	/**
	 * Filter the fallback footer menus.
	 *
	 * @param array<string,array<string,string>> $menus    Location => (path => label).
	 * @param string                             $location Current menu location.
	 */
	$menus = (array) apply_filters( 'wpistic/footer_menu_fallback', $menus, $location );

	if ( empty( $menus[ $location ] ) ) { return; }
	echo '<ul class="footer-links">';
	foreach ( $menus[ $location ] as $path => $label ) { echo '<li><a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a></li>'; }
	echo '</ul>';
}

add_filter( 'body_class', 'wpistic_body_classes' );

/**
 * Add a body flag so the fixed header renders light over the dark masthead every
 * template opens with. Templates that intentionally start on a light surface can
 * remove it with: add_filter('wpistic_has_dark_hero', '__return_false').
 *
 * @param array $classes Body classes.
 * @return array
 */
function wpistic_body_classes( $classes ) {
	if ( ! is_admin() && apply_filters( 'wpistic_has_dark_hero', true ) ) {
		$classes[] = 'has-dark-hero';
	}
	return $classes;
}

/**
 * Canonical inquiry CTA destination.
 *
 * The base theme ships a "Plan My Laos Trip" route. A child theme may point the
 * action somewhere else -- Brother Tours locks it to /build-my-trip/ -- so the
 * destination is filterable rather than hard-coded across templates.
 *
 * @return string Absolute URL.
 */
function wpistic_cta_url() {
	return (string) apply_filters( 'wpistic/cta_url', wpistic_cta_url() );
}

/**
 * Canonical inquiry CTA label.
 *
 * @return string
 */
function wpistic_cta_label() {
	return (string) apply_filters( 'wpistic/cta_label', __( 'Plan My Laos Trip', 'wpistic' ) );
}

/**
 * Featured destinations for the homepage.
 *
 * Prefers real `wpistic_destination` posts so the homepage reflects what an
 * editor changes in the admin. Falls back to the bundled sample set only while
 * the CPT is still empty, so a fresh install shows a designed page rather than
 * an empty grid.
 *
 * @param int $limit Maximum cards to return.
 * @return array<int, array{name: string, tag: string, desc: string, url: string, image_url: string}>
 */
function wpistic_home_destinations( $limit = 6 ) {
	$posts = get_posts(
		array(
			'post_type'        => 'wpistic_destination',
			'post_status'      => 'publish',
			'numberposts'      => (int) $limit,
			'orderby'          => 'menu_order date',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);

	if ( ! $posts ) {
		$out = array();
		foreach ( array_slice( wpistic_sample_destinations(), 0, (int) $limit ) as $sample ) {
			$out[] = array(
				'name'      => $sample['name'],
				'tag'       => $sample['tag'],
				'desc'      => $sample['desc'],
				'url'       => home_url( '/destinations/' . $sample['slug'] . '/' ),
				'image_url' => (string) wpistic_demo_img( 'dest-' . $sample['slug'] ),
			);
		}
		return $out;
	}

	$out = array();
	foreach ( $posts as $post ) {
		$regions = wp_get_post_terms( $post->ID, 'region', array( 'fields' => 'names' ) );

		$out[] = array(
			'name'      => get_the_title( $post ),
			'tag'       => is_array( $regions ) && $regions ? (string) $regions[0] : '',
			'desc'      => (string) get_post_meta( $post->ID, 'wpistic_short_summary', true ),
			'url'       => (string) get_permalink( $post ),
			'image_url' => (string) get_the_post_thumbnail_url( $post->ID, 'bt-card' ),
		);
	}

	return $out;
}

/**
 * Featured journeys for the homepage.
 *
 * Same contract as wpistic_home_destinations(): real `wpistic_tour` posts win,
 * samples fill in only while the CPT is empty. Using get_permalink() here also
 * means the cards follow the CPT's registered rewrite rather than a hard-coded
 * path that can drift out of step with it.
 *
 * @param int $limit Maximum cards to return.
 * @return array<int, array<string, mixed>>
 */
function wpistic_home_tours( $limit = 6 ) {
	$posts = get_posts(
		array(
			'post_type'        => 'wpistic_tour',
			'post_status'      => 'publish',
			'numberposts'      => (int) $limit,
			'orderby'          => 'menu_order date',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);

	if ( ! $posts ) {
		$images = array( 'tour-1', 'tour-2', 'tour-3', 'tour-4', 'tour-5' );
		$out    = array();
		foreach ( array_slice( wpistic_sample_tours(), 0, (int) $limit ) as $i => $sample ) {
			$out[] = array(
				'name'      => $sample['name'],
				'url'       => home_url( '/tours/' . $sample['slug'] . '/' ),
				'region'    => 'Laos',
				'meta'      => $sample['meta'],
				'blurb'     => $sample['blurb'],
				'tags'      => array( $sample['cap'] ),
				'price'     => wpistic_price_line( '' ),
				'image_url' => (string) wpistic_demo_img( $images[ $i % count( $images ) ] ),
			);
		}
		return $out;
	}

	$out = array();
	foreach ( $posts as $post ) {
		$regions    = wp_get_post_terms( $post->ID, 'region', array( 'fields' => 'names' ) );
		$duration   = (string) get_post_meta( $post->ID, 'wpistic_duration', true );
		$availability = (string) get_post_meta( $post->ID, 'wpistic_availability', true );

		$out[] = array(
			'name'      => get_the_title( $post ),
			'url'       => (string) get_permalink( $post ),
			'region'    => is_array( $regions ) && $regions ? (string) $regions[0] : '',
			'meta'      => $duration,
			'blurb'     => (string) get_post_meta( $post->ID, 'wpistic_short_summary', true ),
			// Capacity is communicated per tour, never as a company-wide number.
			'tags'      => $availability ? array( $availability ) : array(),
			'price'     => wpistic_price_line( (string) get_post_meta( $post->ID, 'wpistic_from_price', true ) ),
			'image_url' => (string) get_the_post_thumbnail_url( $post->ID, 'bt-portrait' ),
		);
	}

	return $out;
}

/**
 * Whether the visitor has narrowed the tours archive.
 *
 * The archive falls back to the bundled sample journeys when the loop is empty,
 * which is right for a site with no tours yet but wrong after a filter: showing
 * every sample when a filter matched nothing reads as though the filter was
 * ignored. Templates use this to tell the two cases apart.
 *
 * @return bool
 */
function wpistic_tour_filters_active() {
	foreach ( array( 'category', 'destination', 'difficulty', 'duration', 'region', 'style', 'season' ) as $param ) {
		// Read-only, shareable filter links; there is no form submission to nonce.
		if ( ! empty( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
	}

	return false;
}

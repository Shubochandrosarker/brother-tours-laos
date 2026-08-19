<?php
/**
 * Brother Tours child theme bootstrap.
 *
 * Loads after the WPistic parent. Presentation only -- template overrides,
 * enqueues, site settings and brand markup. Tour, payment, form-processing,
 * email and integration logic belongs in the plugins, never here.
 *
 * @package BrotherTours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor widget bootstrap (registration only runs when Elementor is active).
 */
$brother_tours_elementor_bootstrap = get_stylesheet_directory() . '/inc/elementor/bootstrap.php';
if ( is_readable( $brother_tours_elementor_bootstrap ) ) {
	require_once $brother_tours_elementor_bootstrap;
}
unset( $brother_tours_elementor_bootstrap );

/**
 * Canonical Brother Tours routes.
 *
 * Kept in one place so navigation, CTAs, the sticky action and the page seeder
 * cannot drift apart. Paths are relative to the site root.
 */
function brother_tours_routes() {
	return array(
		'about'        => '/about/',
		'destinations' => '/destinations/',
		'tours'        => '/tours/',
		'journal'      => '/journal/',
		'contact'      => '/contact/',
		'build'        => '/build-my-trip/',
		'agents'       => '/agents/',
		'thank_you'    => '/thank-you/',
		'sitemap'      => '/sitemap/',
		'faq'          => '/faq/',
		'visa'         => '/visa-guide/',
		'when_to_visit' => '/when-to-visit/',
		'privacy'      => '/privacy/',
		'terms'        => '/terms/',
		'cancellation' => '/cancellation/',
	);
}

/**
 * Resolve a canonical route to an absolute URL.
 *
 * @param string $key Route key from brother_tours_routes().
 * @return string
 */
function brother_tours_url( $key ) {
	$routes = brother_tours_routes();
	return home_url( $routes[ $key ] ?? '/' );
}

/* =============================================================================
 * Assets
 * ========================================================================== */

add_action( 'wp_enqueue_scripts', 'brother_tours_enqueue', 20 );

/**
 * Load the locked type stack and design tokens after the parent's base styles.
 *
 * The parent enqueues its own Google Fonts under the handle `wpistic-fonts`
 * (Cormorant Garamond / Manrope). Brother Tours is locked to a different stack,
 * so that request is replaced outright rather than added to -- loading both
 * would ship two font payloads and slow the largest contentful paint for
 * families the brand never renders.
 *
 * @return void
 */
function brother_tours_enqueue() {
	$version = wp_get_theme()->get( 'Version' );

	// Replace the parent's font request with the locked families.
	wp_deregister_style( 'wpistic-fonts' );
	wp_enqueue_style(
		'wpistic-fonts',
		'https://fonts.googleapis.com/css2'
			. '?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400;1,600'
			. '&family=Crimson+Pro:ital,wght@0,300;0,400;0,500;1,400'
			. '&family=DM+Mono:wght@300;400'
			. '&family=Caveat:wght@400;500;600'
			. '&display=swap',
		array(),
		null // Google serves its own cache headers; a version query busts them.
	);

	wp_enqueue_style(
		'brother-tours-tokens',
		get_stylesheet_directory_uri() . '/assets/css/brand-tokens.css',
		array( 'wpistic-tokens', 'wpistic-style', 'wpistic-components', 'wpistic-pages' ),
		$version
	);

	wp_enqueue_style(
		'brother-tours-style',
		get_stylesheet_uri(),
		array( 'brother-tours-tokens' ),
		$version
	);

	wp_enqueue_script(
		'brother-tours-ui',
		get_stylesheet_directory_uri() . '/assets/js/brother-tours.js',
		array(),
		$version,
		true
	);

	if ( brother_tours_is_landing_template() ) {
		wp_enqueue_style(
			'brother-tours-landing',
			get_stylesheet_directory_uri() . '/assets/css/bt-landing.css',
			array( 'brother-tours-style' ),
			$version
		);
	}
}

/* =============================================================================
 * Elementor landing templates
 *
 * Support for the template kit in brother-tours-elementor-landing-system/.
 * The stylesheet is small, but a page that does not use a landing template
 * should not pay for it at all, so it loads only where the Elementor document
 * actually contains one of its classes.
 * ========================================================================== */

/**
 * Whether the current singular view was built from a Brother Tours landing
 * template.
 *
 * Elementor stores its document in the `_elementor_data` postmeta as JSON, so
 * the class names the templates carry are present in that string. Checking for
 * the namespace prefix is enough and costs one meta read that is already in
 * the object cache by the time styles are enqueued.
 *
 * @return bool
 */
function brother_tours_is_landing_template() {
	if ( ! is_singular() ) {
		return false;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return false;
	}

	$data = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! is_string( $data ) || '' === $data ) {
		return false;
	}

	return false !== strpos( $data, 'bt-landing-' );
}

/**
 * Reveals the REQUIRES BUSINESS VERIFICATION notes to an editor viewing the
 * published page.
 *
 * The notes are display:none for visitors and visible inside the Elementor
 * editor. This adds the third case: someone with edit rights reading the live
 * page, who can then see what still needs confirming without opening the
 * builder. Visitors and logged-out crawlers never match this.
 *
 * @param array $classes Body classes.
 * @return array
 */
function brother_tours_landing_body_class( $classes ) {
	if ( brother_tours_is_landing_template() && current_user_can( 'edit_posts' ) ) {
		$classes[] = 'bt-show-verify';
	}
	return $classes;
}
add_filter( 'body_class', 'brother_tours_landing_body_class' );

/* =============================================================================
 * Light / dark mode default
 * ========================================================================== */

/**
 * Default new visitors to dark mode -- Brother Tours' primary, locked
 * identity -- rather than the parent theme's own 'light' default.
 *
 * The toggle, no-flash script, and localStorage persistence are all parent
 * theme mechanics (themes/wpistic/header.php); this only changes what an
 * operator sees as the starting choice in Appearance -> Customize ->
 * Colour Mode before they have ever saved one. An operator's own saved
 * choice always wins.
 *
 * Reads the raw theme_mods array directly rather than calling
 * get_theme_mod() again from inside this filter: get_theme_mod() applies
 * this very filter before returning, so calling it here would re-enter this
 * callback and recurse until the stack overflows.
 *
 * @param mixed $value Resolved value get_theme_mod() would otherwise return.
 * @return mixed
 */
add_filter( 'theme_mod_wpistic_default_mode', 'brother_tours_default_mode' );

function brother_tours_default_mode( $value ) {
	$mods = get_theme_mods();
	return isset( $mods['wpistic_default_mode'] ) ? $value : 'dark';
}

/* =============================================================================
 * Navigation and CTA
 * ========================================================================== */

/**
 * Main navigation, exactly as the brand locks it.
 *
 * About, Destinations, Tours, Journal, Contact -- then one Build My Trip CTA.
 * Experiences and travel styles stay out of the main navigation by design:
 * experiences are nested under destinations, travel styles are a filter on the
 * tours archive.
 *
 * Supplied as the `primary` fallback so a site with no menu assigned still
 * renders the locked navigation rather than the base theme's generic set.
 *
 * @param array<string,string> $items Incoming path => label map.
 * @return array<string,string>
 */
add_filter( 'wpistic/primary_menu_fallback', 'brother_tours_primary_menu' );

function brother_tours_primary_menu( $items ) {
	unset( $items );
	$routes = brother_tours_routes();

	return array(
		$routes['about']        => __( 'About', 'brother-tours' ),
		$routes['destinations'] => __( 'Destinations', 'brother-tours' ),
		$routes['tours']        => __( 'Tours', 'brother-tours' ),
		$routes['journal']      => __( 'Journal', 'brother-tours' ),
		$routes['contact']      => __( 'Contact', 'brother-tours' ),
	);
}

/**
 * Footer columns.
 *
 * Travel Agents is B2B and lives here only -- never in the main navigation.
 *
 * @param array<string,array<string,string>> $menus    Location => (path => label).
 * @param string                             $location Menu location.
 * @return array<string,array<string,string>>
 */
add_filter( 'wpistic/footer_menu_fallback', 'brother_tours_footer_menus', 10, 2 );

function brother_tours_footer_menus( $menus, $location ) {
	unset( $menus, $location );
	$r = brother_tours_routes();

	return array(
		'footer-journeys'     => array(
			$r['tours'] => __( 'All Tours', 'brother-tours' ),
			$r['tours'] . '?category=signature-journeys' => __( 'Signature Journeys', 'brother-tours' ),
			$r['build'] => __( 'Build My Trip', 'brother-tours' ),
		),
		'footer-destinations' => array(
			'/destinations/northern-laos/'    => __( 'Northern Laos', 'brother-tours' ),
			'/destinations/southern-laos/'    => __( 'Southern Laos', 'brother-tours' ),
			'/destinations/luang-prabang/'    => __( 'Luang Prabang', 'brother-tours' ),
			'/destinations/vientiane/'        => __( 'Vientiane', 'brother-tours' ),
			'/destinations/plain-of-jars/'    => __( 'Plain of Jars', 'brother-tours' ),
			'/destinations/bolaven-plateau/'  => __( 'Bolaven Plateau', 'brother-tours' ),
		),
		'footer-company'      => array(
			$r['about']   => __( 'About', 'brother-tours' ),
			$r['journal'] => __( 'Journal', 'brother-tours' ),
			$r['agents']  => __( 'Travel Agents (B2B)', 'brother-tours' ),
			$r['contact'] => __( 'Contact', 'brother-tours' ),
		),
		'footer-practical'    => array(
			$r['faq']           => __( 'FAQ', 'brother-tours' ),
			$r['visa']          => __( 'Visa Guide', 'brother-tours' ),
			$r['when_to_visit'] => __( 'When to Visit', 'brother-tours' ),
			$r['privacy']       => __( 'Privacy', 'brother-tours' ),
			$r['terms']         => __( 'Terms', 'brother-tours' ),
			$r['cancellation']  => __( 'Cancellation', 'brother-tours' ),
		),
	);
}

/**
 * Point the base theme's inquiry CTA at the Build My Trip route.
 *
 * The base ships a "Plan My Laos Trip" action. Brother Tours locks both the
 * label and the destination, so both are filtered here rather than by copying
 * parent templates into the child.
 */
add_filter( 'wpistic/cta_url', 'brother_tours_cta_url' );
add_filter( 'wpistic/cta_label', 'brother_tours_cta_label' );

/**
 * @param string $url Incoming URL.
 * @return string
 */
function brother_tours_cta_url( $url ) {
	unset( $url );
	return brother_tours_url( 'build' );
}

/**
 * @param string $label Incoming label.
 * @return string
 */
function brother_tours_cta_label( $label ) {
	unset( $label );
	return __( 'Build My Trip', 'brother-tours' );
}

/**
 * Keep the legacy inquiry route reachable.
 *
 * The base theme's /plan-my-laos-trip/ page may already be published and
 * indexed. Rather than deleting it -- which would throw away any ranking it has
 * and 404 existing links -- send it to the canonical Build My Trip route with a
 * permanent redirect. Legacy URLs from the old domain are handled separately by
 * the redirect map; see docs/content-import-guide.md.
 *
 * @return void
 */
add_action( 'template_redirect', 'brother_tours_legacy_inquiry_redirect' );

function brother_tours_legacy_inquiry_redirect() {
	if ( is_admin() || ! is_page() ) {
		return;
	}

	if ( is_page( 'plan-my-laos-trip' ) ) {
		wp_safe_redirect( brother_tours_url( 'build' ), 301 );
		exit;
	}
}

/**
 * Mobile sticky Build My Trip action.
 *
 * Rendered as a real link in the footer so it is reachable in the tab order and
 * announced by assistive technology. The stylesheet hides it while a form
 * control has focus so it can never obscure the field being typed into.
 *
 * @return void
 */
add_action( 'wp_footer', 'brother_tours_sticky_cta' );

function brother_tours_sticky_cta() {
	if ( is_admin() ) {
		return;
	}
	?>
	<div class="bt-sticky-cta" data-bt-sticky-cta>
		<a class="bt-sticky-cta__link" href="<?php echo esc_url( brother_tours_url( 'build' ) ); ?>">
			<?php esc_html_e( 'Build My Trip', 'brother-tours' ); ?>
		</a>
	</div>
	<?php
}

/**
 * Body classes the sticky action and page styles key off.
 *
 * @param string[] $classes Existing classes.
 * @return string[]
 */
add_filter( 'body_class', 'brother_tours_body_class' );

function brother_tours_body_class( $classes ) {
	$classes[] = 'bt-has-sticky-cta';

	$build = trim( (string) ( brother_tours_routes()['build'] ?? '' ), '/' );
	if ( $build && is_page( $build ) ) {
		$classes[] = 'page-build-my-trip';
	}

	return $classes;
}

/* =============================================================================
 * Tours archive filtering
 * ========================================================================== */

/**
 * Drive the tours archive filters from the Tour Manager taxonomies.
 *
 * The parent renders the filter controls but never wired them to the query, so
 * selecting a filter reloaded the same unfiltered list. This translates the
 * archive's GET parameters into a real tax_query.
 *
 * Runs only on the front-end tour archive, and only for the main query, so it
 * cannot affect admin listings, feeds or secondary loops.
 *
 * @param WP_Query $query Query being prepared.
 * @return void
 */
add_action( 'pre_get_posts', 'brother_tours_filter_tours' );

function brother_tours_filter_tours( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! $query->is_post_type_archive( 'wpistic_tour' ) ) {
		return;
	}

	// GET key => taxonomy slug, as registered by Tour Manager's ContentTypes.
	$filters = array(
		'category'    => 'tour_category',
		'destination' => 'tour_destination',
		'difficulty'  => 'tour_difficulty',
		'duration'    => 'tour_duration_range',
		'region'      => 'region',
		'style'       => 'travel_style',
		'season'      => 'tour_season',
	);

	$tax_query = array();

	foreach ( $filters as $param => $taxonomy ) {
		if ( empty( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			continue;
		}

		// Filters are shareable, bookmarkable links rather than a form
		// submission, so there is no nonce to verify. Values are read-only and
		// sanitized to term slugs before they reach the query.
		$raw   = wp_unslash( $_GET[ $param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$terms = array_filter( array_map( 'sanitize_title', (array) $raw ) );

		if ( ! $terms ) {
			continue;
		}

		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => $terms,
		);
	}

	if ( $tax_query ) {
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		$query->set( 'tax_query', $tax_query );
	}
}

/* =============================================================================
 * Security headers
 * ========================================================================== */

add_action( 'send_headers', 'brother_tours_security_headers' );

/**
 * Baseline security headers.
 *
 * HSTS is only sent over HTTPS. The Content-Security-Policy is filterable and
 * ships in report-only mode by default: a blocking policy that has not been
 * tested against the live payment and analytics embeds would break checkout
 * links and consent-gated scripts. Switch it to enforcing via the filter below
 * once the report endpoint is quiet -- see docs/launch-checklist.md.
 *
 * @return void
 */
function brother_tours_security_headers() {
	if ( is_admin() ) {
		return;
	}

	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );

	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
	}

	/**
	 * Filter the Content-Security-Policy.
	 *
	 * @param string $policy    Policy string.
	 * @param bool   $enforcing Whether to send it as enforcing.
	 */
	$policy = apply_filters(
		'brother_tours/csp',
		"default-src 'self'; "
			. "img-src 'self' data: https:; "
			. "font-src 'self' https://fonts.gstatic.com; "
			. "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
			. "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://www.clarity.ms https://connect.facebook.net; "
			. "connect-src 'self' https://www.google-analytics.com https://www.clarity.ms; "
			. "frame-src https://www.google.com https://www.tripadvisor.com; "
			. "base-uri 'self'; form-action 'self'; frame-ancestors 'self'",
		false
	);

	$enforcing = (bool) apply_filters( 'brother_tours/csp_enforce', false );

	if ( $policy ) {
		header( ( $enforcing ? 'Content-Security-Policy: ' : 'Content-Security-Policy-Report-Only: ' ) . $policy );
	}
}

/* =============================================================================
 * Site settings
 * ========================================================================== */

add_action( 'customize_register', 'brother_tours_customize' );

/**
 * Customizer: review profile links, verification tokens and consent-gated
 * analytics identifiers.
 *
 * Analytics identifiers are site settings, not secrets -- they are public
 * strings that appear in page source. Provider API keys and payment
 * credentials are never stored here.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 * @return void
 */
function brother_tours_customize( $wp_customize ) {

	/* ---- Reviews ---- */
	$wp_customize->add_section(
		'brother_tours_reviews',
		array(
			'title'       => __( 'Brother Tours — Reviews', 'brother-tours' ),
			'description' => __( 'Profile links and an optional review widget embed. Numeric ratings and review-score structured data stay switched off until the verified-review threshold is reached.', 'brother-tours' ),
			'priority'    => 160,
		)
	);

	$wp_customize->add_setting( 'wpistic_google_reviews_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'wpistic_google_reviews_url', array( 'label' => __( 'Google reviews URL', 'brother-tours' ), 'section' => 'brother_tours_reviews', 'type' => 'url' ) );

	$wp_customize->add_setting( 'wpistic_tripadvisor_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'wpistic_tripadvisor_url', array( 'label' => __( 'TripAdvisor URL', 'brother-tours' ), 'section' => 'brother_tours_reviews', 'type' => 'url' ) );

	$wp_customize->add_setting( 'wpistic_reviews_embed', array( 'sanitize_callback' => 'wp_kses_post' ) );
	$wp_customize->add_control( 'wpistic_reviews_embed', array( 'label' => __( 'Reviews widget embed (HTML)', 'brother-tours' ), 'section' => 'brother_tours_reviews', 'type' => 'textarea' ) );

	/* ---- Analytics and verification ---- */
	$wp_customize->add_section(
		'brother_tours_analytics',
		array(
			'title'       => __( 'Brother Tours — Analytics', 'brother-tours' ),
			'description' => __( 'Measurement identifiers. Non-essential tags load only after the visitor consents.', 'brother-tours' ),
			'priority'    => 161,
		)
	);

	$fields = array(
		'brother_tours_ga4_id'         => __( 'GA4 measurement ID', 'brother-tours' ),
		'brother_tours_meta_pixel_id'  => __( 'Meta Pixel ID', 'brother-tours' ),
		'brother_tours_clarity_id'     => __( 'Microsoft Clarity ID', 'brother-tours' ),
		'brother_tours_gsc_token'      => __( 'Google Search Console verification token', 'brother-tours' ),
		'brother_tours_bing_token'     => __( 'Bing Webmaster verification token', 'brother-tours' ),
	);

	foreach ( $fields as $id => $label ) {
		$wp_customize->add_setting( $id, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( $id, array( 'label' => $label, 'section' => 'brother_tours_analytics', 'type' => 'text' ) );
	}
}

/**
 * Search-console and Bing verification meta tags.
 *
 * These are ownership proofs, not tracking, so they are not consent-gated.
 *
 * @return void
 */
add_action( 'wp_head', 'brother_tours_verification_tags', 1 );

function brother_tours_verification_tags() {
	$tags = array(
		'google-site-verification' => get_theme_mod( 'brother_tours_gsc_token', '' ),
		'msvalidate.01'            => get_theme_mod( 'brother_tours_bing_token', '' ),
	);

	foreach ( $tags as $name => $value ) {
		if ( $value ) {
			printf(
				'<meta name="%1$s" content="%2$s">' . "\n",
				esc_attr( $name ),
				esc_attr( $value )
			);
		}
	}
}

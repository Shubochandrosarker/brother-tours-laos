<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

final class Sitemap {
	public static function activate(): void {
		flush_rewrite_rules();
	}

	public function register(): void {
		add_action( 'init', array( $this, 'rewrites' ), 1 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve' ), 0 );
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 20, 2 );
	}

	public function rewrites(): void {
		add_rewrite_rule( '^sitemap_index\.xml$', 'index.php?bt_cs_sitemap=index', 'top' );
		add_rewrite_rule( '^bt-tour-sitemap\.xml$', 'index.php?bt_cs_sitemap=tours', 'top' );
		add_rewrite_rule( '^bt-destination-sitemap\.xml$', 'index.php?bt_cs_sitemap=destinations', 'top' );
		add_rewrite_rule( '^bt-content-sitemap\.xml$', 'index.php?bt_cs_sitemap=content', 'top' );
	}

	/** @param string[] $vars @return string[] */
	public function query_vars( array $vars ): array {
		$vars[] = 'bt_cs_sitemap';
		return $vars;
	}

	public function serve(): void {
		$type = sanitize_key( (string) get_query_var( 'bt_cs_sitemap' ) );
		if ( ! in_array( $type, array( 'index', 'tours', 'destinations', 'content' ), true ) ) {
			return;
		}
		if ( self::is_staging() ) {
			status_header( 404 );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		$xml = 'index' === $type ? $this->index() : $this->urlset( $type );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is generated from escaped URLs.
		exit;
	}

	public function robots_txt( string $output, bool $public ): string {
		if ( self::is_staging() ) {
			return "User-agent: *\nDisallow: /\n";
		}
		if ( $public ) {
			$output = rtrim( $output ) . "\nSitemap: " . esc_url_raw( home_url( '/sitemap_index.xml' ) ) . "\n";
		}
		return $output;
	}

	public static function is_staging(): bool {
		$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		return function_exists( 'wp_get_environment_type' ) && 'staging' === wp_get_environment_type() || (bool) preg_match( '/(^|\.)staging\./', $host );
	}

	private function index(): string {
		$urls = array( home_url( '/bt-tour-sitemap.xml' ), home_url( '/bt-destination-sitemap.xml' ), home_url( '/bt-content-sitemap.xml' ) );
		$out  = '<?xml version="1.0" encoding="UTF-8"?>';
		$out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		foreach ( $urls as $url ) { $out .= '<sitemap><loc>' . esc_url( $url ) . '</loc></sitemap>'; }
		return $out . '</sitemapindex>';
	}

	private function urlset( string $type ): string {
		$query = array( 'post_status' => 'publish', 'posts_per_page' => 50000, 'fields' => 'ids', 'no_found_rows' => true );
		$query['post_type'] = match ( $type ) {
			'tours'        => 'wpistic_tour',
			'destinations' => 'wpistic_destination',
			default        => array( 'page', 'post' ),
		};
		$ids = get_posts( $query );
		$out = '<?xml version="1.0" encoding="UTF-8"?>';
		$out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		foreach ( $ids as $id ) {
			$url = get_permalink( $id );
			if ( ! $url ) { continue; }
			$out .= '<url><loc>' . esc_url( $url ) . '</loc><lastmod>' . esc_html( get_post_modified_time( 'c', true, $id ) ) . '</lastmod></url>';
		}
		return $out . '</urlset>';
	}
}

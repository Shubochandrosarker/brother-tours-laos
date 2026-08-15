<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Admin;

use Wpistic\TourManager\Connections\ConnectionsManager;

/**
 * Idempotent site scaffolding: the locked Brother Tours routes, and the named
 * Tourflows connection profile.
 *
 * Everything here is fill-only. A page that already exists is never rewritten,
 * so running the seeder twice -- or running it on a live site months after
 * launch -- cannot overwrite an editor's work. Content is created as real
 * WordPress pages so it stays editable in the admin rather than living in a
 * hard-coded template array.
 *
 * Pages whose copy has not been supplied are created as **drafts** and clearly
 * labelled, so nothing unreviewed can reach the public site by accident.
 */
final class SiteSeeder {

	/** Meta key marking a page as seeder-owned, and recording its route key. */
	private const META_ROUTE = '_brother_tours_route';

	/** Option recording the last seed run. */
	private const OPT_SEEDED = 'brother_tours_site_seeded_at';

	/** The named Tourflows connection profile. */
	private const TOURFLOWS_NAME = 'Tourflows';

	private ConnectionsManager $connections;

	public function __construct( ConnectionsManager $connections ) {
		$this->connections = $connections;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_post_wpistic_tm_seed_site', array( $this, 'handle' ) );
	}

	/**
	 * Route definitions.
	 *
	 * `publish` marks a page whose structure is complete and safe to show.
	 * `draft` marks a page that needs owner-supplied copy or legal review before
	 * it can go live -- see docs/launch-checklist.md.
	 *
	 * @return array<string, array{title: string, slug: string, template: string, status: string, excerpt: string}>
	 */
	public static function routes(): array {
		return array(
			'about'         => array( 'title' => 'About',            'slug' => 'about',          'template' => 'page-about.php',        'status' => 'publish', 'excerpt' => 'Lao-led. Globally understood.' ),
			'build'         => array( 'title' => 'Build My Trip',    'slug' => 'build-my-trip',  'template' => 'page-build-my-trip.php', 'status' => 'publish', 'excerpt' => 'We design your journey, your way.' ),
			'contact'       => array( 'title' => 'Contact',          'slug' => 'contact',        'template' => 'page-contact.php',      'status' => 'publish', 'excerpt' => '' ),
			'agents'        => array( 'title' => 'Travel Agents',    'slug' => 'agents',         'template' => 'page-agents.php',       'status' => 'publish', 'excerpt' => '' ),
			'thank_you'     => array( 'title' => 'Thank You',        'slug' => 'thank-you',      'template' => 'page-thank-you.php',    'status' => 'publish', 'excerpt' => '' ),
			'journal'       => array( 'title' => 'Journal',          'slug' => 'journal',        'template' => '',                      'status' => 'publish', 'excerpt' => '' ),

			// Practical pages. Held as drafts: each needs reviewed copy, and the
			// three legal pages need owner sign-off before publication.
			'faq'           => array( 'title' => 'FAQ',              'slug' => 'faq',            'template' => '', 'status' => 'draft', 'excerpt' => 'Draft — needs reviewed content.' ),
			'visa'          => array( 'title' => 'Visa Guide',       'slug' => 'visa-guide',     'template' => '', 'status' => 'draft', 'excerpt' => 'Draft — needs reviewed content.' ),
			'when_to_visit' => array( 'title' => 'When to Visit',    'slug' => 'when-to-visit',  'template' => '', 'status' => 'draft', 'excerpt' => 'Draft — needs reviewed content.' ),
			'privacy'       => array( 'title' => 'Privacy Policy',   'slug' => 'privacy',        'template' => '', 'status' => 'draft', 'excerpt' => 'Draft — template supplied in the handoff pack; requires legal review before publishing.' ),
			'terms'         => array( 'title' => 'Terms',            'slug' => 'terms',          'template' => '', 'status' => 'draft', 'excerpt' => 'Draft — template supplied in the handoff pack; requires legal review before publishing.' ),
			'cancellation'  => array( 'title' => 'Cancellation Policy', 'slug' => 'cancellation', 'template' => '', 'status' => 'draft', 'excerpt' => 'Draft — template supplied in the handoff pack; requires legal review before publishing.' ),
			'sitemap'       => array( 'title' => 'Sitemap',          'slug' => 'sitemap',        'template' => '', 'status' => 'draft', 'excerpt' => 'Draft — HTML sitemap.' ),
		);
	}

	public function menu(): void {
		add_submenu_page(
			'wpistic-tour-manager',
			__( 'Site Setup', 'wpistic-tour-manager' ),
			__( 'Site Setup', 'wpistic-tour-manager' ),
			'manage_options',
			'wpistic-tm-site-setup',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$seeded  = (string) get_option( self::OPT_SEEDED, '' );
		$missing = $this->missing_routes();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Brother Tours — Site Setup', 'wpistic-tour-manager' ); ?></h1>

			<p>
				<?php esc_html_e( 'Creates any missing Brother Tours page and registers the Tourflows connection profile. Existing pages are never modified, so this is safe to run again at any time.', 'wpistic-tour-manager' ); ?>
			</p>

			<?php if ( $seeded ) : ?>
				<p><em><?php
					/* translators: %s: ISO 8601 timestamp. */
					printf( esc_html__( 'Last run: %s', 'wpistic-tour-manager' ), esc_html( $seeded ) );
				?></em></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Routes', 'wpistic-tour-manager' ); ?></h2>
			<?php if ( $missing ) : ?>
				<p><?php esc_html_e( 'Missing pages:', 'wpistic-tour-manager' ); ?> <code><?php echo esc_html( implode( ', ', $missing ) ); ?></code></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Every Brother Tours page exists.', 'wpistic-tour-manager' ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Tourflows', 'wpistic-tour-manager' ); ?></h2>
			<p>
				<?php esc_html_e( 'The seeder registers a disabled Tourflows connection profile. Add the endpoint and signing secret on the Connections screen, then enable it — credentials are stored in the database, never in the repository.', 'wpistic-tour-manager' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpistic_tm_seed_site">
				<?php wp_nonce_field( 'wpistic_tm_seed_site' ); ?>
				<?php submit_button( __( 'Create missing pages and profile', 'wpistic-tour-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wpistic-tour-manager' ) );
		}
		check_admin_referer( 'wpistic_tm_seed_site' );

		$this->seed_pages();
		$this->seed_tourflows_profile();

		update_option( self::OPT_SEEDED, gmdate( 'c' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=wpistic-tm-site-setup&seeded=1' ) );
		exit;
	}

	/**
	 * Route keys that have no page yet.
	 *
	 * @return string[]
	 */
	private function missing_routes(): array {
		$missing = array();

		foreach ( self::routes() as $key => $route ) {
			if ( ! get_page_by_path( $route['slug'] ) ) {
				$missing[] = $route['slug'];
			}
		}

		return $missing;
	}

	/**
	 * Create any missing page. Never touches one that already exists.
	 */
	private function seed_pages(): void {
		foreach ( self::routes() as $key => $route ) {

			if ( get_page_by_path( $route['slug'] ) ) {
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => $route['status'],
					'post_title'   => $route['title'],
					'post_name'    => $route['slug'],
					'post_excerpt' => $route['excerpt'],
				),
				true
			);

			if ( is_wp_error( $page_id ) || ! $page_id ) {
				continue;
			}

			update_post_meta( $page_id, self::META_ROUTE, $key );

			if ( '' !== $route['template'] ) {
				update_post_meta( $page_id, '_wp_page_template', $route['template'] );
			}
		}

		// Point the posts page at the Journal so /journal/ is the blog index.
		$journal = get_page_by_path( 'journal' );
		if ( $journal && ! (int) get_option( 'page_for_posts' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_for_posts', $journal->ID );
		}
	}

	/**
	 * Register the named Tourflows connection profile.
	 *
	 * Created **disabled**, with an empty endpoint and secret. An operator adds
	 * both on the Connections screen and enables it there. Nothing secret is
	 * ever written by this code, so nothing secret can reach the repository.
	 */
	private function seed_tourflows_profile(): void {
		foreach ( $this->connections->all() as $connection ) {
			if ( isset( $connection['name'] ) && self::TOURFLOWS_NAME === $connection['name'] ) {
				return; // Already registered; leave the operator's settings alone.
			}
		}

		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wpistic_connections',
			array(
				'name'       => self::TOURFLOWS_NAME,
				'type'       => 'webhook',
				'target_url' => '',
				'secret'     => '',
				'events'     => wp_json_encode(
					array(
						'inquiry.created',
						'booking.deposit_paid',
						'booking.confirmed',
						'booking.balance_paid',
					)
				),
				'enabled'    => 0,
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}
}

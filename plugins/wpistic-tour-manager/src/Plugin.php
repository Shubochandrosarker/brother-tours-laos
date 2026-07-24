<?php

declare(strict_types=1);

namespace Wpistic\TourManager;

/**
 * Plugin bootstrap. Wires the modules; holds no business logic itself
 * (that lives in the framework-agnostic core).
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		load_plugin_textdomain( 'wpistic-tour-manager', false, dirname( plugin_basename( WPISTIC_TM_FILE ) ) . '/languages' );

		( new PostTypes\ContentTypes() )->register();

		$gateways    = new Payments\GatewayManager();
		$gateways->register();

		$bookings    = new Booking\BookingService();
		$bookings->register();
		$connections = new Connections\ConnectionsManager();
		$connections->register();

		( new Notifications\Notifier() )->register();
		( new Booking\CaptureController( $bookings, $connections ) )->register();
		// The single path from Brother Tours Forms into an inquiry record.
		// Nothing else may turn a Formistic submission into a booking.
		( new Integration\FormisticIngestion( $bookings, $connections ) )->register();
		( new Payments\WebhookController( $gateways, $bookings ) )->register();
		( new Integration\SchemaData() )->register();
		( new Frontend\Assets() )->register();
		( new Frontend\BookingWidget() )->register();
		( new Frontend\Newsletter() )->register();

		if ( is_admin() ) {
			( new Admin\Settings() )->register();
			( new Admin\MetaBoxes() )->register();
			( new Admin\AdminAssets() )->register();
			( new Admin\Dashboard( $bookings, $connections ) )->register();
			( new Admin\Bookings( $bookings, $gateways, $connections ) )->register();
			( new Admin\ContentSeeder() )->register();
			( new Admin\SiteSeeder( $connections ) )->register();
		}

		add_action( 'init', array( $this, 'maybe_flush' ), 999 );
		/*
		 * On `init`, not `admin_init`: a visitor can submit a form before any
		 * administrator loads a page after an upgrade. If the ingestion table
		 * did not exist yet, that submission would fail to claim and the inquiry
		 * would be lost. The version check below is a single cached option read,
		 * so the cost on front-end requests is negligible.
		 */
		add_action( 'init', array( $this, 'maybe_upgrade' ), 1 );
	}

	public function maybe_flush(): void {
		if ( get_option( 'wpistic_tm_flush' ) ) {
			flush_rewrite_rules();
			delete_option( 'wpistic_tm_flush' );
		}
	}

	/**
	 * Bring an already-activated install up to the current schema.
	 *
	 * The activation hook only runs on activation, so a site that was already
	 * running the plugin never receives tables added in a later release. This
	 * closes that gap on the first admin request after an upgrade.
	 *
	 * Tables::create() is dbDelta-based and purely additive — it creates missing
	 * tables and columns and never drops data, so re-running it is safe.
	 */
	public function maybe_upgrade(): void {
		if ( get_option( 'wpistic_tm_db_version' ) === WPISTIC_TM_DB_VERSION ) {
			return;
		}

		Install\Tables::create();
		update_option( 'wpistic_tm_db_version', WPISTIC_TM_DB_VERSION );
	}
}

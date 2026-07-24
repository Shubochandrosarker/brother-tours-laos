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
		$connections = new Connections\ConnectionsManager();
		$connections->register();

		( new Notifications\Notifier() )->register();
		( new Booking\CaptureController( $bookings, $connections ) )->register();
		( new Payments\WebhookController( $gateways, $bookings ) )->register();
		( new Integration\SchemaData() )->register();
		( new Frontend\Assets() )->register();
		( new Frontend\BookingWidget() )->register();
		( new Frontend\Newsletter() )->register();

		if ( is_admin() ) {
			( new Admin\Settings() )->register();
			( new Admin\MetaBoxes() )->register();
			( new Admin\Portal( $bookings, $gateways, $connections ) )->register();
			( new Admin\ContentSeeder() )->register();
		}

		add_action( 'init', array( $this, 'maybe_flush' ), 999 );
	}

	public function maybe_flush(): void {
		if ( get_option( 'wpistic_tm_flush' ) ) {
			flush_rewrite_rules();
			delete_option( 'wpistic_tm_flush' );
		}
	}
}

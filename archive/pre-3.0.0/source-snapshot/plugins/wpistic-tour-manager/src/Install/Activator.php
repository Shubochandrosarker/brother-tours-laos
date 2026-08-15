<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Install;

use Wpistic\TourManager\PostTypes\ContentTypes;

final class Activator {

	public static function activate(): void {
		Tables::create();
		self::default_options();

		// Register CPTs so rewrite rules can be flushed for the new permalinks.
		( new ContentTypes() )->register();
		update_option( 'wpistic_tm_flush', 1 );
		update_option( 'wpistic_tm_db_version', WPISTIC_TM_DB_VERSION );
	}

	private static function default_options(): void {
		$defaults = array(
			'wpistic_tm_currency'      => 'USD',
			'wpistic_tm_deposit_type'  => 'percent',
			'wpistic_tm_deposit_value' => '30',
			'wpistic_tm_pay_to_hold'   => 0,
			'wpistic_tm_from_email'    => get_option( 'admin_email' ),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value );
			}
		}
	}
}

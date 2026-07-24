<?php
/**
 * PSR-4 autoloader. Loads the plugin's own classes and the bundled (or monorepo)
 * framework-agnostic tour-core package — so no `composer install` is required.
 *
 * @package WpisticTourManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class ) {
		$prefixes = array(
			'Wpistic\\TourManager\\' => array( WPISTIC_TM_DIR . 'src/' ),
			'Wpistic\\TourCore\\'    => array(
				WPISTIC_TM_DIR . 'lib/tour-core/src/',
				dirname( WPISTIC_TM_DIR, 2 ) . '/packages/tour-core/src/',
			),
		);

		foreach ( $prefixes as $prefix => $bases ) {
			$len = strlen( $prefix );
			if ( 0 !== strncmp( $class, $prefix, $len ) ) {
				continue;
			}
			$relative = str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
			foreach ( $bases as $base ) {
				$file = $base . $relative;
				if ( is_file( $file ) ) {
					require $file;
					return;
				}
			}
		}
	}
);

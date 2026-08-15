<?php

declare(strict_types=1);

/**
 * Focused, WordPress-free regression checks for release-critical fixes.
 * Run with: php scripts/test-release-fixes.php
 */

$test_meta     = array();
$test_options  = array();
$test_filters  = array();
$test_failures = array();

function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
	global $test_filters;
	$test_filters[ $hook ] = array( $callback, $priority, $accepted_args );
}

function get_the_title( int $post_id ): string {
	return 'Test tour ' . $post_id;
}

function get_the_excerpt( int $post_id ): string {
	return 'Test excerpt ' . $post_id;
}

function get_permalink( int $post_id ): string {
	return 'https://example.test/tour/test-' . $post_id . '/';
}

function get_post_meta( int $post_id, string $key, bool $single = false ) {
	global $test_meta;
	return $test_meta[ $post_id ][ $key ] ?? '';
}

function get_option( string $key, $default = false ) {
	global $test_options;
	return array_key_exists( $key, $test_options ) ? $test_options[ $key ] : $default;
}

function test_assert( bool $condition, string $message ): void {
	global $test_failures;
	if ( ! $condition ) {
		$test_failures[] = $message;
	}
}

require_once dirname( __DIR__ ) . '/plugins/wpistic-tour-manager/src/Integration/SchemaData.php';

$schema = new \Wpistic\TourManager\Integration\SchemaData();
$schema->register();

test_assert( isset( $test_filters['seoistic/tour_data'] ), 'SEOISTIC tour-data filter was not registered.' );

$test_meta[1]['wpistic_from_price'] = '';
$test_options['wpistic_tm_currency'] = 'USD';
$result = $schema->tour( array( 'offers' => array( '@type' => 'Offer', 'availability' => 'guessed' ) ), 1 );
test_assert( ! isset( $result['offers'] ), 'Empty price must not emit Offer.' );

$test_meta[1]['wpistic_from_price'] = '595';
$test_options['wpistic_tm_currency'] = '';
$result = $schema->tour( array(), 1 );
test_assert( ! isset( $result['offers'] ), 'Missing currency must not emit Offer.' );

$test_options['wpistic_tm_currency'] = 'USD';
$result = $schema->tour( array(), 1 );
test_assert( isset( $result['offers'] ), 'Verified numeric price and ISO currency should emit Offer.' );
test_assert( ! isset( $result['offers']['availability'] ), 'Availability must not be guessed.' );

$root = dirname( __DIR__ );
$url_files = array(
	'themes/wpistic/inc/template-tags.php',
	'themes/wpistic/page-travel-from.php',
	'themes/wpistic/parts/home-v2.php',
	'themes/wpistic/parts/home-v3.php',
	'themes/wpistic/single-destination.php',
	'themes/wpistic/archive-tour.php',
);
foreach ( $url_files as $file ) {
	$source = (string) file_get_contents( $root . '/' . $file );
	test_assert( false === strpos( $source, "home_url( '/tours/' ." ), $file . ' still builds a tour detail URL under /tours/.' );
}

$plugin_source = (string) file_get_contents( $root . '/plugins/wpistic-tour-manager/src/Plugin.php' );
test_assert( false === strpos( $plugin_source, '( new Admin\\ContentSeeder() )->register();' ), 'Unapproved ContentSeeder remains registered.' );

$sample_source = (string) file_get_contents( $root . '/themes/wpistic/inc/sample-data.php' );
test_assert( false === strpos( $sample_source, 'departures / year' ), 'Fabricated departure-capacity fallback remains.' );
test_assert( false === strpos( $sample_source, "'slug'   =>" ), 'Fabricated tour fallback slugs remain.' );

$tour_template_source = (string) file_get_contents( $root . '/themes/wpistic/single-tour.php' );
foreach ( array( "'wpistic_start'", "'wpistic_end'", "'wpistic_style'", 'Arrival in Vientiane', 'Licensed Lao host throughout', 'Free cancellation up to 30 days', 'Each journey runs a fixed number of times each year.' ) as $forbidden_tour_fallback ) {
	test_assert( false === strpos( $tour_template_source, $forbidden_tour_fallback ), 'Tour template still contains an unverified fallback: ' . $forbidden_tour_fallback );
}

$spam_source = (string) file_get_contents( $root . '/plugins/formistic/includes/class-formistic-spam.php' );
test_assert( false === strpos( $spam_source, "'formistic_rl_lock_' . \$key" ), 'Formistic still sends an overlong advisory-lock name to MySQL.' );

$capture_source = (string) file_get_contents( $root . '/plugins/formistic/includes/class-formistic-capture.php' );
$notifier_source = (string) file_get_contents( $root . '/plugins/wpistic-tour-manager/src/Notifications/Notifier.php' );
test_assert( false !== strpos( $capture_source, "defined( 'WPISTIC_FORMISTIC_EMAIL_DISABLED' )" ), 'Formistic central mail wrapper ignores the staging kill switch.' );
test_assert( false !== strpos( $notifier_source, "defined( 'WPISTIC_FORMISTIC_EMAIL_DISABLED' )" ), 'Tour Manager mail fallback ignores the staging kill switch.' );

$booking_source = (string) file_get_contents( $root . '/plugins/wpistic-tour-manager/src/Booking/BookingService.php' );
test_assert( 2 === substr_count( $booking_source, "PaymentStatus::Pending, PaymentStatus::Paid" ), 'Payment-link failure guards are incomplete.' );
$capture_controller_source = (string) file_get_contents( $root . '/plugins/wpistic-tour-manager/src/Booking/CaptureController.php' );
test_assert( false !== strpos( $capture_controller_source, "'wpistic_booking_not_saved'" ), 'Booking persistence failure still returns false success.' );

if ( $test_failures ) {
	fwrite( STDERR, "Release regression checks failed:\n- " . implode( "\n- ", $test_failures ) . "\n" );
	exit( 1 );
}

echo "Release regression checks passed (schema, tour URLs, seeder quarantine).\n";

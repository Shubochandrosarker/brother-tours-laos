<?php
/**
 * Email sender — wraps wp_mail() with from-name/from-address from CRM settings.
 *
 * Returns a { ok: bool, provider_message_id: string, error: string } shape so
 * the dispatcher can branch uniformly with the SMS sender.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Email_Sender {

	public static function send( $to, $subject, $body ) {
		$to = sanitize_email( (string) $to );
		if ( '' === $to ) {
			return array( 'ok' => false, 'error' => 'Empty or invalid recipient address.' );
		}

		$from_name    = (string) G2A_CRM_Settings::get( 'email_from_name', 'WPistic' );
		$from_address = (string) G2A_CRM_Settings::get( 'email_from_address', '' );

		$headers   = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( $from_name && $from_address ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_address );
			$headers[] = sprintf( 'Reply-To: %s', $from_address );
		}

		if ( G2A_CRM_Settings::get( 'messaging_test_mode', false ) ) {
			error_log( sprintf( '[G2A CRM test_mode] EMAIL → %s | %s', $to, $subject ) );
			return array( 'ok' => true, 'provider_message_id' => 'test:' . wp_generate_uuid4() );
		}

		$captured_error = '';
		$listener       = static function ( $wp_error ) use ( &$captured_error ) {
			$captured_error = is_wp_error( $wp_error ) ? $wp_error->get_error_message() : (string) $wp_error;
		};
		add_action( 'wp_mail_failed', $listener );

		$result = wp_mail( $to, $subject, $body, $headers );

		remove_action( 'wp_mail_failed', $listener );

		if ( ! $result ) {
			return array( 'ok' => false, 'error' => $captured_error ?: 'wp_mail returned false.', 'retryable' => true );
		}
		return array( 'ok' => true, 'provider_message_id' => 'wp_mail:' . wp_generate_uuid4() );
	}
}

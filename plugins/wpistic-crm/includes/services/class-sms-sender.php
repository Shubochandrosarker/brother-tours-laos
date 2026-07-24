<?php
/**
 * SMS sender — pluggable provider with Twilio and "log only" adapters.
 *
 * Choose via setting `sms_provider`: 'twilio' or 'log_only' (default).
 * Twilio requires `sms_twilio_sid`, `sms_twilio_token`, `sms_twilio_from`.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_SMS_Sender {

	public static function send( $to, $body ) {
		$to = g2a_crm_sanitize_phone( (string) $to );
		if ( '' === $to ) {
			return array( 'ok' => false, 'error' => 'Empty or invalid phone number.' );
		}

		if ( G2A_CRM_Settings::get( 'messaging_test_mode', false ) ) {
			error_log( sprintf( '[G2A CRM test_mode] SMS → %s | %s', $to, substr( $body, 0, 80 ) ) );
			return array( 'ok' => true, 'provider_message_id' => 'test:' . wp_generate_uuid4() );
		}

		$provider = (string) G2A_CRM_Settings::get( 'sms_provider', 'log_only' );

		switch ( $provider ) {
			case 'twilio':
				return self::send_via_twilio( $to, $body );
			case 'log_only':
			default:
				return self::send_via_log( $to, $body );
		}
	}

	private static function send_via_log( $to, $body ) {
		error_log( sprintf( '[G2A CRM sms log_only] → %s | %s', $to, substr( (string) $body, 0, 160 ) ) );
		return array( 'ok' => true, 'provider' => 'log_only', 'provider_message_id' => 'log:' . wp_generate_uuid4() );
	}

	private static function send_via_twilio( $to, $body ) {
		$sid   = (string) G2A_CRM_Settings::get( 'sms_twilio_sid', '' );
		$token = (string) G2A_CRM_Settings::get( 'sms_twilio_token', '' );
		$from  = (string) G2A_CRM_Settings::get( 'sms_twilio_from', '' );

		if ( '' === $sid || '' === $token || '' === $from ) {
			return array( 'ok' => false, 'error' => 'Twilio credentials are not configured (sms_twilio_sid / sms_twilio_token / sms_twilio_from).' );
		}

		$url = sprintf( 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', rawurlencode( $sid ) );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'To'   => $to,
					'From' => $from,
					'Body' => substr( (string) $body, 0, 1600 ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'error' => 'Twilio request failed: ' . $response->get_error_message(), 'retryable' => true );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body_r = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $body_r, true );

		if ( $status >= 200 && $status < 300 ) {
			return array(
				'ok'                  => true,
				'provider'            => 'twilio',
				'provider_message_id' => isset( $data['sid'] ) ? (string) $data['sid'] : '',
			);
		}

		$message   = isset( $data['message'] ) ? $data['message'] : $body_r;
		$retryable = $status >= 500 || 429 === $status;
		return array( 'ok' => false, 'error' => sprintf( 'Twilio %d: %s', $status, $message ), 'retryable' => $retryable );
	}
}

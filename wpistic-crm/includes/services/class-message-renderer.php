<?php
/**
 * Mustache-lite template renderer.
 *
 * Supports {{variable}} substitution from a context array. No conditionals, no
 * loops — intentionally tiny so it's safe and predictable. Anything more
 * complex belongs in PHP code that builds the context, not in the template.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Message_Renderer {

	public static function render( $template, array $context ) {
		if ( null === $template || '' === $template ) {
			return '';
		}
		return preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
			static function ( $matches ) use ( $context ) {
				$key = $matches[1];
				return array_key_exists( $key, $context ) ? (string) $context[ $key ] : '';
			},
			(string) $template
		);
	}

	/**
	 * Build the variable context for an outbound message. Pulls customer fields
	 * and merges any extra keys passed in.
	 *
	 * @param array $customer Customer row.
	 * @param array $extra    Additional variables (booking_time, plan_name, etc.).
	 */
	public static function context_for_customer( $customer, array $extra = array() ) {
		$base = array(
			'first_name'    => $customer['first_name'] ?? '',
			'last_name'     => $customer['last_name'] ?? '',
			'full_name'     => trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) ),
			'email'         => $customer['email'] ?? '',
			'phone'         => $customer['phone'] ?? '',
			'business_name' => (string) G2A_CRM_Settings::get( 'business_name', 'WPistic' ),
			'business_phone'=> (string) G2A_CRM_Settings::get( 'business_phone', '' ),
			'business_email'=> (string) G2A_CRM_Settings::get( 'business_email', '' ),
			'site_url'      => home_url( '/' ),
			'waiver_link'   => add_query_arg( array( 'g2a_kiosk' => 1 ), home_url( '/' ) ),
		);

		if ( $customer && ! empty( $customer['id'] ) ) {
			$base['unsubscribe_email_url'] = G2A_CRM_Unsubscribe::build_url( (int) $customer['id'], 'email' );
			$base['unsubscribe_sms_url']   = G2A_CRM_Unsubscribe::build_url( (int) $customer['id'], 'sms' );
		}

		return array_merge( $base, $extra );
	}

	/**
	 * Append an unsubscribe footer to email body if not already present.
	 */
	public static function with_email_footer( $body, $unsub_url ) {
		if ( ! $unsub_url || false !== stripos( (string) $body, 'unsubscribe' ) ) {
			return $body;
		}
		$separator = "\n\n---\n";
		$footer    = sprintf(
			/* translators: %s: unsubscribe URL */
			__( "You're receiving this because you have a profile with us. To stop receiving emails, visit: %s", 'guns2ammo-crm' ),
			esc_url_raw( $unsub_url )
		);
		return $body . $separator . $footer;
	}

	/**
	 * Append the SMS opt-out instruction (carriers require this).
	 */
	public static function with_sms_footer( $body ) {
		if ( false !== stripos( (string) $body, 'reply stop' ) || false !== stripos( (string) $body, 'unsubscribe' ) ) {
			return $body;
		}
		return rtrim( $body, " \n\r\t" ) . ' Reply STOP to unsubscribe.';
	}
}

<?php
/**
 * Outbound webhook dispatcher.
 *
 * Flow:
 *   1. G2A_CRM_Audit_Log::record() fires the `g2a_crm_event` action.
 *   2. on_event() finds active subscribers for the event (or wildcard "*"),
 *      enqueues one delivery row per subscriber.
 *   3. The cron job `g2a_crm_cron_dispatch_webhooks` runs every 5 min, picks
 *      due deliveries (status queued/retrying, next_attempt_at <= now), signs
 *      each payload with the webhook's secret, POSTs it.
 *
 * Signature:
 *   X-G2A-CRM-Signature: sha256=<hex hmac of timestamp + "." + body>
 *   X-G2A-CRM-Timestamp: <unix seconds>
 *   X-G2A-CRM-Event: <event name>
 *   X-G2A-CRM-Delivery: <delivery id>
 *
 * Receivers should recompute the HMAC over `{timestamp}.{raw body}` using
 * their shared secret and reject requests with a stale timestamp (> 5 min skew)
 * to prevent replay.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Webhook_Dispatcher {

	const CRON_HOOK = 'g2a_crm_cron_dispatch_webhooks';

	const BATCH_SIZE = 25;

	const TIMEOUT_SECONDS = 10;

	public static function boot() {
		add_action( 'g2a_crm_event', array( __CLASS__, 'on_event' ), 10, 6 );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'g2a_crm_five_minutes', self::CRON_HOOK );
		}
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_dispatch' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedule' ) );
	}

	public static function add_cron_schedule( $schedules ) {
		if ( ! isset( $schedules['g2a_crm_five_minutes'] ) ) {
			$schedules['g2a_crm_five_minutes'] = array(
				'interval' => 300,
				'display'  => __( 'Every 5 minutes (G2A CRM)', 'guns2ammo-crm' ),
			);
		}
		return $schedules;
	}

	/**
	 * React to an audit-log event by enqueueing a delivery per subscriber.
	 */
	public static function on_event( $action, $object_type, $object_id, $old_value, $new_value, $actor_id ) {
		// Read-only audit entries (e.g. sensitive_record.read_*) should not fan
		// out — they're forensics, not state changes. Filter them.
		if ( false !== strpos( $action, '.read_' ) ) {
			return;
		}

		$subscribers = G2A_CRM_Webhooks_Repository::subscribers_for( $action );
		if ( empty( $subscribers ) ) {
			return;
		}

		$payload = array(
			'event'       => $action,
			'object_type' => $object_type,
			'object_id'   => (int) $object_id,
			'actor_id'    => (int) $actor_id,
			'occurred_at' => g2a_crm_now(),
			'old'         => $old_value,
			'new'         => $new_value,
			'site_url'    => home_url( '/' ),
		);

		foreach ( $subscribers as $webhook ) {
			G2A_CRM_Webhook_Deliveries_Repository::enqueue(
				(int) $webhook['id'],
				$action,
				$webhook['target_url'],
				$payload
			);
		}
	}

	/**
	 * Cron entrypoint. Picks up due deliveries and sends them.
	 */
	public static function run_dispatch() {
		$due = G2A_CRM_Webhook_Deliveries_Repository::due( self::BATCH_SIZE );
		foreach ( $due as $delivery ) {
			self::send_one( $delivery );
		}
	}

	/**
	 * Send one delivery now. Used by both the cron worker and the "test" REST
	 * endpoint.
	 *
	 * @param array $delivery Row from webhook_deliveries.
	 * @return array{code:?int,body:?string,error:?string}
	 */
	public static function send_one( array $delivery ) {
		$webhook = G2A_CRM_Webhooks_Repository::find( (int) $delivery['webhook_id'] );
		if ( ! $webhook ) {
			G2A_CRM_Webhook_Deliveries_Repository::mark_failure(
				(int) $delivery['id'],
				0,
				null,
				'Webhook subscriber no longer exists.'
			);
			return array( 'code' => 0, 'body' => null, 'error' => 'Webhook missing' );
		}

		$body      = is_string( $delivery['payload'] ) ? $delivery['payload'] : wp_json_encode( $delivery['payload'] );
		$timestamp = (string) time();
		$signature = self::sign( $body, $timestamp, $webhook['secret'] );

		$args = array(
			'method'      => 'POST',
			'timeout'     => self::TIMEOUT_SECONDS,
			'redirection' => 3,
			'headers'     => array(
				'Content-Type'         => 'application/json',
				'User-Agent'           => 'G2A-CRM-Webhook/1.0',
				'X-G2A-CRM-Event'      => $delivery['event'],
				'X-G2A-CRM-Delivery'   => (string) $delivery['id'],
				'X-G2A-CRM-Timestamp'  => $timestamp,
				'X-G2A-CRM-Signature'  => 'sha256=' . $signature,
			),
			'body'        => $body,
		);

		$response = wp_remote_post( $delivery['target_url'], $args );

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			G2A_CRM_Webhook_Deliveries_Repository::mark_failure( (int) $delivery['id'], 0, null, $error );
			G2A_CRM_Webhooks_Repository::record_attempt( (int) $webhook['id'], 'failed', g2a_crm_now() );
			return array( 'code' => 0, 'body' => null, 'error' => $error );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$resp = wp_remote_retrieve_body( $response );

		if ( $code >= 200 && $code < 300 ) {
			G2A_CRM_Webhook_Deliveries_Repository::mark_sent( (int) $delivery['id'], $code, $resp );
			G2A_CRM_Webhooks_Repository::record_attempt( (int) $webhook['id'], 'sent', g2a_crm_now() );
			return array( 'code' => $code, 'body' => $resp, 'error' => null );
		}

		$error = sprintf( 'HTTP %d response', $code );
		G2A_CRM_Webhook_Deliveries_Repository::mark_failure( (int) $delivery['id'], $code, $resp, $error );
		G2A_CRM_Webhooks_Repository::record_attempt( (int) $webhook['id'], 'failed', g2a_crm_now() );
		return array( 'code' => $code, 'body' => $resp, 'error' => $error );
	}

	/**
	 * Send a synthetic "ping" event for a single webhook id. Used by the test
	 * button in the UI to verify connectivity + signing.
	 *
	 * @param int $webhook_id
	 * @return array
	 */
	public static function send_test( $webhook_id ) {
		$webhook = G2A_CRM_Webhooks_Repository::find( (int) $webhook_id );
		if ( ! $webhook ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Webhook not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$payload = array(
			'event'       => 'webhook.ping',
			'object_type' => 'webhook',
			'object_id'   => (int) $webhook_id,
			'actor_id'    => get_current_user_id(),
			'occurred_at' => g2a_crm_now(),
			'new'         => array( 'ping' => true ),
			'site_url'    => home_url( '/' ),
		);
		$delivery_id = G2A_CRM_Webhook_Deliveries_Repository::enqueue(
			(int) $webhook_id,
			'webhook.ping',
			$webhook['target_url'],
			$payload
		);
		if ( is_wp_error( $delivery_id ) ) {
			return $delivery_id;
		}
		$delivery = G2A_CRM_Webhook_Deliveries_Repository::find( $delivery_id );
		return self::send_one( $delivery );
	}

	/**
	 * Compute HMAC-SHA256 of "{timestamp}.{body}" using the webhook secret.
	 */
	public static function sign( $body, $timestamp, $secret ) {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, (string) $secret );
	}
}

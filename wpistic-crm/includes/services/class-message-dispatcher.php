<?php
/**
 * Message dispatcher — picks up queued rows and sends them via the right
 * channel adapter.
 *
 * Runs on the `g2a_crm_cron_dispatch` event (default: every 5 minutes). Can
 * also be invoked synchronously by the staff "send now" REST action.
 *
 * Opt-in check happens here, not upstream, so any code path that queues a
 * message can stay simple and the dispatcher is the single decision point on
 * whether a customer should actually receive.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Message_Dispatcher {

	const HOOK              = 'g2a_crm_cron_dispatch';
	const SCHEDULE_NAME     = 'g2a_crm_every_5_min';
	const SCHEDULE_INTERVAL = 300;

	public static function boot() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 120, self::SCHEDULE_NAME, self::HOOK );
		}
	}

	public static function unschedule() {
		$next = wp_next_scheduled( self::HOOK );
		while ( $next ) {
			wp_unschedule_event( $next, self::HOOK );
			$next = wp_next_scheduled( self::HOOK );
		}
	}

	public static function register_schedule( $schedules ) {
		$schedules[ self::SCHEDULE_NAME ] = array(
			'interval' => self::SCHEDULE_INTERVAL,
			'display'  => __( 'Every 5 minutes (G2A CRM dispatcher)', 'guns2ammo-crm' ),
		);
		return $schedules;
	}

	/**
	 * Claim and send a batch. Returns the count of rows processed.
	 */
	public static function run( $limit = 25 ) {
		$batch = G2A_CRM_Messages_Repository::claim_batch( (int) $limit );
		if ( empty( $batch ) ) {
			return 0;
		}

		$processed = 0;
		foreach ( $batch as $message ) {
			self::dispatch_one( $message );
			$processed++;
		}
		return $processed;
	}

	/**
	 * Send a single message row that has already been claimed (status=sending).
	 */
	public static function dispatch_one( array $message ) {
		$id = (int) $message['id'];

		$customer = null;
		if ( ! empty( $message['customer_id'] ) ) {
			$customer = G2A_CRM_Customers_Repository::find( (int) $message['customer_id'] );
		}

		// Opt-out gate.
		if ( $customer && self::is_opted_out( $customer, $message['channel'] ) ) {
			self::skip( $id, 'Recipient is opted out of this channel.' );
			return;
		}

		$channel = $message['channel'];

		// Reminder rows are channel-agnostic at queue time. Pick a delivery
		// channel based on customer preference + opt-in state.
		if ( 'reminder' === $channel && $customer ) {
			$channel = self::choose_channel_for( $customer );
			if ( ! $channel ) {
				self::skip( $id, 'No usable channel (customer has no opt-in for email or SMS).' );
				return;
			}
		}

		// Render variables from any context the queuer passed via subject/body.
		$ctx = $customer ? G2A_CRM_Message_Renderer::context_for_customer( $customer ) : array();
		$rendered_subject = G2A_CRM_Message_Renderer::render( $message['subject'], $ctx );
		$rendered_body    = G2A_CRM_Message_Renderer::render( $message['body'], $ctx );

		// Decide recipient address.
		$to = '';
		if ( ! empty( $message['to_address'] ) ) {
			$to = $message['to_address'];
		} elseif ( $customer ) {
			$to = 'sms' === $channel ? $customer['phone'] : $customer['email'];
		}
		if ( '' === $to ) {
			self::skip( $id, 'No recipient address.' );
			return;
		}

		// Suppression gate. A hard bounce / complaint / unsubscribe / manual
		// suppression must not be re-tried — sending again risks ISP /
		// carrier reputation penalties.
		if ( G2A_CRM_Suppressions_Repository::is_suppressed( $channel, $to ) ) {
			self::skip( $id, 'Recipient is on the suppression list (' . $channel . ').' );
			return;
		}

		// Channel-specific footers.
		if ( 'email' === $channel ) {
			$unsub_url     = $customer ? G2A_CRM_Unsubscribe::build_url( (int) $customer['id'], 'email' ) : '';
			$rendered_body = G2A_CRM_Message_Renderer::with_email_footer( $rendered_body, $unsub_url );
		} elseif ( 'sms' === $channel ) {
			$rendered_body = G2A_CRM_Message_Renderer::with_sms_footer( $rendered_body );
		}

		// Persist the rendered output before we hand off to the network — so
		// retries can show what was attempted.
		global $wpdb;
		$wpdb->update(
			G2A_CRM_Messages_Repository::table(),
			array(
				'channel'          => $channel,
				'rendered_subject' => $rendered_subject,
				'rendered_body'    => $rendered_body,
				'to_address'       => $to,
			),
			array( 'id' => $id )
		);

		// Hand off to the right adapter.
		if ( 'email' === $channel ) {
			$result = G2A_CRM_Email_Sender::send( $to, $rendered_subject, $rendered_body );
		} elseif ( 'sms' === $channel ) {
			$result = G2A_CRM_SMS_Sender::send( $to, $rendered_body );
		} elseif ( 'internal' === $channel ) {
			$result = array( 'ok' => true, 'provider' => 'internal', 'provider_message_id' => 'internal:' . $id );
		} else {
			$result = array( 'ok' => false, 'error' => 'Unknown channel: ' . $channel );
		}

		if ( ! empty( $result['ok'] ) ) {
			G2A_CRM_Messages_Repository::mark_sent(
				$id,
				$result['provider'] ?? $channel,
				$result['provider_message_id'] ?? ''
			);
			G2A_CRM_Audit_Log::record(
				'message.sent',
				'message',
				$id,
				null,
				array(
					'channel'             => $channel,
					'provider'            => $result['provider'] ?? $channel,
					'provider_message_id' => $result['provider_message_id'] ?? '',
				)
			);
		} else {
			$retryable = ! empty( $result['retryable'] );
			G2A_CRM_Messages_Repository::mark_failed( $id, $result['error'] ?? 'Unknown error', $retryable );
			// Permanent failure → auto-suppress so future messages don't
			// keep failing into the same dead address. Operators can
			// remove suppressions manually if a transient issue was
			// misclassified.
			if ( ! $retryable ) {
				G2A_CRM_Suppressions_Repository::add(
					$channel,
					$to,
					substr( (string) ( $result['error'] ?? 'Send failed' ), 0, 250 ),
					'send_failure'
				);
			}
		}
	}

	private static function skip( $id, $reason ) {
		global $wpdb;
		$wpdb->update(
			G2A_CRM_Messages_Repository::table(),
			array(
				'status'     => 'skipped',
				'error_text' => substr( (string) $reason, 0, 2000 ),
			),
			array( 'id' => (int) $id )
		);
		G2A_CRM_Audit_Log::record( 'message.skipped', 'message', (int) $id, null, array( 'reason' => $reason ) );
	}

	private static function is_opted_out( array $customer, $channel ) {
		if ( 'email' === $channel ) {
			return empty( $customer['email_opt_in'] );
		}
		if ( 'sms' === $channel ) {
			return empty( $customer['sms_opt_in'] );
		}
		if ( 'reminder' === $channel ) {
			// reminder needs *some* channel available.
			return empty( $customer['email_opt_in'] ) && empty( $customer['sms_opt_in'] );
		}
		return false;
	}

	private static function choose_channel_for( array $customer ) {
		$pref = $customer['preferred_contact'] ?? 'email';
		if ( 'sms' === $pref && ! empty( $customer['sms_opt_in'] ) && ! empty( $customer['phone'] ) ) {
			return 'sms';
		}
		if ( ! empty( $customer['email_opt_in'] ) && ! empty( $customer['email'] ) ) {
			return 'email';
		}
		if ( ! empty( $customer['sms_opt_in'] ) && ! empty( $customer['phone'] ) ) {
			return 'sms';
		}
		return '';
	}
}

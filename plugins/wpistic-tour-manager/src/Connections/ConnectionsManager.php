<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Connections;

use Wpistic\TourCore\Support\Signature;

/**
 * Generic 3rd-party Connections engine. The client adds their own outbound webhook
 * targets (URL + secret + events); the plugin posts HMAC-signed JSON on each event,
 * logs the result, and retries on failure. TourFlows is just one configured connection.
 */
final class ConnectionsManager {

	private const EVENTS = array(
		'inquiry.created',
		'booking.deposit_link',
		'booking.deposit_paid',
		'booking.confirmed',
		'booking.balance_link',
		'booking.balance_paid',
	);

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 21 );
		add_action( 'admin_post_wpistic_tm_save_connection', array( $this, 'save' ) );
		add_action( 'admin_post_wpistic_tm_delete_connection', array( $this, 'delete' ) );
		add_action( 'wpistic_tm_connection_retry', array( $this, 'retry' ), 10, 4 );
		add_action( 'wpistic/notify', array( $this, 'dispatch_notification' ) );
	}

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpistic_connections';
	}

	public function dispatch( string $event, array $payload ): void {
		foreach ( $this->enabled_for( $event ) as $connection ) {
			$this->send( $connection, $event, $payload, 1 );
		}
	}

	/**
	 * Forward booking lifecycle notifications to configured outbound webhooks.
	 *
	 * @param array<string, mixed> $payload Notification payload.
	 * @return void
	 */
	public function dispatch_notification( array $payload ): void {
		$event = sanitize_text_field( (string) ( $payload['event'] ?? '' ) );
		if ( ! in_array( $event, self::EVENTS, true ) || 'inquiry.created' === $event ) {
			return;
		}

		$this->dispatch( $event, $payload );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function enabled_for( string $event ): array {
		$out = array();
		foreach ( $this->all() as $connection ) {
			$events = json_decode( (string) $connection['events'], true );
			if ( ! empty( $connection['enabled'] ) && is_array( $events ) && in_array( $event, $events, true ) ) {
				$out[] = $connection;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $connection
	 */
	private function send( array $connection, string $event, array $payload, int $attempt ): void {
		$body    = (string) wp_json_encode( array( 'event' => $event, 'data' => $payload, 'sent_at' => gmdate( 'c' ) ) );
		$headers = array( 'Content-Type' => 'application/json' );
		if ( ! empty( $connection['secret'] ) ) {
			$headers['X-Wpistic-Signature'] = 'sha256=' . Signature::sign( $body, (string) $connection['secret'] );
		}

		$response = wp_remote_post(
			(string) $connection['target_url'],
			array( 'headers' => $headers, 'body' => $body, 'timeout' => 12 )
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$note = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
		$this->log( (int) $connection['id'], $event, $code, (string) $note, $attempt );

		if ( ( $code < 200 || $code >= 300 ) && $attempt < 3 ) {
			wp_schedule_single_event( time() + ( 60 * $attempt * $attempt ), 'wpistic_tm_connection_retry', array( (int) $connection['id'], $event, $payload, $attempt + 1 ) );
		}
	}

	public function retry( $connection_id, $event, $payload, $attempt ): void {
		$connection = $this->get( (int) $connection_id );
		if ( $connection && ! empty( $connection['enabled'] ) ) {
			$this->send( $connection, (string) $event, (array) $payload, (int) $attempt );
		}
	}

	private function log( int $connection_id, string $event, int $code, string $response, int $attempt ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wpistic_connection_log',
			array(
				'connection_id' => $connection_id,
				'event'         => $event,
				'status_code'   => $code,
				'response'      => substr( $response, 0, 2000 ),
				'attempts'      => $attempt,
				'created_at'    => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$this->table()} ORDER BY id DESC", ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ?: null;
	}

	public function menu(): void {
		add_submenu_page(
			'wpistic-tour-manager',
			__( 'Connections', 'wpistic-tour-manager' ),
			__( 'Connections', 'wpistic-tour-manager' ),
			'manage_options',
			'wpistic-tm-connections',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connections', 'wpistic-tour-manager' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Send booking and inquiry events to any 3rd-party tool (TourFlows, a CRM, Zapier, n8n…) via signed webhooks.', 'wpistic-tour-manager' ); ?></p>

			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Name', 'wpistic-tour-manager' ); ?></th><th><?php esc_html_e( 'Target URL', 'wpistic-tour-manager' ); ?></th><th><?php esc_html_e( 'Events', 'wpistic-tour-manager' ); ?></th><th><?php esc_html_e( 'Enabled', 'wpistic-tour-manager' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $this->all() as $connection ) : ?>
					<tr>
						<td><?php echo esc_html( $connection['name'] ); ?></td>
						<td><code><?php echo esc_html( $connection['target_url'] ); ?></code></td>
						<td><?php echo esc_html( implode( ', ', (array) json_decode( (string) $connection['events'], true ) ) ); ?></td>
						<td><?php echo $connection['enabled'] ? '✓' : '—'; ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Delete this connection?', 'wpistic-tour-manager' ); ?>');">
								<input type="hidden" name="action" value="wpistic_tm_delete_connection">
								<input type="hidden" name="id" value="<?php echo (int) $connection['id']; ?>">
								<?php wp_nonce_field( 'wpistic_tm_connection' ); ?>
								<button class="button-link-delete"><?php esc_html_e( 'Delete', 'wpistic-tour-manager' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Add a connection', 'wpistic-tour-manager' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpistic_tm_save_connection">
				<?php wp_nonce_field( 'wpistic_tm_connection' ); ?>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Name', 'wpistic-tour-manager' ); ?></th><td><input type="text" name="name" class="regular-text" required></td></tr>
					<tr><th><?php esc_html_e( 'Target URL', 'wpistic-tour-manager' ); ?></th><td><input type="url" name="target_url" class="regular-text" required></td></tr>
					<tr><th><?php esc_html_e( 'Signing secret', 'wpistic-tour-manager' ); ?></th><td><input type="text" name="secret" class="regular-text"> <span class="description"><?php esc_html_e( 'HMAC-SHA256 in X-Wpistic-Signature.', 'wpistic-tour-manager' ); ?></span></td></tr>
					<tr><th><?php esc_html_e( 'Events', 'wpistic-tour-manager' ); ?></th><td>
						<?php foreach ( self::EVENTS as $event ) : ?>
							<label style="display:block;"><input type="checkbox" name="events[]" value="<?php echo esc_attr( $event ); ?>"> <?php echo esc_html( $event ); ?></label>
						<?php endforeach; ?>
					</td></tr>
					<tr><th><?php esc_html_e( 'Enabled', 'wpistic-tour-manager' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" checked> <?php esc_html_e( 'Active', 'wpistic-tour-manager' ); ?></label></td></tr>
				</table>
				<?php submit_button( __( 'Add connection', 'wpistic-tour-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'wpistic_tm_connection' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpistic-tour-manager' ) );
		}
		global $wpdb;

		$events = isset( $_POST['events'] ) && is_array( $_POST['events'] )
			? array_values( array_intersect( self::EVENTS, array_map( 'sanitize_text_field', wp_unslash( $_POST['events'] ) ) ) )
			: array();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'name'       => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
				'type'       => 'webhook',
				'target_url' => esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) ),
				'secret'     => sanitize_text_field( wp_unslash( $_POST['secret'] ?? '' ) ),
				'events'     => wp_json_encode( $events ),
				'enabled'    => isset( $_POST['enabled'] ) ? 1 : 0,
				'created_at' => current_time( 'mysql', true ),
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=wpistic-tm-connections' ) );
		exit;
	}

	public function delete(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'wpistic_tm_connection' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpistic-tour-manager' ) );
		}
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'id' => absint( $_POST['id'] ?? 0 ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_safe_redirect( admin_url( 'admin.php?page=wpistic-tm-connections' ) );
		exit;
	}
}

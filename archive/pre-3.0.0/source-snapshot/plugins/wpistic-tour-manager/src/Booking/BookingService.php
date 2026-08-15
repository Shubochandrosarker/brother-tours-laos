<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Booking;

use Wpistic\TourCore\Booking\BookingStateMachine;
use Wpistic\TourCore\Booking\BookingStatus;
use Wpistic\TourCore\Payment\PaymentGateway;
use Wpistic\TourCore\Payment\PaymentRequest;
use Wpistic\TourCore\Payment\PaymentResult;
use Wpistic\TourCore\Payment\PaymentStatus;
use Wpistic\TourCore\Pricing\DepositCalculator;
use Wpistic\TourCore\Pricing\DepositPolicy;
use Wpistic\TourCore\Pricing\DepositType;
use Wpistic\TourCore\Support\Currency;
use Wpistic\TourCore\Support\Money;

/**
 * Orchestrates bookings: persistence, the (core) state machine, (core) deposit
 * calculation, gateway payment links, capacity, the transactions ledger, and the
 * immutable audit log. No payment-provider or pricing rules live here — those are
 * in the framework-agnostic core.
 */
final class BookingService {

	private BookingStateMachine $machine;
	private DepositCalculator $calculator;

	public function __construct() {
		$this->machine    = new BookingStateMachine();
		$this->calculator = new DepositCalculator();
	}

	private function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'wpistic_' . $name;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return int Booking id.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$now       = current_time( 'mysql', true );
		$reference = $this->next_reference();

		$row = array(
			'reference'        => $reference,
			'type'             => sanitize_key( (string) ( $data['type'] ?? 'inquiry' ) ),
			'status'           => BookingStatus::Inquiry->value,
			'portal_status'    => 'new',
			'tour_id'          => isset( $data['tour_id'] ) ? absint( $data['tour_id'] ) : null,
			'departure_id'     => isset( $data['departure_id'] ) ? absint( $data['departure_id'] ) : null,
			'customer_name'    => sanitize_text_field( (string) ( $data['customer_name'] ?? '' ) ),
			'customer_email'   => sanitize_email( (string) ( $data['customer_email'] ?? '' ) ),
			'customer_phone'   => sanitize_text_field( (string) ( $data['customer_phone'] ?? '' ) ),
			'customer_country' => sanitize_text_field( (string) ( $data['customer_country'] ?? '' ) ),
			'party_adults'     => isset( $data['party_adults'] ) ? absint( $data['party_adults'] ) : null,
			'party_children'   => sanitize_text_field( (string) ( $data['party_children'] ?? '' ) ),
			'hotel_pref'       => sanitize_text_field( (string) ( $data['hotel_pref'] ?? '' ) ),
			'addons'           => isset( $data['addons'] ) ? wp_json_encode( $data['addons'] ) : null,
			'special_requests' => sanitize_textarea_field( (string) ( $data['special_requests'] ?? '' ) ),
			'currency'         => sanitize_text_field( (string) ( $data['currency'] ?? get_option( 'wpistic_tm_currency', 'USD' ) ) ),
			'source_url'       => esc_url_raw( (string) ( $data['source_url'] ?? '' ) ),
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$inserted = $wpdb->insert( $this->table( 'bookings' ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = (int) $wpdb->insert_id;
		if ( false === $inserted || $id <= 0 ) {
			return 0;
		}
		$this->audit( 'booking', $id, 'created', array( 'reference' => $reference, 'type' => $row['type'] ) );

		return $id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table( 'bookings' )} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ?: null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_by_reference( string $reference ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table( 'bookings' )} WHERE reference = %s", $reference ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ?: null;
	}

	public function transition( int $id, BookingStatus $to ): bool {
		$row = $this->get( $id );
		if ( ! $row ) {
			return false;
		}
		$from = BookingStatus::tryFrom( (string) $row['status'] ) ?? BookingStatus::Inquiry;
		if ( $from === $to ) {
			return true;
		}
		if ( ! $this->machine->canTransition( $from, $to ) ) {
			return false;
		}
		$this->set_status( $id, $to );
		$this->audit( 'booking', $id, 'status', array( 'from' => $from->value, 'to' => $to->value ) );
		return true;
	}

	/**
	 * Admin-facing lifecycle actions. These wrap the strict core state machine so
	 * staff can run the booking through the real business flow from the portal.
	 */
	public function apply_lifecycle_action( int $id, string $action ): bool {
		$row = $this->get( $id );
		if ( ! $row ) {
			return false;
		}

		$result = match ( $action ) {
			'quote'             => $this->transition( $id, BookingStatus::Quoted ),
			'mark_deposit_paid' => $this->manual_mark_deposit_paid( $id ),
			'confirm'           => $this->transition( $id, BookingStatus::Confirmed ),
			'balance_due'       => $this->transition( $id, BookingStatus::BalanceDue ),
			'mark_balance_paid' => $this->manual_mark_balance_paid( $id ),
			'complete'          => $this->transition( $id, BookingStatus::Completed ),
			'expire'            => $this->transition( $id, BookingStatus::Expired ),
			'refund'            => $this->transition( $id, BookingStatus::Refunded ),
			'cancel'            => $this->transition( $id, BookingStatus::Cancelled ),
			default             => false,
		};

		if ( $result && 'confirm' === $action ) {
			do_action( 'wpistic/notify', array( 'event' => 'booking.confirmed', 'booking' => $this->get( $id ) ) );
		}

		return $result;
	}

	/**
	 * @return array<string, string> Action => label.
	 */
	public function lifecycle_actions( int $id ): array {
		$row = $this->get( $id );
		if ( ! $row ) {
			return array();
		}

		$current = BookingStatus::tryFrom( (string) $row['status'] ) ?? BookingStatus::Inquiry;
		$actions = array();
		foreach ( $this->machine->allowedFrom( $current ) as $status ) {
			if ( BookingStatus::DepositLinkSent === $status ) {
				continue;
			}
			$actions[ $this->action_for_status( $status ) ] = $status->label();
		}

		if ( in_array( $current, array( BookingStatus::Inquiry, BookingStatus::Quoted, BookingStatus::DepositLinkSent ), true ) ) {
			$actions['mark_deposit_paid'] = __( 'Mark deposit paid', 'wpistic-tour-manager' );
		}
		if ( in_array( $current, array( BookingStatus::Confirmed, BookingStatus::BalanceDue ), true ) ) {
			$actions['mark_balance_paid'] = __( 'Mark balance paid', 'wpistic-tour-manager' );
		}

		return $actions;
	}

	private function action_for_status( BookingStatus $status ): string {
		return match ( $status ) {
			BookingStatus::Quoted          => 'quote',
			BookingStatus::DepositPaid     => 'mark_deposit_paid',
			BookingStatus::Confirmed       => 'confirm',
			BookingStatus::BalanceDue      => 'balance_due',
			BookingStatus::PaidInFull      => 'mark_balance_paid',
			BookingStatus::Completed       => 'complete',
			BookingStatus::Expired         => 'expire',
			BookingStatus::Refunded        => 'refund',
			BookingStatus::Cancelled       => 'cancel',
			BookingStatus::DepositLinkSent => 'deposit_link_sent',
			BookingStatus::Inquiry         => 'inquiry',
		};
	}

	private function manual_mark_deposit_paid( int $id ): bool {
		$this->mark_deposit_paid( $id, 'manual-' . gmdate( 'YmdHis' ) );
		return true;
	}

	private function manual_mark_balance_paid( int $id ): bool {
		$this->mark_balance_paid( $id, 'manual-' . gmdate( 'YmdHis' ) );
		return true;
	}

	private function set_status( int $id, BookingStatus $to ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table( 'bookings' ),
			array( 'status' => $to->value, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id )
		);
	}

	public function set_portal_status( int $id, string $status ): void {
		global $wpdb;
		$allowed = array( 'new', 'reviewed', 'sent', 'closed' );
		$status  = in_array( $status, $allowed, true ) ? $status : 'new';
		$wpdb->update( $this->table( 'bookings' ), array( 'portal_status' => $status ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->audit( 'booking', $id, 'portal_status', array( 'to' => $status ) );
	}

	public function assign( int $id, int $user_id ): void {
		global $wpdb;
		$wpdb->update( $this->table( 'bookings' ), array( 'assigned_to' => $user_id ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Compute the deposit for a tour and price using the core calculator and the
	 * tour's policy (per-tour override, else the global default).
	 */
	public function deposit_for( int $tour_id, Money $price ): Money {
		return $this->calculator->calculate( $price, $this->deposit_policy( $tour_id, $price->currency() ) );
	}

	private function deposit_policy( int $tour_id, Currency $currency ): DepositPolicy {
		$type  = (string) get_post_meta( $tour_id, 'wpistic_deposit_type', true );
		$value = (string) get_post_meta( $tour_id, 'wpistic_deposit_value', true );

		if ( '' === $type ) {
			$type = (string) get_option( 'wpistic_tm_deposit_type', 'percent' );
		}
		if ( '' === $value ) {
			$value = (string) get_option( 'wpistic_tm_deposit_value', '30' );
		}

		$min = (string) get_post_meta( $tour_id, 'wpistic_deposit_min', true );
		$max = (string) get_post_meta( $tour_id, 'wpistic_deposit_max', true );

		return new DepositPolicy(
			DepositType::fromString( $type ),
			$value,
			'' !== $min ? Money::of( $min, $currency ) : null,
			'' !== $max ? Money::of( $max, $currency ) : null
		);
	}

	public function currency( string $code ): Currency {
		$code = strtoupper( '' !== $code ? $code : 'USD' );
		return match ( $code ) {
			'BTC'   => Currency::btc(),
			'USDT'  => Currency::usdt(),
			'BNB'   => Currency::bnb(),
			'EUR'   => Currency::of( 'EUR', 2, Currency::TYPE_FIAT, '€' ),
			default => Currency::usd(),
		};
	}

	/**
	 * Quote the deposit, create a payment with the gateway, record the transaction,
	 * and advance the booking to "deposit link sent".
	 */
	public function send_deposit_link( int $id, PaymentGateway $gateway ): ?PaymentResult {
		$row = $this->get( $id );
		if ( ! $row ) {
			return null;
		}

		$currency = $this->currency( (string) $row['currency'] );
		$price    = $this->resolve_price( $row, $currency );
		if ( null === $price ) {
			return null;
		}

		$deposit = $this->deposit_for( (int) $row['tour_id'], $price );
		$balance = $price->subtract( $deposit );

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table( 'bookings' ),
			array(
				'price_amount'   => $price->amount(),
				'deposit_amount' => $deposit->amount(),
				'balance_amount' => $balance->amount(),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);

		$request = new PaymentRequest(
			(string) $row['reference'],
			$deposit,
			sprintf( __( 'Deposit for booking %s', 'wpistic-tour-manager' ), $row['reference'] ),
			'deposit',
			home_url( '/thank-you/' ),
			home_url( '/' ),
			(string) $row['reference'] . '-dep',
			(string) $row['customer_email']
		);

		$result = $gateway->createPayment( $request );

		$this->write_transaction(
			$id,
			$gateway->id(),
			$result->gatewayReference,
			'deposit',
			$deposit,
			$result->status->value,
			$request->idempotencyKey
		);

		if ( ! in_array( $result->status, array( PaymentStatus::Pending, PaymentStatus::Paid ), true ) ) {
			$this->audit( 'booking', $id, 'deposit_link_failed', array( 'gateway' => $gateway->id(), 'status' => $result->status->value ) );
			return null;
		}

		// inquiry -> quoted -> deposit_link_sent
		$current = BookingStatus::tryFrom( (string) $row['status'] ) ?? BookingStatus::Inquiry;
		if ( BookingStatus::Inquiry === $current ) {
			$this->transition( $id, BookingStatus::Quoted );
		}
		$this->transition( $id, BookingStatus::DepositLinkSent );

		$this->audit( 'booking', $id, 'deposit_link', array( 'gateway' => $gateway->id(), 'amount' => $deposit->amount() ) );

		do_action(
			'wpistic/notify',
			array(
				'event'   => 'booking.deposit_link',
				'booking' => $this->get( $id ),
				'link'    => '' !== $result->redirectUrl ? $result->redirectUrl : $result->instructions,
			)
		);

		return $result;
	}

	/**
	 * Create and email a balance payment link once the trip is confirmed.
	 */
	public function send_balance_link( int $id, PaymentGateway $gateway ): ?PaymentResult {
		$row = $this->get( $id );
		if ( ! $row ) {
			return null;
		}

		$currency = $this->currency( (string) $row['currency'] );
		$balance  = $this->resolve_balance( $row, $currency );
		if ( null === $balance || ! $balance->isPositive() ) {
			return null;
		}

		$request = new PaymentRequest(
			(string) $row['reference'],
			$balance,
			sprintf( __( 'Balance for booking %s', 'wpistic-tour-manager' ), $row['reference'] ),
			'balance',
			home_url( '/thank-you/' ),
			home_url( '/' ),
			(string) $row['reference'] . '-bal',
			(string) $row['customer_email']
		);

		$result = $gateway->createPayment( $request );

		$this->write_transaction(
			$id,
			$gateway->id(),
			$result->gatewayReference,
			'balance',
			$balance,
			$result->status->value,
			$request->idempotencyKey
		);

		if ( ! in_array( $result->status, array( PaymentStatus::Pending, PaymentStatus::Paid ), true ) ) {
			$this->audit( 'booking', $id, 'balance_link_failed', array( 'gateway' => $gateway->id(), 'status' => $result->status->value ) );
			return null;
		}

		$current = BookingStatus::tryFrom( (string) $row['status'] ) ?? BookingStatus::Inquiry;
		if ( BookingStatus::Confirmed === $current ) {
			$this->transition( $id, BookingStatus::BalanceDue );
		}

		$this->audit( 'booking', $id, 'balance_link', array( 'gateway' => $gateway->id(), 'amount' => $balance->amount() ) );

		do_action(
			'wpistic/notify',
			array(
				'event'   => 'booking.balance_link',
				'booking' => $this->get( $id ),
				'link'    => '' !== $result->redirectUrl ? $result->redirectUrl : $result->instructions,
			)
		);

		return $result;
	}

	/**
	 * Record a confirmed deposit payment: advance status, decrement capacity, mark
	 * the transaction paid, and fire notifications.
	 */
	public function mark_deposit_paid( int $id, string $gateway_txn_ref = '' ): void {
		$row = $this->get( $id );
		if ( ! $row ) {
			return;
		}

		$current     = BookingStatus::tryFrom( (string) $row['status'] ) ?? BookingStatus::Inquiry;
		$held_before = $current->holdsCapacity();
		if ( BookingStatus::DepositPaid !== $current && ! $current->holdsCapacity() ) {
			if ( BookingStatus::Inquiry === $current ) {
				$this->transition( $id, BookingStatus::Quoted );
			}
			$mid = BookingStatus::tryFrom( (string) ( $this->get( $id )['status'] ?? '' ) );
			if ( BookingStatus::Quoted === $mid ) {
				$this->transition( $id, BookingStatus::DepositLinkSent );
			}
			$this->transition( $id, BookingStatus::DepositPaid );
		}

		$this->mark_transaction_paid( $id, 'deposit', $gateway_txn_ref );

		$latest = BookingStatus::tryFrom( (string) ( $this->get( $id )['status'] ?? '' ) ) ?? $current;
		if ( ! $held_before && $latest->holdsCapacity() ) {
			$this->decrement_capacity( (int) ( $row['departure_id'] ?? 0 ) );
			do_action( 'wpistic/notify', array( 'event' => 'booking.deposit_paid', 'booking' => $this->get( $id ) ) );
		}
	}

	public function mark_balance_paid( int $id, string $gateway_txn_ref = '' ): void {
		$row = $this->get( $id );
		if ( ! $row ) {
			return;
		}

		$current = BookingStatus::tryFrom( (string) $row['status'] ) ?? BookingStatus::Inquiry;
		if ( BookingStatus::Confirmed === $current ) {
			$this->transition( $id, BookingStatus::BalanceDue );
		}
		$latest = BookingStatus::tryFrom( (string) ( $this->get( $id )['status'] ?? '' ) );
		if ( BookingStatus::BalanceDue === $latest ) {
			$this->transition( $id, BookingStatus::PaidInFull );
		}

		$this->mark_transaction_paid( $id, 'balance', $gateway_txn_ref );

		do_action( 'wpistic/notify', array( 'event' => 'booking.balance_paid', 'booking' => $this->get( $id ) ) );
	}

	private function resolve_price( array $row, Currency $currency ): ?Money {
		if ( ! empty( $row['price_amount'] ) ) {
			return Money::of( (string) $row['price_amount'], $currency );
		}
		$from = (string) get_post_meta( (int) ( $row['tour_id'] ?? 0 ), 'wpistic_from_price', true );
		return '' !== $from ? Money::of( $from, $currency ) : null;
	}

	private function resolve_balance( array $row, Currency $currency ): ?Money {
		if ( ! empty( $row['balance_amount'] ) ) {
			return Money::of( (string) $row['balance_amount'], $currency );
		}
		$price = $this->resolve_price( $row, $currency );
		if ( null === $price ) {
			return null;
		}
		$deposit = $this->deposit_for( (int) $row['tour_id'], $price );
		$balance = $price->subtract( $deposit );

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table( 'bookings' ),
			array(
				'price_amount'   => $price->amount(),
				'deposit_amount' => $deposit->amount(),
				'balance_amount' => $balance->amount(),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $row['id'] )
		);

		return $balance;
	}

	private function decrement_capacity( int $departure_id ): void {
		if ( $departure_id <= 0 ) {
			return;
		}
		$left = (int) get_post_meta( $departure_id, 'wpistic_dep_seats_left', true );
		$left = max( 0, $left - 1 );
		update_post_meta( $departure_id, 'wpistic_dep_seats_left', $left );
		if ( 0 === $left ) {
			update_post_meta( $departure_id, 'wpistic_dep_status', 'closed' );
		}
	}

	private function write_transaction( int $booking_id, string $gateway, string $txn_id, string $type, Money $amount, string $status, string $idempotency_key ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table( 'transactions' ),
			array(
				'booking_id'      => $booking_id,
				'gateway'         => $gateway,
				'gateway_txn_id'  => $txn_id,
				'type'            => $type,
				'amount'          => $amount->amount(),
				'currency'        => $amount->currency()->code(),
				'status'          => $status,
				'idempotency_key' => $idempotency_key,
				'payload_hash'    => '',
				'created_at'      => current_time( 'mysql', true ),
			)
		);
	}

	private function mark_transaction_paid( int $booking_id, string $type, string $txn_ref ): void {
		global $wpdb;
		$updated = $wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"UPDATE {$this->table( 'transactions' )} SET status = 'paid', gateway_txn_id = CASE WHEN %s <> '' THEN %s ELSE gateway_txn_id END WHERE booking_id = %d AND type = %s ORDER BY id DESC LIMIT 1",
				$txn_ref,
				$txn_ref,
				$booking_id,
				$type
			)
		);
		if ( $updated ) {
			return;
		}

		$row = $this->get( $booking_id );
		if ( ! $row ) {
			return;
		}
		$currency = $this->currency( (string) $row['currency'] );
		$amount   = 'balance' === $type ? (string) ( $row['balance_amount'] ?? '' ) : (string) ( $row['deposit_amount'] ?? '' );
		if ( '' === $amount ) {
			$price = $this->resolve_price( $row, $currency );
			if ( null === $price ) {
				return;
			}
			$money = 'balance' === $type ? $this->resolve_balance( $row, $currency ) : $this->deposit_for( (int) $row['tour_id'], $price );
		} else {
			$money = Money::of( $amount, $currency );
		}
		if ( $money ) {
			$this->write_transaction( $booking_id, 'manual', $txn_ref, $type, $money, 'paid', '' );
		}
	}

	/* ==============================================================
	 * Wiring
	 * ============================================================== */

	/**
	 * Hook registration. Deliberately called unconditionally from
	 * Plugin::boot() (not only under is_admin()): `wpistic_tm_connection_dispatched`
	 * fires from front-end requests too (e.g. a Formistic submission dispatching
	 * `inquiry.created` on the public site), so the listener that records dispatch
	 * history into the audit log must be live outside wp-admin as well.
	 */
	public function register(): void {
		add_action( 'wpistic_tm_connection_dispatched', array( $this, 'record_connection_dispatch' ), 10, 5 );
	}

	/**
	 * Listener for `wpistic_tm_connection_dispatched`, fired by
	 * ConnectionsManager::send() right after it writes to wpistic_connection_log.
	 *
	 * wpistic_connection_log has no booking_id column, so a booking's connection
	 * delivery history cannot be read from that table directly. Rather than add a
	 * schema migration, every dispatch is mirrored here into the existing,
	 * already-working audit_log (object_type='booking', action='connection_dispatch').
	 * The admin Connections tab then reads it by filtering the booking's normal
	 * audit-log rows -- no new query, no new table.
	 */
	public function record_connection_dispatch( int $booking_id, string $event, int $status_code, int $connection_id, string $target_url ): void {
		if ( $booking_id <= 0 ) {
			return;
		}
		$this->audit(
			'booking',
			$booking_id,
			'connection_dispatch',
			array(
				'event'         => $event,
				'status_code'   => $status_code,
				'connection_id' => $connection_id,
				'target_url'    => $target_url,
			)
		);
	}

	/* ==============================================================
	 * Admin list / dashboard query layer.
	 *
	 * Every dynamic value below is bound through $wpdb->prepare(). Sortable
	 * columns, filter columns, and GROUP BY columns are validated against
	 * fixed allow-lists before being interpolated as identifiers, since
	 * prepare() can only parameterize values, never column/table names.
	 * ============================================================== */

	/** @var array<int, string> */
	private const LIST_TYPES = array( 'build_my_trip', 'booking', 'contact', 'agent', 'inquiry' );

	/** @var array<string, string> Public sort key => real column. */
	private const SORTABLE_COLUMNS = array(
		'reference'     => 'reference',
		'created_at'    => 'created_at',
		'customer_name' => 'customer_name',
		'id'            => 'id',
	);

	/**
	 * Lifecycle status groups used as a payment-status proxy filter, since the
	 * booking lifecycle (see BookingStatus/BookingStateMachine) already tracks
	 * payment progress deterministically -- mark_deposit_paid()/mark_balance_paid()
	 * are the only ways a booking advances past these boundaries.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const PAYMENT_STATUS_GROUPS = array(
		'unpaid'       => array( 'inquiry', 'quoted', 'deposit_link_sent' ),
		'deposit_paid' => array( 'deposit_paid', 'confirmed' ),
		'balance_due'  => array( 'balance_due' ),
		'paid_in_full' => array( 'paid_in_full', 'completed' ),
	);

	/**
	 * @return array<int, string>
	 */
	public function known_types(): array {
		return self::LIST_TYPES;
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public function payment_status_groups(): array {
		return self::PAYMENT_STATUS_GROUPS;
	}

	private function valid_date( string $value ): string {
		$value = sanitize_text_field( $value );
		if ( '' === $value ) {
			return '';
		}
		$dt = \DateTime::createFromFormat( 'Y-m-d', $value );
		return ( $dt && $dt->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	/**
	 * Build the WHERE clause + bound params shared by query() and export_rows().
	 * Every value is returned for binding via $wpdb->prepare() by the caller --
	 * nothing here is interpolated directly into SQL.
	 *
	 * @param array<string, mixed> $args
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function build_where( array $args ): array {
		global $wpdb;
		$clauses = array();
		$params  = array();

		$search = isset( $args['search'] ) ? trim( sanitize_text_field( (string) $args['search'] ) ) : '';
		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$clauses[] = '(reference LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s)';
			array_push( $params, $like, $like, $like );
		}

		$type = isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : '';
		if ( '' !== $type && in_array( $type, self::LIST_TYPES, true ) ) {
			$clauses[] = 'type = %s';
			$params[]  = $type;
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( '' !== $status && null !== BookingStatus::tryFrom( $status ) ) {
			$clauses[] = 'status = %s';
			$params[]  = $status;
		}

		$portal_status = isset( $args['portal_status'] ) ? sanitize_key( (string) $args['portal_status'] ) : '';
		if ( '' !== $portal_status && in_array( $portal_status, array( 'new', 'reviewed', 'sent', 'closed' ), true ) ) {
			$clauses[] = 'portal_status = %s';
			$params[]  = $portal_status;
		}

		$payment_status = isset( $args['payment_status'] ) ? sanitize_key( (string) $args['payment_status'] ) : '';
		if ( '' !== $payment_status && isset( self::PAYMENT_STATUS_GROUPS[ $payment_status ] ) ) {
			$statuses     = self::PAYMENT_STATUS_GROUPS[ $payment_status ];
			$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
			$clauses[]    = "status IN ({$placeholders})";
			array_push( $params, ...$statuses );
		}

		$assigned_to = isset( $args['assigned_to'] ) ? absint( $args['assigned_to'] ) : 0;
		if ( $assigned_to > 0 ) {
			$clauses[] = 'assigned_to = %d';
			$params[]  = $assigned_to;
		}

		$tour_id = isset( $args['tour_id'] ) ? absint( $args['tour_id'] ) : 0;
		if ( $tour_id > 0 ) {
			$clauses[] = 'tour_id = %d';
			$params[]  = $tour_id;
		}

		$date_from = isset( $args['date_from'] ) ? $this->valid_date( (string) $args['date_from'] ) : '';
		if ( '' !== $date_from ) {
			$clauses[] = 'created_at >= %s';
			$params[]  = $date_from . ' 00:00:00';
		}

		$date_to = isset( $args['date_to'] ) ? $this->valid_date( (string) $args['date_to'] ) : '';
		if ( '' !== $date_to ) {
			$clauses[] = 'created_at <= %s';
			$params[]  = $date_to . ' 23:59:59';
		}

		$sql = $clauses ? ( ' WHERE ' . implode( ' AND ', $clauses ) ) : '';
		return array( $sql, $params );
	}

	/**
	 * Paginated, filtered, sorted list for the admin bookings screen. Replaces
	 * the old fixed `LIMIT 200` raw query in Admin\Portal.
	 *
	 * @param array<string, mixed> $args search, type, status, portal_status,
	 *                                    payment_status, assigned_to, tour_id,
	 *                                    date_from, date_to, orderby, order, page, per_page.
	 * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function query( array $args ): array {
		global $wpdb;
		$table = $this->table( 'bookings' );

		list( $where, $params ) = $this->build_where( $args );

		$order_key = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'created_at';
		$order_col = self::SORTABLE_COLUMNS[ $order_key ] ?? 'created_at';
		$order_dir = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, absint( $args['per_page'] ) ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table}{$where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB

		$list_sql    = "SELECT * FROM {$table}{$where} ORDER BY {$order_col} {$order_dir} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order_col/$order_dir are allow-listed above, never raw input.
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB

		return array(
			'rows'     => $rows,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * All rows matching the same filters as query(), unpaginated, for CSV export.
	 * Hard-capped so an unfiltered export can never exhaust memory on one request.
	 *
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public function export_rows( array $args ): array {
		global $wpdb;
		list( $where, $params ) = $this->build_where( $args );
		$table = $this->table( 'bookings' );
		$sql   = "SELECT reference, type, status, portal_status, customer_name, customer_email, customer_phone, customer_country, currency, price_amount, deposit_amount, created_at FROM {$table}{$where} ORDER BY id DESC LIMIT 5000";
		return $params ? ( $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: array() ) : ( $wpdb->get_results( $sql, ARRAY_A ) ?: array() ); // phpcs:ignore WordPress.DB
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 10 ): array {
		global $wpdb;
		$limit = max( 1, min( 50, $limit ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table( 'bookings' )} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB
	}

	/**
	 * @param array<int, int> $ids
	 */
	public function bulk_assign( array $ids, int $user_id ): int {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids ) {
			return 0;
		}
		global $wpdb;
		$table        = $this->table( 'bookings' );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "UPDATE {$table} SET assigned_to = %d WHERE id IN ({$placeholders})";
		$updated      = (int) $wpdb->query( $wpdb->prepare( $sql, array_merge( array( $user_id ), $ids ) ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			$this->audit( 'booking', $id, 'bulk_assign', array( 'assigned_to' => $user_id ) );
		}
		return $updated;
	}

	/**
	 * @param array<int, int> $ids
	 */
	public function bulk_set_portal_status( array $ids, string $status ): int {
		$allowed = array( 'new', 'reviewed', 'sent', 'closed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return 0;
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids ) {
			return 0;
		}
		global $wpdb;
		$table        = $this->table( 'bookings' );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "UPDATE {$table} SET portal_status = %s WHERE id IN ({$placeholders})";
		$updated      = (int) $wpdb->query( $wpdb->prepare( $sql, array_merge( array( $status ), $ids ) ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			$this->audit( 'booking', $id, 'bulk_portal_status', array( 'to' => $status ) );
		}
		return $updated;
	}

	/**
	 * Aggregate KPI data for the dashboard for a given date window (inclusive,
	 * Y-m-d), plus a same-length prior window used for the percentage-delta
	 * comparison on period-bound metrics.
	 *
	 * @return array<string, mixed>
	 */
	public function dashboard_stats( string $since, string $until ): array {
		global $wpdb;
		$bookings_table = $this->table( 'bookings' );

		$since_dt = $this->valid_date( $since ) ?: gmdate( 'Y-m-d', strtotime( '-29 days' ) );
		$until_dt = $this->valid_date( $until ) ?: gmdate( 'Y-m-d' );
		if ( $since_dt > $until_dt ) {
			list( $since_dt, $until_dt ) = array( $until_dt, $since_dt );
		}

		$period_days = (int) max( 1, ( strtotime( $until_dt ) - strtotime( $since_dt ) ) / DAY_IN_SECONDS + 1 );
		$prev_until  = gmdate( 'Y-m-d', strtotime( $since_dt . ' -1 day' ) );
		$prev_since  = gmdate( 'Y-m-d', strtotime( $prev_until . ' -' . ( $period_days - 1 ) . ' days' ) );

		$new_inquiries      = $this->count_created_between( $since_dt, $until_dt );
		$new_inquiries_prev = $this->count_created_between( $prev_since, $prev_until );

		$revenue      = $this->sum_paid_transactions( $since_dt, $until_dt );
		$revenue_prev = $this->sum_paid_transactions( $prev_since, $prev_until );

		$deposits_paid      = $this->count_paid_transactions( 'deposit', $since_dt, $until_dt );
		$deposits_paid_prev = $this->count_paid_transactions( 'deposit', $prev_since, $prev_until );

		$failed      = $this->count_failed_dispatches( $since_dt, $until_dt );
		$failed_prev = $this->count_failed_dispatches( $prev_since, $prev_until );

		return array(
			'since'      => $since_dt,
			'until'      => $until_dt,
			'prev_since' => $prev_since,
			'prev_until' => $prev_until,

			'new_inquiries'     => array( 'value' => $new_inquiries, 'delta' => $this->delta( (float) $new_inquiries, (float) $new_inquiries_prev ) ),
			'awaiting_review'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE portal_status = %s", 'new' ) ), // phpcs:ignore WordPress.DB
			'awaiting_customer' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE portal_status = %s", 'sent' ) ), // phpcs:ignore WordPress.DB
			'open_bookings'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE status NOT IN (%s, %s, %s, %s)", 'completed', 'expired', 'refunded', 'cancelled' ) ), // phpcs:ignore WordPress.DB
			'confirmed_bookings' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE status IN (%s, %s, %s)", 'confirmed', 'balance_due', 'paid_in_full' ) ), // phpcs:ignore WordPress.DB
			'outstanding_balance' => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CAST(balance_amount AS DECIMAL(14,2))), 0) FROM {$bookings_table} WHERE status = %s", 'balance_due' ) ), // phpcs:ignore WordPress.DB

			'deposits_paid'     => array( 'value' => $deposits_paid, 'delta' => $this->delta( (float) $deposits_paid, (float) $deposits_paid_prev ) ),
			'revenue'           => array( 'value' => $revenue, 'delta' => $this->delta( $revenue, $revenue_prev ) ),
			'failed_deliveries' => array( 'value' => $failed, 'delta' => $this->delta( (float) $failed, (float) $failed_prev ) ),

			'by_type'           => $this->group_counts( 'type', $since_dt, $until_dt ),
			'by_portal_status'  => $this->group_counts( 'portal_status' ),
			'gateway_breakdown' => $this->transactions_by_gateway( $since_dt, $until_dt ),
		);
	}

	private function delta( float $current, float $previous ): ?float {
		if ( $previous <= 0.0 ) {
			return null;
		}
		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}

	private function count_created_between( string $since, string $until ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table( 'bookings' )} WHERE created_at >= %s AND created_at <= %s",
				$since . ' 00:00:00',
				$until . ' 23:59:59'
			)
		); // phpcs:ignore WordPress.DB
	}

	private function sum_paid_transactions( string $since, string $until ): float {
		global $wpdb;
		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CAST(amount AS DECIMAL(14,2))), 0) FROM {$this->table( 'transactions' )} WHERE status = %s AND created_at >= %s AND created_at <= %s",
				'paid',
				$since . ' 00:00:00',
				$until . ' 23:59:59'
			)
		); // phpcs:ignore WordPress.DB
	}

	private function count_paid_transactions( string $type, string $since, string $until ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table( 'transactions' )} WHERE status = %s AND type = %s AND created_at >= %s AND created_at <= %s",
				'paid',
				$type,
				$since . ' 00:00:00',
				$until . ' 23:59:59'
			)
		); // phpcs:ignore WordPress.DB
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function group_counts( string $column, string $since = '', string $until = '' ): array {
		global $wpdb;
		$allowed = array( 'type', 'portal_status', 'status' );
		if ( ! in_array( $column, $allowed, true ) ) {
			return array();
		}
		$table = $this->table( 'bookings' );
		if ( '' !== $since && '' !== $until ) {
			$sql = $wpdb->prepare(
				"SELECT {$column} AS label, COUNT(*) AS c FROM {$table} WHERE created_at >= %s AND created_at <= %s GROUP BY {$column} ORDER BY c DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is allow-listed above.
				$since . ' 00:00:00',
				$until . ' 23:59:59'
			);
		} else {
			$sql = "SELECT {$column} AS label, COUNT(*) AS c FROM {$table} GROUP BY {$column} ORDER BY c DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is allow-listed above, no user input in this branch.
		}
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function transactions_by_gateway( string $since, string $until ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT gateway, type, COUNT(*) AS c, COALESCE(SUM(CAST(amount AS DECIMAL(14,2))), 0) AS total FROM {$this->table( 'transactions' )} WHERE status = %s AND created_at >= %s AND created_at <= %s GROUP BY gateway, type ORDER BY total DESC",
				'paid',
				$since . ' 00:00:00',
				$until . ' 23:59:59'
			),
			ARRAY_A
		) ?: array(); // phpcs:ignore WordPress.DB
	}

	private function count_failed_dispatches( string $since, string $until ): int {
		$rows  = $this->connection_dispatch_log( 1000, $since, $until );
		$count = 0;
		foreach ( $rows as $row ) {
			$code = (int) ( $row['status_code'] ?? 0 );
			if ( $code < 200 || $code >= 300 ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Connection-dispatch history read from the audit log -- see
	 * `wpistic_tm_connection_dispatched` / record_connection_dispatch() above.
	 * This is the source of truth for "who got dispatched where and how it went"
	 * since wpistic_connection_log itself has no booking_id column.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function connection_dispatch_log( int $limit = 300, string $since = '', string $until = '' ): array {
		global $wpdb;
		$limit = max( 1, min( 2000, $limit ) );
		$table = $this->table( 'audit_log' );

		if ( '' !== $since && '' !== $until ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE object_type = %s AND action = %s AND created_at >= %s AND created_at <= %s ORDER BY id DESC LIMIT %d",
				'booking',
				'connection_dispatch',
				$since . ' 00:00:00',
				$until . ' 23:59:59',
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE object_type = %s AND action = %s ORDER BY id DESC LIMIT %d",
				'booking',
				'connection_dispatch',
				$limit
			);
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB
		foreach ( $rows as &$row ) {
			$row['detail'] = json_decode( (string) $row['detail'], true ) ?: array();
		}
		unset( $row );
		return $rows;
	}

	public function audit( string $object_type, int $object_id, string $action, array $detail = array() ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table( 'audit_log' ),
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'action'      => $action,
				'actor'       => is_user_logged_in() ? wp_get_current_user()->user_login : 'system',
				'detail'      => wp_json_encode( $detail ),
				'created_at'  => current_time( 'mysql', true ),
			)
		);
	}

	private function next_reference(): string {
		return 'BT-' . gmdate( 'ymd' ) . '-' . strtoupper( wp_generate_password( 5, false, false ) );
	}
}

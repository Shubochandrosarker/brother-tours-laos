<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Booking;

use Wpistic\TourCore\Booking\BookingStateMachine;
use Wpistic\TourCore\Booking\BookingStatus;
use Wpistic\TourCore\Payment\PaymentGateway;
use Wpistic\TourCore\Payment\PaymentRequest;
use Wpistic\TourCore\Payment\PaymentResult;
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

		$wpdb->insert( $this->table( 'bookings' ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = (int) $wpdb->insert_id;
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

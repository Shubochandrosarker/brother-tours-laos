<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Integration;

use Wpistic\TourManager\Booking\BookingService;
use Wpistic\TourManager\Connections\ConnectionsManager;

/**
 * The single ingestion path from Brother Tours Forms (Formistic) into Tour Manager.
 *
 * One visitor submission produces exactly one local inquiry record and exactly
 * one Tourflows event. This class is the only place that turns a Formistic
 * submission into a booking; nothing else may listen to
 * `wpistic_formistic_submission_captured` and call BookingService::create().
 *
 * What guarantees "exactly once":
 *
 *  1. Routing — only the four named Brother Tours forms are ingested, resolved
 *     through the stable `_brother_tours_form` slug rather than the editable
 *     form title. An unrecognized form is ignored outright, so a marketing form
 *     added later never silently creates inquiries.
 *  2. Claiming — a row is inserted into the `form_ingestions` ledger *before*
 *     the booking is created. The table's UNIQUE KEY (source, external_id) is
 *     the guard: replays, retries and concurrent workers all collide on that
 *     insert and only the winner proceeds.
 *  3. Dispatch — the Tourflows event fires once, from here, after the booking
 *     exists, and the ledger records that it was sent.
 *
 * Newsletter never reaches this class: the upstream Formistic submit handler
 * subscribes the address and returns before Capture::store(), so it never fires
 * the captured action at all.
 *
 * Tour-page "Request Availability" is deliberately not handled here either. It
 * stays owned by CaptureController because it carries tour_id, departure and the
 * deposit workflow. It must not also be posted through a Formistic form.
 */
final class FormisticIngestion {

	/** Ledger source key for submissions arriving from Formistic. */
	private const SOURCE = 'formistic';

	private BookingService $bookings;

	private ConnectionsManager $connections;

	public function __construct( BookingService $bookings, ConnectionsManager $connections ) {
		$this->bookings    = $bookings;
		$this->connections = $connections;
	}

	public function register(): void {
		add_action( 'wpistic_formistic_submission_captured', array( $this, 'ingest' ), 20, 3 );
	}

	/**
	 * Map the Brother Tours form slug to a Tour Manager inquiry type.
	 *
	 * @return array<string, string>
	 */
	private function type_map(): array {
		return array(
			'build-my-trip' => 'build_my_trip',
			'contact'       => 'contact',
			'travel-agent'  => 'agent',
		);
	}

	/**
	 * Ingest one captured Formistic submission.
	 *
	 * @param mixed $submission_id Submission id.
	 * @param mixed $form_name     Form title as captured.
	 * @param mixed $fields        Label => value map.
	 */
	public function ingest( $submission_id, $form_name, $fields ): void {
		$submission_id = (int) $submission_id;
		if ( $submission_id <= 0 ) {
			return;
		}

		if ( ! class_exists( '\\Wpistic_Formistic_BT_Forms' ) || ! class_exists( '\\Wpistic_Formistic_Database' ) ) {
			return;
		}

		// Route by stable slug, never by the editable form title.
		$slug = (string) \Wpistic_Formistic_BT_Forms::slug_for_form_name( (string) $form_name );
		$map  = $this->type_map();

		if ( '' === $slug || ! isset( $map[ $slug ] ) ) {
			return; // Not one of the four Brother Tours forms; not ours to ingest.
		}

		// Claim before creating. Losing this race means another pass already
		// ingested this submission, so there is nothing left to do.
		if ( ! $this->claim( $submission_id, $slug ) ) {
			return;
		}

		$submission = \Wpistic_Formistic_Database::get_submission( $submission_id );
		if ( ! $submission ) {
			// Nothing was created, so give the claim back rather than blocking
			// this submission from ever being ingested again.
			$this->release( $submission_id );
			return;
		}

		$fields  = is_array( $fields ) ? $fields : array();
		$context = $this->request_context( $submission );

		$booking_id = $this->bookings->create(
			array(
				'type'             => $map[ $slug ],
				'customer_name'    => (string) ( $submission->sender_name ?? '' ),
				'customer_email'   => (string) ( $submission->sender_email ?? '' ),
				'customer_phone'   => (string) ( $submission->sender_phone ?? '' ),
				'customer_country' => $this->field( $fields, array( 'country' ) ),
				'party_adults'     => (int) $this->field( $fields, array( 'adults' ) ),
				'party_children'   => (int) $this->field( $fields, array( 'children' ) ),
				'hotel_pref'       => $this->field( $fields, array( 'hotel' ) ),
				'special_requests' => $this->summary( $fields, $submission ),
				'source_url'       => (string) ( $submission->source_url ?? '' ),
			)
		);

		if ( $booking_id <= 0 ) {
			$this->release( $submission_id );
			return;
		}

		$this->attach_booking( $submission_id, $booking_id );

		$booking = $this->bookings->get( $booking_id );
		if ( ! is_array( $booking ) ) {
			return;
		}

		// Audit trail: provenance, consent and campaign attribution, kept out of
		// the booking row itself so the guest record stays minimal.
		$this->bookings->audit(
			'booking',
			$booking_id,
			'ingested',
			array(
				'source'        => self::SOURCE,
				'form_slug'     => $slug,
				'submission_id' => $submission_id,
				'consent'       => $this->consent( $fields ),
				'utm'           => $context['utm'],
				'referrer'      => $context['referrer'],
				'source_url'    => (string) ( $submission->source_url ?? '' ),
			)
		);

		// Exactly one notification and one outbound event per submission.
		do_action( 'wpistic/notify', array( 'event' => 'inquiry.created', 'booking' => $booking ) );

		$this->connections->dispatch( 'inquiry.created', $booking );
		$this->mark_dispatched( $submission_id );
	}

	/* ==============================================================
	 * Idempotency ledger
	 * ============================================================== */

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpistic_form_ingestions';
	}

	/**
	 * Attempt to claim a submission.
	 *
	 * Relies on the UNIQUE KEY (source, external_id) rather than a read-then-write
	 * check, so concurrent callers cannot both succeed.
	 *
	 * @return bool True when this call won the claim and should proceed.
	 */
	private function claim( int $submission_id, string $slug ): bool {
		global $wpdb;

		// Suppress the duplicate-key warning: losing the race is expected and
		// handled, not an error worth surfacing.
		$suppress = $wpdb->suppress_errors( true );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'source'      => self::SOURCE,
				'external_id' => (string) $submission_id,
				'form_slug'   => $slug,
				'created_at'  => current_time( 'mysql', true ),
			)
		);

		$wpdb->suppress_errors( $suppress );

		return false !== $inserted && $inserted > 0;
	}

	/**
	 * Give back a claim that produced no booking.
	 *
	 * Only ever called on paths where nothing was created, so it cannot orphan
	 * a booking. Deliberately scoped to rows with no booking_id, so a successful
	 * ingestion can never be released by a later bug.
	 */
	private function release( int $submission_id ): void {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'source'      => self::SOURCE,
				'external_id' => (string) $submission_id,
				'booking_id'  => null,
			)
		);
	}

	private function attach_booking( int $submission_id, int $booking_id ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array( 'booking_id' => $booking_id ),
			array( 'source' => self::SOURCE, 'external_id' => (string) $submission_id )
		);
	}

	private function mark_dispatched( int $submission_id ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array( 'dispatched' => 1 ),
			array( 'source' => self::SOURCE, 'external_id' => (string) $submission_id )
		);
	}

	/* ==============================================================
	 * Field helpers
	 * ============================================================== */

	/**
	 * Pull a value out of the label => value map by fuzzy label match.
	 *
	 * Formistic captures human labels, and editors are free to reword them, so
	 * matching is a case-insensitive substring test rather than an exact key.
	 *
	 * @param array<string, mixed> $fields  Captured fields.
	 * @param string[]             $needles Label fragments to look for.
	 */
	private function field( array $fields, array $needles ): string {
		foreach ( $fields as $label => $value ) {
			$haystack = strtolower( (string) $label );
			foreach ( $needles as $needle ) {
				if ( '' !== $needle && false !== strpos( $haystack, strtolower( $needle ) ) ) {
					return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
				}
			}
		}

		return '';
	}

	/**
	 * Readable summary of everything the guest told us, for the portal view.
	 *
	 * The dedicated booking columns only cover a handful of fields; the rest of
	 * the form (dates, duration, interests, agency, free text) would otherwise be
	 * invisible to the team triaging the inquiry.
	 *
	 * @param array<string, mixed> $fields     Captured fields.
	 * @param object               $submission Stored submission row.
	 */
	private function summary( array $fields, $submission ): string {
		$skip  = array( 'consent', 'name', 'email' );
		$lines = array();

		foreach ( $fields as $label => $value ) {
			$flat = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			$flat = trim( $flat );
			if ( '' === $flat ) {
				continue;
			}

			$lower = strtolower( (string) $label );
			foreach ( $skip as $needle ) {
				if ( false !== strpos( $lower, $needle ) ) {
					continue 2;
				}
			}

			$lines[] = $label . ': ' . $flat;
		}

		if ( ! $lines && ! empty( $submission->message ) ) {
			$lines[] = (string) $submission->message;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Consent value and the time it was recorded.
	 *
	 * @param array<string, mixed> $fields Captured fields.
	 * @return array<string, string>
	 */
	private function consent( array $fields ): array {
		$value = $this->field( $fields, array( 'consent', 'i agree' ) );

		return array(
			'value'       => $value,
			'recorded_at' => '' !== $value ? current_time( 'mysql', true ) : '',
		);
	}

	/**
	 * Campaign attribution taken from the stored source URL.
	 *
	 * Read from the persisted submission rather than $_GET: ingestion can run on
	 * a retry or a cron pass where the original request is long gone.
	 *
	 * @param object $submission Stored submission row.
	 * @return array{utm: array<string, string>, referrer: string}
	 */
	private function request_context( $submission ): array {
		$utm       = array();
		$source    = (string) ( $submission->source_url ?? '' );
		$query     = (string) wp_parse_url( $source, PHP_URL_QUERY );
		$permitted = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid' );

		if ( '' !== $query ) {
			$parsed = array();
			wp_parse_str( $query, $parsed );
			foreach ( $permitted as $key ) {
				if ( ! empty( $parsed[ $key ] ) ) {
					$utm[ $key ] = sanitize_text_field( (string) $parsed[ $key ] );
				}
			}
		}

		return array(
			'utm'      => $utm,
			'referrer' => $source,
		);
	}
}

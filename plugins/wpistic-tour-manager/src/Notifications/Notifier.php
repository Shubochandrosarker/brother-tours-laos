<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Notifications;

use Wpistic\TourManager\Booking\BookingService;

/**
 * Routes operational email through Formistic with a wp_mail fallback.
 */
final class Notifier {

	public function register(): void {
		add_action( 'wpistic/notify', array( $this, 'handle' ) );
		add_action( 'wpistic_formistic_submission_captured', array( $this, 'on_formistic' ), 50, 3 );
	}

	/**
	 * @param mixed $payload Notification payload.
	 */
	public function handle( $payload ): void {
		if ( ! is_array( $payload ) ) {
			return;
		}

		$event   = (string) ( $payload['event'] ?? '' );
		$booking = (array) ( $payload['booking'] ?? array() );

		switch ( $event ) {
			case 'inquiry.created':
				$this->on_inquiry( $booking );
				break;
			case 'booking.deposit_link':
				$this->on_deposit_link( $booking, (string) ( $payload['link'] ?? '' ) );
				break;
			case 'booking.deposit_paid':
				$this->on_deposit_paid( $booking );
				break;
			case 'booking.balance_link':
				$this->on_balance_link( $booking, (string) ( $payload['link'] ?? '' ) );
				break;
			case 'booking.balance_paid':
				$this->on_balance_paid( $booking );
				break;
		}
	}

	private function on_inquiry( array $booking ): void {
		$reference = (string) ( $booking['reference'] ?? '' );
		$name      = (string) ( $booking['customer_name'] ?? __( 'Guest', 'wpistic-tour-manager' ) );
		$type      = ucwords( str_replace( '_', ' ', (string) ( $booking['type'] ?? 'inquiry' ) ) );

		$this->send(
			$this->team_email(),
			sprintf( '[%1$s] %2$s - %3$s', $reference, $type, $name ),
			EmailTemplate::render(
				array(
					'heading' => __( 'New request', 'wpistic-tour-manager' ),
					'intro'   => __( 'A new request just came in through the website.', 'wpistic-tour-manager' ),
					'rows'    => $this->rows( $booking ),
				)
			)
		);

		if ( ! empty( $booking['customer_email'] ) ) {
			$this->send(
				(string) $booking['customer_email'],
				__( 'We have your request - Brother Tours', 'wpistic-tour-manager' ),
				EmailTemplate::render(
					array(
						/* translators: %s: guest name. */
						'heading' => sprintf( __( 'Thank you, %s.', 'wpistic-tour-manager' ), $name ),
						'intro'   => __( 'Your request is with our team in Vientiane. We reply within 24 hours. Designed to you, no pressure, no upselling.', 'wpistic-tour-manager' ),
					)
				)
			);
		}
	}

	private function on_deposit_link( array $booking, string $link ): void {
		if ( empty( $booking['customer_email'] ) ) {
			return;
		}

		$is_url = (bool) preg_match( '#^https?://#i', $link );
		$cta    = $is_url ? array( 'label' => __( 'Pay deposit', 'wpistic-tour-manager' ), 'url' => $link ) : null;
		$intro  = $is_url
			? __( 'Your journey is ready to hold. Pay your deposit to secure your departure and lock your pricing.', 'wpistic-tour-manager' )
			: __( 'Your journey is ready to hold. Payment instructions:', 'wpistic-tour-manager' ) . '<br><br>' . nl2br( esc_html( $link ) );

		$this->send(
			(string) $booking['customer_email'],
			__( 'Secure your departure - Brother Tours', 'wpistic-tour-manager' ),
			EmailTemplate::render(
				array(
					'heading' => __( 'Secure your departure', 'wpistic-tour-manager' ),
					'intro'   => $intro,
					'rows'    => array(
						__( 'Reference', 'wpistic-tour-manager' ) => (string) ( $booking['reference'] ?? '' ),
						__( 'Deposit', 'wpistic-tour-manager' )   => trim( (string) ( $booking['deposit_amount'] ?? '' ) . ' ' . (string) ( $booking['currency'] ?? '' ) ),
					),
					'cta'     => $cta,
				)
			)
		);
	}

	private function on_deposit_paid( array $booking ): void {
		$reference = (string) ( $booking['reference'] ?? '' );

		$this->send(
			$this->team_email(),
			sprintf( '[%s] Deposit paid', $reference ),
			EmailTemplate::render(
				array(
					'heading' => __( 'Deposit received', 'wpistic-tour-manager' ),
					/* translators: %s: booking reference. */
					'intro'   => sprintf( __( 'Deposit received for booking %s.', 'wpistic-tour-manager' ), $reference ),
					'rows'    => $this->rows( $booking ),
				)
			)
		);

		if ( ! empty( $booking['customer_email'] ) ) {
			$this->send(
				(string) $booking['customer_email'],
				__( 'Deposit received - Brother Tours', 'wpistic-tour-manager' ),
				EmailTemplate::render(
					array(
						'heading' => __( 'Thank you - deposit received', 'wpistic-tour-manager' ),
						'intro'   => __( 'Your deposit is received. Our team will confirm your departure shortly.', 'wpistic-tour-manager' ),
					)
				)
			);
		}
	}

	private function on_balance_link( array $booking, string $link ): void {
		if ( empty( $booking['customer_email'] ) ) {
			return;
		}

		$is_url = (bool) preg_match( '#^https?://#i', $link );
		$cta    = $is_url ? array( 'label' => __( 'Pay balance', 'wpistic-tour-manager' ), 'url' => $link ) : null;
		$intro  = $is_url
			? __( 'Your balance payment link is ready.', 'wpistic-tour-manager' )
			: __( 'Your balance payment instructions:', 'wpistic-tour-manager' ) . '<br><br>' . nl2br( esc_html( $link ) );

		$this->send(
			(string) $booking['customer_email'],
			__( 'Balance payment - Brother Tours', 'wpistic-tour-manager' ),
			EmailTemplate::render(
				array(
					'heading' => __( 'Balance payment', 'wpistic-tour-manager' ),
					'intro'   => $intro,
					'rows'    => array(
						__( 'Reference', 'wpistic-tour-manager' ) => (string) ( $booking['reference'] ?? '' ),
						__( 'Balance', 'wpistic-tour-manager' )   => trim( (string) ( $booking['balance_amount'] ?? '' ) . ' ' . (string) ( $booking['currency'] ?? '' ) ),
					),
					'cta'     => $cta,
				)
			)
		);
	}

	private function on_balance_paid( array $booking ): void {
		$reference = (string) ( $booking['reference'] ?? '' );

		$this->send(
			$this->team_email(),
			sprintf( '[%s] Balance paid', $reference ),
			EmailTemplate::render(
				array(
					'heading' => __( 'Balance received', 'wpistic-tour-manager' ),
					'intro'   => sprintf(
						/* translators: %s: booking reference. */
						__( 'Balance received for booking %s.', 'wpistic-tour-manager' ),
						$reference
					),
					'rows'    => $this->rows( $booking ),
				)
			)
		);

		if ( ! empty( $booking['customer_email'] ) ) {
			$this->send(
				(string) $booking['customer_email'],
				__( 'Balance received - Brother Tours', 'wpistic-tour-manager' ),
				EmailTemplate::render(
					array(
						'heading' => __( 'Balance received', 'wpistic-tour-manager' ),
						'intro'   => __( 'Thank you. Your balance payment has been recorded by Brother Tours.', 'wpistic-tour-manager' ),
					)
				)
			);
		}
	}

	/**
	 * Mirror a Formistic contact submission into the portal.
	 *
	 * @param int    $submission_id Submission id.
	 * @param string $form_name     Form name.
	 * @param array  $fields        Submitted fields.
	 * @return void
	 */
	public function on_formistic( $submission_id, $form_name, $fields ): void {
		if ( ! class_exists( '\\Wpistic_Formistic_Database' ) ) {
			return;
		}

		$submission = \Wpistic_Formistic_Database::get_submission( (int) $submission_id );
		if ( ! $submission ) {
			return;
		}

		( new BookingService() )->create(
			array(
				'type'             => 'contact',
				'customer_name'    => $submission->sender_name ?? '',
				'customer_email'   => $submission->sender_email ?? '',
				'customer_phone'   => $submission->sender_phone ?? '',
				'special_requests' => $submission->message ?? '',
				'source_url'       => $submission->source_url ?? '',
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function rows( array $booking ): array {
		$rows = array();
		$map  = array(
			'reference'        => __( 'Reference', 'wpistic-tour-manager' ),
			'type'             => __( 'Type', 'wpistic-tour-manager' ),
			'customer_name'    => __( 'Name', 'wpistic-tour-manager' ),
			'customer_email'   => __( 'Email', 'wpistic-tour-manager' ),
			'customer_phone'   => __( 'Phone', 'wpistic-tour-manager' ),
			'customer_country' => __( 'Country', 'wpistic-tour-manager' ),
			'party_adults'     => __( 'Adults', 'wpistic-tour-manager' ),
			'party_children'   => __( 'Children', 'wpistic-tour-manager' ),
			'hotel_pref'       => __( 'Hotel', 'wpistic-tour-manager' ),
			'special_requests' => __( 'Notes', 'wpistic-tour-manager' ),
		);

		foreach ( $map as $key => $label ) {
			if ( ! empty( $booking[ $key ] ) ) {
				$rows[ $label ] = (string) $booking[ $key ];
			}
		}

		return $rows;
	}

	private function send( string $to, string $subject, string $html ): bool {
		if ( '' === $to ) {
			return false;
		}

		$from_email = (string) get_option( 'wpistic_tm_from_email', get_option( 'admin_email' ) );
		$from_name  = get_bloginfo( 'name' );
		$headers    = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $from_email ) ) {
			$headers[] = sprintf( 'From: %1$s <%2$s>', $from_name, $from_email );
		}

		if ( class_exists( '\\Wpistic_Formistic_Capture' ) ) {
			return (bool) \Wpistic_Formistic_Capture::send_internal( $to, $subject, $html, $headers );
		}

		return (bool) wp_mail( $to, $subject, $html, $headers );
	}

	private function team_email(): string {
		return (string) get_option( 'wpistic_tm_from_email', get_option( 'admin_email' ) );
	}
}

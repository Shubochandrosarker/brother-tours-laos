<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Frontend;

/**
 * Front-end forms as shortcodes (also usable in Elementor / blocks):
 *  - [wpistic_booking_widget]      "Request Availability" on tour pages
 *  - [wpistic_build_my_trip_form]  custom journey request
 *  - [wpistic_agent_form]          B2B partner inquiry
 * All post to the REST capture endpoint; the trip stays human-confirmed.
 */
final class BookingWidget {

	public function register(): void {
		add_shortcode( 'wpistic_booking_widget', array( $this, 'booking_widget' ) );
		add_shortcode( 'wpistic_build_my_trip_form', array( $this, 'build_my_trip' ) );
		add_shortcode( 'wpistic_contact_form', array( $this, 'contact_form' ) );
		add_shortcode( 'wpistic_agent_form', array( $this, 'agent_form' ) );
	}

	private function open( string $type, int $tour_id = 0 ): string {
		$html  = '<form class="wpistic-booking-form" data-type="' . esc_attr( $type ) . '">';
		$html .= '<input type="hidden" name="type" value="' . esc_attr( $type ) . '">';
		if ( $tour_id ) {
			$html .= '<input type="hidden" name="tour_id" value="' . esc_attr( (string) $tour_id ) . '">';
		}
		$source = isset( $_SERVER['REQUEST_URI'] ) ? home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : home_url( '/' );
		$html .= '<input type="hidden" name="source_url" value="' . esc_url( $source ) . '">';
		$html .= '<input type="text" name="website" class="wpistic-hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
		return $html;
	}

	private function status(): string {
		return '<div class="wpistic-form-status" role="status" aria-live="polite"></div></form>';
	}

	public function booking_widget( $atts ): string {
		$atts    = shortcode_atts( array( 'id' => get_the_ID() ), (array) $atts, 'wpistic_booking_widget' );
		$tour_id = absint( $atts['id'] );
		$html    = $this->open( 'booking', $tour_id );
		$html   .= '<div class="field"><label>' . esc_html__( 'Preferred dates', 'wpistic-tour-manager' ) . '</label><input type="text" name="dates" placeholder="' . esc_attr__( 'flexible', 'wpistic-tour-manager' ) . '"></div>';
		$html   .= '<div class="field-row"><div class="field"><label>' . esc_html__( 'Adults', 'wpistic-tour-manager' ) . '</label><input type="number" name="adults" min="1" value="2"></div><div class="field"><label>' . esc_html__( 'Children', 'wpistic-tour-manager' ) . '</label><input type="text" name="children" placeholder="' . esc_attr__( 'ages', 'wpistic-tour-manager' ) . '"></div></div>';
		$html   .= '<div class="field"><label>' . esc_html__( 'Hotel preference', 'wpistic-tour-manager' ) . '</label><select name="hotel"><option value="">' . esc_html__( 'No preference', 'wpistic-tour-manager' ) . '</option><option>3-star</option><option>4-star</option><option>5-star</option><option>Heritage</option></select></div>';
		$html   .= '<div class="field"><label>' . esc_html__( 'Special requests', 'wpistic-tour-manager' ) . '</label><textarea name="notes"></textarea></div>';
		$html   .= '<div class="field-row"><div class="field"><label>' . esc_html__( 'Name', 'wpistic-tour-manager' ) . '</label><input type="text" name="name"></div><div class="field"><label>' . esc_html__( 'Email', 'wpistic-tour-manager' ) . '</label><input type="email" name="email" required></div></div>';
		$html   .= '<button class="btn btn-solid" type="submit">' . esc_html__( 'Request Availability', 'wpistic-tour-manager' ) . '</button>';
		return $html . $this->status();
	}

	public function build_my_trip( $atts ): string {
		$tour = isset( $_GET['tour'] ) ? sanitize_text_field( wp_unslash( $_GET['tour'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$html = $this->open( 'build_my_trip' );
		if ( '' !== $tour ) {
			$html .= '<input type="hidden" name="notes_context" value="' . esc_attr( $tour ) . '">';
		}
		$html .= '<div class="field-row"><div class="field"><label>' . esc_html__( 'Travel dates (or flexible)', 'wpistic-tour-manager' ) . '</label><input type="text" name="dates"></div><div class="field"><label>' . esc_html__( 'Duration', 'wpistic-tour-manager' ) . '</label><input type="text" name="duration"></div></div>';
		$html .= '<div class="field-row"><div class="field"><label>' . esc_html__( 'Adults', 'wpistic-tour-manager' ) . '</label><input type="number" name="adults" min="1" value="2"></div><div class="field"><label>' . esc_html__( 'Children (ages)', 'wpistic-tour-manager' ) . '</label><input type="text" name="children"></div></div>';
		$html .= '<div class="field"><label>' . esc_html__( 'Hotel preference', 'wpistic-tour-manager' ) . '</label><select name="hotel"><option value="">' . esc_html__( 'No preference', 'wpistic-tour-manager' ) . '</option><option>3-star</option><option>4-star</option><option>5-star</option><option>Heritage</option></select></div>';
		$html .= '<div class="field"><label>' . esc_html__( 'What kind of experience are you imagining?', 'wpistic-tour-manager' ) . '</label><textarea name="notes"></textarea></div>';
		$html .= '<div class="field-row"><div class="field"><label>' . esc_html__( 'Name', 'wpistic-tour-manager' ) . '</label><input type="text" name="name"></div><div class="field"><label>' . esc_html__( 'Country', 'wpistic-tour-manager' ) . '</label><input type="text" name="country"></div></div>';
		$html .= '<div class="field-row"><div class="field"><label>' . esc_html__( 'Email', 'wpistic-tour-manager' ) . '</label><input type="email" name="email" required></div><div class="field"><label>' . esc_html__( 'Phone', 'wpistic-tour-manager' ) . '</label><input type="tel" name="phone"></div></div>';
		$html .= '<button class="btn btn-solid" type="submit">' . esc_html__( 'Send my request', 'wpistic-tour-manager' ) . '</button>';
		return $html . $this->status();
	}

	public function contact_form( $atts ): string {
		$html  = $this->open( 'inquiry' );
		$html .= '<div class="field"><label>' . esc_html__( 'Your name', 'wpistic-tour-manager' ) . '</label><input type="text" name="name" autocomplete="name"></div>';
		$html .= '<div class="field-row"><div class="field"><label>' . esc_html__( 'Email', 'wpistic-tour-manager' ) . '</label><input type="email" name="email" autocomplete="email" required></div><div class="field"><label>' . esc_html__( 'WhatsApp', 'wpistic-tour-manager' ) . '</label><input type="tel" name="phone"></div></div>';
		$html .= '<div class="field"><label>' . esc_html__( 'Tell us about your trip', 'wpistic-tour-manager' ) . '</label><textarea name="message"></textarea></div>';
		$html .= '<button class="btn btn-solid" type="submit">' . esc_html__( 'Send to Brother Tours', 'wpistic-tour-manager' ) . '</button>';
		return $html . $this->status();
	}

	public function agent_form( $atts ): string {
		$html  = $this->open( 'agent' );
		$html .= '<div class="field"><label>' . esc_html__( 'Organization', 'wpistic-tour-manager' ) . '</label><input type="text" name="country" placeholder="' . esc_attr__( 'Company / agency', 'wpistic-tour-manager' ) . '"></div>';
		$html .= '<div class="field-row"><div class="field"><label>' . esc_html__( 'Contact', 'wpistic-tour-manager' ) . '</label><input type="text" name="name"></div><div class="field"><label>' . esc_html__( 'Email', 'wpistic-tour-manager' ) . '</label><input type="email" name="email" required></div></div>';
		$html .= '<div class="field"><label>' . esc_html__( 'Regions of interest', 'wpistic-tour-manager' ) . '</label><input type="text" name="hotel"></div>';
		$html .= '<div class="field"><label>' . esc_html__( 'Details', 'wpistic-tour-manager' ) . '</label><textarea name="notes"></textarea></div>';
		$html .= '<button class="btn btn-solid" type="submit">' . esc_html__( 'Send inquiry', 'wpistic-tour-manager' ) . '</button>';
		return $html . $this->status();
	}
}

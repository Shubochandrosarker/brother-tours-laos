<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Admin;

/**
 * Tour authoring meta boxes. Sanitize on input, escape on output, nonce + capability
 * checks on save. Itinerary and FAQ are JS-cloned repeaters (assets/js/admin.js).
 */
final class MetaBoxes {

	private const NONCE = 'wpistic_tm_meta';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( ! in_array( $screen->post_type, array( 'wpistic_tour', 'wpistic_departure', 'wpistic_experience', 'wpistic_destination' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'wpistic-tm-admin', WPISTIC_TM_URL . 'assets/css/admin.css', array(), WPISTIC_TM_VERSION );
		wp_enqueue_script( 'wpistic-tm-admin', WPISTIC_TM_URL . 'assets/js/admin.js', array(), WPISTIC_TM_VERSION, true );
	}

	public function add(): void {
		add_meta_box( 'wpistic_tour_facts', __( 'At a Glance & Pricing', 'wpistic-tour-manager' ), array( $this, 'box_tour_facts' ), 'wpistic_tour', 'normal', 'high' );
		add_meta_box( 'wpistic_tour_itinerary', __( 'Itinerary (day by day)', 'wpistic-tour-manager' ), array( $this, 'box_tour_itinerary' ), 'wpistic_tour', 'normal' );
		add_meta_box( 'wpistic_tour_incl', __( 'Inclusions & Exclusions', 'wpistic-tour-manager' ), array( $this, 'box_tour_incl' ), 'wpistic_tour', 'normal' );
		add_meta_box( 'wpistic_tour_faq', __( 'FAQ', 'wpistic-tour-manager' ), array( $this, 'box_tour_faq' ), 'wpistic_tour', 'normal' );
		add_meta_box( 'wpistic_dep', __( 'Departure', 'wpistic-tour-manager' ), array( $this, 'box_departure' ), 'wpistic_departure', 'normal', 'high' );
		add_meta_box( 'wpistic_exp', __( 'Experience', 'wpistic-tour-manager' ), array( $this, 'box_experience' ), 'wpistic_experience', 'side' );
		add_meta_box( 'wpistic_dest', __( 'Destination', 'wpistic-tour-manager' ), array( $this, 'box_destination' ), 'wpistic_destination', 'side' );
	}

	private function nonce(): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
	}

	public function box_tour_facts( \WP_Post $post ): void {
		$this->nonce();
		echo '<div class="wpistic-fields">';
		$this->text( $post->ID, 'wpistic_accent_word', __( 'Accent word (italic gold)', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_subtitle', __( 'Tour subtitle', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_short_summary', __( 'Short card summary', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_duration', __( 'Duration', 'wpistic-tour-manager' ), 'e.g. 14 days' );
		$this->text( $post->ID, 'wpistic_start_location', __( 'Starting location', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_end_location', __( 'Ending location', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_group_size', __( 'Group size', 'wpistic-tour-manager' ), 'e.g. Private' );
		$this->text( $post->ID, 'wpistic_minimum_age', __( 'Minimum age', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_season', __( 'Season', 'wpistic-tour-manager' ), 'e.g. Year-round' );
		$this->text( $post->ID, 'wpistic_accommodation', __( 'Accommodation level', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_transport', __( 'Transport type', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_availability', __( 'Availability', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_departures_label', __( 'Departures label', 'wpistic-tour-manager' ), 'e.g. 8 departures / year' );
		$this->text( $post->ID, 'wpistic_from_price', __( 'From price (number only, no currency)', 'wpistic-tour-manager' ), 'e.g. 2400' );
		$this->select( $post->ID, 'wpistic_pricing_type', __( 'Pricing type', 'wpistic-tour-manager' ), array( 'request' => __( 'Price on request', 'wpistic-tour-manager' ), 'starting' => __( 'Starting price', 'wpistic-tour-manager' ), 'person' => __( 'Per person', 'wpistic-tour-manager' ), 'group' => __( 'Group price', 'wpistic-tour-manager' ) ) );
		echo '<hr>';
		echo '<p class="description">' . esc_html__( 'Deposit override (leave blank to use the global default).', 'wpistic-tour-manager' ) . '</p>';
		$this->select(
			$post->ID,
			'wpistic_deposit_type',
			__( 'Deposit type', 'wpistic-tour-manager' ),
			array( '' => __( '— Use global default —', 'wpistic-tour-manager' ), 'fixed' => __( 'Fixed amount', 'wpistic-tour-manager' ), 'percent' => __( 'Percentage', 'wpistic-tour-manager' ) )
		);
		$this->text( $post->ID, 'wpistic_deposit_value', __( 'Deposit value', 'wpistic-tour-manager' ), 'e.g. 30 (percent) or 500 (fixed)' );
		$this->text( $post->ID, 'wpistic_deposit_min', __( 'Deposit minimum', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_deposit_max', __( 'Deposit maximum', 'wpistic-tour-manager' ) );
		echo '</div>';
	}

	public function box_tour_itinerary( \WP_Post $post ): void {
		$rows = get_post_meta( $post->ID, 'wpistic_itinerary', true );
		$rows = is_array( $rows ) ? $rows : array();
		echo '<div class="wpistic-repeater" data-name="wpistic_itinerary"><div class="wpistic-rows">';
		foreach ( $rows as $row ) {
			$this->itinerary_row( (string) ( $row['title'] ?? '' ), (string) ( $row['body'] ?? '' ), false );
		}
		echo '</div>';
		$this->itinerary_row( '', '', true );
		echo '<button type="button" class="button wpistic-add">' . esc_html__( '+ Add day', 'wpistic-tour-manager' ) . '</button></div>';
	}

	private function itinerary_row( string $title, string $body, bool $template ): void {
		$cls = $template ? ' wpistic-row-template' : '';
		$dis = $template ? ' disabled' : '';
		echo '<div class="wpistic-row' . esc_attr( $cls ) . '"' . ( $template ? ' hidden' : '' ) . '>';
		echo '<input type="text" name="wpistic_itinerary[][title]" value="' . esc_attr( $title ) . '" placeholder="' . esc_attr__( 'Day title', 'wpistic-tour-manager' ) . '"' . $dis . '>';
		echo '<textarea name="wpistic_itinerary[][body]" placeholder="' . esc_attr__( 'What happens', 'wpistic-tour-manager' ) . '"' . $dis . '>' . esc_textarea( $body ) . '</textarea>';
		echo '<button type="button" class="button-link wpistic-remove">&times;</button></div>';
	}

	public function box_tour_incl( \WP_Post $post ): void {
		$inc = get_post_meta( $post->ID, 'wpistic_inclusions', true );
		$exc = get_post_meta( $post->ID, 'wpistic_exclusions', true );
		echo '<p><label>' . esc_html__( 'Included (one per line)', 'wpistic-tour-manager' ) . '</label>';
		echo '<textarea name="wpistic_inclusions" rows="5" class="widefat">' . esc_textarea( is_array( $inc ) ? implode( "\n", $inc ) : (string) $inc ) . '</textarea></p>';
		echo '<p><label>' . esc_html__( 'Not included (one per line)', 'wpistic-tour-manager' ) . '</label>';
		echo '<textarea name="wpistic_exclusions" rows="4" class="widefat">' . esc_textarea( is_array( $exc ) ? implode( "\n", $exc ) : (string) $exc ) . '</textarea></p>';
	}

	public function box_tour_faq( \WP_Post $post ): void {
		$rows = get_post_meta( $post->ID, 'wpistic_faq', true );
		$rows = is_array( $rows ) ? $rows : array();
		echo '<div class="wpistic-repeater" data-name="wpistic_faq"><div class="wpistic-rows">';
		foreach ( $rows as $row ) {
			$this->faq_row( (string) ( $row['q'] ?? '' ), (string) ( $row['a'] ?? '' ), false );
		}
		echo '</div>';
		$this->faq_row( '', '', true );
		echo '<button type="button" class="button wpistic-add">' . esc_html__( '+ Add question', 'wpistic-tour-manager' ) . '</button></div>';
	}

	private function faq_row( string $q, string $a, bool $template ): void {
		$cls = $template ? ' wpistic-row-template' : '';
		$dis = $template ? ' disabled' : '';
		echo '<div class="wpistic-row' . esc_attr( $cls ) . '"' . ( $template ? ' hidden' : '' ) . '>';
		echo '<input type="text" name="wpistic_faq[][q]" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Question', 'wpistic-tour-manager' ) . '"' . $dis . '>';
		echo '<textarea name="wpistic_faq[][a]" placeholder="' . esc_attr__( 'Answer', 'wpistic-tour-manager' ) . '"' . $dis . '>' . esc_textarea( $a ) . '</textarea>';
		echo '<button type="button" class="button-link wpistic-remove">&times;</button></div>';
	}

	public function box_departure( \WP_Post $post ): void {
		$this->nonce();
		$tours = get_posts( array( 'post_type' => 'wpistic_tour', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$options = array( '' => __( '— Select tour —', 'wpistic-tour-manager' ) );
		foreach ( $tours as $tour ) {
			$options[ (string) $tour->ID ] = $tour->post_title;
		}
		$this->select( $post->ID, 'wpistic_dep_tour', __( 'Tour', 'wpistic-tour-manager' ), $options );
		$this->date( $post->ID, 'wpistic_dep_date', __( 'Date', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_dep_seats_total', __( 'Seats total', 'wpistic-tour-manager' ) );
		$this->text( $post->ID, 'wpistic_dep_seats_left', __( 'Seats left', 'wpistic-tour-manager' ) );
		$this->select(
			$post->ID,
			'wpistic_dep_status',
			__( 'Status', 'wpistic-tour-manager' ),
			array( 'open' => __( 'Open', 'wpistic-tour-manager' ), 'limited' => __( 'Limited', 'wpistic-tour-manager' ), 'closed' => __( 'Closed', 'wpistic-tour-manager' ) )
		);
	}

	public function box_experience( \WP_Post $post ): void {
		$this->nonce();
		$dests = get_posts( array( 'post_type' => 'wpistic_destination', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$options = array( '' => __( '— Select destination —', 'wpistic-tour-manager' ) );
		foreach ( $dests as $dest ) {
			$options[ (string) $dest->ID ] = $dest->post_title;
		}
		$this->select( $post->ID, 'wpistic_parent_destination', __( 'Parent destination', 'wpistic-tour-manager' ), $options );
		$this->text( $post->ID, 'wpistic_best_season', __( 'Best season', 'wpistic-tour-manager' ) );
	}

	public function box_destination( \WP_Post $post ): void {
		$this->nonce();
		$this->text( $post->ID, 'wpistic_one_line', __( 'One-line character', 'wpistic-tour-manager' ) );
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$scalars = array(
			'wpistic_tour'        => array( 'wpistic_accent_word', 'wpistic_subtitle', 'wpistic_short_summary', 'wpistic_duration', 'wpistic_start_location', 'wpistic_end_location', 'wpistic_group_size', 'wpistic_minimum_age', 'wpistic_season', 'wpistic_accommodation', 'wpistic_transport', 'wpistic_availability', 'wpistic_departures_label', 'wpistic_from_price', 'wpistic_pricing_type', 'wpistic_deposit_type', 'wpistic_deposit_value', 'wpistic_deposit_min', 'wpistic_deposit_max' ),
			'wpistic_departure'   => array( 'wpistic_dep_tour', 'wpistic_dep_date', 'wpistic_dep_seats_total', 'wpistic_dep_seats_left', 'wpistic_dep_status' ),
			'wpistic_experience'  => array( 'wpistic_parent_destination', 'wpistic_best_season' ),
			'wpistic_destination' => array( 'wpistic_one_line' ),
		);

		foreach ( $scalars[ $post->post_type ] ?? array() as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		if ( 'wpistic_tour' === $post->post_type ) {
			$this->save_repeater( $post_id, 'wpistic_itinerary', array( 'title', 'body' ) );
			$this->save_repeater( $post_id, 'wpistic_faq', array( 'q', 'a' ) );
			$this->save_lines( $post_id, 'wpistic_inclusions' );
			$this->save_lines( $post_id, 'wpistic_exclusions' );
		}
	}

	/**
	 * @param array<int, string> $fields
	 */
	private function save_repeater( int $post_id, string $key, array $fields ): void {
		$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore WordPress.Security.ValidationSanitization
		$clean = array();
		foreach ( $raw as $row ) {
			$entry = array();
			$empty = true;
			foreach ( $fields as $field ) {
				$value = isset( $row[ $field ] ) ? sanitize_textarea_field( (string) $row[ $field ] ) : '';
				$entry[ $field ] = $value;
				if ( '' !== $value ) {
					$empty = false;
				}
			}
			if ( ! $empty ) {
				$clean[] = $entry;
			}
		}
		update_post_meta( $post_id, $key, $clean );
	}

	private function save_lines( int $post_id, string $key ): void {
		if ( ! isset( $_POST[ $key ] ) ) {
			return;
		}
		$raw   = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
		update_post_meta( $post_id, $key, $lines );
	}

	private function text( int $post_id, string $key, string $label, string $placeholder = '' ): void {
		$value = (string) get_post_meta( $post_id, $key, true );
		echo '<p class="wpistic-field"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"></p>';
	}

	/**
	 * Same as text(), but a native date input -- a browser date picker and
	 * basic Y-m-d format validation, rather than a free-text field an editor
	 * could enter any format into. Used for wpistic_dep_date, which the
	 * Tour Manager dashboard's "Upcoming departures" KPI sorts and filters
	 * on (see Admin\Dashboard::upcoming_departure_query()) and therefore
	 * needs in a consistently parseable shape.
	 */
	private function date( int $post_id, string $key, string $label ): void {
		$value = (string) get_post_meta( $post_id, $key, true );
		echo '<p class="wpistic-field"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="date" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" class="widefat" value="' . esc_attr( $value ) . '"></p>';
	}

	/**
	 * @param array<string, string> $options
	 */
	private function select( int $post_id, string $key, string $label, array $options ): void {
		$value = (string) get_post_meta( $post_id, $key, true );
		echo '<p class="wpistic-field"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" class="widefat">';
		foreach ( $options as $opt_value => $opt_label ) {
			echo '<option value="' . esc_attr( $opt_value ) . '"' . selected( $value, $opt_value, false ) . '>' . esc_html( $opt_label ) . '</option>';
		}
		echo '</select></p>';
	}
}

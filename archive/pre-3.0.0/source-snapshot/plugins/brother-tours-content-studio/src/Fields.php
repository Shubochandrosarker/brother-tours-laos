<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

final class Fields {
	/** @var string[] */
	private const CONTENT_TYPES = array( 'wpistic_tour', 'wpistic_destination' );

	/** @var array<string,array{label:string,sanitize:string,type:string}> */
	private const TOUR_FIELDS = array(
		'wpistic_duration'       => array( 'label' => 'Duration', 'sanitize' => 'text', 'type' => 'text' ),
		'wpistic_from_price'     => array( 'label' => 'Starting price', 'sanitize' => 'price', 'type' => 'text' ),
		'bt_price_currency'      => array( 'label' => 'Price currency', 'sanitize' => 'currency', 'type' => 'text' ),
		'bt_price_note'          => array( 'label' => 'Price note', 'sanitize' => 'text', 'type' => 'text' ),
		'bt_ideal_traveler'      => array( 'label' => 'Ideal traveler profile', 'sanitize' => 'textarea', 'type' => 'textarea' ),
		'wpistic_accommodation'  => array( 'label' => 'Accommodation standard', 'sanitize' => 'text', 'type' => 'text' ),
		'wpistic_transport'      => array( 'label' => 'Transport details', 'sanitize' => 'text', 'type' => 'text' ),
		'bt_guide_credentials'   => array( 'label' => 'Guide credentials', 'sanitize' => 'text', 'type' => 'text' ),
		'bt_group_min'           => array( 'label' => 'Minimum group size', 'sanitize' => 'integer', 'type' => 'number' ),
		'bt_group_max'           => array( 'label' => 'Maximum group size', 'sanitize' => 'integer', 'type' => 'number' ),
		'wpistic_availability'   => array( 'label' => 'Availability status', 'sanitize' => 'status', 'type' => 'select' ),
		'wpistic_season'         => array( 'label' => 'Best season', 'sanitize' => 'text', 'type' => 'text' ),
		'bt_destination_ids'     => array( 'label' => 'Destination IDs', 'sanitize' => 'ids', 'type' => 'text' ),
	);

	/** @var array<string,array{label:string,sanitize:string,type:string}> */
	private const DESTINATION_FIELDS = array(
		'bt_best_time'       => array( 'label' => 'Best time to visit', 'sanitize' => 'text', 'type' => 'text' ),
		'bt_top_attractions' => array( 'label' => 'Top attractions', 'sanitize' => 'lines', 'type' => 'textarea' ),
		'bt_local_tips'      => array( 'label' => 'Local tips', 'sanitize' => 'textarea', 'type' => 'textarea' ),
		'bt_related_tours'   => array( 'label' => 'Related tour IDs', 'sanitize' => 'ids', 'type' => 'text' ),
	);

	/** @var array<string,array{label:string,sanitize:string,type:string}> */
	private const SEO_FIELDS = array(
		'bt_seo_title'       => array( 'label' => 'SEO title', 'sanitize' => 'text', 'type' => 'text' ),
		'bt_seo_description' => array( 'label' => 'Meta description', 'sanitize' => 'textarea', 'type' => 'textarea' ),
		'bt_seo_canonical'   => array( 'label' => 'Canonical URL', 'sanitize' => 'url', 'type' => 'url' ),
		'bt_seo_focus_keyword'=> array( 'label' => 'Focus keyword', 'sanitize' => 'text', 'type' => 'text' ),
	);

	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'enable_block_editor' ), 999, 2 );
		add_filter( 'use_block_editor_for_post', array( $this, 'preserve_elementor_posts' ), 999, 2 );
	}

	/**
	 * Content Studio is the controlled Gutenberg surface. Existing Elementor
	 * content is not deleted; it remains available for legacy pages and widgets.
	 */
	public function enable_block_editor( bool $use_block_editor, string $post_type ): bool {
		if ( in_array( $post_type, array( 'page', 'post', 'wpistic_tour', 'wpistic_destination' ), true ) ) {
			return true;
		}
		return $use_block_editor;
	}

	/** @param \WP_Post $post */
	public function preserve_elementor_posts( bool $use_block_editor, \WP_Post $post ): bool {
		$elementor_data = (string) get_post_meta( $post->ID, '_elementor_data', true );
		return $elementor_data !== '' && '[]' !== $elementor_data ? false : $use_block_editor;
	}

	public function register_meta(): void {
		foreach ( self::CONTENT_TYPES as $post_type ) {
			$fields = 'wpistic_tour' === $post_type ? self::TOUR_FIELDS : self::DESTINATION_FIELDS;
			foreach ( $fields as $key => $field ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => array( $this, 'sanitize_meta' ),
						'auth_callback'     => static fn(): bool => current_user_can( Capabilities::MANAGE_CONTENT ),
					)
				);
			}
		}

		foreach ( array( 'page', 'post', 'wpistic_tour', 'wpistic_destination' ) as $post_type ) {
			foreach ( self::SEO_FIELDS as $key => $field ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => array( $this, 'sanitize_meta' ),
						'auth_callback'     => static fn(): bool => current_user_can( Capabilities::VIEW_SEO ),
					)
				);
			}
		}
	}

	public function meta_boxes(): void {
		foreach ( self::CONTENT_TYPES as $post_type ) {
			add_meta_box( 'bt-cs-' . $post_type, __( 'Content Studio fields', 'brother-tours-content-studio' ), array( $this, 'content_box' ), $post_type, 'normal', 'high' );
		}
		foreach ( array( 'page', 'post', 'wpistic_tour', 'wpistic_destination' ) as $post_type ) {
			add_meta_box( 'bt-cs-seo', __( 'Content Studio SEO', 'brother-tours-content-studio' ), array( $this, 'seo_box' ), $post_type, 'normal', 'default' );
		}
	}

	/** @param \WP_Post $post */
	public function content_box( \WP_Post $post ): void {
		if ( ! current_user_can( Capabilities::MANAGE_CONTENT ) ) {
			return;
		}

		wp_nonce_field( 'bt_cs_save_content', 'bt_cs_content_nonce' );
		$fields = 'wpistic_tour' === $post->post_type ? self::TOUR_FIELDS : self::DESTINATION_FIELDS;
		?><div class="bt-cs-fields">
			<p><?php esc_html_e( 'These fields power tour cards, schema, filters and the structured visual templates. Leave unknown facts blank rather than inventing them.', 'brother-tours-content-studio' ); ?></p>
			<?php foreach ( $fields as $key => $field ) : $value = get_post_meta( $post->ID, $key, true ); ?>
				<p><label for="<?php echo esc_attr( 'bt-cs-' . $key ); ?>"><strong><?php echo esc_html( $field['label'] ); ?></strong></label><br>
				<?php $this->input( $key, $field['type'], $value ); ?></p>
			<?php endforeach; ?>
		</div><?php
	}

	/** @param \WP_Post $post */
	public function seo_box( \WP_Post $post ): void {
		if ( ! current_user_can( Capabilities::VIEW_SEO ) ) {
			return;
		}

		wp_nonce_field( 'bt_cs_save_seo', 'bt_cs_seo_nonce' );
		?><p><?php esc_html_e( 'SEOISTIC remains the primary SEO owner when active. These fields provide a portable, structured editing layer and fallback metadata for sites without another SEO provider.', 'brother-tours-content-studio' ); ?></p>
		<?php foreach ( self::SEO_FIELDS as $key => $field ) : $value = get_post_meta( $post->ID, $key, true ); ?>
			<p><label for="<?php echo esc_attr( 'bt-cs-' . $key ); ?>"><strong><?php echo esc_html( $field['label'] ); ?></strong></label><br>
			<?php $this->input( $key, $field['type'], $value ); ?></p>
		<?php endforeach;
	}

	/** @param mixed $value */
	private function input( string $key, string $type, mixed $value ): void {
		$id    = 'bt-cs-' . $key;
		$name  = 'bt_cs[' . $key . ']';
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( 'textarea' === $type ) {
			printf( '<textarea class="large-text" rows="4" id="%1$s" name="%2$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
			return;
		}
		if ( 'select' === $type ) {
			printf( '<select id="%1$s" name="%2$s"><option value="">%3$s</option>', esc_attr( $id ), esc_attr( $name ), esc_html__( 'Not specified', 'brother-tours-content-studio' ) );
			foreach ( array( 'enquiry_only' => 'Enquiry only', 'available' => 'Available', 'seasonal' => 'Seasonal', 'closed' => 'Closed' ) as $option => $label ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $option ), selected( $value, $option, false ), esc_html( $label ) );
			}
			echo '</select>';
			return;
		}
		printf( '<input class="regular-text" id="%1$s" type="%2$s" name="%3$s" value="%4$s">', esc_attr( $id ), esc_attr( $type ), esc_attr( $name ), esc_attr( $value ) );
	}

	/** @param \WP_Post $post */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, array_merge( self::CONTENT_TYPES, array( 'page', 'post' ) ), true ) ) {
			return;
		}

		$posted = isset( $_POST['bt_cs'] ) && is_array( $_POST['bt_cs'] ) ? wp_unslash( $_POST['bt_cs'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $posted ) {
			return;
		}

		$is_content = isset( $_POST['bt_cs_content_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bt_cs_content_nonce'] ) ), 'bt_cs_save_content' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$is_seo     = isset( $_POST['bt_cs_seo_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bt_cs_seo_nonce'] ) ), 'bt_cs_save_seo' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $is_content && current_user_can( Capabilities::MANAGE_CONTENT ) ) {
			$fields = 'wpistic_tour' === $post->post_type ? self::TOUR_FIELDS : ( 'wpistic_destination' === $post->post_type ? self::DESTINATION_FIELDS : array() );
			$this->save_fields( $post_id, $posted, $fields );
		}
		if ( $is_seo && current_user_can( Capabilities::VIEW_SEO ) ) {
			$this->save_fields( $post_id, $posted, self::SEO_FIELDS );
		}
	}

	/** @param array<string,mixed> $posted @param array<string,array{label:string,sanitize:string,type:string}> $fields */
	private function save_fields( int $post_id, array $posted, array $fields ): void {
		foreach ( $fields as $key => $field ) {
			$value = $this->sanitize_value( $posted[ $key ] ?? '', $field['sanitize'] );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/** @param mixed $value */
	public function sanitize_meta( mixed $value ): string {
		return sanitize_text_field( (string) $value );
	}

	/** @return string */
	private function sanitize_value( mixed $value, string $type ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		switch ( $type ) {
			case 'url': return esc_url_raw( $value );
			case 'price': return preg_match( '/^\d+(?:\.\d{1,2})?$/', trim( $value ) ) ? trim( $value ) : '';
			case 'currency': return preg_match( '/^[A-Z]{3}$/', strtoupper( trim( $value ) ) ) ? strtoupper( trim( $value ) ) : '';
			case 'integer': return (string) max( 0, absint( $value ) );
			case 'ids': return implode( ',', array_filter( array_map( 'absint', preg_split( '/[,\s]+/', $value ) ?: array() ) ) );
			case 'lines': return implode( "\n", array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', $value ) ?: array() ) ) );
			case 'textarea': return wp_kses_post( $value );
			case 'status': return in_array( sanitize_key( $value ), array( 'enquiry_only', 'available', 'seasonal', 'closed' ), true ) ? sanitize_key( $value ) : '';
			default: return sanitize_text_field( $value );
		}
	}
}

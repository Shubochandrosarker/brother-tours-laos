<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

final class Migration {
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::add_command( 'bt content-studio', $this );
		}
	}

	/** Export the current tour/destination source data without mutation. */
	public function export( array $args, array $assoc_args ): void {
		unset( $args );
		$payload = array( 'generatedAt' => gmdate( 'c' ), 'tours' => $this->records( 'wpistic_tour' ), 'destinations' => $this->records( 'wpistic_destination' ) );
		$json    = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$file    = sanitize_file_name( (string) ( $assoc_args['file'] ?? '' ) );
		if ( $file ) {
			$path = trailingslashit( sys_get_temp_dir() ) . $file;
			if ( ! wp_mkdir_p( dirname( $path ) ) || false === file_put_contents( $path, $json ) ) {
				\WP_CLI::error( __( 'The export file could not be written.', 'brother-tours-content-studio' ) );
			}
			\WP_CLI::success( sprintf( __( 'Export written to %s', 'brother-tours-content-studio' ), $path ) );
			return;
		}
		\WP_CLI::line( $json );
	}

	/** Show the migration map and never change data. */
	public function dry_run(): void {
		foreach ( array( 'wpistic_tour' => 'Tours', 'wpistic_destination' => 'Destinations' ) as $post_type => $label ) {
			$records = $this->records( $post_type );
			\WP_CLI::line( sprintf( '%s: %d records discovered', $label, count( $records ) ) );
			foreach ( array_slice( $records, 0, 5 ) as $record ) {
				\WP_CLI::line( sprintf( '  #%d %s', $record['id'], $record['title'] ) );
			}
		}
		\WP_CLI::warning( __( 'Dry run only. No content, metadata or URLs were changed.', 'brother-tours-content-studio' ) );
	}

	/**
	 * Copy only legacy values into empty Content Studio fields.
	 *
	 * @param array<int,string> $args
	 * @param array<string,mixed> $assoc_args
	 */
	public function migrate( array $args, array $assoc_args ): void {
		unset( $args );
		if ( empty( $assoc_args['confirm'] ) ) {
			\WP_CLI::error( __( 'Migration is write-enabled only with --confirm after a verified backup.', 'brother-tours-content-studio' ) );
		}
		$count = 0;
		$currency = strtoupper( trim( (string) get_option( 'wpistic_tm_currency', '' ) ) );
		foreach ( $this->records( 'wpistic_tour' ) as $record ) {
			if ( preg_match( '/^[A-Z]{3}$/', $currency ) && '' === (string) get_post_meta( $record['id'], 'bt_price_currency', true ) ) {
				update_post_meta( $record['id'], 'bt_price_currency', $currency );
				++$count;
			}
			$destination_ids = wp_get_post_terms( $record['id'], 'tour_destination', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $destination_ids ) && $destination_ids && '' === (string) get_post_meta( $record['id'], 'bt_destination_ids', true ) ) {
				update_post_meta( $record['id'], 'bt_destination_ids', implode( ',', array_map( 'absint', $destination_ids ) ) );
				++$count;
			}
			update_post_meta( $record['id'], '_bt_cs_migrated_version', BT_CS_VERSION );
		}
		\WP_CLI::success( sprintf( __( 'Migration completed. %d legacy fields were copied; reviewed content was not overwritten.', 'brother-tours-content-studio' ), $count ) );
	}

	/** @return array<int,array<string,mixed>> */
	private function records( string $post_type ): array {
		$ids = get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
		return array_map(
			static function ( int $id ): array {
				$keys = array( 'wpistic_duration', 'wpistic_from_price', 'wpistic_accommodation', 'wpistic_transport', 'wpistic_season', 'wpistic_availability', 'bt_price_currency', 'bt_price_note', 'bt_ideal_traveler', 'bt_guide_credentials', 'bt_group_min', 'bt_group_max', 'bt_destination_ids', 'bt_best_time', 'bt_top_attractions', 'bt_local_tips', 'bt_related_tours' );
				$meta = array();
				foreach ( $keys as $key ) {
					$value = get_post_meta( $id, $key, true );
					if ( '' !== (string) $value ) { $meta[ $key ] = is_scalar( $value ) ? (string) $value : $value; }
				}
				return array( 'id' => $id, 'title' => get_the_title( $id ), 'url' => get_permalink( $id ), 'thumbnailId' => get_post_thumbnail_id( $id ), 'content' => get_post_field( 'post_content', $id ), 'meta' => $meta );
			},
			array_map( 'absint', $ids )
		);
	}

}

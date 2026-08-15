<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Tours;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_Error;
use WP_Query;
use WP_REST_Request;

use function BrotherTours\OperationsApi\error;
use function BrotherTours\OperationsApi\response;

final class ToursController {

	private const META_SCALAR = array(
		'wpistic_accent_word', 'wpistic_subtitle', 'wpistic_short_summary', 'wpistic_duration',
		'wpistic_start_location', 'wpistic_end_location', 'wpistic_group_size', 'wpistic_minimum_age',
		'wpistic_season', 'wpistic_accommodation', 'wpistic_transport', 'wpistic_availability',
		'wpistic_departures_label', 'wpistic_from_price', 'wpistic_pricing_type', 'wpistic_deposit_type',
		'wpistic_deposit_value', 'wpistic_deposit_min', 'wpistic_deposit_max',
	);

	private const TAXONOMIES = array( 'country', 'region', 'travel_style', 'tour_category', 'tour_destination', 'tour_duration_range', 'tour_difficulty', 'tour_season' );

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			BTOA_NAMESPACE,
			'/tours',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', false ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', true ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/tours/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', false ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', true ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'delete_posts', true ),
				),
			)
		);
	}

	public function list( WP_REST_Request $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$status   = sanitize_key( (string) $request->get_param( 'status' ) );
		$allowed_status = array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' );
		$post_status = in_array( $status, $allowed_status, true ) ? $status : array( 'publish', 'draft', 'pending', 'private', 'future' );
		$query = new WP_Query(
			array(
				'post_type'      => 'wpistic_tour',
				'post_status'    => $post_status,
				'paged'          => $page,
				'posts_per_page' => $per_page,
				's'              => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'orderby'        => $this->orderby( (string) $request->get_param( 'orderby' ) ),
				'order'          => 'ASC' === strtoupper( (string) $request->get_param( 'order' ) ) ? 'ASC' : 'DESC',
			)
		);
		$items = array_map( fn( $post ) => $this->format( $post->ID ), $query->posts );
		return response(
			array(
				'items'      => $items,
				'total'      => (int) $query->found_posts,
				'page'       => $page,
				'perPage'    => $per_page,
				'totalPages' => (int) $query->max_num_pages,
			)
		);
	}

	public function get( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$post = get_post( $id );
		if ( ! $post || 'wpistic_tour' !== $post->post_type ) {
			return error( 'bt_ops_tour_not_found', __( 'Tour not found.', 'brother-tours-operations-api' ), 404 );
		}
		return response( $this->format( $id ) );
	}

	public function create( WP_REST_Request $request ) {
		$params = $this->params( $request );
		if ( empty( $params['title'] ) ) {
			return error( 'bt_ops_tour_title_required', __( 'A tour title is required.', 'brother-tours-operations-api' ), 422 );
		}
		$status = $this->status( (string) ( $params['status'] ?? 'draft' ) );
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			return error( 'bt_ops_publish_forbidden', __( 'You are not allowed to publish tours.', 'brother-tours-operations-api' ), 403 );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'wpistic_tour',
				'post_title'   => sanitize_text_field( (string) $params['title'] ),
				'post_content' => wp_kses_post( (string) ( $params['content'] ?? '' ) ),
				'post_excerpt' => sanitize_textarea_field( (string) ( $params['excerpt'] ?? '' ) ),
				'post_status'  => $status,
				'post_name'    => sanitize_title( (string) ( $params['slug'] ?? '' ) ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$this->save_fields( (int) $id, $params );
		return response( $this->format( (int) $id ), 201 );
	}

	public function update( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$post = get_post( $id );
		if ( ! $post || 'wpistic_tour' !== $post->post_type ) {
			return error( 'bt_ops_tour_not_found', __( 'Tour not found.', 'brother-tours-operations-api' ), 404 );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return error( 'bt_ops_tour_edit_forbidden', __( 'You are not allowed to edit this tour.', 'brother-tours-operations-api' ), 403 );
		}
		$params = $this->params( $request );
		$patch = array( 'ID' => $id );
		foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'slug' => 'post_name' ) as $input => $field ) {
			if ( array_key_exists( $input, $params ) ) {
				$patch[ $field ] = 'content' === $input ? wp_kses_post( (string) $params[ $input ] ) : ( 'excerpt' === $input ? sanitize_textarea_field( (string) $params[ $input ] ) : ( 'slug' === $input ? sanitize_title( (string) $params[ $input ] ) : sanitize_text_field( (string) $params[ $input ] ) ) );
			}
		}
		if ( array_key_exists( 'status', $params ) ) {
			$status = $this->status( (string) $params['status'] );
			if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
				return error( 'bt_ops_publish_forbidden', __( 'You are not allowed to publish tours.', 'brother-tours-operations-api' ), 403 );
			}
			$patch['post_status'] = $status;
		}
		$result = wp_update_post( $patch, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->save_fields( $id, $params );
		return response( $this->format( $id ) );
	}

	public function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$post = get_post( $id );
		if ( ! $post || 'wpistic_tour' !== $post->post_type ) {
			return error( 'bt_ops_tour_not_found', __( 'Tour not found.', 'brother-tours-operations-api' ), 404 );
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			return error( 'bt_ops_tour_delete_forbidden', __( 'You are not allowed to delete this tour.', 'brother-tours-operations-api' ), 403 );
		}
		$force = filter_var( $request->get_param( 'force' ), FILTER_VALIDATE_BOOLEAN );
		$deleted = wp_delete_post( $id, $force );
		return $deleted ? response( array( 'id' => $id, 'deleted' => true, 'force' => $force ) ) : error( 'bt_ops_tour_delete_failed', __( 'The tour could not be deleted.', 'brother-tours-operations-api' ), 500 );
	}

	/** @return array<string,mixed> */
	private function params( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		return is_array( $json ) ? $json : $request->get_params();
	}

	/** @param array<string,mixed> $params */
	private function save_fields( int $id, array $params ): void {
		if ( isset( $params['featuredMedia'] ) ) {
			$media_id = absint( $params['featuredMedia'] );
			if ( 0 === $media_id || 'attachment' === get_post_type( $media_id ) ) {
				set_post_thumbnail( $id, $media_id );
			}
		}
		if ( isset( $params['meta'] ) && is_array( $params['meta'] ) ) {
			foreach ( self::META_SCALAR as $key ) {
				if ( array_key_exists( $key, $params['meta'] ) ) {
					$value = sanitize_text_field( (string) $params['meta'][ $key ] );
					if ( 'wpistic_pricing_type' === $key && ! in_array( $value, array( 'request', 'starting', 'person', 'group' ), true ) ) { $value = 'request'; }
					if ( 'wpistic_deposit_type' === $key && ! in_array( $value, array( '', 'fixed', 'percent' ), true ) ) { $value = ''; }
					update_post_meta( $id, $key, $value );
				}
			}
			foreach ( array( 'wpistic_itinerary', 'wpistic_faq', 'wpistic_inclusions', 'wpistic_exclusions' ) as $key ) {
				if ( array_key_exists( $key, $params['meta'] ) ) {
					update_post_meta( $id, $key, $this->sanitize_complex_meta( $key, $params['meta'][ $key ] ) );
				}
			}
		}
		if ( isset( $params['taxonomies'] ) && is_array( $params['taxonomies'] ) ) {
			foreach ( self::TAXONOMIES as $taxonomy ) {
				if ( ! array_key_exists( $taxonomy, $params['taxonomies'] ) || ! taxonomy_exists( $taxonomy ) ) { continue; }
				$terms = is_array( $params['taxonomies'][ $taxonomy ] ) ? array_map( 'absint', $params['taxonomies'][ $taxonomy ] ) : array();
				wp_set_object_terms( $id, array_filter( $terms ), $taxonomy, false );
			}
		}
	}

	private function sanitize_complex_meta( string $key, mixed $value ): array {
		if ( ! is_array( $value ) ) { return array(); }
		if ( in_array( $key, array( 'wpistic_inclusions', 'wpistic_exclusions' ), true ) ) {
			return array_values( array_filter( array_map( static fn( $v ) => sanitize_text_field( (string) $v ), $value ) ) );
		}
		$out = array();
		if ( 'wpistic_itinerary' === $key ) {
			foreach ( $value as $row ) {
				if ( ! is_array( $row ) ) { continue; }
				$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
				$body  = sanitize_textarea_field( (string) ( $row['body'] ?? '' ) );
				if ( '' !== $title || '' !== $body ) { $out[] = array( 'title' => $title, 'body' => $body ); }
			}
		} elseif ( 'wpistic_faq' === $key ) {
			foreach ( $value as $row ) {
				if ( ! is_array( $row ) ) { continue; }
				$q = sanitize_text_field( (string) ( $row['q'] ?? '' ) );
				$a = sanitize_textarea_field( (string) ( $row['a'] ?? '' ) );
				if ( '' !== $q || '' !== $a ) { $out[] = array( 'q' => $q, 'a' => $a ); }
			}
		}
		return $out;
	}

	/** @return array<string,mixed>|null */
	private function format( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post ) { return null; }
		$meta = array();
		foreach ( self::META_SCALAR as $key ) { $meta[ $key ] = (string) get_post_meta( $id, $key, true ); }
		foreach ( array( 'wpistic_itinerary', 'wpistic_faq', 'wpistic_inclusions', 'wpistic_exclusions' ) as $key ) {
			$value = get_post_meta( $id, $key, true );
			$meta[ $key ] = is_array( $value ) ? $value : array();
		}
		$tax = array();
		foreach ( self::TAXONOMIES as $taxonomy ) {
			$terms = get_the_terms( $id, $taxonomy );
			$tax[ $taxonomy ] = is_array( $terms ) ? array_map( static fn( $term ) => array( 'id' => (int) $term->term_id, 'name' => (string) $term->name, 'slug' => (string) $term->slug ), $terms ) : array();
		}
		$thumb = get_post_thumbnail_id( $id );
		return array(
			'id'            => $id,
			'title'         => (string) $post->post_title,
			'slug'          => (string) $post->post_name,
			'content'       => (string) $post->post_content,
			'excerpt'       => (string) $post->post_excerpt,
			'status'        => (string) $post->post_status,
			'featuredMedia' => (int) $thumb,
			'featuredImage' => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'large' ) : '',
			'permalink'     => 'publish' === $post->post_status ? (string) get_permalink( $id ) : (string) get_preview_post_link( $id ),
			'meta'          => $meta,
			'taxonomies'    => $tax,
			'modifiedAt'    => get_post_modified_time( DATE_ATOM, true, $post ),
		);
	}

	private function status( string $status ): string {
		return in_array( $status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ? $status : 'draft';
	}

	private function orderby( string $orderby ): string {
		return in_array( $orderby, array( 'date', 'modified', 'title', 'ID' ), true ) ? $orderby : 'modified';
	}
}

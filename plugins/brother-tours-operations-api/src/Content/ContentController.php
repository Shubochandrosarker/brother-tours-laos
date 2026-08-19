<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Content;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_Post;
use WP_Query;
use WP_REST_Request;

use function BrotherTours\OperationsApi\error;
use function BrotherTours\OperationsApi\response;

/**
 * Articles, pages, tour CPTs, taxonomy and revisions.
 *
 * Registers inside BTOA_NAMESPACE because SessionController's
 * determine_current_user() only resolves the operations session for URIs
 * containing it — core wp/v2 with the ops cookie resolves to user 0 and 401s.
 */
final class ContentController {

	/**
	 * Post types this controller will ever touch.
	 *
	 * Accepting an arbitrary post_type from a request is how a content endpoint
	 * quietly becomes an arbitrary-CPT write endpoint. Never widen from input.
	 */
	private const ALLOWED_TYPES = array( 'post', 'page', 'wpistic_tour', 'wpistic_destination', 'wpistic_experience' );

	private const ALLOWED_STATUSES = array( 'draft', 'pending', 'publish', 'private', 'future' );

	/** Meta the dashboard may write. Exactly the keys already in production use. */
	private const WRITABLE_META = array( 'bt_seo_title', 'bt_seo_description', '_wpistic_tone' );

	/** SEOISTIC audit output. Readable so the editor can show a score; never written. */
	private const READONLY_META = array( '_seoistic_title', '_seoistic_description', '_seoistic_score', '_seoistic_last_audit' );

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			BTOA_NAMESPACE,
			'/content/types',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'types' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'edit_posts', false ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/content/posts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'edit_posts', false ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'edit_posts', true ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/content/posts/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'edit_posts', false ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'edit_posts', true ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'delete_posts', true ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/content/posts/(?P<id>\d+)/restore',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'restore' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'edit_posts', true ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/content/posts/(?P<id>\d+)/revisions',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'revisions' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'edit_posts', false ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/content/taxonomies',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'taxonomies' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'edit_posts', false ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/content/terms',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'terms' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'edit_posts', false ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_term' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'manage_categories', true ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ read */

	public function types( WP_REST_Request $request ) {
		$types = array();
		foreach ( self::ALLOWED_TYPES as $type ) {
			$object = get_post_type_object( $type );
			if ( ! $object || ! current_user_can( $object->cap->edit_posts ) ) {
				continue;
			}
			$counts  = (array) wp_count_posts( $type );
			$types[] = array(
				'type'         => $type,
				'label'        => $object->labels->name,
				'singular'     => $object->labels->singular_name,
				'hierarchical' => (bool) $object->hierarchical,
				'counts'       => array(
					'publish' => (int) ( $counts['publish'] ?? 0 ),
					'draft'   => (int) ( $counts['draft'] ?? 0 ),
					'pending' => (int) ( $counts['pending'] ?? 0 ),
					'private' => (int) ( $counts['private'] ?? 0 ),
					'future'  => (int) ( $counts['future'] ?? 0 ),
					'trash'   => (int) ( $counts['trash'] ?? 0 ),
				),
			);
		}
		return response( array( 'types' => $types ) );
	}

	public function list( WP_REST_Request $request ) {
		$type = $this->type_from( $request->get_param( 'type' ) );
		if ( is_wp_error( $type ) ) {
			return $type;
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$status   = sanitize_key( (string) $request->get_param( 'status' ) );
		$all      = array_merge( self::ALLOWED_STATUSES, array( 'trash' ) );

		$args = array(
			'post_type'      => $type,
			'post_status'    => in_array( $status, $all, true ) ? $status : $all,
			'paged'          => $page,
			'posts_per_page' => $per_page,
			's'              => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'orderby'        => $this->orderby( (string) $request->get_param( 'orderby' ) ),
			'order'          => 'ASC' === strtoupper( (string) $request->get_param( 'order' ) ) ? 'ASC' : 'DESC',
		);

		$author = (int) $request->get_param( 'author' );
		if ( $author > 0 ) {
			$args['author'] = $author;
		}
		$category = (int) $request->get_param( 'category' );
		if ( $category > 0 && 'post' === $type ) {
			$args['cat'] = $category;
		}

		$query = new WP_Query( $args );

		return response(
			array(
				'items'      => array_map( fn( WP_Post $post ) => $this->summary( $post ), $query->posts ),
				'total'      => (int) $query->found_posts,
				'page'       => $page,
				'perPage'    => $per_page,
				'totalPages' => (int) $query->max_num_pages,
			)
		);
	}

	public function get( WP_REST_Request $request ) {
		$post = $this->post_from( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return error( 'bt_ops_forbidden', __( 'You cannot edit this record.', 'brother-tours-operations-api' ), 403 );
		}
		return response( $this->detail( $post ) );
	}

	public function revisions( WP_REST_Request $request ) {
		$post = $this->post_from( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return error( 'bt_ops_forbidden', __( 'You cannot edit this record.', 'brother-tours-operations-api' ), 403 );
		}
		$items = array();
		foreach ( wp_get_post_revisions( $post->ID, array( 'posts_per_page' => 20 ) ) as $revision ) {
			$items[] = array(
				'id'         => (int) $revision->ID,
				'authorName' => get_the_author_meta( 'display_name', (int) $revision->post_author ),
				'modifiedAt' => $this->iso( (string) $revision->post_modified_gmt ),
				'isAutosave' => (bool) wp_is_post_autosave( $revision ),
			);
		}
		return response( array( 'items' => $items, 'total' => count( $items ) ) );
	}

	public function taxonomies( WP_REST_Request $request ) {
		$type = $this->type_from( $request->get_param( 'type' ) );
		if ( is_wp_error( $type ) ) {
			return $type;
		}
		$out = array();
		foreach ( get_object_taxonomies( $type, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->show_ui && ! $taxonomy->public ) {
				continue;
			}
			$out[] = array(
				'taxonomy'     => $taxonomy->name,
				'label'        => $taxonomy->labels->name,
				'hierarchical' => (bool) $taxonomy->hierarchical,
				'termCount'    => (int) wp_count_terms( array( 'taxonomy' => $taxonomy->name, 'hide_empty' => false ) ),
				'canAssign'    => current_user_can( $taxonomy->cap->assign_terms ),
				'canManage'    => current_user_can( $taxonomy->cap->manage_terms ),
			);
		}
		return response( array( 'taxonomies' => $out ) );
	}

	public function terms( WP_REST_Request $request ) {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return error( 'bt_ops_unknown_taxonomy', __( 'Unknown taxonomy.', 'brother-tours-operations-api' ), 404 );
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'search'     => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'number'     => 200,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return error( 'bt_ops_term_query_failed', $terms->get_error_message(), 500 );
		}
		$items = array_map(
			static fn( $term ) => array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => (int) $term->count,
			),
			$terms
		);
		return response( array( 'items' => $items, 'total' => count( $items ) ) );
	}

	/* ----------------------------------------------------------------- write */

	public function create( WP_REST_Request $request ) {
		$type = $this->type_from( $request->get_param( 'type' ) );
		if ( is_wp_error( $type ) ) {
			return $type;
		}

		$fields = $this->fields_from( $request );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		if ( 'publish' === ( $fields['core']['post_status'] ?? '' ) && ! current_user_can( 'publish_posts' ) ) {
			return error( 'bt_ops_forbidden', __( 'Publishing requires the publish_posts capability.', 'brother-tours-operations-api' ), 403 );
		}
		if ( '' === trim( (string) ( $fields['core']['post_title'] ?? '' ) ) ) {
			return error( 'bt_ops_missing_title', __( 'A title is required.', 'brother-tours-operations-api' ), 422 );
		}

		$id = wp_insert_post(
			array_merge(
				array( 'post_type' => $type, 'post_status' => 'draft', 'post_author' => get_current_user_id() ),
				$fields['core']
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return error( 'bt_ops_create_failed', $id->get_error_message(), 500 );
		}

		$this->apply_meta( (int) $id, $fields['meta'] );
		$this->apply_terms( (int) $id, $type, $fields['terms'] );
		$this->apply_thumbnail( (int) $id, $fields['featuredImageId'] );

		return response( $this->detail( get_post( (int) $id ) ), 201 );
	}

	public function update( WP_REST_Request $request ) {
		$post = $this->post_from( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return error( 'bt_ops_forbidden', __( 'You cannot edit this record.', 'brother-tours-operations-api' ), 403 );
		}

		// Elementor and block content cannot survive a round-trip through a plain
		// text field. The dashboard opens those read-only; enforce it here too,
		// because a client-side guard is not a guarantee.
		if ( null !== $request->get_param( 'content' ) && $this->is_builder_content( $post ) ) {
			return error(
				'bt_ops_builder_content_readonly',
				__( 'This record carries Elementor or block content and cannot be edited from the dashboard.', 'brother-tours-operations-api' ),
				409,
				array( 'editLink' => (string) get_edit_post_link( $post->ID, 'raw' ) )
			);
		}

		// Optimistic concurrency. Two administrators, one site, no locking.
		$expected = (string) $request->get_param( 'modifiedGmt' );
		if ( '' !== $expected && $expected !== $this->iso( (string) $post->post_modified_gmt ) ) {
			return error(
				'bt_ops_conflict',
				__( 'This record changed since you loaded it. Reload before saving.', 'brother-tours-operations-api' ),
				409,
				array( 'currentModifiedGmt' => $this->iso( (string) $post->post_modified_gmt ) )
			);
		}

		$fields = $this->fields_from( $request );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		if ( 'publish' === ( $fields['core']['post_status'] ?? '' ) && ! current_user_can( 'publish_posts' ) ) {
			return error( 'bt_ops_forbidden', __( 'Publishing requires the publish_posts capability.', 'brother-tours-operations-api' ), 403 );
		}

		if ( array() !== $fields['core'] ) {
			$result = wp_update_post( array_merge( array( 'ID' => $post->ID ), $fields['core'] ), true );
			if ( is_wp_error( $result ) ) {
				return error( 'bt_ops_update_failed', $result->get_error_message(), 500 );
			}
		}

		$this->apply_meta( (int) $post->ID, $fields['meta'] );
		$this->apply_terms( (int) $post->ID, $post->post_type, $fields['terms'] );
		$this->apply_thumbnail( (int) $post->ID, $fields['featuredImageId'] );

		return response( $this->detail( get_post( $post->ID ) ) );
	}

	public function delete( WP_REST_Request $request ) {
		$post = $this->post_from( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return error( 'bt_ops_forbidden', __( 'You cannot delete this record.', 'brother-tours-operations-api' ), 403 );
		}

		$force = filter_var( $request->get_param( 'force' ), FILTER_VALIDATE_BOOLEAN );
		$done  = $force ? wp_delete_post( $post->ID, true ) : wp_trash_post( $post->ID );
		if ( ! $done ) {
			return error( 'bt_ops_delete_failed', __( 'The record could not be removed.', 'brother-tours-operations-api' ), 500 );
		}
		return response( array( 'deleted' => true, 'id' => (int) $post->ID, 'permanent' => $force ) );
	}

	public function restore( WP_REST_Request $request ) {
		$post = $this->post_from( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return error( 'bt_ops_forbidden', __( 'You cannot edit this record.', 'brother-tours-operations-api' ), 403 );
		}
		if ( 'trash' !== $post->post_status ) {
			return error( 'bt_ops_not_trashed', __( 'This record is not in the trash.', 'brother-tours-operations-api' ), 409 );
		}
		if ( ! wp_untrash_post( $post->ID ) ) {
			return error( 'bt_ops_restore_failed', __( 'The record could not be restored.', 'brother-tours-operations-api' ), 500 );
		}
		return response( $this->detail( get_post( $post->ID ) ) );
	}

	public function create_term( WP_REST_Request $request ) {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return error( 'bt_ops_unknown_taxonomy', __( 'Unknown taxonomy.', 'brother-tours-operations-api' ), 404 );
		}
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === trim( $name ) ) {
			return error( 'bt_ops_missing_term_name', __( 'A term name is required.', 'brother-tours-operations-api' ), 422 );
		}
		$created = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $created ) ) {
			return error( 'bt_ops_term_create_failed', $created->get_error_message(), 422 );
		}
		$term = get_term( (int) $created['term_id'], $taxonomy );
		return response(
			array( 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'count' => (int) $term->count ),
			201
		);
	}

	/* ------------------------------------------------------------ input */

	/**
	 * Pulls only recognised fields off the request.
	 *
	 * An unknown key is rejected outright rather than forwarded to
	 * wp_insert_post(), so a typo fails loudly instead of silently doing
	 * nothing and a hostile key cannot ride along into the postarr.
	 *
	 * @return array{core: array<string,mixed>, meta: array<string,string>, terms: array<string,int[]>, featuredImageId: int|null}|\WP_Error
	 */
	private function fields_from( WP_REST_Request $request ) {
		$known = array(
			'type', 'title', 'slug', 'content', 'excerpt', 'status', 'date', 'author',
			'categories', 'tags', 'featuredImageId', 'modifiedGmt',
			'bt_seo_title', 'bt_seo_description', '_wpistic_tone',
		);
		foreach ( array_keys( (array) $request->get_json_params() ) as $key ) {
			if ( ! in_array( $key, $known, true ) ) {
				return error(
					'bt_ops_unknown_field',
					sprintf( /* translators: %s: field name */ __( 'Unrecognised field: %s', 'brother-tours-operations-api' ), sanitize_key( (string) $key ) ),
					422
				);
			}
		}

		$core = array();
		if ( null !== $request->get_param( 'title' ) ) {
			$core['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'slug' ) ) {
			$core['post_name'] = sanitize_title( (string) $request->get_param( 'slug' ) );
		}
		if ( null !== $request->get_param( 'content' ) ) {
			$core['post_content'] = wp_kses_post( (string) $request->get_param( 'content' ) );
		}
		if ( null !== $request->get_param( 'excerpt' ) ) {
			$core['post_excerpt'] = sanitize_textarea_field( (string) $request->get_param( 'excerpt' ) );
		}
		if ( null !== $request->get_param( 'status' ) ) {
			$status = sanitize_key( (string) $request->get_param( 'status' ) );
			if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
				return error( 'bt_ops_invalid_status', __( 'Unsupported post status.', 'brother-tours-operations-api' ), 422 );
			}
			$core['post_status'] = $status;
		}
		if ( null !== $request->get_param( 'date' ) ) {
			$time = strtotime( (string) $request->get_param( 'date' ) );
			if ( false === $time ) {
				return error( 'bt_ops_invalid_date', __( 'Unparseable publication date.', 'brother-tours-operations-api' ), 422 );
			}
			$core['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $time );
			$core['post_date']     = get_date_from_gmt( $core['post_date_gmt'] );
		}
		if ( null !== $request->get_param( 'author' ) ) {
			$author = (int) $request->get_param( 'author' );
			if ( $author > 0 && get_userdata( $author ) ) {
				$core['post_author'] = $author;
			}
		}

		$meta = array();
		foreach ( self::WRITABLE_META as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$meta[ $key ] = sanitize_text_field( (string) $request->get_param( $key ) );
			}
		}

		$terms = array();
		if ( null !== $request->get_param( 'categories' ) ) {
			$terms['category'] = array_map( 'absint', (array) $request->get_param( 'categories' ) );
		}
		if ( null !== $request->get_param( 'tags' ) ) {
			$terms['post_tag'] = array_map( 'absint', (array) $request->get_param( 'tags' ) );
		}

		return array(
			'core'            => $core,
			'meta'            => $meta,
			'terms'           => $terms,
			'featuredImageId' => null !== $request->get_param( 'featuredImageId' ) ? (int) $request->get_param( 'featuredImageId' ) : null,
		);
	}

	/** @param array<string,string> $meta */
	private function apply_meta( int $post_id, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			if ( ! in_array( $key, self::WRITABLE_META, true ) ) {
				continue;
			}
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
				continue;
			}
			update_post_meta( $post_id, $key, $value );
		}
	}

	/** @param array<string,int[]> $terms */
	private function apply_terms( int $post_id, string $post_type, array $terms ): void {
		foreach ( $terms as $taxonomy => $ids ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! in_array( $taxonomy, get_object_taxonomies( $post_type ), true ) ) {
				continue;
			}
			$object = get_taxonomy( $taxonomy );
			if ( ! $object || ! current_user_can( $object->cap->assign_terms ) ) {
				continue;
			}
			wp_set_object_terms( $post_id, array_values( array_filter( $ids ) ), $taxonomy, false );
		}
	}

	private function apply_thumbnail( int $post_id, ?int $attachment_id ): void {
		if ( null === $attachment_id ) {
			return;
		}
		if ( $attachment_id <= 0 ) {
			delete_post_thumbnail( $post_id );
			return;
		}
		if ( 'attachment' === get_post_type( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/* ----------------------------------------------------------- shaping */

	/** @return array<string,mixed> */
	private function summary( WP_Post $post ): array {
		$thumb = get_post_thumbnail_id( $post->ID );
		return array(
			'id'               => (int) $post->ID,
			'type'             => $post->post_type,
			'title'            => get_the_title( $post ),
			'slug'             => $post->post_name,
			'status'           => $post->post_status,
			'excerpt'          => (string) $post->post_excerpt,
			'authorId'         => (int) $post->post_author,
			'authorName'       => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'date'             => $this->iso( (string) $post->post_date_gmt ),
			'modifiedGmt'      => $this->iso( (string) $post->post_modified_gmt ),
			'featuredImage'    => $thumb ? ( wp_get_attachment_image_url( (int) $thumb, 'medium' ) ?: null ) : null,
			'hasBlocks'        => has_blocks( $post ),
			'hasElementorData' => $this->has_elementor( (int) $post->ID ),
			'seo'              => array(
				'title'       => (string) get_post_meta( $post->ID, 'bt_seo_title', true ),
				'description' => (string) get_post_meta( $post->ID, 'bt_seo_description', true ),
				'score'       => $this->seoistic_score( (int) $post->ID ),
			),
			'editLink'         => (string) get_edit_post_link( $post->ID, 'raw' ),
			'viewLink'         => (string) get_permalink( $post ),
		);
	}

	/** @return array<string,mixed> */
	private function detail( WP_Post $post ): array {
		$detail = $this->summary( $post );

		$detail['content']      = $post->post_content;
		$detail['canPublish']   = current_user_can( 'publish_posts' );
		$detail['canDelete']    = current_user_can( 'delete_post', $post->ID );
		$detail['readOnlyBody'] = $this->is_builder_content( $post );

		$detail['terms'] = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$assigned = wp_get_object_terms( $post->ID, $taxonomy );
			if ( is_wp_error( $assigned ) ) {
				continue;
			}
			$detail['terms'][ $taxonomy ] = array_map(
				static fn( $term ) => array( 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug ),
				$assigned
			);
		}

		// Read-only: SEOISTIC owns these keys and there is no write path for them.
		$detail['seoistic'] = array();
		foreach ( self::READONLY_META as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( '' !== $value && null !== $value && is_scalar( $value ) ) {
				$detail['seoistic'][ ltrim( $key, '_' ) ] = $value;
			}
		}

		return $detail;
	}

	/* ----------------------------------------------------------- helpers */

	private function post_from( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof WP_Post ) {
			return error( 'bt_ops_not_found', __( 'Record not found.', 'brother-tours-operations-api' ), 404 );
		}
		if ( ! in_array( $post->post_type, self::ALLOWED_TYPES, true ) ) {
			return error( 'bt_ops_type_not_allowed', __( 'This post type is not managed by the operations console.', 'brother-tours-operations-api' ), 403 );
		}
		return $post;
	}

	private function type_from( $type ) {
		$type = sanitize_key( (string) ( $type ?: 'post' ) );
		if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
			return error( 'bt_ops_type_not_allowed', __( 'This post type is not managed by the operations console.', 'brother-tours-operations-api' ), 403 );
		}
		return $type;
	}

	private function orderby( string $orderby ): string {
		return in_array( $orderby, array( 'date', 'modified', 'title', 'ID', 'menu_order' ), true ) ? $orderby : 'modified';
	}

	private function has_elementor( int $post_id ): bool {
		return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	private function is_builder_content( WP_Post $post ): bool {
		return $this->has_elementor( (int) $post->ID ) || has_blocks( $post );
	}

	private function seoistic_score( int $post_id ): ?int {
		$score = get_post_meta( $post_id, '_seoistic_score', true );
		return ( '' === $score || null === $score ) ? null : (int) $score;
	}

	private function iso( string $mysql_gmt ): ?string {
		if ( '' === $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) {
			return null;
		}
		$time = strtotime( $mysql_gmt . ' UTC' );
		return false === $time ? null : gmdate( 'c', $time );
	}
}

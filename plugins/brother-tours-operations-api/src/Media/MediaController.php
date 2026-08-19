<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Media;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_Post;
use WP_Query;
use WP_REST_Request;

use function BrotherTours\OperationsApi\error;
use function BrotherTours\OperationsApi\response;

/**
 * Media library — listing, upload, metadata and deletion.
 *
 * The plugin README says to use the core wp/v2 media API for uploads. That is
 * wrong for this dashboard and will 401: determine_current_user() only resolves
 * the operations session for URIs inside BTOA_NAMESPACE, so wp/v2 sees user 0.
 *
 * The routes below live under /content/media, not /media, and that is not a
 * matter of taste. Two plugins share the bridgistic/v1 namespace: this one and
 * the Bridgistic connector, which registers /media and /media/{id} of its own.
 * WP_REST_Server::register_route() array_merges a second registration into the
 * first rather than rejecting it, and dispatch() takes the first handler whose
 * methods match — so the connector, loading earlier, answered every request the
 * dashboard made here and rejected it with "Missing authentication headers",
 * demanding the HMAC headers of a plane the browser must never carry.
 *
 * Nothing errored. The route simply belonged to someone else. /content/* is
 * already proven clear by the sibling content routes, so media joins them.
 */
final class MediaController {

	/** Hard ceiling regardless of what PHP or the role would otherwise allow. */
	private const MAX_BYTES = 16777216; // 16 MB

	/**
	 * Explicit allowlist on top of get_allowed_mime_types() for the current
	 * user. SVG is deliberately absent — an XSS vector without a sanitiser.
	 */
	private const ALLOWED_MIME = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'application/pdf' );

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			BTOA_NAMESPACE,
			'/content/media',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'upload_files', false ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'upload_files', true ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/content/media/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'upload_files', false ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'upload_files', true ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'delete_posts', true ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */

	public function list( WP_REST_Request $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 40 ) ) );

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'paged'          => $page,
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => sanitize_text_field( (string) $request->get_param( 'search' ) ),
		);
		$mime = sanitize_text_field( (string) $request->get_param( 'mime_type' ) );
		if ( '' !== $mime ) {
			$args['post_mime_type'] = $mime;
		}

		$query = new WP_Query( $args );

		return response(
			array(
				'items'      => array_map( fn( WP_Post $post ) => $this->shape( $post ), $query->posts ),
				'total'      => (int) $query->found_posts,
				'page'       => $page,
				'perPage'    => $per_page,
				'totalPages' => (int) $query->max_num_pages,
			)
		);
	}

	public function get( WP_REST_Request $request ) {
		$post = $this->attachment_from( $request );
		return is_wp_error( $post ) ? $post : response( $this->shape( $post ) );
	}

	public function upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return error( 'bt_ops_no_file', __( 'No file was uploaded.', 'brother-tours-operations-api' ), 422 );
		}

		$file = $files['file'];
		if ( ! empty( $file['error'] ) ) {
			return error( 'bt_ops_upload_error', __( 'The upload did not complete.', 'brother-tours-operations-api' ), 422 );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return error(
				'bt_ops_file_too_large',
				sprintf( /* translators: %d: size in megabytes */ __( 'Files must be %d MB or smaller.', 'brother-tours-operations-api' ), (int) ( self::MAX_BYTES / 1048576 ) ),
				422
			);
		}

		// Trust the file's real type, not its name or the client-declared type.
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$mime    = (string) ( $checked['type'] ?: '' );
		if ( ! in_array( $mime, self::ALLOWED_MIME, true ) ) {
			return error( 'bt_ops_mime_not_allowed', __( 'That file type is not accepted. Images and PDFs only.', 'brother-tours-operations-api' ), 422 );
		}
		// And still respect what this particular user may upload.
		if ( ! in_array( $mime, (array) get_allowed_mime_types(), true ) ) {
			return error( 'bt_ops_mime_not_allowed_for_user', __( 'Your account cannot upload that file type.', 'brother-tours-operations-api' ), 403 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$handled = wp_handle_upload( $file, array( 'test_form' => false ) );
		if ( isset( $handled['error'] ) ) {
			return error( 'bt_ops_upload_failed', (string) $handled['error'], 500 );
		}

		$title = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?: pathinfo( (string) $handled['file'], PATHINFO_FILENAME ) ) );

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => (string) $handled['type'],
				'post_title'     => $title,
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			(string) $handled['file'],
			0,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			return error( 'bt_ops_attach_failed', $attachment_id->get_error_message(), 500 );
		}

		wp_update_attachment_metadata( (int) $attachment_id, wp_generate_attachment_metadata( (int) $attachment_id, (string) $handled['file'] ) );

		// Alt text matters: the site's SEO audit already flags missing alt, and
		// an uploader that skips it makes that worse.
		$alt = sanitize_text_field( (string) $request->get_param( 'alt' ) );
		if ( '' !== $alt ) {
			update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $alt );
		}
		$caption = sanitize_text_field( (string) $request->get_param( 'caption' ) );
		if ( '' !== $caption ) {
			wp_update_post( array( 'ID' => (int) $attachment_id, 'post_excerpt' => $caption ) );
		}

		return response( $this->shape( get_post( (int) $attachment_id ) ), 201 );
	}

	public function update( WP_REST_Request $request ) {
		$post = $this->attachment_from( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return error( 'bt_ops_forbidden', __( 'You cannot edit this attachment.', 'brother-tours-operations-api' ), 403 );
		}

		$update = array( 'ID' => (int) $post->ID );
		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'caption' ) ) {
			$update['post_excerpt'] = sanitize_text_field( (string) $request->get_param( 'caption' ) );
		}
		if ( null !== $request->get_param( 'description' ) ) {
			$update['post_content'] = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return error( 'bt_ops_update_failed', $result->get_error_message(), 500 );
			}
		}

		if ( null !== $request->get_param( 'alt' ) ) {
			update_post_meta( (int) $post->ID, '_wp_attachment_image_alt', sanitize_text_field( (string) $request->get_param( 'alt' ) ) );
		}

		return response( $this->shape( get_post( $post->ID ) ) );
	}

	public function delete( WP_REST_Request $request ) {
		$post = $this->attachment_from( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return error( 'bt_ops_forbidden', __( 'You cannot delete this attachment.', 'brother-tours-operations-api' ), 403 );
		}
		$force = filter_var( $request->get_param( 'force' ), FILTER_VALIDATE_BOOLEAN );
		if ( ! wp_delete_attachment( (int) $post->ID, $force ) ) {
			return error( 'bt_ops_delete_failed', __( 'The attachment could not be deleted.', 'brother-tours-operations-api' ), 500 );
		}
		return response( array( 'deleted' => true, 'id' => (int) $post->ID, 'permanent' => $force ) );
	}

	/* ------------------------------------------------------------------ */

	private function attachment_from( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return error( 'bt_ops_not_found', __( 'Attachment not found.', 'brother-tours-operations-api' ), 404 );
		}
		return $post;
	}

	/** @return array<string,mixed> */
	private function shape( WP_Post $post ): array {
		$id       = (int) $post->ID;
		$metadata = wp_get_attachment_metadata( $id );
		$alt      = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );

		// The full sizes map, so a grid can pick a thumbnail rather than loading
		// every full-size original.
		$sizes = array();
		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
			foreach ( array_keys( (array) $metadata['sizes'] ) as $size ) {
				$src = wp_get_attachment_image_src( $id, (string) $size );
				if ( is_array( $src ) ) {
					$sizes[ (string) $size ] = array( 'url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] );
				}
			}
		}

		return array(
			'id'          => $id,
			'title'       => get_the_title( $post ),
			'alt'         => $alt,
			'caption'     => (string) $post->post_excerpt,
			'description' => (string) $post->post_content,
			'mimeType'    => $post->post_mime_type,
			'url'         => (string) wp_get_attachment_url( $id ),
			'thumbnail'   => wp_get_attachment_image_url( $id, 'thumbnail' ) ?: null,
			'medium'      => wp_get_attachment_image_url( $id, 'medium' ) ?: null,
			'sizes'       => $sizes,
			'width'       => isset( $metadata['width'] ) ? (int) $metadata['width'] : null,
			'height'      => isset( $metadata['height'] ) ? (int) $metadata['height'] : null,
			'uploadedAt'  => $post->post_date_gmt ? gmdate( 'c', (int) strtotime( $post->post_date_gmt . ' UTC' ) ) : null,
			'authorName'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'missingAlt'  => '' === $alt && 0 === strpos( (string) $post->post_mime_type, 'image/' ),
		);
	}
}

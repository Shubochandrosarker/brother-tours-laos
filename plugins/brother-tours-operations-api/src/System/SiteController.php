<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\System;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_REST_Request;
use WP_User_Query;

use function BrotherTours\OperationsApi\response;

/**
 * Read-only site overview — the "what the WP dashboard shows" surface.
 *
 * Read-only by design. Plugin activation, updates and user creation stay in
 * wp-admin: those belong to the connector plane and its approval gate, not to
 * a browser session. The dashboard links out rather than reimplementing them.
 */
final class SiteController {

	/** Cron hooks worth surfacing. Anything else is noise for an operator. */
	private const WATCHED_HOOKS = array(
		'insightistic_run_sync',
		'insightistic_license_validate',
		'insightistic_send_email_automation',
		'bt_ops_run_pagespeed',
		'wp_scheduled_delete',
		'wp_version_check',
	);

	private const CONTENT_TYPES = array( 'post', 'page', 'wpistic_tour', 'wpistic_destination', 'wpistic_experience' );

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			BTOA_NAMESPACE,
			'/site/overview',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'overview' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_view_health', false ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/site/plugins',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'plugins' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'manage_options', false ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/site/users',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'users' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'list_users', false ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/site/cron',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'cron' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'manage_options', false ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */

	public function overview( WP_REST_Request $request ) {
		$types = array();
		foreach ( self::CONTENT_TYPES as $type ) {
			if ( ! post_type_exists( $type ) ) {
				continue;
			}
			$counts  = (array) wp_count_posts( $type );
			$object  = get_post_type_object( $type );
			$types[] = array(
				'type'    => $type,
				'label'   => $object ? $object->labels->name : $type,
				'publish' => (int) ( $counts['publish'] ?? 0 ),
				'draft'   => (int) ( $counts['draft'] ?? 0 ),
				'pending' => (int) ( $counts['pending'] ?? 0 ),
				'trash'   => (int) ( $counts['trash'] ?? 0 ),
			);
		}

		$theme        = wp_get_theme();
		$attachments  = (array) wp_count_posts( 'attachment' );
		$user_counts  = count_users();

		return response(
			array(
				'wpVersion'     => get_bloginfo( 'version' ),
				'phpVersion'    => PHP_VERSION,
				'siteName'      => get_bloginfo( 'name' ),
				'homeUrl'       => home_url( '/' ),
				'adminUrl'      => admin_url(),
				'timezone'      => wp_timezone_string(),
				'theme'         => array(
					'name'     => (string) $theme->get( 'Name' ),
					'version'  => (string) $theme->get( 'Version' ),
					'template' => $theme->get_template(),
				),
				'contentTypes'  => $types,
				// The cast binds tighter than ??, so coalesce first or a missing
				// key warns instead of defaulting.
				'mediaCount'    => (int) ( $attachments['inherit'] ?? 0 ),
				'userCount'     => (int) ( $user_counts['total_users'] ?? 0 ),
				'activePlugins' => count( (array) get_option( 'active_plugins', array() ) ),
			)
		);
	}

	public function plugins( WP_REST_Request $request ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		$items  = array();
		foreach ( get_plugins() as $file => $data ) {
			$items[] = array(
				'file'    => (string) $file,
				'name'    => (string) ( $data['Name'] ?? $file ),
				'version' => (string) ( $data['Version'] ?? '' ),
				'active'  => in_array( (string) $file, $active, true ),
			);
		}
		usort(
			$items,
			static function ( array $a, array $b ): int {
				if ( $a['active'] === $b['active'] ) {
					return strcasecmp( $a['name'], $b['name'] );
				}
				return $a['active'] ? -1 : 1;
			}
		);
		return response( array( 'items' => $items, 'total' => count( $items ) ) );
	}

	public function users( WP_REST_Request $request ) {
		$query = new WP_User_Query( array( 'number' => 100, 'orderby' => 'display_name', 'order' => 'ASC' ) );
		$items = array();
		foreach ( $query->get_results() as $user ) {
			$items[] = array(
				'id'           => (int) $user->ID,
				'displayName'  => $user->display_name,
				'email'        => $user->user_email,
				'roles'        => array_values( (array) $user->roles ),
				'postCount'    => (int) count_user_posts( (int) $user->ID, 'post' ),
				// Surfaced because bt_manage_operations does not imply edit_posts:
				// six of the seven operations roles cannot edit content.
				'canEditPosts' => user_can( $user, 'edit_posts' ),
			);
		}
		return response( array( 'items' => $items, 'total' => count( $items ) ) );
	}

	public function cron( WP_REST_Request $request ) {
		$crons = _get_cron_array();
		$items = array();
		if ( is_array( $crons ) ) {
			foreach ( $crons as $timestamp => $hooks ) {
				foreach ( (array) $hooks as $hook => $events ) {
					if ( ! in_array( (string) $hook, self::WATCHED_HOOKS, true ) ) {
						continue;
					}
					foreach ( (array) $events as $event ) {
						$items[] = array(
							'hook'     => (string) $hook,
							'nextRun'  => gmdate( 'c', (int) $timestamp ),
							'schedule' => (string) ( $event['schedule'] ?: 'single' ),
							'overdue'  => (int) $timestamp < time(),
						);
					}
				}
			}
		}
		usort( $items, static fn( array $a, array $b ): int => strcmp( $a['nextRun'], $b['nextRun'] ) );

		return response(
			array(
				'items'        => $items,
				'total'        => count( $items ),
				'cronDisabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			)
		);
	}
}

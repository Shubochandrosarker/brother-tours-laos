<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

final class Capabilities {
	private const SCHEMA_VERSION = '1.0.1';

	public const MANAGE_CONTENT    = 'bt_manage_content';
	public const EDIT_TEMPLATES    = 'bt_edit_templates';
	public const VIEW_SEO          = 'bt_view_seo';
	public const MANAGE_BOOKINGS   = 'bt_manage_bookings';
	public const VIEW_BOOKING_PII  = 'bt_view_booking_pii';
	public const MANAGE_PAYMENTS   = 'bt_manage_payments';
	public const MANAGE_OPERATIONS = 'bt_manage_operations';
	public const VIEW_HEALTH       = 'bt_view_health';

	/** @return string[] */
	public static function all(): array {
		return array(
			self::MANAGE_CONTENT,
			self::EDIT_TEMPLATES,
			self::VIEW_SEO,
			self::MANAGE_BOOKINGS,
			self::VIEW_BOOKING_PII,
			self::MANAGE_PAYMENTS,
			self::MANAGE_OPERATIONS,
			self::VIEW_HEALTH,
		);
	}

	public static function activate(): void {
		self::grant_caps();
		update_option( 'bt_cs_capabilities_version', self::SCHEMA_VERSION, false );
	}

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_upgrade' ), 20 );
	}

	public function maybe_upgrade(): void {
		if ( get_option( 'bt_cs_capabilities_version' ) === self::SCHEMA_VERSION ) {
			return;
		}

		self::grant_caps();
		update_option( 'bt_cs_capabilities_version', self::SCHEMA_VERSION, false );
	}

	/** @return array<string,string[]> */
	private static function role_capability_map(): array {
		return array(
			'administrator'                => self::all(),
			'editor'                       => array( self::MANAGE_CONTENT, self::EDIT_TEMPLATES, self::VIEW_SEO ),
			'tour_author'                  => array( self::MANAGE_CONTENT, self::VIEW_SEO ),
			'tour_staff'                   => array( self::MANAGE_CONTENT, self::MANAGE_BOOKINGS, self::MANAGE_OPERATIONS ),
			'wpistic_travel_manager'       => self::all(),
			'wpistic_travel_agent'         => array( self::MANAGE_CONTENT, self::VIEW_SEO, self::MANAGE_BOOKINGS, self::VIEW_BOOKING_PII, self::MANAGE_OPERATIONS ),
			'crm_owner'                    => array( self::MANAGE_BOOKINGS, self::VIEW_BOOKING_PII, self::MANAGE_PAYMENTS, self::MANAGE_OPERATIONS, self::VIEW_HEALTH ),
			'crm_manager'                  => array( self::MANAGE_BOOKINGS, self::VIEW_BOOKING_PII, self::MANAGE_OPERATIONS ),
			'crm_compliance_officer'       => array( self::VIEW_BOOKING_PII, self::VIEW_HEALTH ),
			'crm_sales'                    => array( self::MANAGE_BOOKINGS, self::VIEW_BOOKING_PII, self::MANAGE_OPERATIONS ),
			'crm_marketing'                => array( self::MANAGE_CONTENT, self::VIEW_SEO ),
			'crm_accountant'               => array( self::MANAGE_BOOKINGS, self::VIEW_BOOKING_PII, self::MANAGE_PAYMENTS ),
		);
	}

	private static function grant_caps(): void {
		foreach ( self::role_capability_map() as $role_name => $capabilities ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}
}

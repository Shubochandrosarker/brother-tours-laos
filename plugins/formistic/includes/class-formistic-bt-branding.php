<?php
/**
 * Brother Tours branding layer for the Formistic fork.
 *
 * Everything here is additive and hook-based. No upstream file is edited, so a
 * future upstream merge stays a clean fast-forward of includes/class-formistic-*.php
 * with this file layered on top. See ../UPSTREAM.md and docs/formistic-brother-tours-integration.md.
 *
 * Responsibilities:
 *  - relabel the admin submenu items (Forms/Inbox) for a shorter, cleaner
 *    sidebar, without changing the plugin's own "Formistic" identity;
 *  - route outbound Formistic mail through a configurable sender / Reply-To
 *    (never a hard-coded credential);
 *  - teach the capture normalizer the field labels the Brother Tours forms use.
 *
 * @package Wpistic_Formistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brother Tours presentation + delivery configuration.
 */
final class Wpistic_Formistic_BT_Branding {

	/** Option: envelope sender address used for Formistic mail. */
	const OPT_FROM_EMAIL = 'brother_tours_from_email';

	/** Option: envelope sender name. */
	const OPT_FROM_NAME = 'brother_tours_from_name';

	/** Option: Reply-To address. Falls back to the sender address. */
	const OPT_REPLY_TO = 'brother_tours_reply_to';

	/**
	 * The address the brand uses for guest correspondence. This is a default
	 * only — it is stored as an option and editable in the admin. Mail
	 * credentials (SMTP user/password, API keys) are never stored here; those
	 * belong to the transactional provider configured at the host level.
	 */
	const DEFAULT_FROM_EMAIL = 'enquiry@brothertours.com';

	/** Singleton. */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function boot() {
		// Admin presentation.
		add_action( 'admin_menu', [ $this, 'rebrand_menu' ], 999 );
		add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

		// Delivery configuration.
		add_filter( 'wp_mail_from', [ $this, 'mail_from' ], 20 );
		add_filter( 'wp_mail_from_name', [ $this, 'mail_from_name' ], 20 );
		add_filter( 'wp_mail', [ $this, 'add_reply_to' ], 20 );

		// Capture normalization for the Brother Tours field labels.
		add_filter( 'wpistic_formistic_field_aliases', [ $this, 'field_aliases' ] );
	}

	/* ==================================================================
	 * Admin presentation
	 * ================================================================== */

	/**
	 * Relabel the top-level menu and its first submenu entry.
	 *
	 * Done by rewriting the registered menu arrays rather than editing the
	 * upstream admin class, so upstream stays mergeable.
	 */
	public function rebrand_menu() {
		global $menu, $submenu;

		$page  = Wpistic_Formistic_Admin::PAGE;
		$short = __( 'Forms', 'formistic' );

		if ( is_array( $menu ) ) {
			foreach ( $menu as $i => $item ) {
				if ( isset( $item[2] ) && $page === $item[2] ) {
					/*
					 * Index 0 is the menu title with the unread-count bubble
					 * appended: 'Formistic <span class="awaiting-mod">3</span>'.
					 * Carry the bubble across so relabeling does not drop the
					 * count an operator relies on.
					 */
					$bubble  = '';
					$current = isset( $item[0] ) ? (string) $item[0] : '';
					if ( preg_match( '/<span class="awaiting-mod.*$/s', $current, $m ) ) {
						$bubble = $m[0];
					}
					$menu[ $i ][0] = $short . $bubble;
					break;
				}
			}
		}

		if ( isset( $submenu[ $page ][0][0] ) ) {
			$submenu[ $page ][0][0] = __( 'Inbox', 'formistic' );
		}
	}

	/**
	 * Note the fork provenance on the Plugins screen.
	 *
	 * @param string[] $links Existing row meta.
	 * @param string   $file  Plugin basename.
	 * @return string[]
	 */
	public function row_meta( $links, $file ) {
		if ( WPISTIC_FORMISTIC_BASENAME === $file ) {
			$links[] = esc_html__( 'Brother Tours fork of Formistic — see UPSTREAM.md', 'formistic' );
		}
		return $links;
	}

	/* ==================================================================
	 * Delivery configuration
	 * ================================================================== */

	/**
	 * Configured sender address, falling back to the brand default.
	 *
	 * @return string
	 */
	public static function from_email() {
		$email = (string) get_option( self::OPT_FROM_EMAIL, self::DEFAULT_FROM_EMAIL );
		return is_email( $email ) ? $email : self::DEFAULT_FROM_EMAIL;
	}

	/**
	 * Configured sender name.
	 *
	 * @return string
	 */
	public static function from_name() {
		$name = trim( (string) get_option( self::OPT_FROM_NAME, '' ) );
		return '' !== $name ? $name : get_bloginfo( 'name' );
	}

	/**
	 * Configured Reply-To, defaulting to the sender address.
	 *
	 * @return string
	 */
	public static function reply_to() {
		$email = (string) get_option( self::OPT_REPLY_TO, '' );
		return is_email( $email ) ? $email : self::from_email();
	}

	/**
	 * Whether the mail currently being sent originated from this plugin.
	 *
	 * Scoping to our own traffic matters: hijacking every wp_mail() on the site
	 * would silently rewrite the sender for unrelated plugins and core notices.
	 *
	 * @return bool
	 */
	private function is_ours() {
		return class_exists( 'Wpistic_Formistic_Capture' )
			&& true === Wpistic_Formistic_Capture::$sending_internal;
	}

	/**
	 * Sender address for our outbound mail.
	 *
	 * @param string $from Current from address.
	 * @return string
	 */
	public function mail_from( $from ) {
		return $this->is_ours() ? self::from_email() : $from;
	}

	/**
	 * Sender name for our outbound mail.
	 *
	 * @param string $name Current from name.
	 * @return string
	 */
	public function mail_from_name( $name ) {
		return $this->is_ours() ? self::from_name() : $name;
	}

	/**
	 * Add a Reply-To header to our outbound mail when one is not already set.
	 *
	 * @param array $args wp_mail() arguments.
	 * @return array
	 */
	public function add_reply_to( $args ) {
		if ( ! $this->is_ours() ) {
			return $args;
		}

		$headers = isset( $args['headers'] ) ? $args['headers'] : [];
		if ( is_string( $headers ) ) {
			$headers = array_filter( preg_split( '/\r\n|\r|\n/', $headers ) );
		}
		$headers = (array) $headers;

		foreach ( $headers as $header ) {
			if ( is_string( $header ) && 0 === stripos( trim( $header ), 'reply-to:' ) ) {
				return $args; // Caller set one explicitly; leave it alone.
			}
		}

		$headers[]       = 'Reply-To: ' . self::reply_to();
		$args['headers'] = $headers;

		return $args;
	}

	/* ==================================================================
	 * Capture normalization
	 * ================================================================== */

	/**
	 * Extend the label aliases Wpistic_Formistic_Capture::store() uses to pull
	 * sender name / phone / message out of an arbitrary field map.
	 *
	 * The Brother Tours forms label their phone field "Phone / WhatsApp" and
	 * their free-text field "Interests & notes" / "Anything else", none of which
	 * the stock alias list matches.
	 *
	 * @param array $aliases Existing alias map.
	 * @return array
	 */
	public function field_aliases( $aliases ) {
		$aliases = is_array( $aliases ) ? $aliases : [];

		$aliases['name'] = array_unique( array_merge(
			(array) ( $aliases['name'] ?? [] ),
			[ 'name', 'contact name', 'your name' ]
		) );

		$aliases['phone'] = array_unique( array_merge(
			(array) ( $aliases['phone'] ?? [] ),
			[ 'phone', 'mobile', 'whatsapp' ]
		) );

		$aliases['message'] = array_unique( array_merge(
			(array) ( $aliases['message'] ?? [] ),
			[ 'message', 'note', 'notes', 'help', 'interests', 'anything else', 'details' ]
		) );

		return $aliases;
	}
}

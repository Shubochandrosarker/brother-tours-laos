<?php
/**
 * The five Brother Tours forms, as editable Formistic form definitions.
 *
 * Seeding is idempotent and fill-only: a form is created once, tagged with the
 * meta key below, and never rewritten afterwards. Editors own the fields, the
 * labels and the copy from that point on — this class only guarantees the forms
 * exist with a sane starting schema.
 *
 * Routing to Tour Manager is keyed off the form's slug meta (not its title), so
 * an editor can rename "Build My Trip" without breaking ingestion.
 *
 * @package Wpistic_Formistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brother Tours form definitions + seeder.
 */
final class Wpistic_Formistic_BT_Forms {

	/**
	 * Meta key holding the stable Brother Tours form slug.
	 *
	 * This is the contract between Formistic and the Tour Manager ingestion
	 * adapter. Changing a value here changes how submissions are routed.
	 */
	const META_SLUG = '_brother_tours_form';

	/** Option recording when the seed last ran. */
	const OPT_SEEDED = 'brother_tours_forms_seeded_at';

	/* Stable slugs. */
	const BUILD_MY_TRIP         = 'build-my-trip';
	const CONTACT               = 'contact';
	const NEWSLETTER            = 'newsletter';
	const TRAVEL_AGENT          = 'travel-agent';
	const REQUEST_AVAILABILITY  = 'request-availability';

	/** Field key of the hidden tour-id field on the Request Availability form. */
	const FIELD_TOUR_ID = 'tour_id';

	/** Field key of the hidden tour-title field on the Request Availability form. */
	const FIELD_TOUR_TITLE = 'tour_title';

	/**
	 * All Brother Tours form slugs.
	 *
	 * @return string[]
	 */
	public static function slugs() {
		return [ self::BUILD_MY_TRIP, self::CONTACT, self::NEWSLETTER, self::TRAVEL_AGENT, self::REQUEST_AVAILABILITY ];
	}

	/**
	 * Slugs whose submissions become a Tour Manager inquiry record.
	 *
	 * Newsletter is deliberately absent: it stays a Formistic list entry only.
	 *
	 * @return string[]
	 */
	public static function ingested_slugs() {
		return [ self::BUILD_MY_TRIP, self::CONTACT, self::TRAVEL_AGENT, self::REQUEST_AVAILABILITY ];
	}

	/**
	 * Look up a form post id by its Brother Tours slug.
	 *
	 * @param string $slug One of the class constants.
	 * @return int Post id, or 0 when the form does not exist.
	 */
	public static function form_id( $slug ) {
		$found = get_posts( [
			'post_type'        => Wpistic_Formistic_Forms::POST_TYPE,
			'post_status'      => 'any',
			'numberposts'      => 1,
			'fields'           => 'ids',
			'meta_key'         => self::META_SLUG,
			'meta_value'       => (string) $slug,
			'suppress_filters' => false,
		] );

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Resolve the Brother Tours slug for a form, by form title.
	 *
	 * Formistic's capture pipeline passes the form *name* (its post title)
	 * rather than its id, so ingestion resolves back to a slug through here.
	 *
	 * @param string $form_name Form post title as captured.
	 * @return string Brother Tours slug, or '' when the form is not one of ours.
	 */
	public static function slug_for_form_name( $form_name ) {
		$form_name = trim( (string) $form_name );
		if ( '' === $form_name ) {
			return '';
		}

		// get_page_by_title() is deprecated as of WP 6.2; WP_Query's `title`
		// parameter is the supported exact-title lookup.
		$query = new WP_Query( [
			'post_type'              => Wpistic_Formistic_Forms::POST_TYPE,
			'post_status'            => 'any',
			'title'                  => $form_name,
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		] );

		if ( empty( $query->posts ) ) {
			return '';
		}

		$slug = (string) get_post_meta( (int) $query->posts[0], self::META_SLUG, true );

		return in_array( $slug, self::slugs(), true ) ? $slug : '';
	}

	/* ==================================================================
	 * Definitions
	 * ================================================================== */

	/**
	 * The seed definitions.
	 *
	 * Field `key` values are stable identifiers; `label` is editor-facing copy.
	 * No banned words, no budget question, no rating claims — see
	 * scripts/brand-lint.php, which scans this file.
	 *
	 * @return array<string,array>
	 */
	public static function definitions() {
		$consent_label = __( 'I agree that Brother Tours may store this message and contact me about my enquiry.', 'formistic' );

		return [

			self::BUILD_MY_TRIP => [
				'title'    => __( 'Build My Trip', 'formistic' ),
				'settings' => [
					'type'         => 'contact',
					'submit_label' => __( 'Send My Request', 'formistic' ),
					'success'      => __( 'Received with thanks. Your request is with our team in Vientiane.', 'formistic' ),
					'layout'       => 'two',
				],
				'fields'   => [
					[ 'key' => 'name',        'label' => __( 'Your name', 'formistic' ),                'type' => 'text',           'required' => true ],
					[ 'key' => 'email',       'label' => __( 'Email', 'formistic' ),                    'type' => 'email',          'required' => true ],
					[ 'key' => 'phone',       'label' => __( 'Phone / WhatsApp', 'formistic' ),         'type' => 'tel',            'required' => false ],
					[ 'key' => 'country',     'label' => __( 'Country', 'formistic' ),                  'type' => 'text',           'required' => false ],
					[ 'key' => 'dates',       'label' => __( 'Travel dates', 'formistic' ),             'type' => 'text',           'required' => false, 'placeholder' => __( 'Month and year, or a flexible range', 'formistic' ) ],
					[ 'key' => 'flexibility', 'label' => __( 'Are your dates flexible?', 'formistic' ), 'type' => 'radio',          'required' => false, 'options' => "Yes\nNo\nNot sure yet" ],
					[ 'key' => 'duration',    'label' => __( 'Duration', 'formistic' ),                 'type' => 'select',         'required' => false, 'options' => "Under 7 days\n7 to 10 days\n11 to 14 days\n15 to 20 days\n21+ days\nNot sure yet" ],
					[ 'key' => 'adults',      'label' => __( 'Adults', 'formistic' ),                   'type' => 'text',           'required' => false, 'placeholder' => '2' ],
					[ 'key' => 'children',    'label' => __( 'Children (and ages)', 'formistic' ),      'type' => 'text',           'required' => false ],
					[ 'key' => 'hotel',       'label' => __( 'Hotel preference', 'formistic' ),         'type' => 'select',         'required' => false, 'options' => "3-star\n4-star\n5-star\nHeritage\nRecommend for me" ],
					[ 'key' => 'interests',   'label' => __( 'Interests', 'formistic' ),                'type' => 'checkbox_group', 'required' => false, 'options' => "Culture and ritual\nHistory and heritage\nNature and landscape\nCuisine and markets\nSlow travel\nTrekking and light adventure\nCraft and artisans\nFamily travel\nPhotography" ],
					[ 'key' => 'notes',       'label' => __( 'What kind of experience are you imagining?', 'formistic' ), 'type' => 'textarea', 'required' => false ],
					[ 'key' => 'consent',     'label' => $consent_label,                                'type' => 'consent',        'required' => true ],
				],
			],

			self::CONTACT => [
				'title'    => __( 'Contact', 'formistic' ),
				'settings' => [
					'type'         => 'contact',
					'submit_label' => __( 'Send Message', 'formistic' ),
					'success'      => __( 'Received with thanks. We reply from Vientiane.', 'formistic' ),
					'layout'       => 'one',
				],
				'fields'   => [
					[ 'key' => 'name',     'label' => __( 'Your name', 'formistic' ),           'type' => 'text',     'required' => true ],
					[ 'key' => 'email',    'label' => __( 'Email', 'formistic' ),               'type' => 'email',    'required' => true ],
					[ 'key' => 'phone',    'label' => __( 'WhatsApp (optional)', 'formistic' ), 'type' => 'tel',      'required' => false ],
					[ 'key' => 'message',  'label' => __( 'Message', 'formistic' ),             'type' => 'textarea', 'required' => true ],
					[ 'key' => 'consent',  'label' => $consent_label,                           'type' => 'consent',  'required' => true ],
				],
			],

			self::NEWSLETTER => [
				'title'    => __( 'Newsletter', 'formistic' ),
				'settings' => [
					// 'newsletter' keeps these out of the inbox entirely: the
					// upstream submit handler subscribes the address and returns
					// before store(), so no inquiry record is ever created.
					'type'         => 'newsletter',
					'submit_label' => __( 'Subscribe', 'formistic' ),
					'success'      => __( 'You are on the list. A few notes a year, from Vientiane.', 'formistic' ),
					'layout'       => 'one',
				],
				'fields'   => [
					[ 'key' => 'email',   'label' => __( 'Email', 'formistic' ), 'type' => 'email',   'required' => true ],
					[ 'key' => 'consent', 'label' => __( 'I agree to receive occasional stories from Brother Tours. Unsubscribe anytime.', 'formistic' ), 'type' => 'consent', 'required' => true ],
				],
			],

			self::REQUEST_AVAILABILITY => [
				'title'    => __( 'Request Tour Availability', 'formistic' ),
				'settings' => [
					'type'         => 'contact',
					'submit_label' => __( 'Request Availability', 'formistic' ),
					'success'      => __( 'Received with thanks. Our team in Vientiane will confirm availability and follow up.', 'formistic' ),
					'layout'       => 'two',
				],
				'fields'   => [
					// Populated per-instance by self::render_request_availability();
					// the seeded placeholder is only ever seen if the form is placed
					// via the raw [wpistic_form] shortcode outside a tour context.
					[ 'key' => self::FIELD_TOUR_ID,    'label' => __( 'Tour', 'formistic' ),        'type' => 'hidden', 'required' => false, 'placeholder' => '' ],
					[ 'key' => self::FIELD_TOUR_TITLE, 'label' => __( 'Tour name', 'formistic' ),   'type' => 'hidden', 'required' => false, 'placeholder' => '' ],
					[ 'key' => 'preferred_date',  'label' => __( 'Preferred date', 'formistic' ),   'type' => 'text',    'required' => false, 'placeholder' => __( 'Month and year', 'formistic' ) ],
					[ 'key' => 'alternative_date', 'label' => __( 'Alternative date', 'formistic' ), 'type' => 'text',   'required' => false, 'placeholder' => __( 'Month and year', 'formistic' ) ],
					[ 'key' => 'adults',      'label' => __( 'Adults', 'formistic' ),               'type' => 'text',    'required' => true,  'placeholder' => '2' ],
					[ 'key' => 'children',    'label' => __( 'Children (and ages)', 'formistic' ),  'type' => 'text',    'required' => false ],
					[ 'key' => 'hotel',       'label' => __( 'Hotel preference', 'formistic' ),     'type' => 'select',  'required' => false, 'options' => "3-star\n4-star\n5-star\nHeritage\nRecommend for me" ],
					[ 'key' => 'name',        'label' => __( 'Your name', 'formistic' ),            'type' => 'text',    'required' => true ],
					[ 'key' => 'country',     'label' => __( 'Country', 'formistic' ),              'type' => 'text',    'required' => false ],
					[ 'key' => 'email',       'label' => __( 'Email', 'formistic' ),                'type' => 'email',   'required' => true ],
					[ 'key' => 'phone',       'label' => __( 'Phone / WhatsApp', 'formistic' ),     'type' => 'tel',     'required' => false ],
					[ 'key' => 'notes',       'label' => __( 'Special requests', 'formistic' ),     'type' => 'textarea', 'required' => false ],
					[ 'key' => 'consent',     'label' => $consent_label,                            'type' => 'consent', 'required' => true ],
				],
			],

			self::TRAVEL_AGENT => [
				'title'    => __( 'Travel Agent', 'formistic' ),
				'settings' => [
					'type'         => 'contact',
					'submit_label' => __( 'Send Enquiry', 'formistic' ),
					'success'      => __( 'Received with thanks. Our partnerships team will be in touch.', 'formistic' ),
					'layout'       => 'two',
				],
				'fields'   => [
					[ 'key' => 'agency',     'label' => __( 'Agency', 'formistic' ),           'type' => 'text',           'required' => true ],
					[ 'key' => 'name',       'label' => __( 'Contact name', 'formistic' ),     'type' => 'text',           'required' => true ],
					[ 'key' => 'email',      'label' => __( 'Email', 'formistic' ),            'type' => 'email',          'required' => true ],
					[ 'key' => 'phone',      'label' => __( 'Phone / WhatsApp', 'formistic' ), 'type' => 'tel',            'required' => false ],
					[ 'key' => 'region',     'label' => __( 'Region or market', 'formistic' ), 'type' => 'text',           'required' => false ],
					[ 'key' => 'interest',   'label' => __( 'Interest', 'formistic' ),         'type' => 'checkbox_group', 'required' => false, 'options' => "Signature Journeys\nCustom journeys\nGroup departures\nMulti-country itineraries" ],
					[ 'key' => 'notes',      'label' => __( 'Notes', 'formistic' ),            'type' => 'textarea',       'required' => false ],
					[ 'key' => 'consent',    'label' => $consent_label,                        'type' => 'consent',        'required' => true ],
				],
			],
		];
	}

	/* ==================================================================
	 * Seeder
	 * ================================================================== */

	/**
	 * Seed once if activation never managed to.
	 *
	 * Called on `init`, where the form post type is definitely registered. Guards
	 * on the option rather than re-running the seeder on every request, so the
	 * cost after the first run is one cached option read. Without this, a site
	 * whose activation seeding failed would show no forms at all and the only
	 * remedy would be deactivating and reactivating the plugin.
	 *
	 * @return void
	 */
	public static function maybe_seed() {
		if ( get_option( self::OPT_SEEDED, '' ) ) {
			return;
		}

		self::seed();
	}

	/**
	 * Create any Brother Tours form that does not exist yet.
	 *
	 * Safe to run repeatedly — on activation, from WP-CLI, or by hand. Existing
	 * forms are left exactly as the editor left them.
	 *
	 * @return array<string,int> Map of slug => post id for every form that now exists.
	 */
	public static function seed() {
		$result = [];

		foreach ( self::definitions() as $slug => $def ) {

			$existing = self::form_id( $slug );
			if ( $existing ) {
				$result[ $slug ] = $existing;
				continue;
			}

			$post_id = wp_insert_post( [
				'post_type'   => Wpistic_Formistic_Forms::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $def['title'],
			], true );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, self::META_SLUG, $slug );

			// Normalize field rows to the shape the upstream editor expects.
			$fields = [];
			foreach ( $def['fields'] as $field ) {
				$fields[] = wp_parse_args( $field, [
					'key'         => '',
					'label'       => '',
					'type'        => 'text',
					'required'    => false,
					'placeholder' => '',
					'options'     => '',
				] );
			}

			$settings = wp_parse_args( $def['settings'], [
				'type'         => 'contact',
				'recipients'   => '',
				'submit_label' => __( 'Send', 'formistic' ),
				'success'      => '',
				'redirect'     => '',
				// Brother Tours palette: gold action on dark ink.
				'accent'       => '#C9A96E',
				'button_text'  => '#0E100D',
				'radius'       => '0',
				'spacing'      => '18',
				'layout'       => 'one',
				'width'        => '720',
			] );

			update_post_meta( $post_id, Wpistic_Formistic_Forms::META_FIELDS, wp_slash( wp_json_encode( $fields ) ) );
			update_post_meta( $post_id, Wpistic_Formistic_Forms::META_SETTINGS, wp_slash( wp_json_encode( $settings ) ) );

			$result[ $slug ] = (int) $post_id;
		}

		update_option( self::OPT_SEEDED, gmdate( 'c' ) );

		return $result;
	}

	/* ==================================================================
	 * Context-aware rendering — Request Tour Availability
	 * ================================================================== */

	/**
	 * Render the Request Tour Availability form with the current tour's id
	 * and title pre-filled into its two hidden fields.
	 *
	 * `[wpistic_form]` renders a hidden field's value from its static,
	 * form-wide `placeholder` (see Wpistic_Formistic_Forms::render_field()),
	 * which cannot vary per page. This wraps that render and substitutes the
	 * two known hidden inputs by field key, so the same seeded form carries a
	 * different tour on every tour page without editing upstream rendering.
	 *
	 * Used by the "Request Availability" Elementor widget and by editors
	 * placing the form manually with a shortcode wrapper. Falls back to an
	 * empty string if the form has been deleted (never happens through normal
	 * editing — forms are seeded, not deletable from the UI in the default
	 * role set — but a direct DB edit or WP-CLI call could remove one).
	 *
	 * @param int    $tour_id    Tour post id (0 when rendered outside a tour context).
	 * @param string $tour_title Tour title as it should appear in the team's inbox.
	 * @return string Rendered form HTML, or '' when the form does not exist.
	 */
	public static function render_request_availability( $tour_id, $tour_title ) {
		$form_id = self::form_id( self::REQUEST_AVAILABILITY );
		if ( ! $form_id || ! class_exists( 'Wpistic_Formistic_Forms' ) ) {
			return '';
		}

		$html = do_shortcode( sprintf( '[wpistic_form id="%d"]', $form_id ) );
		if ( '' === $html ) {
			return '';
		}

		$replacements = [
			self::hidden_field_pattern( self::FIELD_TOUR_ID )    => 'value="' . esc_attr( (string) absint( $tour_id ) ) . '"',
			self::hidden_field_pattern( self::FIELD_TOUR_TITLE ) => 'value="' . esc_attr( (string) $tour_title ) . '"',
		];

		foreach ( $replacements as $pattern => $value_attr ) {
			$html = preg_replace_callback(
				$pattern,
				static function ( $m ) use ( $value_attr ) {
					return $m[1] . $value_attr . $m[2];
				},
				$html,
				1
			);
		}

		return (string) $html;
	}

	/**
	 * Regex matching the hidden `<input>` for one field key, capturing the
	 * markup before and after its `value="..."` attribute so the value alone
	 * can be swapped without touching the rest of the tag.
	 *
	 * @param string $key Field key.
	 * @return string PCRE pattern.
	 */
	private static function hidden_field_pattern( $key ) {
		$name = preg_quote( 'wpistic_formistic_f[' . $key . ']', '~' );
		return '~(<input type="hidden" name="' . $name . '" )value="[^"]*"( ?>)~';
	}
}

<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

use WP_Block;

final class Blocks {
	/** @var array<int,array<string,mixed>> */
	private array $faq_schema = array();

	/** @var array<int,array<string,mixed>> */
	private array $review_schema = array();

	/** @var array<string,array<string,mixed>> */
	private const DEFINITIONS = array(
		'hero' => array(
			'heading'    => array( 'type' => 'string', 'default' => 'Experience Laos through the people who call it home.' ),
			'eyebrow'    => array( 'type' => 'string', 'default' => 'Private Laos journeys' ),
			'body'       => array( 'type' => 'string', 'default' => '' ),
			'imageId'    => array( 'type' => 'number', 'default' => 0 ),
			'imageUrl'   => array( 'type' => 'string', 'default' => '' ),
			'primaryText'=> array( 'type' => 'string', 'default' => 'Explore our journeys' ),
			'primaryUrl' => array( 'type' => 'string', 'default' => '/tours/' ),
			'secondaryText' => array( 'type' => 'string', 'default' => 'Build my trip' ),
			'secondaryUrl'  => array( 'type' => 'string', 'default' => '/build-my-trip/' ),
		),
		'tour-collection' => array(
			'heading' => array( 'type' => 'string', 'default' => 'Signature journeys' ),
			'body'    => array( 'type' => 'string', 'default' => '' ),
			'count'   => array( 'type' => 'number', 'default' => 6 ),
			'layout'  => array( 'type' => 'string', 'default' => 'grid' ),
			'category'=> array( 'type' => 'string', 'default' => '' ),
		),
		'destination-grid' => array(
			'heading' => array( 'type' => 'string', 'default' => 'Where we go' ),
			'body'    => array( 'type' => 'string', 'default' => '' ),
			'count'   => array( 'type' => 'number', 'default' => 6 ),
		),
		'trust-facts' => array(
			'items' => array( 'type' => 'array', 'default' => array( array( 'value' => '2010', 'label' => 'Licensed Lao guide' ), array( 'value' => '2018', 'label' => 'Brother Tours founded' ) ) ),
		),
		'founder-profile' => array(
			'name'        => array( 'type' => 'string', 'default' => '' ),
			'role'        => array( 'type' => 'string', 'default' => '' ),
			'bio'         => array( 'type' => 'string', 'default' => '' ),
			'credentials' => array( 'type' => 'string', 'default' => '' ),
			'imageId'     => array( 'type' => 'number', 'default' => 0 ),
			'imageUrl'    => array( 'type' => 'string', 'default' => '' ),
		),
		'review' => array(
			'quote'         => array( 'type' => 'string', 'default' => '' ),
			'author'        => array( 'type' => 'string', 'default' => '' ),
			'tripReference' => array( 'type' => 'string', 'default' => '' ),
			'rating'        => array( 'type' => 'number', 'default' => 0 ),
		),
		'itinerary' => array(
			'heading' => array( 'type' => 'string', 'default' => 'Day by day' ),
			'items'   => array( 'type' => 'array', 'default' => array() ),
		),
		'included-excluded' => array(
			'includedHeading' => array( 'type' => 'string', 'default' => "What's included" ),
			'excludedHeading' => array( 'type' => 'string', 'default' => "What's not included" ),
			'included'       => array( 'type' => 'array', 'default' => array() ),
			'excluded'       => array( 'type' => 'array', 'default' => array() ),
		),
		'faq' => array(
			'heading' => array( 'type' => 'string', 'default' => 'Questions travellers ask' ),
			'items'   => array( 'type' => 'array', 'default' => array() ),
		),
		'gallery-story' => array(
			'heading' => array( 'type' => 'string', 'default' => 'A closer look' ),
			'images'  => array( 'type' => 'array', 'default' => array() ),
		),
		'cta-inquiry' => array(
			'heading'      => array( 'type' => 'string', 'default' => 'Tell us what you are imagining.' ),
			'body'         => array( 'type' => 'string', 'default' => 'We design your journey around the way you want to experience Laos.' ),
			'primaryText'  => array( 'type' => 'string', 'default' => 'Build my trip' ),
			'primaryUrl'   => array( 'type' => 'string', 'default' => '/build-my-trip/' ),
			'whatsappText' => array( 'type' => 'string', 'default' => 'Ask on WhatsApp' ),
			'whatsappUrl'  => array( 'type' => 'string', 'default' => '' ),
		),
		'newsletter' => array(
			'heading' => array( 'type' => 'string', 'default' => 'Stay close to Laos' ),
			'body'    => array( 'type' => 'string', 'default' => '' ),
		),
	);

	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ), 30 );
		add_action( 'wp_footer', array( $this, 'print_schema' ), 20 );
		add_filter( 'block_categories_all', array( $this, 'category' ), 10, 2 );
	}

	/** @param array<int,array<string,mixed>> $categories @param \WP_Block_Editor_Context $context @return array<int,array<string,mixed>> */
	public function category( array $categories, mixed $context ): array {
		unset( $context );
		array_unshift( $categories, array( 'slug' => 'brother-tours', 'title' => __( 'Brother Tours', 'brother-tours-content-studio' ) ) );
		return $categories;
	}

	public function register_blocks(): void {
		wp_register_script( 'bt-cs-editor', BT_CS_URL . 'assets/editor.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ), BT_CS_VERSION, true );
		wp_register_style( 'bt-cs-frontend', BT_CS_URL . 'assets/frontend.css', array(), BT_CS_VERSION );
		wp_register_style( 'bt-cs-editor', BT_CS_URL . 'assets/editor.css', array( 'bt-cs-frontend' ), BT_CS_VERSION );

		foreach ( self::DEFINITIONS as $slug => $definition ) {
			register_block_type(
				'brother-tours/' . $slug,
				array(
					'api_version'     => 3,
					'attributes'      => $definition,
					'category'        => 'brother-tours',
					'editor_script'   => 'bt-cs-editor',
					'editor_style'    => 'bt-cs-editor',
					'style'           => 'bt-cs-frontend',
					'render_callback' => array( $this, 'render' ),
					'supports'        => array( 'align' => array( 'wide', 'full' ), 'anchor' => true, 'spacing' => array( 'margin' => true, 'padding' => true ) ),
				)
			);
		}
		$this->register_patterns();
	}

	private function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}
		register_block_pattern_category( 'brother-tours-content-studio', array( 'label' => __( 'Brother Tours Content Studio', 'brother-tours-content-studio' ) ) );
		register_block_pattern(
			'brother-tours/premium-homepage',
			array(
				'title'      => __( 'Premium homepage starter', 'brother-tours-content-studio' ),
				'description' => __( 'A controlled, tour-first homepage made from Brother Tours blocks.', 'brother-tours-content-studio' ),
				'categories' => array( 'brother-tours-content-studio' ),
				'content'    => '<!-- wp:brother-tours/hero /--><!-- wp:brother-tours/trust-facts /--><!-- wp:brother-tours/tour-collection /--><!-- wp:brother-tours/destination-grid /--><!-- wp:brother-tours/founder-profile /--><!-- wp:brother-tours/faq /--><!-- wp:brother-tours/cta-inquiry /-->',
			)
		);
		register_block_pattern(
			'brother-tours/tour-detail-starter',
			array(
				'title'      => __( 'Tour detail starter', 'brother-tours-content-studio' ),
				'description' => __( 'The recommended information order for a premium tour package.', 'brother-tours-content-studio' ),
				'categories' => array( 'brother-tours-content-studio' ),
				'content'    => '<!-- wp:paragraph {"className":"bt-cs-editor-note"} --><p class="bt-cs-editor-note">Add the tour overview above these blocks, then complete the structured Content Studio fields.</p><!-- /wp:paragraph --><!-- wp:brother-tours/itinerary /--><!-- wp:brother-tours/included-excluded /--><!-- wp:brother-tours/gallery-story /--><!-- wp:brother-tours/faq /--><!-- wp:brother-tours/cta-inquiry /-->',
			)
		);
	}

	/** @param array<string,mixed> $attributes */
	public function render( array $attributes, string $content, WP_Block $block ): string {
		unset( $content );
		$slug  = str_replace( 'brother-tours/', '', $block->name );
		$class = 'bt-cs-block bt-cs-' . sanitize_html_class( $slug );
		$class .= ! empty( $attributes['className'] ) ? ' ' . sanitize_html_class( (string) $attributes['className'] ) : '';

		$output = match ( $slug ) {
			'hero'              => $this->hero( $attributes, $class ),
			'tour-collection'   => $this->tour_collection( $attributes, $class ),
			'destination-grid'  => $this->destination_grid( $attributes, $class ),
			'trust-facts'       => $this->trust_facts( $attributes, $class ),
			'founder-profile'   => $this->founder_profile( $attributes, $class ),
			'review'            => $this->review( $attributes, $class ),
			'itinerary'         => $this->itinerary( $attributes, $class ),
			'included-excluded' => $this->included_excluded( $attributes, $class ),
			'faq'               => $this->faq( $attributes, $class ),
			'gallery-story'     => $this->gallery_story( $attributes, $class ),
			'cta-inquiry'       => $this->cta( $attributes, $class ),
			'newsletter'        => $this->newsletter( $attributes, $class ),
			default             => '',
		};

		return apply_filters( 'bt_cs_render_block', $output, $slug, $attributes );
	}

	/** @param array<string,mixed> $a */
	private function hero( array $a, string $class ): string {
		$media = $this->image( absint( $a['imageId'] ?? 0 ), (string) ( $a['imageUrl'] ?? '' ), (string) ( $a['heading'] ?? '' ), 'bt-cs-hero__image' );
		return '<section class="' . esc_attr( $class . ' bt-cs-hero' ) . '">' . $media . '<div class="bt-cs-hero__overlay"></div><div class="bt-cs-container bt-cs-hero__content"><p class="bt-cs-eyebrow">' . esc_html( (string) ( $a['eyebrow'] ?? '' ) ) . '</p><h1>' . wp_kses_post( (string) ( $a['heading'] ?? '' ) ) . '</h1><div class="bt-cs-lead">' . wp_kses_post( (string) ( $a['body'] ?? '' ) ) . '</div><div class="bt-cs-actions">' . $this->button( (string) ( $a['primaryText'] ?? '' ), (string) ( $a['primaryUrl'] ?? '' ), 'primary' ) . $this->button( (string) ( $a['secondaryText'] ?? '' ), (string) ( $a['secondaryUrl'] ?? '' ), 'secondary' ) . '</div></div></section>';
	}

	/** @param array<string,mixed> $a */
	private function tour_collection( array $a, string $class ): string {
		$query = array( 'post_type' => 'wpistic_tour', 'post_status' => 'publish', 'posts_per_page' => min( 12, max( 1, absint( $a['count'] ?? 6 ) ) ), 'no_found_rows' => true, 'orderby' => 'menu_order title', 'order' => 'ASC' );
		if ( ! empty( $a['category'] ) ) {
			$query['tax_query'] = array( array( 'taxonomy' => 'tour_category', 'field' => 'slug', 'terms' => sanitize_title( (string) $a['category'] ) ) );
		}
		$posts = get_posts( $query );
		$cards = '';
		foreach ( $posts as $post ) {
			$cards .= $this->tour_card( $post );
		}
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container"><div class="bt-cs-section-heading"><h2>' . esc_html( (string) ( $a['heading'] ?? '' ) ) . '</h2><div>' . wp_kses_post( (string) ( $a['body'] ?? '' ) ) . '</div></div><div class="bt-cs-tour-grid bt-cs-tour-grid--' . esc_attr( sanitize_key( (string) ( $a['layout'] ?? 'grid' ) ) ) . '">' . ( $cards ?: '<p>' . esc_html__( 'Tours will appear here once published.', 'brother-tours-content-studio' ) . '</p>' ) . '</div></div></section>';
	}

	/** @param array<string,mixed> $a */
	private function destination_grid( array $a, string $class ): string {
		$posts = get_posts( array( 'post_type' => 'wpistic_destination', 'post_status' => 'publish', 'posts_per_page' => min( 12, max( 1, absint( $a['count'] ?? 6 ) ) ), 'no_found_rows' => true, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
		$cards = '';
		foreach ( $posts as $post ) {
			$image = $this->image( get_post_thumbnail_id( $post ), '', get_the_title( $post ), 'bt-cs-card__image' );
			$tours = get_posts( array( 'post_type' => 'wpistic_tour', 'post_status' => 'publish', 'posts_per_page' => -1, 'no_found_rows' => false, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'tour_destination', 'field' => 'slug', 'terms' => $post->post_name ) ) ) );
			$cards .= '<a class="bt-cs-card bt-cs-destination-card" href="' . esc_url( get_permalink( $post ) ) . '">' . $image . '<span class="bt-cs-card__body"><span class="bt-cs-eyebrow">' . esc_html( sprintf( _n( '%d journey', '%d journeys', count( $tours ), 'brother-tours-content-studio' ), count( $tours ) ) ) . '</span><strong>' . esc_html( get_the_title( $post ) ) . '</strong></span></a>';
		}
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container"><div class="bt-cs-section-heading"><h2>' . esc_html( (string) ( $a['heading'] ?? '' ) ) . '</h2><div>' . wp_kses_post( (string) ( $a['body'] ?? '' ) ) . '</div></div><div class="bt-cs-destination-grid">' . ( $cards ?: '<p>' . esc_html__( 'Destinations will appear here once published.', 'brother-tours-content-studio' ) . '</p>' ) . '</div></div></section>';
	}

	/** @param array<string,mixed> $a */
	private function trust_facts( array $a, string $class ): string {
		$items = '';
		foreach ( (array) ( $a['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$items .= '<div class="bt-cs-fact"><strong>' . esc_html( (string) ( $item['value'] ?? '' ) ) . '</strong><span>' . esc_html( (string) ( $item['label'] ?? '' ) ) . '</span></div>';
		}
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container bt-cs-facts">' . $items . '</div></section>';
	}

	/** @param array<string,mixed> $a */
	private function founder_profile( array $a, string $class ): string {
		$name = trim( (string) ( $a['name'] ?? '' ) ) ?: (string) Settings::get( 'founder_name', '' );
		$role = trim( (string) ( $a['role'] ?? '' ) ) ?: (string) Settings::get( 'founder_credentials', '' );
		$image = $this->image( absint( $a['imageId'] ?? 0 ), (string) ( $a['imageUrl'] ?? '' ), $name, 'bt-cs-founder__image' );
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container bt-cs-founder"><div>' . $image . '</div><div><p class="bt-cs-eyebrow">' . esc_html__( 'The host', 'brother-tours-content-studio' ) . '</p><h2>' . esc_html( $name ) . '</h2><p class="bt-cs-founder__role">' . esc_html( $role ) . '</p><div>' . wp_kses_post( (string) ( $a['bio'] ?? '' ) ) . '</div><p class="bt-cs-founder__credentials">' . esc_html( (string) ( $a['credentials'] ?? $role ) ) . '</p></div></div></section>';
	}

	/** @param array<string,mixed> $a */
	private function review( array $a, string $class ): string {
		$quote = trim( (string) ( $a['quote'] ?? '' ) );
		$author = trim( (string) ( $a['author'] ?? '' ) );
		$rating = min( 5, max( 0, (int) ( $a['rating'] ?? 0 ) ) );
		if ( $quote === '' ) { return ''; }
		if ( $rating > 0 && $author !== '' && apply_filters( 'bt_cs_emit_review_schema', false ) ) {
			$this->review_schema[] = array( '@type' => 'Review', 'reviewBody' => wp_strip_all_tags( $quote ), 'author' => array( '@type' => 'Person', 'name' => $author ), 'reviewRating' => array( '@type' => 'Rating', 'ratingValue' => $rating, 'bestRating' => 5 ), 'itemReviewed' => array( '@type' => 'Organization', 'name' => (string) Settings::get( 'organization_name', 'Brother Tours' ) ) );
		}
		return '<article class="' . esc_attr( $class ) . '"><div class="bt-cs-stars" aria-label="' . esc_attr( sprintf( __( '%d out of 5 stars', 'brother-tours-content-studio' ), $rating ) ) . '">' . esc_html( $rating ? str_repeat( '★', $rating ) : '' ) . '</div><blockquote>“' . esc_html( $quote ) . '”</blockquote><cite>' . esc_html( $author ) . '</cite><span>' . esc_html( (string) ( $a['tripReference'] ?? '' ) ) . '</span></article>';
	}

	/** @param array<string,mixed> $a */
	private function itinerary( array $a, string $class ): string {
		$items = '';
		foreach ( (array) ( $a['items'] ?? array() ) as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$items .= '<details class="bt-cs-itinerary__item"' . ( 0 === $index ? ' open' : '' ) . '><summary><span>Day ' . esc_html( (string) ( $index + 1 ) ) . '</span><strong>' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</strong></summary><div>' . wp_kses_post( (string) ( $item['body'] ?? '' ) ) . ( ! empty( $item['meals'] ) ? '<p><b>' . esc_html__( 'Meals:', 'brother-tours-content-studio' ) . '</b> ' . esc_html( (string) $item['meals'] ) . '</p>' : '' ) . ( ! empty( $item['accommodation'] ) ? '<p><b>' . esc_html__( 'Stay:', 'brother-tours-content-studio' ) . '</b> ' . esc_html( (string) $item['accommodation'] ) . '</p>' : '' ) . '</div></details>';
		}
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container"><h2>' . esc_html( (string) ( $a['heading'] ?? '' ) ) . '</h2><div class="bt-cs-itinerary">' . $items . '</div></div></section>';
	}

	/** @param array<string,mixed> $a */
	private function included_excluded( array $a, string $class ): string {
		$column = static function ( mixed $values, string $heading, string $modifier ): string {
			$list = '';
			foreach ( (array) $values as $value ) {
				if ( is_array( $value ) ) { $value = $value['text'] ?? ''; }
				if ( trim( (string) $value ) !== '' ) { $list .= '<li>' . esc_html( (string) $value ) . '</li>'; }
			}
			return '<div class="bt-cs-included__column bt-cs-included__column--' . esc_attr( $modifier ) . '"><h3>' . esc_html( $heading ) . '</h3><ul>' . $list . '</ul></div>';
		};
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container bt-cs-included">' . $column( $a['included'] ?? array(), (string) ( $a['includedHeading'] ?? '' ), 'included' ) . $column( $a['excluded'] ?? array(), (string) ( $a['excludedHeading'] ?? '' ), 'excluded' ) . '</div></section>';
	}

	/** @param array<string,mixed> $a */
	private function faq( array $a, string $class ): string {
		$items = '';
		foreach ( (array) ( $a['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || trim( (string) ( $item['question'] ?? '' ) ) === '' || trim( (string) ( $item['answer'] ?? '' ) ) === '' ) { continue; }
			$question = sanitize_text_field( (string) $item['question'] );
			$answer   = wp_kses_post( (string) $item['answer'] );
			$items   .= '<details class="bt-cs-faq__item"><summary>' . esc_html( $question ) . '</summary><div>' . $answer . '</div></details>';
			$this->faq_schema[] = array( '@type' => 'Question', 'name' => $question, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $answer ) ) );
		}
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container"><h2>' . esc_html( (string) ( $a['heading'] ?? '' ) ) . '</h2><div class="bt-cs-faq">' . $items . '</div></div></section>';
	}

	/** @param array<string,mixed> $a */
	private function gallery_story( array $a, string $class ): string {
		$images = '';
		foreach ( (array) ( $a['images'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$images .= '<figure>' . $this->image( absint( $item['id'] ?? 0 ), (string) ( $item['url'] ?? '' ), (string) ( $item['alt'] ?? '' ), 'bt-cs-gallery__image' ) . ( ! empty( $item['caption'] ) ? '<figcaption>' . esc_html( (string) $item['caption'] ) . '</figcaption>' : '' ) . '</figure>';
		}
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container"><h2>' . esc_html( (string) ( $a['heading'] ?? '' ) ) . '</h2><div class="bt-cs-gallery">' . $images . '</div></div></section>';
	}

	/** @param array<string,mixed> $a */
	private function cta( array $a, string $class ): string {
		$whatsapp = (string) ( $a['whatsappUrl'] ?? '' );
		if ( '' === $whatsapp ) { $whatsapp = (string) Settings::get( 'whatsapp_url', '' ); }
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container bt-cs-cta"><div><h2>' . esc_html( (string) ( $a['heading'] ?? '' ) ) . '</h2><div>' . wp_kses_post( (string) ( $a['body'] ?? '' ) ) . '</div></div><div class="bt-cs-actions">' . $this->button( (string) ( $a['primaryText'] ?? '' ), (string) ( $a['primaryUrl'] ?? '' ), 'primary' ) . ( $whatsapp ? $this->button( (string) ( $a['whatsappText'] ?? '' ), $whatsapp, 'secondary' ) : '' ) . '</div></div></section>';
	}

	/** @param array<string,mixed> $a */
	private function newsletter( array $a, string $class ): string {
		$form = '';
		foreach ( array( 'wpistic_newsletter_form', 'formistic_newsletter' ) as $shortcode ) {
			if ( shortcode_exists( $shortcode ) ) { $form = do_shortcode( '[' . $shortcode . ']' ); break; }
		}
		if ( '' === $form ) { $form = '<p class="bt-cs-notice">' . esc_html__( 'Newsletter delivery is not configured yet. Connect Formistic or the approved email provider before publishing this block.', 'brother-tours-content-studio' ) . '</p>'; }
		return '<section class="' . esc_attr( $class ) . '"><div class="bt-cs-container"><h2>' . esc_html( (string) ( $a['heading'] ?? '' ) ) . '</h2><div>' . wp_kses_post( (string) ( $a['body'] ?? '' ) ) . '</div>' . wp_kses_post( $form ) . '</div></section>';
	}

	private function button( string $label, string $url, string $style ): string {
		if ( '' === trim( $label ) || '' === trim( $url ) ) { return ''; }
		return '<a class="bt-cs-button bt-cs-button--' . esc_attr( $style ) . '" href="' . esc_url( $this->url( $url ) ) . '">' . esc_html( $label ) . '<span aria-hidden="true">→</span></a>';
	}

	private function url( string $url ): string {
		return str_starts_with( $url, '/' ) ? home_url( $url ) : $url;
	}

	private function image( int $id, string $url, string $alt, string $class ): string {
		if ( $id > 0 ) {
			$attachment_alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$alt            = $attachment_alt ?: ( $alt ?: (string) get_the_title( $id ) );
			$image          = wp_get_attachment_image( $id, 'large', false, array( 'class' => $class, 'loading' => 'lazy', 'decoding' => 'async', 'alt' => $alt ) );
			return $image ?: '';
		}
		return $url ? '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">' : '';
	}

	private function tour_card( \WP_Post $post ): string {
		$price    = (string) get_post_meta( $post->ID, 'wpistic_from_price', true );
		$currency = (string) get_post_meta( $post->ID, 'bt_price_currency', true ) ?: (string) get_option( 'wpistic_tm_currency', '' );
		$price    = ( $price !== '' && $currency !== '' ) ? $currency . ' ' . $price : '';
		return '<a class="bt-cs-card bt-cs-tour-card" href="' . esc_url( get_permalink( $post ) ) . '">' . $this->image( get_post_thumbnail_id( $post ), '', get_the_title( $post ), 'bt-cs-card__image' ) . '<span class="bt-cs-card__body"><span class="bt-cs-eyebrow">' . esc_html( (string) get_post_meta( $post->ID, 'wpistic_duration', true ) ) . '</span><strong>' . esc_html( get_the_title( $post ) ) . '</strong>' . ( $price ? '<span class="bt-cs-card__price">' . esc_html( sprintf( __( 'From %s', 'brother-tours-content-studio' ), $price ) ) . '</span>' : '' ) . '</span></a>';
	}

	public function print_schema(): void {
		$graphs = array();
		if ( $this->faq_schema ) {
			$graphs[] = array( '@type' => 'FAQPage', '@id' => trailingslashit( get_permalink() ) . '#faq', 'mainEntity' => array_values( $this->faq_schema ) );
		}
		if ( ! empty( $this->review_schema ) ) {
			$graphs = array_merge( $graphs, $this->review_schema );
		}
		if ( ! $graphs ) { return; }
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graphs ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	}
}

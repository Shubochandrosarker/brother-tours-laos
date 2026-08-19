<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

final class Templates {
	public function register(): void {
		add_filter( 'default_content', array( $this, 'starter_content' ), 10, 2 );
	}

	/**
	 * Render the front page only when an operator explicitly enables it and the
	 * page contains block content. Empty content never replaces the proven PHP
	 * template, which makes rollout reversible.
	 */
	public static function render_front_page(): bool {
		if ( ! (bool) Settings::get( 'visual_homepage', false ) ) {
			return false;
		}
		$page_id = (int) get_option( 'page_on_front' );
		$content = $page_id ? (string) get_post_field( 'post_content', $page_id ) : '';
		if ( '' === trim( $content ) || ! has_blocks( $content ) ) {
			return false;
		}
		get_header();
		echo '<main class="bt-cs-visual-homepage">' . apply_filters( 'the_content', $content ) . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content applies block rendering and escaping.
		get_footer();
		return true;
	}

	/**
	 * Render a visual single template when the post has block content. Existing
	 * meta-driven templates remain the fallback until each record is migrated.
	 */
	public static function render_post_content( string $post_type ): bool {
		if ( ! is_singular( $post_type ) ) {
			return false;
		}
		$content = (string) get_post_field( 'post_content', get_queried_object_id() );
		if ( '' === trim( $content ) || ! has_blocks( $content ) ) {
			return false;
		}
		get_header();
		echo '<main class="bt-cs-visual-single bt-cs-visual-single--' . esc_attr( sanitize_html_class( $post_type ) ) . '">' . apply_filters( 'the_content', $content ) . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content applies block rendering and escaping.
		get_footer();
		return true;
	}

	/** @param string $content @param \WP_Post $post @return string */
	public function starter_content( string $content, \WP_Post $post ): string {
		unset( $post );
		return $content;
	}
}


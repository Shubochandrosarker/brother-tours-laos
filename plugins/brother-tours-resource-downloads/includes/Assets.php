<?php

declare(strict_types=1);

namespace BrotherTours\ResourceDownloads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end assets and the popup markup.
 *
 * Assets load only on pages that actually have a ready resource, so the rest of
 * the site pays nothing for this system. Nothing external is enqueued: no icon
 * font, no popup framework, no analytics snippet. The site's global GA4/GTM is
 * used if present and silently skipped if not.
 */
final class Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_popup' ) );
	}

	public function enqueue(): void {
		$resource = Registry::for_current_request();
		if ( null === $resource ) {
			return;
		}

		wp_enqueue_style(
			'btrd-resource',
			BTRD_URL . 'assets/css/bt-resource.css',
			array(),
			BTRD_VERSION
		);

		wp_enqueue_script(
			'btrd-resource',
			BTRD_URL . 'assets/js/bt-resource.js',
			array(),
			BTRD_VERSION,
			true
		);

		/**
		 * Filters the popup trigger configuration.
		 *
		 * Defaults deliberately differ from the legacy implementation, which
		 * opened after 2 seconds. Two seconds interrupts before a visitor has
		 * read anything, which reads as an ad rather than an offer. These
		 * defaults wait for evidence of interest instead.
		 *
		 * @param array<string, mixed> $config
		 */
		$triggers = (array) apply_filters(
			'btrd_trigger_config',
			array(
				// Timed trigger fires only after this long AND only if the
				// visitor has shown engagement (any scroll, or a pointer/key
				// interaction). A visitor who opened a tab and walked away
				// never sees it.
				'delay_ms'          => 10000,
				'require_engagement' => true,
				// Scroll trigger, whichever comes first.
				'scroll_percent'    => 40,
				// Desktop only; pointer-based exit intent is meaningless on touch.
				'exit_intent'       => true,
				// Do not auto-open again for this many days after a dismissal or
				// download. Manual buttons always work regardless.
				'suppress_days'     => 5,
			)
		);

		wp_localize_script(
			'btrd-resource',
			'BTResourceConfig',
			array(
				'resource'  => $resource,
				'triggers'  => $triggers,
				'landing'   => (string) $resource['canonical_page'],
				'i18n'      => array(
					'close'          => __( 'Close', 'brother-tours-resource-downloads' ),
					'downloadToast'  => __( 'Your guide is downloading.', 'brother-tours-resource-downloads' ),
					'openedToast'    => __( 'Opening your guide in a new tab.', 'brother-tours-resource-downloads' ),
					'blockedToast'   => __( 'Your browser blocked the new tab. Allow pop-ups for this site, or use Download instead.', 'brother-tours-resource-downloads' ),
					'dialogLabel'    => __( 'Free Brother Tours guide', 'brother-tours-resource-downloads' ),
				),
			)
		);
	}

	/**
	 * The popup lives in the footer as one instance for the page.
	 *
	 * It is inert markup until opened — no iframe, no PDF fetch, nothing that
	 * costs anything before interaction.
	 */
	public function render_popup(): void {
		$resource = Registry::for_current_request();
		if ( null === $resource ) {
			return;
		}

		$secondary = (string) $resource['secondary_action'];
		?>
		<div class="btrd-overlay" data-btrd-overlay hidden>
			<div
				class="btrd-dialog"
				role="dialog"
				aria-modal="true"
				aria-labelledby="btrd-title"
				aria-describedby="btrd-desc"
				data-btrd-dialog
			>
				<button type="button" class="btrd-close" data-btrd-close aria-label="<?php esc_attr_e( 'Close', 'brother-tours-resource-downloads' ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>

				<div class="btrd-grid">
					<?php if ( '' !== $resource['cover_image'] ) : ?>
						<div class="btrd-cover-wrap">
							<img
								class="btrd-cover"
								src="<?php echo esc_url( $resource['cover_image'] ); ?>"
								alt="<?php echo esc_attr( sprintf( /* translators: %s: guide name */ __( 'Cover of the %s', 'brother-tours-resource-downloads' ), $resource['resource_name'] ) ); ?>"
								width="420" height="594" loading="lazy" decoding="async"
							/>
						</div>
					<?php endif; ?>

					<div class="btrd-body">
						<p class="btrd-eyebrow"><?php esc_html_e( 'Free Brother Tours guide', 'brother-tours-resource-downloads' ); ?></p>
						<h2 class="btrd-title" id="btrd-title"><?php echo esc_html( $resource['popup_headline'] ?: $resource['resource_name'] ); ?></h2>
						<p class="btrd-name"><?php echo esc_html( $resource['resource_name'] ); ?></p>
						<p class="btrd-desc" id="btrd-desc"><?php echo esc_html( $resource['description'] ); ?></p>

						<?php if ( array() !== $resource['benefits'] ) : ?>
							<ul class="btrd-benefits">
								<?php foreach ( $resource['benefits'] as $benefit ) : ?>
									<li><?php echo esc_html( $benefit ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<div class="btrd-actions">
							<?php // A real file link, not a button — right-click and open-in-new-tab work. ?>
							<a
								class="btrd-btn btrd-btn--primary"
								href="<?php echo esc_url( $resource['pdf_url'] ); ?>"
								download="<?php echo esc_attr( $resource['pdf_filename'] ); ?>"
								data-btrd-download
							><?php echo esc_html( $resource['cta_label'] ); ?></a>

							<?php if ( 'none' !== $secondary ) : ?>
								<a
									class="btrd-btn btrd-btn--ghost"
									href="<?php echo esc_url( $resource['pdf_url'] ); ?>"
									target="_blank" rel="noopener"
									data-btrd-secondary="<?php echo esc_attr( $secondary ); ?>"
								><?php echo 'print' === $secondary
									? esc_html__( 'Print guide', 'brother-tours-resource-downloads' )
									: esc_html__( 'View guide', 'brother-tours-resource-downloads' ); ?></a>
							<?php endif; ?>
						</div>

						<p class="btrd-reassure">
							<?php
							$bits = array( __( 'Free', 'brother-tours-resource-downloads' ), __( 'No email required', 'brother-tours-resource-downloads' ) );
							if ( '' !== $resource['updated_date'] ) {
								/* translators: %s: review date, e.g. August 2026 */
								$bits[] = sprintf( __( 'Reviewed %s', 'brother-tours-resource-downloads' ), $resource['updated_date'] );
							}
							echo esc_html( implode( ' · ', $bits ) );
							?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<div class="btrd-toast" data-btrd-toast role="status" aria-live="polite" hidden></div>
		<?php
	}
}

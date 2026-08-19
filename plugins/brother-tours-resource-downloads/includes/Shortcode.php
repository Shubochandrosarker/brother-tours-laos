<?php

declare(strict_types=1);

namespace BrotherTours\ResourceDownloads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The inline resource CTA: [bt_resource_download id="lcr-guide"].
 *
 * Placed roughly 40-65% into the editorial content of a landing page, so the
 * offer arrives after the page has earned it rather than instead of content.
 * The popup is the interruption path; this is the patient one.
 */
final class Shortcode {

	public function register(): void {
		add_shortcode( 'bt_resource_download', array( $this, 'render' ) );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'    => '',
				'style' => 'inline', // inline | compact
			),
			is_array( $atts ) ? $atts : array(),
			'bt_resource_download'
		);

		$resource = Registry::get( sanitize_key( $atts['id'] ) );
		if ( null === $resource ) {
			return $this->admin_notice(
				sprintf( /* translators: %s: resource id */ __( 'Unknown resource id "%s".', 'brother-tours-resource-downloads' ), $atts['id'] )
			);
		}

		// A CTA for a resource with no file is worse than no CTA. Editors see
		// why in the editor; visitors see nothing at all.
		if ( ! Registry::is_ready( $resource ) ) {
			return $this->admin_notice(
				sprintf(
					/* translators: %s: resource name */
					__( 'The "%s" resource has no PDF attached yet, so its download section is hidden. Set pdf_url via the btrd_resources filter.', 'brother-tours-resource-downloads' ),
					$resource['resource_name']
				)
			);
		}

		$compact   = 'compact' === $atts['style'];
		$secondary = (string) $resource['secondary_action'];

		ob_start();
		?>
		<section class="btrd-inline<?php echo $compact ? ' btrd-inline--compact' : ''; ?>" data-btrd-inline>
			<?php if ( ! $compact && '' !== $resource['cover_image'] ) : ?>
				<div class="btrd-inline__cover">
					<img
						src="<?php echo esc_url( $resource['cover_image'] ); ?>"
						alt="<?php echo esc_attr( sprintf( /* translators: %s: guide name */ __( 'Cover of the %s', 'brother-tours-resource-downloads' ), $resource['resource_name'] ) ); ?>"
						width="420" height="594" loading="lazy" decoding="async"
					/>
				</div>
			<?php endif; ?>

			<div class="btrd-inline__body">
				<p class="btrd-eyebrow"><?php esc_html_e( 'Free travel guide', 'brother-tours-resource-downloads' ); ?></p>
				<h2 class="btrd-inline__title"><?php echo esc_html( $resource['resource_name'] ); ?></h2>
				<p class="btrd-inline__desc"><?php echo esc_html( $resource['description'] ); ?></p>

				<?php if ( array() !== $resource['benefits'] ) : ?>
					<ul class="btrd-benefits">
						<?php foreach ( $resource['benefits'] as $benefit ) : ?>
							<li><?php echo esc_html( $benefit ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="btrd-actions">
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
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shows a configuration problem to editors only. Visitors get an empty
	 * string rather than a broken section.
	 */
	private function admin_notice( string $message ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="btrd-admin-notice">' . esc_html( $message ) . '</p>';
	}
}

<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Notifications;

/**
 * Branded, email-client-safe HTML template (table layout, inline styles). Brand colors
 * from the design tokens. The footer tagline is filterable so the plugin stays reusable.
 */
final class EmailTemplate {

	/**
	 * @param array{heading?:string,intro?:string,rows?:array<string,string>,cta?:array{label?:string,url?:string}} $args
	 */
	public static function render( array $args ): string {
		$brand   = get_bloginfo( 'name' );
		$heading = $args['heading'] ?? '';
		$intro   = $args['intro'] ?? '';
		$rows    = $args['rows'] ?? array();
		$cta     = $args['cta'] ?? null;
		$tagline = (string) apply_filters( 'wpistic/email_tagline', 'Born Here. Guide Here.' );

		ob_start();
		?>
<!doctype html>
<html>
<body style="margin:0;background:#f4f1ea;font-family:Georgia,'Times New Roman',serif;color:#2a2a26;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f1ea;padding:24px 0;"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;max-width:600px;width:100%;border:1px solid #e7e1d4;">
	<tr><td style="background:#1B5E3B;padding:22px 32px;">
		<span style="font-family:Georgia,serif;font-size:18px;color:#EDE6D3;letter-spacing:.02em;"><?php echo esc_html( $brand ); ?></span>
	</td></tr>
	<tr><td style="padding:32px;">
		<?php if ( '' !== $heading ) : ?>
			<h1 style="font-family:Georgia,serif;font-size:24px;font-weight:400;color:#1B5E3B;margin:0 0 16px;"><?php echo wp_kses( $heading, array( 'em' => array() ) ); ?></h1>
		<?php endif; ?>
		<?php if ( '' !== $intro ) : ?>
			<p style="font-size:16px;line-height:1.7;color:#3a382f;margin:0 0 18px;"><?php echo wp_kses_post( $intro ); ?></p>
		<?php endif; ?>
		<?php if ( array() !== $rows ) : ?>
			<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 20px;border-top:1px solid #e7e1d4;">
				<?php foreach ( $rows as $label => $value ) : ?>
					<tr>
						<td style="padding:9px 0;font-size:13px;color:#8a8675;border-bottom:1px solid #e7e1d4;width:42%;vertical-align:top;"><?php echo esc_html( (string) $label ); ?></td>
						<td style="padding:9px 0;font-size:14px;color:#2a2a26;border-bottom:1px solid #e7e1d4;"><?php echo esc_html( (string) $value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>
		<?php if ( is_array( $cta ) && ! empty( $cta['url'] ) ) : ?>
			<p style="margin:24px 0 0;"><a href="<?php echo esc_url( $cta['url'] ); ?>" style="display:inline-block;background:#C9A96E;color:#0E100D;text-decoration:none;font-family:'Courier New',monospace;font-size:13px;letter-spacing:.08em;text-transform:uppercase;padding:14px 28px;"><?php echo esc_html( $cta['label'] ?? 'Continue' ); ?></a></p>
		<?php endif; ?>
	</td></tr>
	<tr><td style="background:#0E100D;padding:20px 32px;text-align:center;">
		<?php if ( '' !== $tagline ) : ?>
			<p style="font-family:Georgia,serif;font-size:14px;color:#C9A96E;margin:0;"><?php echo esc_html( $tagline ); ?></p>
		<?php endif; ?>
		<p style="font-family:'Courier New',monospace;font-size:11px;color:#7A7566;margin:6px 0 0;"><?php echo esc_html( $brand ); ?></p>
	</td></tr>
</table>
</td></tr></table>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}
}

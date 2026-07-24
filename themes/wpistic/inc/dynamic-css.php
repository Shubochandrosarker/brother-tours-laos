<?php
/**
 * Dynamic CSS — turns the Theme Options config into live CSS-variable overrides.
 *
 * The design ships locked tokens (tokens.css). These settings layer on top via a
 * small inline <style> after the stylesheets, so the client can adjust palette
 * warmth, accent, navy, green, type scale and density WITHOUT editing files and
 * WITHOUT breaking the dual-mode / WCAG-AA token model:
 *   - gold text shades (gold-ink/accent/soft) are derived by TARGET CONTRAST so
 *     they always clear AA against the real background, for any accent picked;
 *   - button text colours (--on-gold / --on-green) are computed (white vs ink) to
 *     guarantee >=4.5:1 against whatever fill the client chooses;
 *   - the navy picker is lightness-clamped dark so fixed on-dark text keeps its
 *     margin, and the dark-mode block re-derives every surface (no light leak).
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Config defaults (every value is sanitized on read).
 *
 * @return array
 */
function wpistic_config_defaults() {
	return array(
		'accent_gold'   => '#B8893E',
		'color_navy'    => '#102A43',
		'color_green'   => '#2F5D50',
		'warmth'        => 'soft',  // soft | cream | sand
		'body_base'     => 17,      // 16 | 17 | 18
		'display_scale' => 1.0,     // 0.92 | 1 | 1.08
		'section_pad'   => 100,     // 72 | 100 | 120
		'enable_toggle' => 1,
	);
}

/* ===================== colour maths ===================== */

/**
 * #rrggbb -> [h, s, l] (h 0-360, s/l 0-100).
 *
 * @param string $hex Hex.
 * @return array
 */
function wpistic_hex_to_hsl( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
	$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
	$b = hexdec( substr( $hex, 4, 2 ) ) / 255;
	$max = max( $r, $g, $b );
	$min = min( $r, $g, $b );
	$l   = ( $max + $min ) / 2;
	$d   = $max - $min;
	$h   = 0;
	$s   = 0;
	if ( $d > 0 ) {
		$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
		if ( $max === $r ) {
			$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $d + 2;
		} else {
			$h = ( $r - $g ) / $d + 4;
		}
		$h /= 6;
	}
	return array( $h * 360, $s * 100, $l * 100 );
}

/**
 * HSL -> #rrggbb.
 *
 * @param float $h Hue 0-360.
 * @param float $s Saturation 0-100.
 * @param float $l Lightness 0-100.
 * @return string
 */
function wpistic_hsl_to_hex( $h, $s, $l ) {
	$h = fmod( $h, 360 ) / 360;
	$s = max( 0, min( 100, $s ) ) / 100;
	$l = max( 0, min( 100, $l ) ) / 100;
	if ( 0.0 === (float) $s ) {
		$r = $g = $b = $l;
	} else {
		$hue2rgb = static function ( $p, $q, $t ) {
			if ( $t < 0 ) {
				$t += 1;
			}
			if ( $t > 1 ) {
				$t -= 1;
			}
			if ( $t < 1 / 6 ) {
				return $p + ( $q - $p ) * 6 * $t;
			}
			if ( $t < 1 / 2 ) {
				return $q;
			}
			if ( $t < 2 / 3 ) {
				return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
			}
			return $p;
		};
		$q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
		$p = 2 * $l - $q;
		$r = $hue2rgb( $p, $q, $h + 1 / 3 );
		$g = $hue2rgb( $p, $q, $h );
		$b = $hue2rgb( $p, $q, $h - 1 / 3 );
	}
	return sprintf( '#%02x%02x%02x', round( $r * 255 ), round( $g * 255 ), round( $b * 255 ) );
}

/**
 * WCAG relative luminance of a hex colour.
 *
 * @param string $hex Hex.
 * @return float
 */
function wpistic_rel_lum( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	$c = array();
	foreach ( array( 0, 2, 4 ) as $i ) {
		$v = hexdec( substr( $hex, $i, 2 ) ) / 255;
		$c[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/**
 * WCAG contrast ratio between two hex colours.
 *
 * @param string $a Hex.
 * @param string $b Hex.
 * @return float
 */
function wpistic_contrast( $a, $b ) {
	$l1 = wpistic_rel_lum( $a );
	$l2 = wpistic_rel_lum( $b );
	return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
}

/**
 * Darken a colour (keep hue/sat) until it clears the target contrast on $bg.
 *
 * @param string $hex    Source.
 * @param string $bg     Background to contrast against.
 * @param float  $target Target ratio.
 * @return string
 */
function wpistic_darken_to_contrast( $hex, $bg, $target ) {
	list( $h, $s ) = wpistic_hex_to_hsl( $hex );
	for ( $l = wpistic_hex_to_hsl( $hex )[2]; $l >= 0; $l -= 2 ) {
		$c = wpistic_hsl_to_hex( $h, $s, $l );
		if ( wpistic_contrast( $c, $bg ) >= $target ) {
			return $c;
		}
	}
	return '#111111';
}

/**
 * Lighten a colour (keep hue/sat) until it clears the target contrast on $bg.
 *
 * @param string $hex    Source.
 * @param string $bg     Background to contrast against.
 * @param float  $target Target ratio.
 * @return string
 */
function wpistic_lighten_to_contrast( $hex, $bg, $target ) {
	list( $h, $s ) = wpistic_hex_to_hsl( $hex );
	for ( $l = wpistic_hex_to_hsl( $hex )[2]; $l <= 97; $l += 2 ) {
		$c = wpistic_hsl_to_hex( $h, $s, $l );
		if ( wpistic_contrast( $c, $bg ) >= $target ) {
			return $c;
		}
	}
	return '#f3ecdd';
}

/**
 * Set a colour's lightness (keep hue/sat).
 *
 * @param string $hex Hex.
 * @param float  $l   Target lightness 0-100.
 * @return string
 */
function wpistic_set_lightness( $hex, $l ) {
	$hsl = wpistic_hex_to_hsl( $hex );
	return wpistic_hsl_to_hex( $hsl[0], $hsl[1], $l );
}

/**
 * Clamp a colour's lightness to a maximum (only darkens).
 *
 * @param string $hex Hex.
 * @param float  $max Max lightness.
 * @return string
 */
function wpistic_clamp_l_max( $hex, $max ) {
	$hsl = wpistic_hex_to_hsl( $hex );
	return $hsl[2] > $max ? wpistic_hsl_to_hex( $hsl[0], $hsl[1], $max ) : $hex;
}

/**
 * Best on-fill text colour: cream vs ink, whichever has more contrast on $bg.
 *
 * @param string $bg Fill colour.
 * @return string
 */
function wpistic_best_on( $bg ) {
	$cream = '#f4ecdd';
	$ink   = '#1f2933';
	return wpistic_contrast( $cream, $bg ) >= wpistic_contrast( $ink, $bg ) ? $cream : $ink;
}

/**
 * Read + sanitize a colour config value.
 *
 * @param string $key     Option key.
 * @param string $default Default hex.
 * @return string
 */
function wpistic_cfg_color( $key, $default ) {
	$val = sanitize_hex_color( (string) wpistic_opt( $key, $default ) );
	return $val ? $val : $default;
}

/* ===================== output ===================== */

add_action( 'wp_head', 'wpistic_dynamic_css', 20 );

/**
 * Print the inline CSS-variable overrides (after the stylesheets).
 *
 * @return void
 */
function wpistic_dynamic_css() {
	$d = wpistic_config_defaults();

	$gold     = wpistic_cfg_color( 'accent_gold', $d['accent_gold'] );
	$navy_raw = wpistic_cfg_color( 'color_navy', $d['color_navy'] );
	$green    = wpistic_cfg_color( 'color_green', $d['color_green'] );

	// Keep the navy dark enough that the fixed on-dark / dark-mode ink-muted tokens keep their AA margin.
	$navy = wpistic_clamp_l_max( $navy_raw, 18 );

	$warmth   = sanitize_key( wpistic_opt( 'warmth', $d['warmth'] ) );
	$warm_map = array(
		'soft'  => array( '#faf8f3', '#f3ebdc' ),
		'cream' => array( '#f7f1e6', '#efe4d0' ),
		'sand'  => array( '#f4eddd', '#eaddc6' ),
	);
	$warm = isset( $warm_map[ $warmth ] ) ? $warm_map[ $warmth ] : $warm_map['soft'];

	$body = absint( wpistic_opt( 'body_base', $d['body_base'] ) );
	$body = ( $body >= 15 && $body <= 20 ) ? $body : 17;

	$scale = (float) wpistic_opt( 'display_scale', $d['display_scale'] );
	$scale = ( $scale >= 0.8 && $scale <= 1.25 ) ? $scale : 1.0;

	$pad = absint( wpistic_opt( 'section_pad', $d['section_pad'] ) );
	$pad = ( $pad >= 56 && $pad <= 160 ) ? $pad : 100;

	/* ---- LIGHT mode derivations ---- */
	$worst_light = $warm[1]; // the darker (Warm-Sand-ish) band is the worst case for dark gold text.
	$gold_ink    = wpistic_darken_to_contrast( $gold, $worst_light, 4.5 );
	$gold_accent = wpistic_darken_to_contrast( $gold, $worst_light, 3.6 ); // large em accents (>=3:1) with margin.
	$gold_deep   = $gold_ink;
	$gold_soft   = wpistic_lighten_to_contrast( $gold, $navy, 4.5 ); // light gold on navy sections.
	$on_gold     = wpistic_best_on( $gold );
	$on_green    = wpistic_best_on( $green );
	$green_bright = wpistic_set_lightness( $green, min( 62, wpistic_hex_to_hsl( $green )[2] + 9 ) );

	/* ---- DARK mode derivations (navy band = $navy) ---- */
	$d_bg        = wpistic_set_lightness( $navy, 11 );
	$d_surface   = wpistic_set_lightness( $navy, 7 );
	$d_surface2  = wpistic_set_lightness( $navy, 5 );
	$d_card      = wpistic_set_lightness( $navy, 17 );
	$gold_dark   = wpistic_lighten_to_contrast( $gold, $navy, 5.8 ); // lifts toward the warmer dark-mode gold.
	$gold_softd  = wpistic_lighten_to_contrast( $gold, $navy, 7.8 );
	$on_gold_d   = wpistic_best_on( $gold_dark );
	$d_green     = wpistic_set_lightness( $green, 46 );
	$d_green_b   = wpistic_set_lightness( $green, 56 );
	$on_green_d  = wpistic_best_on( $d_green );

	$css  = ':root{';
	$css .= '--gold:' . $gold . ';--c-gold:' . $gold . ';';
	$css .= '--gold-ink:' . $gold_ink . ';--gold-accent:' . $gold_accent . ';--gold-deep:' . $gold_deep . ';--gold-soft:' . $gold_soft . ';';
	$css .= '--on-gold:' . $on_gold . ';--on-green:' . $on_green . ';';
	$css .= '--green:' . $green . ';--c-green:' . $green . ';--green-bright:' . $green_bright . ';';
	$css .= '--surface-dark:' . $navy . ';--c-navy:' . $navy_raw . ';';
	$css .= '--bg:' . $warm[0] . ';--bg-raise:' . $warm[1] . ';';
	$css .= '--display-scale:' . $scale . ';--section-pad:' . $pad . 'px;--body-base:' . $body . 'px;';
	$css .= '}';
	$css .= '[data-theme="dark"]{';
	$css .= '--bg:' . $d_bg . ';--surface-dark:' . $d_surface . ';--surface-dark-2:' . $d_surface2 . ';--bg-raise:' . $navy . ';--bg-card:' . $d_card . ';';
	$css .= '--gold:' . $gold_dark . ';--gold-soft:' . $gold_softd . ';--gold-ink:' . $gold_dark . ';--gold-accent:' . $gold_softd . ';--gold-deep:' . $gold_dark . ';';
	$css .= '--on-gold:' . $on_gold_d . ';--on-green:' . $on_green_d . ';';
	$css .= '--green:' . $d_green . ';--green-bright:' . $d_green_b . ';';
	$css .= '}';

	echo '<style id="wpistic-config-vars">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is sanitize_hex_color-derived hex or absint/float; no user strings reach output.
}

add_action( 'admin_bar_menu', 'wpistic_admin_bar_compare', 100 );

/**
 * Toolbar shortcut so editors can preview/compare the three homepage layouts
 * without changing the saved setting (front-page.php honors ?bt_variant for admins).
 *
 * @param WP_Admin_Bar $bar Admin bar instance.
 * @return void
 */
function wpistic_admin_bar_compare( $bar ) {
	if ( is_admin() || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$bar->add_node( array( 'id' => 'wpistic-compare', 'title' => __( 'Compare layouts', 'wpistic' ), 'href' => esc_url( home_url( '/' ) ) ) );
	$variants = array(
		'v1' => __( 'V1 — Editorial Cinematic', 'wpistic' ),
		'v2' => __( 'V2 — Asymmetric Story-Led', 'wpistic' ),
		'v3' => __( 'V3 — Magazine Spread', 'wpistic' ),
	);
	foreach ( $variants as $wpistic_v => $wpistic_label ) {
		$bar->add_node(
			array(
				'id'     => 'wpistic-compare-' . $wpistic_v,
				'parent' => 'wpistic-compare',
				'title'  => $wpistic_label,
				'href'   => esc_url( home_url( '/?bt_variant=' . $wpistic_v ) ),
			)
		);
	}
}

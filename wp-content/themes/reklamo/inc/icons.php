<?php
/**
 * Inline SVG line icons in the mockup's style (1.5px strokes, rounded). No icon font,
 * no external requests. Add new ones here and use reklamo_icon( 'name' ).
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param string $name Icon key.
 * @param int    $size Pixel size.
 * @return string SVG markup (safe: static paths).
 */
function reklamo_icon( string $name, int $size = 24 ): string {
	$paths = array(
		'monitor'   => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
		'diamond'   => '<path d="M6 3h12l4 6-10 12L2 9z"/><path d="M2 9h20M10 3l2 6 2-6M12 9v12"/>',
		'pen'       => '<path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/>',
		'truck'     => '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
		'cube'      => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>',
		'upload'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/>',
		'nodes'     => '<circle cx="5" cy="19" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><path d="M6.5 17.5 10.5 13.5M13.5 10.5 17.5 6.5"/>',
		'check'     => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>',
		'card'      => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
		'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
		'medal'     => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-2 7 5.5-3 5.5 3-2-7"/>',
		'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'headset'   => '<path d="M4 13a8 8 0 0 1 16 0"/><rect x="3" y="13" width="4" height="6" rx="1"/><rect x="17" y="13" width="4" height="6" rx="1"/><path d="M19 19a3 3 0 0 1-3 3h-3"/>',
		'phone'     => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6.2 6.2l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
		'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'pin'       => '<path d="M12 22s7-7.5 7-13a7 7 0 1 0-14 0c0 5.5 7 13 7 13z"/><circle cx="12" cy="9" r="2.5"/>',
		'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v9h4v-9h3l1-4h-4V8z"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>',
		'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 11v6M8 7.5v.5M12 17v-6M12 13.5a2.5 2.5 0 0 1 5 0V17"/>',
		'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
		'heart'     => '<path d="M12 21s-7-4.5-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.5-9 9-9 9z"/>',
		'bag'       => '<path d="M6 7h12l1 14H5L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
		'chevron-l' => '<path d="m15 6-6 6 6 6"/>',
		'chevron-r' => '<path d="m9 6 6 6-6 6"/>',
		'notebook'  => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 3v18M3 7.5h2M3 12h2M3 16.5h2"/>',
		'ballpen'   => '<path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4z"/><path d="m14.5 5.5 3 3"/>',
		'gift'      => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13M12 8S10.5 3 8 4s0 4 4 4zM12 8s1.5-5 4-4 0 4-4 4z"/>',
		'mug'       => '<path d="M5 8h11v9a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16M8.5 3v2.5M12.5 3v2.5"/>',
		'bottle'    => '<path d="M10 2h4v3l2 3v12a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V8l2-3z"/><path d="M8 12h8"/>',
		'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'boxes'     => '<path d="M12 2l4.5 2.5v5L12 12 7.5 9.5v-5zM7.5 4.5 12 7l4.5-2.5M12 7v5"/><path d="M7 12l4.5 2.5v5L7 22l-4.5-2.5v-5zM2.5 14.5 7 17l4.5-2.5M7 17v5"/><path d="M17 12l4.5 2.5v5L17 22l-4.5-2.5v-5zM12.5 14.5 17 17l4.5-2.5M17 17v5"/>',
		'tag'       => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		'gear'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="icon icon-%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		$size,
		$paths[ $name ]
	);
}

/**
 * The brand lockup, linked home: the ring-R mark and the wordmark; with $slogan the version
 * carrying "Рекламни продукти с характер" (header only).
 *
 * @param bool $dark   White lockup for dark backgrounds.
 * @param bool $slogan Lockup with the slogan line.
 */
function reklamo_logo( bool $dark = false, bool $slogan = false ): string {
	list( $file, $w, $h ) = $slogan
		? array( 'logo-slogan.png', 852, 162 )
		: array( $dark ? 'logo-light.png' : 'logo.png', 1200, 168 );
	return sprintf(
		'<a class="brand%5$s" href="%1$s" rel="home"><img class="brand__logo" src="%2$s" width="%3$d" height="%4$d" alt="%6$s" decoding="async"></a>',
		esc_url( home_url( '/' ) ),
		esc_url( get_theme_file_uri( 'assets/img/' . $file ) ),
		$w,
		$h,
		$slogan ? ' brand--slogan' : '',
		esc_attr( get_bloginfo( 'name' ) )
	);
}

// Page content is stored in the database, so a block that hard-codes the mark would keep an old
// logo forever. The shortcode renders whatever the theme ships today.
add_shortcode(
	'reklamo_mark',
	function ( $atts ) {
		$a = shortcode_atts( array( 'size' => 76 ), $atts, 'reklamo_mark' );
		return reklamo_mark( (int) $a['size'] );
	}
);

/** The mark on its own, for spots too small or too square for the full lockup. */
function reklamo_mark( int $size = 64 ): string {
	return sprintf(
		'<img class="brand__mark" src="%1$s" width="%2$d" height="%3$d" alt="" decoding="async">',
		esc_url( get_theme_file_uri( 'assets/img/mark.png' ) ),
		$size,
		(int) round( $size * 526 / 512 )
	);
}

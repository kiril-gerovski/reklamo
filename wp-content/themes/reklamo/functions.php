<?php
/**
 * Reklamo theme bootstrap.
 *
 * Presentation only. Anything that touches orders, uploads, statuses or
 * emails belongs in the reklamo-core plugin so it survives a theme change.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

define( 'REKLAMO_THEME_VERSION', '0.1.0' );

/**
 * Theme supports and menu locations.
 */
function reklamo_setup(): void {
	load_theme_textdomain( 'reklamo', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );

	// WooCommerce: classic templates + product gallery features.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus(
		array(
			'primary'     => __( 'Primary Menu', 'reklamo' ),
			'footer-nav'  => __( 'Footer — Navigation', 'reklamo' ),
			'footer-info' => __( 'Footer — Information', 'reklamo' ),
		)
	);
}
add_action( 'after_setup_theme', 'reklamo_setup' );

/**
 * Front-end assets. Version by file mtime so the browser cache busts on every edit.
 */
function reklamo_enqueue_assets(): void {
	$style = get_stylesheet_directory() . '/style.css';
	wp_enqueue_style(
		'reklamo',
		get_stylesheet_uri(),
		array(),
		file_exists( $style ) ? (string) filemtime( $style ) : REKLAMO_THEME_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'reklamo_enqueue_assets' );

/**
 * The design has no sidebar. WooCommerce's default hook calls get_sidebar(),
 * which logs a deprecation when the theme ships no sidebar.php.
 */
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

<?php
/**
 * Reklamo theme bootstrap.
 *
 * Presentation only. Anything that touches orders, uploads, statuses or emails lives
 * in the reklamo-core plugin so it survives a theme change.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

define( 'REKLAMO_THEME_VERSION', '0.2.0' );

require get_template_directory() . '/inc/icons.php';

/**
 * Theme supports, menus, pattern category.
 */
function reklamo_setup(): void {
	load_theme_textdomain( 'reklamo', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'assets/css/fonts.css', 'assets/css/theme.css' ) );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-lightbox' );

	register_nav_menus(
		array(
			'primary'     => __( 'Primary Menu', 'reklamo' ),
			'footer-nav'  => __( 'Footer — Navigation', 'reklamo' ),
			'footer-info' => __( 'Footer — Information', 'reklamo' ),
		)
	);

	register_block_pattern_category( 'reklamo', array( 'label' => __( 'Reklamo', 'reklamo' ) ) );
}
add_action( 'after_setup_theme', 'reklamo_setup' );

/**
 * Front-end assets, versioned by file mtime so edits bust the cache.
 */
function reklamo_enqueue_assets(): void {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	$v   = static fn( string $rel ): string => file_exists( $dir . $rel ) ? (string) filemtime( $dir . $rel ) : REKLAMO_THEME_VERSION;

	wp_enqueue_style( 'reklamo-fonts', $uri . '/assets/css/fonts.css', array(), $v( '/assets/css/fonts.css' ) );
	wp_enqueue_style( 'reklamo', $uri . '/assets/css/theme.css', array( 'reklamo-fonts' ), $v( '/assets/css/theme.css' ) );
	wp_enqueue_script( 'reklamo', $uri . '/assets/js/theme.js', array(), $v( '/assets/js/theme.js' ), array( 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'reklamo_enqueue_assets' );

/** The request page URL, with the plugin's helper when available. */
function reklamo_request_url( $product = null ): string {
	if ( class_exists( 'Reklamo_Request' ) ) {
		return Reklamo_Request::url( $product );
	}
	return home_url( '/kachi-logo/' );
}

/** Company setting with fallback (plugin-provided). */
function reklamo_setting( string $key, string $fallback = '' ): string {
	return class_exists( 'Reklamo_Settings' ) ? Reklamo_Settings::get( $key, $fallback ) : $fallback;
}

/* ---------------------------------------------------------------------------
 * WooCommerce: the design has no sidebar, no cart, no "add to cart". Every package
 * card and the product page lead to the request page.
 * ------------------------------------------------------------------------- */

// Wrappers and sidebar.
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

// Shop header noise.
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
add_filter( 'woocommerce_show_page_title', '__return_false' );
add_filter( 'loop_shop_columns', static fn() => 4 );
add_filter( 'loop_shop_per_page', static fn() => 12 );

// Single product: no add-to-cart form, no rating/meta/sharing/related; one CTA instead.
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );

/**
 * "Choose this package" call to action on the product page.
 */
function reklamo_single_cta(): void {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	printf(
		'<div class="product-cta"><a class="btn btn--primary" href="%s">%s %s</a><p class="nopay-hint">%s</p></div>',
		esc_url( reklamo_request_url( $product ) ),
		esc_html__( 'Choose this package', 'reklamo' ),
		reklamo_icon( 'arrow', 18 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		esc_html__( 'No payment is taken at this stage.', 'reklamo' )
	);
}
add_action( 'woocommerce_single_product_summary', 'reklamo_single_cta', 30 );

/** Placeholder product image from the theme (the owner replaces photos in Products). */
add_filter( 'woocommerce_placeholder_img_src', static fn() => get_template_directory_uri() . '/assets/img/placeholder-product.svg' );

/** Stray shop-loop add-to-cart buttons (shortcodes etc.) become request links too. */
add_filter(
	'woocommerce_loop_add_to_cart_link',
	static function ( string $html, WC_Product $product ): string {
		return sprintf( '<a class="btn btn--primary btn--card" href="%s">%s</a>', esc_url( reklamo_request_url( $product ) ), esc_html__( 'Choose package', 'reklamo' ) );
	},
	10,
	2
);

/** Trust strip as a shortcode so patterns render it live (translated), not as baked HTML. */
add_shortcode(
	'reklamo_trust_strip',
	static function (): string {
		ob_start();
		get_template_part( 'template-parts/trust-strip' );
		return (string) ob_get_clean();
	}
);

/** Body classes for page-specific layout. */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		if ( is_page_template( 'templates/page-request.php' ) ) {
			$classes[] = 'is-request-page';
		}
		return $classes;
	}
);

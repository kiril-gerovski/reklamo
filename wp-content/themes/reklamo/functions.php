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
require get_template_directory() . '/inc/class-reklamo-nav-walker.php';
require get_template_directory() . '/inc/seo.php';

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

/** The contacts page, falling back to a mailto: so the button is never dead. */
function reklamo_contact_url(): string {
	$page = get_page_by_path( 'kontakti' );
	if ( $page instanceof WP_Post ) {
		return (string) get_permalink( $page );
	}
	$mail = reklamo_setting( 'email' );
	return $mail ? 'mailto:' . $mail : home_url( '/' );
}

/* ---------------------------------------------------------------------------
 * WooCommerce: the design has no sidebar, no cart, no "add to cart". A card opens the
 * product page, and the product page's one call to action opens the request form.
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

// Single product: the template prints title, price, excerpt and the CTA itself, in the design's
// order, so the summary hooks are cleared rather than reshuffled.
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );

/**
 * Product tabs, as the design lists them. Description comes from the product; branding, delivery
 * and the FAQ are the same for every product, so they are pulled from the pages that already say
 * it, and a tab is dropped when its page is still empty.
 *
 * @param array $tabs Tabs registered by WooCommerce.
 * @return array
 */
function reklamo_product_tabs( array $tabs ): array {
	unset( $tabs['reviews'], $tabs['additional_information'] );

	global $product;
	$specs = class_exists( 'Reklamo_Product' ) ? Reklamo_Product::specs( $product ) : '';
	if ( $specs ) {
		$tabs['specifications'] = array(
			'title'    => __( 'Specifications', 'reklamo' ),
			'priority' => 15,
			'callback' => static function () use ( $specs ) {
				echo '<div class="product-tab-specs">' . wp_kses_post( wpautop( $specs ) ) . '</div>';
			},
		);
	}

	$pages = array(
		'branding' => array( 'kak-raboti', __( 'Branding', 'reklamo' ), 20 ),
		'delivery' => array( 'dostavka-i-srokove', __( 'Delivery and deadlines', 'reklamo' ), 30 ),
		'faq'      => array( 'chesto-zadavani-vaprosi', __( 'Frequently asked questions', 'reklamo' ), 40 ),
	);

	foreach ( $pages as $key => list( $slug, $title, $priority ) ) {
		$page = get_page_by_path( $slug );
		if ( ! $page instanceof WP_Post ) {
			continue;
		}
		// A page the seed created but nobody has written yet holds just its own title as one
		// sentence; a tab showing that is worse than no tab.
		$text = trim( wp_strip_all_tags( $page->post_content ) );
		if ( '' === $text || rtrim( $text, '.' ) === rtrim( $page->post_title, '.' ) ) {
			continue;
		}
		$tabs[ $key ] = array(
			'title'    => $title,
			'priority' => $priority,
			'callback' => static function () use ( $page ) {
				echo '<div class="product-tab-page">' . wp_kses_post( apply_filters( 'the_content', $page->post_content ) ) . '</div>';
				printf(
					'<p><a class="product-tab-page__more" href="%s">%s</a></p>',
					esc_url( (string) get_permalink( $page ) ),
					esc_html( $page->post_title )
				);
			},
		);
	}

	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'reklamo_product_tabs' );

/** The design gives each tab panel its own heading only in the tab strip. */
add_filter( 'woocommerce_product_description_heading', '__return_empty_string' );

/** Placeholder product image from the theme (the owner replaces photos in Products). */
add_filter( 'woocommerce_placeholder_img_src', static fn() => get_template_directory_uri() . '/assets/img/placeholder-product.svg' );

/** Stray shop-loop add-to-cart buttons (shortcodes etc.) open the product page instead. */
add_filter(
	'woocommerce_loop_add_to_cart_link',
	static function ( string $html, WC_Product $product ): string {
		return sprintf( '<a class="btn btn--primary btn--card" href="%s">%s</a>', esc_url( $product->get_permalink() ), esc_html__( 'View the package', 'reklamo' ) );
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

/**
 * The six-step graphic, for the homepage pattern and the "How it works" page alike. Each
 * step links to its section on the "How it works" page (seeded slug below); on that page
 * itself the links are in-page anchors, elsewhere they carry the page URL. With
 * section="1" the shortcode also wraps the steps in a full-width section with the heading.
 */
const REKLAMO_HOW_IT_WORKS_SLUG = 'kak-raboti';
add_shortcode(
	'reklamo_steps',
	static function ( $atts ): string {
		$atts   = shortcode_atts( array( 'section' => '' ), $atts, 'reklamo_steps' );
		$target = get_page_by_path( REKLAMO_HOW_IT_WORKS_SLUG );
		$here   = $target && get_the_ID() === $target->ID;
		ob_start();
		get_template_part(
			'template-parts/steps',
			null,
			array(
				'base'    => $here || ! $target ? '' : get_permalink( $target ),
				'link'    => (bool) $target,
				'section' => '' !== $atts['section'],
				'heading' => $here ? 'h1' : 'h2', // on its own page the section is the page heading
			)
		);
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

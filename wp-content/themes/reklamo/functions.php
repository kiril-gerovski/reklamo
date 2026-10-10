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
require get_template_directory() . '/inc/page-sections.php';
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
	// Twice the displayed width, so photos stay sharp on high-density screens.
	add_theme_support(
		'woocommerce',
		array(
			'single_image_width'    => 1200,
			'thumbnail_image_width' => 600,
		)
	);
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

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
add_filter( 'loop_shop_per_page', static fn() => 24 );

const REKLAMO_PACKAGES_CAT   = 'paketi';
const REKLAMO_PACKAGE_FILTER = 'kategoria';

/** The category Промо пакети is narrowed to through its chips (?kategoria=<slug>), or null. */
function reklamo_package_filter(): ?WP_Term {
	$slug = isset( $_GET[ REKLAMO_PACKAGE_FILTER ] ) ? sanitize_title( wp_unslash( $_GET[ REKLAMO_PACKAGE_FILTER ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public filter.
	$term = '' !== $slug ? get_term_by( 'slug', $slug, 'product_cat' ) : false;
	return $term instanceof WP_Term ? $term : null;
}

/** Промо пакети lists the packages only, narrowed by a chip when one is chosen. */
add_action(
	'pre_get_posts',
	static function ( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! function_exists( 'is_shop' ) || ! is_shop() ) {
			return;
		}
		$tax  = array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => REKLAMO_PACKAGES_CAT,
			),
		);
		$term = reklamo_package_filter();
		if ( $term ) {
			$tax[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			);
		}
		$query->set( 'tax_query', array_merge( array( 'relation' => 'AND' ), $tax ) );
	}
);

/**
 * The chips: product categories in the order of the ПРОДУКТИ menu, never Пакети itself. For
 * 'packages' only the categories some package is in; for 'products' every category with products.
 *
 * @param string $context 'packages' or 'products'.
 * @return WP_Term[]
 */
function reklamo_shop_chips( string $context ): array {
	if ( 'packages' === $context ) {
		$ids   = wc_get_products(
			array(
				'category' => array( REKLAMO_PACKAGES_CAT ),
				'status'   => 'publish',
				'limit'    => -1,
				'return'   => 'ids',
			)
		);
		$terms = $ids ? wp_get_object_terms( $ids, 'product_cat' ) : array();
	} else {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);
	}
	if ( ! is_array( $terms ) ) {
		return array();
	}
	$terms = array_filter( $terms, static fn( $t ) => REKLAMO_PACKAGES_CAT !== $t->slug );
	$order = array();
	foreach ( (array) wp_get_nav_menu_items( 'Главно меню' ) as $item ) {
		if ( 'product_cat' === ( $item->object ?? '' ) ) {
			$order[ (int) $item->object_id ] = count( $order );
		}
	}
	usort( $terms, static fn( $a, $b ) => ( $order[ $a->term_id ] ?? 99 ) <=> ( $order[ $b->term_id ] ?? 99 ) );
	return $terms;
}

/** Pieces in a package: the number its first contents item starts with, e.g. "50 бр. …" → 50. */
function reklamo_package_quantity( WC_Product $product ): int {
	$short = reklamo_split_short_description( $product->get_short_description() );
	$items = reklamo_package_items( $short['list'] );
	return ( $items && preg_match( '~^(\d+)~', $items[0]['title'], $m ) ) ? (int) $m[1] : 0;
}

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
 * Product tabs: the description and, when the product has them, its specifications. What is the
 * same for every product (how ordering works, delivery, the FAQ) lives on the homepage and its
 * own pages, linked from the help card.
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

	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'reklamo_product_tabs' );

/**
 * A short description is a lead paragraph plus the list of what is in the package; the design
 * shows them in two different places.
 *
 * @param string $short The product's short description.
 * @return array{lead:string,list:string}
 */
function reklamo_split_short_description( string $short ): array {
	if ( preg_match( '~(<ul\b.*</ul>)~is', $short, $m ) ) {
		return array(
			'lead' => trim( str_replace( $m[1], '', $short ) ),
			'list' => $m[1],
		);
	}
	return array(
		'lead' => $short,
		'list' => '',
	);
}

/**
 * The icon for a product or category name, picked from its wording.
 *
 * @param string $text     Name to match.
 * @param string $fallback Icon when nothing matches.
 */
function reklamo_item_icon( string $text, string $fallback = 'cube' ): string {
	$icons = apply_filters(
		'reklamo_package_item_icons',
		array(
			'notebook' => '~тефтер|бележник~iu',
			'ballpen'  => '~химикал~iu',
			'mug'      => '~чаш~iu',
			'bottle'   => '~бутилк~iu',
			'bag'      => '~торб|чант|текстил~iu',
			'calendar' => '~календар~iu',
			'gift'     => '~опаковк|кутия|кутии|пакет~iu',
			'monitor'  => '~технолог~iu',
			'pen'      => '~офис~iu',
		)
	);
	foreach ( $icons as $name => $pattern ) {
		if ( preg_match( $pattern, $text ) ) {
			return $name;
		}
	}
	return $fallback;
}

/**
 * The package contents from the short description's list. Each item is a title, optionally
 * followed by a line break and a detail line; the icon is picked from the title's wording.
 *
 * @param string $html The list markup.
 * @return array<int,array{icon:string,title:string,detail:string,counted:bool}>
 */
function reklamo_package_items( string $html ): array {
	$items = array();
	preg_match_all( '~<li\b[^>]*>(.*?)</li>~is', $html, $m );
	foreach ( $m[1] as $inner ) {
		// The classic editor stores the line break as a plain newline once the product is saved.
		$parts = preg_split( '~<br\s*/?>|\R~iu', trim( $inner ), 2 );
		$title = trim( wp_strip_all_tags( $parts[0] ) );
		if ( '' === $title ) {
			continue;
		}
		$items[] = array(
			'icon'    => reklamo_item_icon( $title ),
			'title'   => $title,
			'detail'  => trim( wp_strip_all_tags( $parts[1] ?? '' ) ),
			'counted' => (bool) preg_match( '~^\d~', $title ),
		);
	}
	return $items;
}

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
				'texts'   => is_front_page(),
			)
		);
		return (string) ob_get_clean();
	}
);

/** The homepage's packages section ends with the large-quantity band, as the catalogue pages do. */
add_filter(
	'render_block_core/group',
	static function ( string $html, array $block ): string {
		if ( ! is_front_page() || ! preg_match( '~(^|\s)packages(\s|$)~', $block['attrs']['className'] ?? '' ) ) {
			return $html;
		}
		$end = strrpos( $html, '</div>' );
		if ( false === $end ) {
			return $html;
		}
		ob_start();
		get_template_part( 'template-parts/bulk-offer' );
		return substr_replace( $html, (string) ob_get_clean(), $end, 0 );
	},
	10,
	2
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

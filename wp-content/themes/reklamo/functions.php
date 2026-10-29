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

// The owner wants plain hyphens; wptexturize would turn " - " back into a dash and quotes into English ones.
add_filter( 'run_wptexturize', '__return_false' );
add_filter(
	'woocommerce_format_price_range',
	static fn( $price, $from, $to ): string => ( is_numeric( $from ) ? wc_price( $from ) : $from ) . ' - ' . ( is_numeric( $to ) ? wc_price( $to ) : $to ),
	10,
	3
);

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
/** The phone's bottom bar to the request page: everywhere but that page and the cart. */
function reklamo_show_mobile_cta(): bool {
	$request = (int) get_option( 'reklamo_request_page_id' );
	if ( ( $request && is_page( $request ) ) || ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
		return false;
	}
	return true;
}

/** The inquiry dialog, once a page, wherever something can open it. */
function reklamo_inquiry_dialog(): void {
	static $done = false;
	if ( ! $done ) {
		$done = true;
		get_template_part( 'template-parts/inquiry-dialog' );
	}
}

/** A new email to the shop for a tailored offer, subject and outline filled in; the contacts page when no email is set. */
function reklamo_offer_mailto(): string {
	$mail = reklamo_setting( 'email' );
	if ( ! $mail ) {
		return reklamo_contact_url();
	}
	$body = implode(
		"\n",
		array(
			__( 'Hello,', 'reklamo' ),
			'',
			__( 'I would like an offer for:', 'reklamo' ),
			__( 'Product or package:', 'reklamo' ) . ' ',
			__( 'Quantity:', 'reklamo' ) . ' ',
			__( 'Wanted by:', 'reklamo' ) . ' ',
			'',
		)
	);
	return 'mailto:' . $mail . '?subject=' . rawurlencode( __( 'Inquiry for a tailored offer', 'reklamo' ) ) . '&body=' . rawurlencode( $body );
}

function reklamo_contact_url(): string {
	$page = get_page_by_path( 'kontakti' );
	if ( $page instanceof WP_Post ) {
		return (string) get_permalink( $page );
	}
	$mail = reklamo_setting( 'email' );
	return $mail ? 'mailto:' . $mail : home_url( '/' );
}

/* ---------------------------------------------------------------------------
 * WooCommerce: the design has no sidebar and no shop-loop "add to cart". A card opens the
 * product page, whose order card adds the product with its logo to the cart (Reklamo_Cart).
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
const REKLAMO_GIFTS_CAT      = 'korporativni-podaratsi'; // also the slug of its page
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
 * A product category page (Тефтери, Химикалки…) lists single products: the packages filed there
 * too, for the chips on Промо пакети, stay on Промо пакети.
 */
add_action(
	'pre_get_posts',
	static function ( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! function_exists( 'is_product_category' ) || ! is_product_category() || is_product_category( array( REKLAMO_PACKAGES_CAT, REKLAMO_GIFTS_CAT ) ) ) {
			return;
		}
		$tax   = (array) $query->get( 'tax_query' );
		$tax[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => REKLAMO_PACKAGES_CAT,
			'operator' => 'NOT IN',
		);
		$query->set( 'tax_query', $tax );
	}
);

/**
 * The categories that hold at least one published single product (not a package), with their parents.
 *
 * @return int[] term IDs
 */
function reklamo_categories_with_products(): array {
	static $ids = null;
	if ( null === $ids ) {
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- a few dozen products.
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => REKLAMO_PACKAGES_CAT,
						'operator' => 'NOT IN',
					),
				),
			)
		);
		$ids      = array();
		foreach ( $products ? wp_get_object_terms( $products, 'product_cat' ) : array() as $term ) {
			$ids = array_merge( $ids, array( (int) $term->term_id ), array_map( 'intval', get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) );
		}
		$ids = array_values( array_unique( $ids ) );
	}
	return $ids;
}

/**
 * The menu leaves out a product category with no single product in it yet (packages stay on Промо
 * пакети); it comes back by itself once one is added. The category and its menu item stay in the dashboard.
 */
add_filter(
	'wp_nav_menu_objects',
	static function ( array $items ): array {
		if ( is_admin() ) {
			return $items;
		}
		return array_filter( $items, static fn( $item ) => 'product_cat' !== ( $item->object ?? '' ) || in_array( (int) $item->object_id, reklamo_categories_with_products(), true ) );
	}
);

/**
 * The chips: product categories in the order of the ПРОДУКТИ menu, never a collection (Пакети,
 * Корпоративни подаръци) itself. For 'packages' and 'gifts' only the categories something in that
 * collection is in; for 'products' every category with single products in it.
 *
 * @param string $context 'packages', 'gifts' or 'products'.
 * @return WP_Term[]
 */
function reklamo_shop_chips( string $context ): array {
	$collection = array(
		'packages' => REKLAMO_PACKAGES_CAT,
		'gifts'    => REKLAMO_GIFTS_CAT,
	)[ $context ] ?? '';
	if ( '' !== $collection ) {
		$ids   = wc_get_products(
			array(
				'category' => array( $collection ),
				'status'   => 'publish',
				'limit'    => -1,
				'return'   => 'ids',
			)
		);
		$terms = $ids ? wp_get_object_terms( $ids, 'product_cat' ) : array();
	} else {
		$ids   = reklamo_categories_with_products();
		$terms = $ids ? get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $ids,
				'hide_empty' => false,
			)
		) : array();
	}
	if ( ! is_array( $terms ) ) {
		return array();
	}
	$terms = array_filter( $terms, static fn( $t ) => ! in_array( $t->slug, array( REKLAMO_PACKAGES_CAT, REKLAMO_GIFTS_CAT ), true ) );
	$order = reklamo_menu_category_order();
	usort( $terms, static fn( $a, $b ) => ( $order[ $a->term_id ] ?? 99 ) <=> ( $order[ $b->term_id ] ?? 99 ) );
	return $terms;
}

/** @return array<int,int> category term ID => its place in the ПРОДУКТИ menu */
function reklamo_menu_category_order(): array {
	$order = array();
	foreach ( (array) wp_get_nav_menu_items( 'Главно меню' ) as $item ) {
		if ( 'product_cat' === ( $item->object ?? '' ) ) {
			$order[ (int) $item->object_id ] = count( $order );
		}
	}
	return $order;
}

/**
 * Продукти's default order: the single products by category, in the order of the ПРОДУКТИ menu;
 * within a category, the order set in the dashboard (Products → Sorting), then by name.
 *
 * @return int[] product IDs
 */
function reklamo_products_in_menu_order(): array {
	$order = reklamo_menu_category_order();
	$ids   = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- a few dozen products.
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => REKLAMO_PACKAGES_CAT,
					'operator' => 'NOT IN',
				),
			),
		)
	);
	$place = array();
	foreach ( $ids as $n => $id ) {
		$cats         = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
		$at           = array_map( static fn( $c ) => $order[ (int) $c ] ?? 99, is_array( $cats ) ? $cats : array() );
		$place[ $id ] = array( $at ? min( $at ) : 99, $n );
	}
	usort( $ids, static fn( $a, $b ) => $place[ $a ] <=> $place[ $b ] );
	return $ids;
}

/**
 * Sorting for a listing built on the [products] shortcode (Продукти, Корпоративни подаръци), which
 * does not read ?orderby itself: WooCommerce's dropdown, and the order the choice stands for.
 *
 * @return array{html:string,orderby:string,order:string}
 */
function reklamo_catalogue_sort(): array {
	// WooCommerce's own labels, so the dropdown reads exactly as on Промо пакети.
	// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
	$options = apply_filters(
		'woocommerce_catalog_orderby',
		array(
			'menu_order' => __( 'Default sorting', 'woocommerce' ),
			'popularity' => __( 'Sort by popularity', 'woocommerce' ),
			'rating'     => __( 'Sort by average rating', 'woocommerce' ),
			'date'       => __( 'Sort by latest', 'woocommerce' ),
			'price'      => __( 'Sort by price: low to high', 'woocommerce' ),
			'price-desc' => __( 'Sort by price: high to low', 'woocommerce' ),
		)
	);
	// phpcs:enable WordPress.WP.I18n.TextDomainMismatch
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'menu_order'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public sort.
	$orderby = isset( $options[ $orderby ] ) ? $orderby : 'menu_order';
	$map     = array(
		'menu_order' => array( 'menu_order', 'ASC' ),
		'popularity' => array( 'popularity', 'DESC' ),
		'rating'     => array( 'rating', 'DESC' ),
		'date'       => array( 'date', 'DESC' ),
		'price'      => array( 'price', 'ASC' ),
		'price-desc' => array( 'price', 'DESC' ),
	);
	ob_start();
	wc_get_template(
		'loop/orderby.php',
		array(
			'catalog_orderby_options' => $options,
			'orderby'                 => $orderby,
			'use_label'               => false,
		)
	);
	return array(
		'html'    => (string) ob_get_clean(),
		'orderby' => $map[ $orderby ][0],
		'order'   => $map[ $orderby ][1],
	);
}

/**
 * Pieces the card's price is for: the Количество of the cheapest variation, whose price the seal
 * shows, or else the number the first contents item starts with, e.g. "50 бр. …" → 50.
 *
 * @return array{qty:int,from:bool,max:int} from: the product comes in more than one quantity, up to max.
 */
function reklamo_package_quantity( WC_Product $product ): array {
	if ( $product instanceof WC_Product_Variable && class_exists( 'Reklamo_Product' ) ) {
		$prices = $product->get_variation_prices( true )['price'];
		asort( $prices );
		$sizes = array();
		foreach ( array_keys( $prices ) as $id ) {
			$variation = wc_get_product( $id );
			$size      = $variation ? (int) $variation->get_attribute( Reklamo_Product::QTY_TAX ) : 0;
			if ( $size ) {
				$sizes[] = $size;
			}
		}
		if ( $sizes ) {
			return array(
				'qty'  => $sizes[0],
				'from' => count( array_unique( $sizes ) ) > 1,
				'max'  => max( $sizes ),
			);
		}
	}
	$short = reklamo_split_short_description( $product->get_short_description() );
	$items = reklamo_package_items( $short['list'] );
	return array(
		'qty'  => ( $items && preg_match( '~^(\d+)~', $items[0]['title'], $m ) ) ? (int) $m[1] : 0,
		'from' => false,
		'max'  => 0,
	);
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
 * Pieces for a card's seal: the minimum for a product sold by the piece, else the cheapest variation's.
 *
 * @return array{qty:int,from:bool,max:int}
 */
function reklamo_card_size( WC_Product $product ): array {
	if ( class_exists( 'Reklamo_Product' ) && Reklamo_Product::by_the_piece( $product ) ) {
		return array(
			'qty'  => Reklamo_Product::min_qty( $product ),
			'from' => true,
			'max'  => 0,
		);
	}
	return reklamo_package_quantity( $product );
}

/** What a product card says under the name: its contents with the range of sizes on offer. */
function reklamo_card_line( WC_Product $product ): string {
	$short = reklamo_split_short_description( $product->get_short_description() );
	$items = reklamo_package_items( $short['list'] );
	$size  = reklamo_card_size( $product );
	$qty   = $size['qty'];
	$range = $size['max'] > $qty ? $qty . '-' . $size['max'] : '';
	$count = '' !== $range ? $range : (string) $qty;
	if ( $items && class_exists( 'Reklamo_Product' ) && Reklamo_Product::is_package( $product ) ) {
		$names = array(
			'in'  => array(),
			'box' => array(),
		);
		foreach ( $items as $item ) {
			$name = (string) preg_replace( '~^\d+\s*(бр\.|броя)?\s*~u', '', $item['title'] );
			$name = mb_strtolower( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );
			$names[ 'gift' === $item['icon'] ? 'box' : 'in' ][] = $name;
		}
		$line = $names['in'] && $names['box']
			/* translators: 1: what is in the package, e.g. "pens, cables"; 2: its box, e.g. "gift boxes" */
			? sprintf( __( '%1$s in %2$s', 'reklamo' ), implode( ', ', $names['in'] ), wp_sprintf( '%l', $names['box'] ) )
			: wp_sprintf( '%l', array_merge( $names['in'], $names['box'] ) );
		/* translators: 1: number or range of packages, e.g. 50–200; 2: what is in each, e.g. "notebooks and pens" */
		$line = $qty ? sprintf( __( '%1$s packs of %2$s', 'reklamo' ), $count, $line ) : $line;
	} elseif ( $items ) {
		$line = implode(
			' · ',
			array_map(
				// Like the product page, which sets every count to the chosen size: here the sizes on offer.
				static fn( array $item ): string => $item['counted'] && '' !== $range ? preg_replace( '~^\d+~', $range, $item['title'] ) : $item['title'],
				$items
			)
		);
	} else {
		$line = wp_strip_all_tags( $short['lead'] );
	}
	return $line;
}

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

/**
 * The labels on a product's photo: "Promotion" while it is on sale, "+ Gift" when something comes
 * with it; "Most popular" only when neither applies.
 *
 * @return array<int,array{class:string,label:string,icon:string}>
 */
function reklamo_product_ribbons( WC_Product $product ): array {
	$out = array();
	if ( $product->is_on_sale() ) {
		$out[] = array(
			'class' => 'badge--promo',
			'label' => __( 'Promotion', 'reklamo' ),
			'icon'  => '',
		);
	}
	if ( class_exists( 'Reklamo_Product' ) && '' !== Reklamo_Product::gift( $product ) ) {
		$out[] = array(
			'class' => 'badge--gift',
			'label' => __( '+ Gift', 'reklamo' ),
			'icon'  => 'gift',
		);
	}
	if ( ! $out && $product->is_featured() ) {
		$out[] = array(
			'class' => '',
			'label' => __( 'Most popular', 'reklamo' ),
			'icon'  => '',
		);
	}
	return $out;
}

/** The ribbons as markup, for the card and the product page. */
function reklamo_ribbons_html( WC_Product $product, string $extra_class = '' ): string {
	$html = '';
	foreach ( reklamo_product_ribbons( $product ) as $r ) {
		$html .= sprintf(
			'<span class="badge %1$s">%2$s%3$s</span>',
			esc_attr( trim( $r['class'] . ' ' . $extra_class ) ),
			$r['icon'] ? reklamo_icon( $r['icon'], 14 ) : '',
			esc_html( $r['label'] )
		);
	}
	return $html ? '<span class="badges">' . $html . '</span>' : '';
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
 * The homepage hero's product: its photo and a badge with a short name, the contents and the
 * lowest price. The product is the one ticked "On the homepage" in Products; without one, the
 * sku="…" given here, then the first "Most popular" product. Name and lines may be given for
 * the sku's product (title="…" items="…|…"); otherwise they come from the product, as its card
 * shows them. The price always does, so it follows the owner's edits in Products.
 */
add_shortcode(
	'reklamo_hero_product',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array(
				'sku'   => '',
				'title' => '',
				'items' => '',
			),
			$atts,
			'reklamo_hero_product'
		);
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$product = class_exists( 'Reklamo_Product' ) ? Reklamo_Product::hero() : null;
		if ( $product ) {
			$atts['title'] = '';
			$atts['items'] = '';
		} else {
			$product = wc_get_product( wc_get_product_id_by_sku( $atts['sku'] ) );
		}
		if ( ! $product || 'publish' !== $product->get_status() ) {
			$atts['title'] = '';
			$atts['items'] = '';

			$featured = wc_get_products(
				array(
					'status'   => 'publish',
					'featured' => true,
					'limit'    => 1,
				)
			);
			$product  = $featured ? $featured[0] : null;
		}
		if ( ! $product ) {
			return '';
		}
		add_filter( 'woocommerce_price_trim_zeros', '__return_true' );
		$price = wc_price( $product->is_type( 'variable' ) ? (float) $product->get_variation_price( 'min', true ) : (float) $product->get_price() );
		remove_filter( 'woocommerce_price_trim_zeros', '__return_true' );
		$title = '' !== $atts['title'] ? $atts['title'] : $product->get_name();
		$items = '' !== $atts['items'] ? array_map( 'trim', explode( '|', $atts['items'] ) ) : array_filter( array( reklamo_card_line( $product ) ) );
		$image = $product->get_image(
			'large',
			array(
				'loading'       => 'eager',
				'fetchpriority' => 'high',
			)
		);
		ob_start();
		?>
		<a class="hero__photo" href="<?php echo esc_url( $product->get_permalink() ); ?>">
			<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes it. ?>
		</a>
		<div class="hero__badge">
			<p class="eyebrow"><?php echo esc_html( $title ); ?></p>
			<?php if ( $items ) : ?>
				<p><small><?php echo implode( '<br>', array_map( 'esc_html', $items ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per item. ?></small></p>
			<?php endif; ?>
			<p><strong><?php echo wp_kses_post( $price ); ?></strong></p>
		</div>
		<?php
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

<?php
/**
 * Продукти: every product, packages and single products alike, under the catalogue band. Its
 * chips open the category pages; sorting works as on Промо пакети.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header();

// The [products] shortcode does not read ?orderby, so the choice made in the dropdown is mapped here.
// WooCommerce's own labels, so the dropdown reads exactly as on Промо пакети.
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
$reklamo_options = apply_filters(
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
$reklamo_orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'menu_order'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public sort.
$reklamo_orderby = isset( $reklamo_options[ $reklamo_orderby ] ) ? $reklamo_orderby : 'menu_order';
$reklamo_sort    = array(
	'menu_order' => array( 'menu_order', 'ASC' ),
	'popularity' => array( 'popularity', 'DESC' ),
	'rating'     => array( 'rating', 'DESC' ),
	'date'       => array( 'date', 'DESC' ),
	'price'      => array( 'price', 'ASC' ),
	'price-desc' => array( 'price', 'DESC' ),
)[ $reklamo_orderby ];
?>
<div class="container shop-page">
	<?php
	ob_start();
	wc_get_template(
		'loop/orderby.php',
		array(
			'catalog_orderby_options' => $reklamo_options,
			'orderby'                 => $reklamo_orderby,
			'use_label'               => false,
		)
	);
	$reklamo_sort_html = (string) ob_get_clean();

	get_template_part(
		'template-parts/shop-hero',
		null,
		array(
			'title'   => get_the_title(),
			'sub'     => __( 'Everything we can brand with your logo', 'reklamo' ),
			'context' => 'products',
			'sort'    => $reklamo_sort_html,
		)
	);

	echo do_shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's own loop markup.
		sprintf(
			'[products limit="48" columns="4" orderby="%s" order="%s"]',
			esc_attr( $reklamo_sort[0] ),
			esc_attr( $reklamo_sort[1] )
		)
	);
	get_template_part( 'template-parts/bulk-offer' );
	?>
</div>
<?php
get_footer();

<?php
/**
 * Промо пакети (the shop page, packages only) and the product category pages: the catalogue
 * band with its chips and sorting, then the product grid.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

$reklamo_term = is_product_category() ? get_queried_object() : null;
$reklamo_desc = $reklamo_term instanceof WP_Term ? trim( wp_strip_all_tags( term_description( $reklamo_term ) ) ) : '';
?>
<div class="container shop-page">
	<?php
	get_template_part(
		'template-parts/shop-hero',
		null,
		array(
			'title'   => $reklamo_term instanceof WP_Term ? $reklamo_term->name : woocommerce_page_title( false ),
			'sub'     => '' !== $reklamo_desc ? $reklamo_desc : __( 'Ready-made solutions for your business', 'reklamo' ),
			'context' => $reklamo_term instanceof WP_Term ? 'products' : 'packages',
			'active'  => $reklamo_term instanceof WP_Term ? $reklamo_term : reklamo_package_filter(),
		)
	);

	if ( woocommerce_product_loop() ) {
		do_action( 'woocommerce_before_shop_loop' );
		woocommerce_product_loop_start();
		while ( have_posts() ) {
			the_post();
			do_action( 'woocommerce_shop_loop' );
			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();
		do_action( 'woocommerce_after_shop_loop' );
	} else {
		do_action( 'woocommerce_no_products_found' );
	}
	?>
</div>
<?php
get_footer( 'shop' );

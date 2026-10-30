<?php
/**
 * Продукти: every single product under the catalogue band; the packages stay on Промо пакети. Its
 * chips open the category pages; sorting works as on Промо пакети.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header();

$reklamo_sort = reklamo_catalogue_sort();
?>
<div class="container shop-page">
	<?php
	get_template_part(
		'template-parts/shop-hero',
		null,
		array(
			'title'   => get_the_title(),
			'sub'     => __( 'Everything we can brand with your logo', 'reklamo' ),
			'context' => 'products',
			'sort'    => $reklamo_sort['html'],
		)
	);

	// Default sorting follows the ПРОДУКТИ menu, category by category; the other sortings are WooCommerce's.
	$reklamo_ids = 'menu_order' === $reklamo_sort['orderby'] ? reklamo_products_in_menu_order() : array();
	echo do_shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's own loop markup.
		$reklamo_ids
			? sprintf( '[products limit="48" columns="4" ids="%s" orderby="post__in"]', esc_attr( implode( ',', $reklamo_ids ) ) )
			: sprintf(
				'[products limit="48" columns="4" category="%s" cat_operator="NOT IN" orderby="%s" order="%s"]',
				esc_attr( REKLAMO_PACKAGES_CAT ),
				esc_attr( $reklamo_sort['orderby'] ),
				esc_attr( $reklamo_sort['order'] )
			)
	);
	reklamo_seo_text_below();
	get_template_part( 'template-parts/bulk-offer' );
	?>
</div>
<?php
get_footer();

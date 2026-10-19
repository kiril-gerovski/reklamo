<?php
/**
 * Продукти: every product, packages and single products alike, under the catalogue band. Its
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

	echo do_shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's own loop markup.
		sprintf(
			'[products limit="48" columns="4" orderby="%s" order="%s"]',
			esc_attr( $reklamo_sort['orderby'] ),
			esc_attr( $reklamo_sort['order'] )
		)
	);
	get_template_part( 'template-parts/bulk-offer' );
	?>
</div>
<?php
get_footer();

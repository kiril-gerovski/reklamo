<?php
/**
 * Корпоративни подаръци: the products in that category, under the catalogue band like Промо
 * пакети. Its chips narrow the gifts in place (?kategoria=<slug>); sorting as on Продукти.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header();

$reklamo_sort   = reklamo_catalogue_sort();
$reklamo_filter = reklamo_package_filter();
$reklamo_cats   = REKLAMO_GIFTS_CAT . ( $reklamo_filter ? ',' . $reklamo_filter->slug : '' );
$reklamo_grid   = do_shortcode(
	sprintf(
		'[products limit="48" columns="4" category="%s" cat_operator="AND" orderby="%s" order="%s"]',
		esc_attr( $reklamo_cats ),
		esc_attr( $reklamo_sort['orderby'] ),
		esc_attr( $reklamo_sort['order'] )
	)
);
?>
<div class="container shop-page">
	<?php
	get_template_part(
		'template-parts/shop-hero',
		null,
		array(
			'title'   => get_the_title(),
			'sub'     => __( 'Gifts for your partners, clients and team', 'reklamo' ),
			'context' => 'gifts',
			'active'  => $reklamo_filter,
			'sort'    => $reklamo_sort['html'],
		)
	);

	if ( false !== strpos( $reklamo_grid, 'class="products' ) ) {
		echo $reklamo_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's own loop markup.
	} else {
		?>
		<div class="shop-empty card">
			<?php echo reklamo_icon( 'briefcase', 34 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<p><strong><?php esc_html_e( 'We are putting the corporate gifts together.', 'reklamo' ); ?></strong><?php esc_html_e( 'Tell us the occasion, the number of people and your budget, and we will suggest gifts with your logo.', 'reklamo' ); ?></p>
			<a class="btn" href="<?php echo esc_url( reklamo_contact_url() ); ?>"><?php esc_html_e( 'Ask for an offer', 'reklamo' ); ?></a>
		</div>
		<?php
	}
	reklamo_seo_text_below();
	get_template_part( 'template-parts/bulk-offer' );
	?>
</div>
<?php
get_footer();

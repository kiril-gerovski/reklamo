<?php
/**
 * The catalogue's title band with its three advantages, and below it the category chips and,
 * where there is a product grid, WooCommerce's sorting. Shared by Промо пакети, the category
 * archives and Продукти.
 *
 * @var array $args {
 *   @type string       $title   Page title.
 *   @type string       $sub     Line under the title.
 *   @type string       $context 'packages' (Промо пакети: chips narrow the packages in place) or
 *                               'products' (Продукти: chips open the category pages).
 *   @type WP_Term|null $active  Category whose chip is lit; null lights the "all" chip.
 *   @type bool         $sorting Print WooCommerce's sorting dropdown.
 *   @type string       $sort    Sorting markup to print instead, for a page outside WooCommerce's loop.
 * }
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

$reklamo_args     = wp_parse_args(
	$args ?? array(),
	array(
		'title'   => '',
		'sub'     => __( 'Ready-made solutions for your business', 'reklamo' ),
		'context' => 'products',
		'active'  => null,
		'sorting' => true,
		'sort'    => '',
	)
);
$reklamo_active   = $reklamo_args['active'];
$reklamo_packages = 'packages' === $reklamo_args['context'];
$reklamo_all_url  = $reklamo_packages ? (string) get_permalink( wc_get_page_id( 'shop' ) ) : (string) get_permalink( get_page_by_path( 'produkti' ) );
?>
<header class="shop-hero">
	<div class="shop-hero__text">
		<?php woocommerce_breadcrumb(); ?>
		<h1 class="shop-hero__title"><?php echo esc_html( $reklamo_args['title'] ); ?></h1>
		<?php if ( '' !== $reklamo_args['sub'] ) : ?>
			<p class="shop-hero__sub"><?php echo esc_html( $reklamo_args['sub'] ); ?></p>
		<?php endif; ?>
	</div>
	<ul class="shop-hero__usps">
		<li><?php echo reklamo_icon( 'diamond', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Stylish and practical combinations', 'reklamo' ); ?></span></li>
		<li><?php echo reklamo_icon( 'gift', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Personalised with your logo', 'reklamo' ); ?></span></li>
		<li><?php echo reklamo_icon( 'truck', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Suitable for any business', 'reklamo' ); ?></span></li>
	</ul>
</header>

<div class="shop-tools">
	<nav class="shop-chips" aria-label="<?php esc_attr_e( 'Filter by category', 'reklamo' ); ?>">
		<a class="shop-chip<?php echo $reklamo_active ? '' : ' is-active'; ?>" href="<?php echo esc_url( $reklamo_all_url ); ?>"<?php echo $reklamo_active ? '' : ' aria-current="page"'; ?>>
			<?php $reklamo_packages ? esc_html_e( 'All packages', 'reklamo' ) : esc_html_e( 'All products', 'reklamo' ); ?>
		</a>
		<?php foreach ( reklamo_shop_chips( $reklamo_args['context'] ) as $reklamo_chip ) : ?>
			<?php
			$reklamo_on  = $reklamo_active && $reklamo_active->term_id === $reklamo_chip->term_id;
			$reklamo_url = $reklamo_packages ? add_query_arg( REKLAMO_PACKAGE_FILTER, $reklamo_chip->slug, $reklamo_all_url ) : (string) get_term_link( $reklamo_chip );
			?>
			<a class="shop-chip<?php echo $reklamo_on ? ' is-active' : ''; ?>" href="<?php echo esc_url( $reklamo_url ); ?>"<?php echo $reklamo_on ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $reklamo_chip->name ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
	if ( '' !== $reklamo_args['sort'] ) {
		echo $reklamo_args['sort']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's own template.
	} elseif ( $reklamo_args['sorting'] ) {
		woocommerce_catalog_ordering();
	}
	?>
</div>

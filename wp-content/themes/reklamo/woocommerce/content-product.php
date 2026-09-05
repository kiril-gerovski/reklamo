<?php
/**
 * Package card (shop grid and the [products] shortcode on the homepage).
 * Image · badge (featured = "Most popular") · name · contents · price · request link.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
	return;
}
?>
<li <?php wc_product_class( 'card package-card', $product ); ?>>
	<a class="package-card__media" href="<?php echo esc_url( reklamo_request_url( $product ) ); ?>" aria-label="<?php echo esc_attr( $product->get_name() ); ?>">
		<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC escapes. ?>
		<?php if ( $product->is_featured() ) : ?>
			<span class="badge"><?php esc_html_e( 'Most popular', 'reklamo' ); ?></span>
		<?php endif; ?>
	</a>
	<div class="package-card__body">
		<h3 class="package-card__name"><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
		<div class="package-card__contents"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div>
		<div class="package-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
		<a class="btn btn--card <?php echo $product->is_featured() ? 'btn--primary' : 'btn--outline'; ?>" href="<?php echo esc_url( reklamo_request_url( $product ) ); ?>"><?php esc_html_e( 'Choose package', 'reklamo' ); ?></a>
	</div>
</li>

<?php
/**
 * Package card (shop grid, category archives and the [products] shortcode on the homepage).
 * Image with the "Most popular" badge and a price seal (pieces + final price) · name · what is in
 * it on one line · link to the product page.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
	return;
}

$reklamo_short = reklamo_split_short_description( $product->get_short_description() );
$reklamo_items = reklamo_package_items( $reklamo_short['list'] );
$reklamo_qty   = reklamo_package_quantity( $product );
$reklamo_line  = $reklamo_items ? implode( ' · ', wp_list_pluck( $reklamo_items, 'title' ) ) : wp_strip_all_tags( $reklamo_short['lead'] );
$reklamo_url   = $product->get_permalink();

add_filter( 'woocommerce_price_trim_zeros', '__return_true' );
$reklamo_price = wc_price( (float) $product->get_price() );
remove_filter( 'woocommerce_price_trim_zeros', '__return_true' );
?>
<li <?php wc_product_class( 'card package-card', $product ); ?>>
	<a class="package-card__media" href="<?php echo esc_url( $reklamo_url ); ?>" tabindex="-1" aria-hidden="true">
		<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC escapes. ?>
		<?php if ( $product->is_featured() ) : ?>
			<span class="badge"><?php esc_html_e( 'Most popular', 'reklamo' ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $product->get_price() ) : ?>
			<span class="package-card__seal">
				<?php if ( $reklamo_qty ) : ?>
					<span class="package-card__qty">
						<?php
						/* translators: %d: number of pieces in the package */
						echo esc_html( sprintf( __( '%d pcs.', 'reklamo' ), $reklamo_qty ) );
						?>
					</span>
				<?php endif; ?>
				<span class="package-card__amount"><?php echo wp_kses_post( $reklamo_price ); ?></span>
				<span class="package-card__vat"><?php esc_html_e( 'incl. VAT', 'reklamo' ); ?></span>
			</span>
		<?php endif; ?>
	</a>
	<div class="package-card__body">
		<h3 class="package-card__name"><a href="<?php echo esc_url( $reklamo_url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
		<?php if ( '' !== $reklamo_line ) : ?>
			<p class="package-card__contents"><?php echo esc_html( $reklamo_line ); ?></p>
		<?php endif; ?>
		<div class="package-card__foot">
			<a class="btn package-card__more" href="<?php echo esc_url( $reklamo_url ); ?>">
				<?php esc_html_e( 'View more', 'reklamo' ); ?>
				<?php echo reklamo_icon( 'chevron-r', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</a>
		</div>
	</div>
</li>

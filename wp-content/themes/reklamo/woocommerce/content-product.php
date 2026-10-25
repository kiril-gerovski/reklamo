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
$reklamo_piece = class_exists( 'Reklamo_Product' ) && Reklamo_Product::by_the_piece( $product );
$reklamo_size  = $reklamo_piece ? array( 'qty' => Reklamo_Product::min_qty( $product ), 'from' => true, 'max' => 0 ) : reklamo_package_quantity( $product );
$reklamo_qty   = $reklamo_size['qty'];
$reklamo_range = $reklamo_size['max'] > $reklamo_qty ? $reklamo_qty . '–' . $reklamo_size['max'] : '';
$reklamo_count = '' !== $reklamo_range ? $reklamo_range : (string) $reklamo_qty;
if ( $reklamo_items && class_exists( 'Reklamo_Product' ) && Reklamo_Product::is_package( $product ) ) {
	$reklamo_names = array(
		'in'  => array(),
		'box' => array(),
	);
	foreach ( $reklamo_items as $reklamo_item ) {
		$reklamo_name = (string) preg_replace( '~^\d+\s*(бр\.|броя)?\s*~u', '', $reklamo_item['title'] );
		$reklamo_name = mb_strtolower( mb_substr( $reklamo_name, 0, 1 ) ) . mb_substr( $reklamo_name, 1 );
		$reklamo_names[ 'gift' === $reklamo_item['icon'] ? 'box' : 'in' ][] = $reklamo_name;
	}
	$reklamo_line = $reklamo_names['in'] && $reklamo_names['box']
		/* translators: 1: what is in the package, e.g. "pens, cables"; 2: its box, e.g. "gift boxes" */
		? sprintf( __( '%1$s in %2$s', 'reklamo' ), implode( ', ', $reklamo_names['in'] ), wp_sprintf( '%l', $reklamo_names['box'] ) )
		: wp_sprintf( '%l', array_merge( $reklamo_names['in'], $reklamo_names['box'] ) );
	/* translators: 1: number or range of packages, e.g. 50–200; 2: what is in each, e.g. "notebooks and pens" */
	$reklamo_line = $reklamo_qty ? sprintf( __( '%1$s packs of %2$s', 'reklamo' ), $reklamo_count, $reklamo_line ) : $reklamo_line;
} elseif ( $reklamo_items ) {
	$reklamo_line = implode(
		' · ',
		array_map(
			// Like the product page, which sets every count to the chosen size: here the sizes on offer.
			static fn( array $item ): string => $item['counted'] && '' !== $reklamo_range ? preg_replace( '~^\d+~', $reklamo_range, $item['title'] ) : $item['title'],
			$reklamo_items
		)
	);
} else {
	$reklamo_line = wp_strip_all_tags( $reklamo_short['lead'] );
}
$reklamo_url   = $product->get_permalink();

add_filter( 'woocommerce_price_trim_zeros', '__return_true' );
$reklamo_price   = wc_price( $product->is_type( 'variable' ) ? (float) $product->get_variation_price( 'min', true ) : (float) $product->get_price() );
$reklamo_sale    = $product->is_on_sale();
$reklamo_regular = $reklamo_sale ? wc_price( $product->is_type( 'variable' ) ? (float) $product->get_variation_regular_price( 'min', true ) : (float) $product->get_regular_price() ) : '';
remove_filter( 'woocommerce_price_trim_zeros', '__return_true' );
$reklamo_gift    = class_exists( 'Reklamo_Product' ) ? Reklamo_Product::gift( $product ) : '';
$reklamo_colours = class_exists( 'Reklamo_Product' ) ? Reklamo_Product::colours( $product ) : array();
?>
<li <?php wc_product_class( 'card package-card', $product ); ?>>
	<a class="package-card__media" href="<?php echo esc_url( $reklamo_url ); ?>" tabindex="-1" aria-hidden="true">
		<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC escapes. ?>
		<?php echo reklamo_ribbons_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
		<?php if ( '' !== $product->get_price() ) : ?>
			<span class="package-card__seal<?php echo $reklamo_sale ? ' is-sale' : ''; ?>">
				<?php if ( $reklamo_qty ) : ?>
					<span class="package-card__qty">
						<?php
						echo esc_html(
							$reklamo_size['from']
								/* translators: %d: smallest quantity */
								? sprintf( __( 'from %d pcs.', 'reklamo' ), $reklamo_qty )
								/* translators: %d: number of pieces in the package */
								: sprintf( __( '%d pcs.', 'reklamo' ), $reklamo_qty )
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( $reklamo_sale ) : ?>
					<del class="package-card__was"><?php echo wp_kses_post( $reklamo_regular ); ?></del>
				<?php endif; ?>
				<span class="package-card__amount"><?php echo wp_kses_post( $reklamo_price ); ?></span>
				<span class="package-card__vat"><?php $reklamo_piece ? esc_html_e( 'per piece', 'reklamo' ) : esc_html_e( 'incl. VAT', 'reklamo' ); ?></span>
			</span>
		<?php endif; ?>
	</a>
	<div class="package-card__body">
		<h3 class="package-card__name"><a href="<?php echo esc_url( $reklamo_url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
		<?php if ( '' !== $reklamo_line ) : ?>
			<p class="package-card__contents"><?php echo esc_html( $reklamo_line ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $reklamo_gift ) : ?>
			<p class="package-card__gift"><?php echo reklamo_icon( 'gift', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( sprintf( /* translators: %s: what comes free with the product */ __( 'Gift: %s', 'reklamo' ), $reklamo_gift ) ); ?></span></p>
		<?php endif; ?>
		<div class="package-card__foot">
			<?php if ( $reklamo_colours ) : ?>
				<ul class="package-card__colours" aria-label="<?php esc_attr_e( 'Colours', 'reklamo' ); ?>">
					<?php foreach ( $reklamo_colours as $reklamo_name => $reklamo_hex ) : ?>
						<li style="--swatch: <?php echo esc_attr( $reklamo_hex ); ?>" title="<?php echo esc_attr( $reklamo_name ); ?>"><span class="screen-reader-text"><?php echo esc_html( $reklamo_name ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<a class="btn package-card__more" href="<?php echo esc_url( $reklamo_url ); ?>">
				<?php esc_html_e( 'View more', 'reklamo' ); ?>
				<?php echo reklamo_icon( 'chevron-r', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</a>
		</div>
	</div>
</li>

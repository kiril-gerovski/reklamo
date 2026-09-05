<?php
/**
 * Shop page — the package grid, nothing else.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>
<div class="container shop-page">
	<header class="section-head">
		<h1 class="section-title"><?php woocommerce_page_title(); ?></h1>
		<?php
		$reklamo_shop_desc = is_shop() ? get_post_field( 'post_content', wc_get_page_id( 'shop' ) ) : term_description();
		if ( $reklamo_shop_desc ) :
			?>
			<div class="section-intro"><?php echo wp_kses_post( wpautop( $reklamo_shop_desc ) ); ?></div>
		<?php endif; ?>
	</header>
	<?php
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

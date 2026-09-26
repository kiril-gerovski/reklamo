<?php
/**
 * Per-product specifications: paper, print, finishing — the "Спецификации" tab in the design.
 *
 * WooCommerce has no prose field beyond the description, and attributes turn a paragraph into a
 * table, so this adds one textarea to the product editor and nothing else. The theme renders it
 * as a tab when it holds something.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Product {

	const META = '_reklamo_specs';

	public static function init(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
	}

	/** The specifications of a product, ready to print. */
	public static function specs( $product ): string {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		return (string) $product->get_meta( self::META );
	}

	public static function field(): void {
		echo '<div class="options_group">';
		woocommerce_wp_textarea_input(
			array(
				'id'          => self::META,
				'label'       => __( 'Specifications', 'reklamo-core' ),
				'description' => __( 'Paper, print and finishing. Shown as its own tab on the product page; leave empty to hide the tab.', 'reklamo-core' ),
				'desc_tip'    => true,
				'rows'        => 6,
				'style'       => 'width:100%',
			)
		);
		echo '</div>';
	}

	public static function save( WC_Product $product ): void {
		// WooCommerce has already verified the product editor's nonce before this hook.
		$raw = isset( $_POST[ self::META ] ) ? wp_unslash( $_POST[ self::META ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( self::META, wp_kses_post( trim( $raw ) ) );
	}
}

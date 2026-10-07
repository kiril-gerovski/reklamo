<?php
/**
 * Per-product specifications: paper, print, finishing — the "Спецификации" tab in the design —
 * and the photo behind the product page's tab section.
 *
 * WooCommerce has no prose field beyond the description, and attributes turn a paragraph into a
 * table, so this adds one textarea and one image to the product editor and nothing else. The
 * theme renders the specifications as a tab when they hold something.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Product {

	const META = '_reklamo_specs';

	const IMAGE_META = '_reklamo_section_image';

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

	/** Attachment ID of the tab section's background photo, 0 when there is none. */
	public static function section_image( $product ): int {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product instanceof WC_Product ) {
			return 0;
		}
		$id = absint( $product->get_meta( self::IMAGE_META ) );
		return $id && wp_attachment_is_image( $id ) ? $id : 0;
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
		global $product_object;
		$image = $product_object instanceof WC_Product ? absint( $product_object->get_meta( self::IMAGE_META ) ) : 0;
		?>
		<fieldset class="form-field">
			<legend><?php esc_html_e( 'Section background image', 'reklamo-core' ); ?></legend>
			<?php Reklamo_Admin_Image::render( self::IMAGE_META, $image, __( 'Shown behind the tabs on the product page, fading in from the right. Leave empty for a plain section.', 'reklamo-core' ) ); ?>
		</fieldset>
		<?php
		echo '</div>';
	}

	public static function save( WC_Product $product ): void {
		// WooCommerce has already verified the product editor's nonce before this hook.
		$raw = isset( $_POST[ self::META ] ) ? wp_unslash( $_POST[ self::META ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( self::META, wp_kses_post( trim( $raw ) ) );
		$image = isset( $_POST[ self::IMAGE_META ] ) ? absint( $_POST[ self::IMAGE_META ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $image ) {
			$product->update_meta_data( self::IMAGE_META, $image );
		} else {
			$product->delete_meta_data( self::IMAGE_META );
		}
	}
}

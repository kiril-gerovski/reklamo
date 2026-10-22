<?php
/**
 * Per-product specifications: paper, print, finishing — the "Спецификации" tab in the design —
 * the photo behind the product page's tab section, and the minimum quantity of a product sold
 * by the piece. Colours are WooCommerce's own variations on the global "Цвят" attribute; each
 * colour term carries the hex of its swatch.
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

	const MIN_META = '_reklamo_min_qty';

	const COLOR_TAX = 'pa_cvyat';

	const HEX_META = 'reklamo_hex';

	const GALLERY_META = '_reklamo_variation_gallery';

	const QTY_TAX = 'pa_kolichestvo';

	/** A promotion: percent off every variation, written as WooCommerce's own sale price. */
	const PROMO_META = '_reklamo_promo_pct';

	/** The Пакети category (the theme's REKLAMO_PACKAGES_CAT): a package is not printed with an inscription. */
	const PACKAGES_CAT = 'paketi';

	/** The promotion's last day (Y-m-d), optional; WooCommerce ends the sale itself. */
	const PROMO_UNTIL_META = '_reklamo_promo_until';

	/** Set while this product's sale prices are ours, so clearing the promotion clears them. */
	const PROMO_APPLIED_META = '_reklamo_promo_applied';

	/** What comes free with the product, as the owner words it. */
	const GIFT_META = '_reklamo_gift';

	public static function init(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_filter( 'woocommerce_quantity_input_args', array( __CLASS__, 'quantity_args' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_quantity' ), 20, 3 );
		add_action( self::COLOR_TAX . '_add_form_fields', array( __CLASS__, 'hex_field_add' ) );
		add_action( self::COLOR_TAX . '_edit_form_fields', array( __CLASS__, 'hex_field_edit' ) );
		add_action( 'created_' . self::COLOR_TAX, array( __CLASS__, 'hex_save' ) );
		add_action( 'edited_' . self::COLOR_TAX, array( __CLASS__, 'hex_save' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_gallery_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'variation_gallery_save' ), 10, 2 );
	}

	/**
	 * Pure: a promotion price — the percent off, then down to the nearest ,99 below
	 * (349,99 at 15 % → 297,49 → 296,99), never under 0,99.
	 */
	public static function promo_price( float $regular, int $pct ): float {
		$off = $regular * ( 100 - $pct ) / 100;
		return max( 0.99, floor( round( $off, 2 ) + 0.01 ) - 0.01 );
	}

	/** The promotion in force: percent and last day, or 0 when there is none or it is over. */
	public static function promo( WC_Product $product ): array {
		$base  = self::base( $product );
		$pct   = (int) $base->get_meta( self::PROMO_META );
		$until = (string) $base->get_meta( self::PROMO_UNTIL_META );
		if ( $pct < 1 || $pct > 90 || ( '' !== $until && $until < wp_date( 'Y-m-d' ) ) ) {
			$pct = 0;
		}
		return array(
			'pct'   => $pct,
			'until' => $until,
		);
	}

	public static function gift( WC_Product $product ): string {
		return trim( (string) self::base( $product )->get_meta( self::GIFT_META ) );
	}

	/**
	 * What a package holds: the titles of its short description's list, such as "50 бр. Тефтери".
	 * The theme reads the same list for the product page (reklamo_package_items()).
	 *
	 * @return string[]
	 */
	public static function contents( WC_Product $product ): array {
		preg_match_all( '~<li\b[^>]*>(.*?)</li>~is', self::base( $product )->get_short_description(), $m );
		$out = array();
		foreach ( $m[1] as $inner ) {
			$title = trim( wp_strip_all_tags( preg_split( '~<br\s*/?>|\R~iu', trim( $inner ), 2 )[0] ) );
			if ( '' !== $title ) {
				$out[] = $title;
			}
		}
		return $out;
	}

	/** Write the promotion into every variation's sale price, or take ours away again. */
	public static function apply_promo( int $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}
		$promo   = self::promo( $product );
		$applied = (bool) $product->get_meta( self::PROMO_APPLIED_META );
		if ( ! $promo['pct'] && ! $applied ) {
			return;
		}
		foreach ( $product->get_children() as $child ) {
			$v = wc_get_product( $child );
			if ( ! $v ) {
				continue;
			}
			$regular = (float) $v->get_regular_price();
			if ( $promo['pct'] && $regular > 0 ) {
				$v->set_sale_price( (string) self::promo_price( $regular, $promo['pct'] ) );
				$v->set_date_on_sale_from( null );
				$v->set_date_on_sale_to( '' !== $promo['until'] ? $promo['until'] . ' 23:59:59' : null );
			} else {
				$v->set_sale_price( '' );
				$v->set_date_on_sale_from( null );
				$v->set_date_on_sale_to( null );
			}
			$v->save();
		}
		if ( $promo['pct'] ) {
			update_post_meta( $product_id, self::PROMO_APPLIED_META, 1 );
		} else {
			delete_post_meta( $product_id, self::PROMO_APPLIED_META );
		}
		WC_Product_Variable::sync( $product_id );
	}

	/** @return int[] a variation's own extra photos, in order */
	public static function variation_gallery( WC_Product $variation ): array {
		return array_values( array_filter( array_map( 'absint', explode( ',', (string) $variation->get_meta( self::GALLERY_META ) ) ), 'wp_attachment_is_image' ) );
	}

	/**
	 * WooCommerce gives a variation one image; the colour's other photos go here and replace the
	 * product gallery on the product page when the colour is chosen.
	 *
	 * @param int     $loop      Row index in the variations panel.
	 * @param array   $data      Unused.
	 * @param WP_Post $variation The variation post.
	 */
	public static function variation_gallery_field( int $loop, array $data, $variation ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$product = wc_get_product( $variation->ID );
		echo '<div class="form-row form-row-full">';
		echo '<label>' . esc_html__( 'Gallery for this colour', 'reklamo-core' ) . '</label>';
		Reklamo_Admin_Image::render_gallery( 'reklamo_variation_gallery[' . $loop . ']', $product ? self::variation_gallery( $product ) : array(), __( 'The other photos of this colour, shown after the variation image above. Empty: the product gallery is shown.', 'reklamo-core' ) );
		echo '</div>';
	}

	public static function variation_gallery_save( int $variation_id, int $loop ): void {
		// WooCommerce verifies the variations nonce before this hook.
		$raw = isset( $_POST['reklamo_variation_gallery'][ $loop ] ) ? sanitize_text_field( wp_unslash( $_POST['reklamo_variation_gallery'][ $loop ] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $raw ) {
			return;
		}
		$ids = implode( ',', array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
		if ( '' === $ids ) {
			delete_post_meta( $variation_id, self::GALLERY_META );
		} else {
			update_post_meta( $variation_id, self::GALLERY_META, $ids );
		}
	}

	/** The product a variation belongs to; settings live there. */
	private static function base( WC_Product $product ): WC_Product {
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			return $parent instanceof WC_Product ? $parent : $product;
		}
		return $product;
	}

	/** Smallest quantity a customer may order; 1 unless set. */
	public static function min_qty( WC_Product $product ): int {
		return max( 1, (int) self::base( $product )->get_meta( self::MIN_META ) );
	}

	/**
	 * Sold by the piece, with a quantity chosen by the customer from a minimum — as opposed to a
	 * package or a calendar tier, which is one fixed set (a package may still come in colours).
	 */
	public static function is_package( WC_Product $product ): bool {
		return has_term( self::PACKAGES_CAT, 'product_cat', $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() );
	}

	public static function by_the_piece( WC_Product $product ): bool {
		return self::min_qty( $product ) > 1;
	}

	/** @return array<string,string> the product's colours that have a swatch, name => hex, in the shop's order */
	public static function colours( WC_Product $product ): array {
		$terms = taxonomy_exists( self::COLOR_TAX ) ? wc_get_product_terms( self::base( $product )->get_id(), self::COLOR_TAX, array( 'fields' => 'all' ) ) : array();
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( '' !== self::hex( $term->term_id ) ) {
				$out[ $term->name ] = self::hex( $term->term_id );
			}
		}
		return $out;
	}

	/** Swatch colour of a colour term, '' when none is set. */
	public static function hex( int $term_id ): string {
		$hex = (string) get_term_meta( $term_id, self::HEX_META, true );
		return preg_match( '/^#[0-9a-f]{6}$/i', $hex ) ? $hex : '';
	}

	/** WooCommerce's quantity input starts at, and never goes below, the minimum. */
	public static function quantity_args( array $args, $product ): array {
		if ( $product instanceof WC_Product ) {
			$min                 = self::min_qty( $product );
			$args['min_value']   = max( (int) ( $args['min_value'] ?? 1 ), $min );
			$args['input_value'] = max( (int) ( $args['input_value'] ?? 1 ), $min );
		}
		return $args;
	}

	public static function validate_quantity( bool $passed, int $product_id, int $quantity ): bool {
		$product = wc_get_product( $product_id );
		if ( $passed && $product && $quantity < self::min_qty( $product ) ) {
			/* translators: 1: product name, 2: minimum quantity */
			wc_add_notice( sprintf( __( '%1$s: the minimum quantity is %2$d pieces.', 'reklamo-core' ), $product->get_name(), self::min_qty( $product ) ), 'error' );
			return false;
		}
		return $passed;
	}

	public static function hex_field_add(): void {
		?>
		<div class="form-field">
			<label for="reklamo_hex"><?php esc_html_e( 'Swatch colour', 'reklamo-core' ); ?></label>
			<input type="color" id="reklamo_hex" name="reklamo_hex" value="#b8892b">
			<p><?php esc_html_e( 'The circle the customer clicks on the product page.', 'reklamo-core' ); ?></p>
		</div>
		<?php
	}

	public static function hex_field_edit( WP_Term $term ): void {
		$hex = self::hex( $term->term_id );
		?>
		<tr class="form-field">
			<th scope="row"><label for="reklamo_hex"><?php esc_html_e( 'Swatch colour', 'reklamo-core' ); ?></label></th>
			<td>
				<input type="color" id="reklamo_hex" name="reklamo_hex" value="<?php echo esc_attr( $hex ? $hex : '#b8892b' ); ?>">
				<p class="description"><?php esc_html_e( 'The circle the customer clicks on the product page.', 'reklamo-core' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function hex_save( int $term_id ): void {
		// The term screens verify their own nonce before these hooks fire.
		$hex = isset( $_POST['reklamo_hex'] ) ? sanitize_hex_color( wp_unslash( $_POST['reklamo_hex'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $hex && current_user_can( 'manage_woocommerce' ) ) {
			update_term_meta( $term_id, self::HEX_META, $hex );
		}
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
		woocommerce_wp_text_input(
			array(
				'id'                => self::MIN_META,
				'label'             => __( 'Minimum quantity', 'reklamo-core' ),
				'description'       => __( 'For a product sold by the piece: the smallest number a customer can order (e.g. 50). The price above is then the price per piece. Leave 1 for packages and fixed sets.', 'reklamo-core' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
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
		echo '</div><div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'                => self::PROMO_META,
				'label'             => __( 'Promotion (% off)', 'reklamo-core' ),
				'description'       => __( 'Every colour and size at this percent less, rounded down to ,99; the card and the page show "Promotion" and the old price struck through. Empty = no promotion.', 'reklamo-core' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '90',
					'step' => '1',
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::PROMO_UNTIL_META,
				'label'       => __( 'Promotion until', 'reklamo-core' ),
				'description' => __( 'The last day of the promotion, included. Empty = until you remove it.', 'reklamo-core' ),
				'desc_tip'    => true,
				'type'        => 'date',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::GIFT_META,
				'label'       => __( 'Gift', 'reklamo-core' ),
				'description' => __( 'What comes free with it, e.g. "Red mug with your logo". Shown as "+ Gift" on the card and the page, and written on the order line. Empty = no gift.', 'reklamo-core' ),
				'desc_tip'    => true,
			)
		);
		echo '</div>';
	}

	/** @return WP_Term[] the package sizes (attribute "Количество") this product is offered in, in order */
	public static function tiers( WC_Product $product ): array {
		if ( ! $product->is_type( 'variable' ) || ! taxonomy_exists( self::QTY_TAX ) ) {
			return array();
		}
		$terms = wc_get_product_terms( $product->get_id(), self::QTY_TAX, array( 'fields' => 'all' ) );
		return is_array( $terms ) ? $terms : array();
	}

	/** Pieces in a package size: the number in the term ("50 бр." → 50). */
	public static function tier_count( string $slug ): int {
		return (int) preg_replace( '/\D/', '', $slug );
	}

	public static function save( WC_Product $product ): void {
		$pct   = isset( $_POST[ self::PROMO_META ] ) ? absint( $_POST[ self::PROMO_META ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$until = isset( $_POST[ self::PROMO_UNTIL_META ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::PROMO_UNTIL_META ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$gift  = isset( $_POST[ self::GIFT_META ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::GIFT_META ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$keep  = array(
			self::PROMO_META       => $pct >= 1 && $pct <= 90 ? $pct : '',
			self::PROMO_UNTIL_META => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ? $until : '',
			self::GIFT_META        => $gift,
		);
		foreach ( $keep as $key => $value ) {
			if ( '' === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}
		// After the product and its variations are saved (Reklamo_Variants): the prices are in by then.
		$apply = static function ( $id ) use ( $product, &$apply ) {
			if ( (int) $id === $product->get_id() ) {
				remove_action( 'woocommerce_update_product', $apply );
				self::apply_promo( (int) $id );
			}
		};
		add_action( 'woocommerce_update_product', $apply );
		// WooCommerce has already verified the product editor's nonce before this hook.
		$raw = isset( $_POST[ self::META ] ) ? wp_unslash( $_POST[ self::META ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( self::META, wp_kses_post( trim( $raw ) ) );
		$min = isset( $_POST[ self::MIN_META ] ) ? absint( $_POST[ self::MIN_META ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $min > 1 ) {
			$product->update_meta_data( self::MIN_META, $min );
		} else {
			$product->delete_meta_data( self::MIN_META );
		}
		$image = isset( $_POST[ self::IMAGE_META ] ) ? absint( $_POST[ self::IMAGE_META ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $image ) {
			$product->update_meta_data( self::IMAGE_META, $image );
		} else {
			$product->delete_meta_data( self::IMAGE_META );
		}
	}
}

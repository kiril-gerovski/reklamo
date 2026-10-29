<?php
/**
 * The product editor's "Colours and quantities" tab: the owner ticks the colours (global attribute
 * Цвят) and gives each one set of photos, ticks the quantities (attribute Количество) and gives each
 * one price. Saving builds WooCommerce's variations from that — one per colour and quantity — so
 * nobody picks a photo or a price per variation. WooCommerce's own Attributes and Variations tabs
 * stay for anything unusual, such as colours priced differently.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Variants {

	const PHOTOS_META = '_reklamo_colour_photos'; // colour slug => attachment IDs, the first is the main photo.

	public static function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		// Before WooCommerce builds the product object from the posted type (priority 10).
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'force_variable' ), 5 );
		// Before Reklamo_Product::save (10), so the variations are in place when the promotion is applied.
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ), 5 );
	}

	public static function tab( array $tabs ): array {
		$tabs['reklamo_variants'] = array(
			'label'    => __( 'Colours and quantities', 'reklamo-core' ),
			'target'   => 'reklamo_variants_data',
			'class'    => array(),
			'priority' => 15,
		);
		return $tabs;
	}

	/** @return WP_Term[] every term of an attribute, in the shop's order */
	private static function terms( string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'menu_order',
			)
		);
		return is_array( $terms ) ? $terms : array();
	}

	/** @return string[] the slugs of the product's terms of an attribute */
	private static function chosen( WC_Product $product, string $taxonomy ): array {
		$terms = taxonomy_exists( $taxonomy ) ? wc_get_product_terms( $product->get_id(), $taxonomy, array( 'fields' => 'slugs' ) ) : array();
		return is_array( $terms ) ? $terms : array();
	}

	/** @return array<string,WC_Product_Variation[]> quantity slug => its variations */
	private static function by_size( WC_Product $product ): array {
		$out = array();
		foreach ( $product->get_children() as $child ) {
			$v    = wc_get_product( $child );
			$size = $v ? (string) ( $v->get_attributes()[ Reklamo_Product::QTY_TAX ] ?? '' ) : '';
			if ( '' !== $size ) {
				$out[ $size ][] = $v;
			}
		}
		return $out;
	}

	/** The colours cost differently in some quantity: one price per quantity would overwrite them. */
	private static function mixed_prices( WC_Product $product ): bool {
		foreach ( self::by_size( $product ) as $variations ) {
			$prices = array_unique( array_filter( array_map( static fn( $v ) => wc_format_decimal( $v->get_regular_price() ), $variations ), 'strlen' ) );
			if ( count( $prices ) > 1 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The photos of each colour: as saved here, or, for a product set up before this tab, the
	 * image and gallery most of its variations in that colour carry (on a tie, the newest upload);
	 * the product's own image, which WooCommerce copies into variations, counts only when nothing else does.
	 *
	 * @return array<string,int[]>
	 */
	private static function photos( WC_Product $product ): array {
		$saved = $product->get_meta( self::PHOTOS_META );
		if ( is_array( $saved ) ) {
			return array_map( static fn( $ids ) => array_values( array_filter( array_map( 'absint', (array) $ids ) ) ), $saved );
		}
		$votes = array();
		foreach ( $product->get_children() as $child ) {
			$v      = wc_get_product( $child );
			$colour = $v ? (string) ( $v->get_attributes()[ Reklamo_Product::COLOR_TAX ] ?? '' ) : '';
			if ( '' !== $colour && $v->get_image_id( 'edit' ) ) {
				$set                      = implode( ',', array_merge( array( (int) $v->get_image_id( 'edit' ) ), Reklamo_Product::variation_gallery( $v ) ) );
				$weight                   = (int) $v->get_image_id( 'edit' ) === (int) $product->get_image_id( 'edit' ) ? 0.001 : 1;
				$votes[ $colour ][ $set ] = ( $votes[ $colour ][ $set ] ?? 0 ) + $weight;
			}
		}
		$out = array();
		foreach ( $votes as $colour => $sets ) {
			uksort( $sets, static fn( $a, $b ) => array( $sets[ $b ], (int) $b ) <=> array( $sets[ $a ], (int) $a ) );
			$out[ $colour ] = array_map( 'intval', explode( ',', (string) array_key_first( $sets ) ) );
		}
		return $out;
	}

	public static function panel(): void {
		global $product_object;
		$product = $product_object instanceof WC_Product ? $product_object : null;
		if ( ! $product ) {
			return;
		}
		wp_enqueue_script( 'reklamo-admin-variants', REKLAMO_URL . 'assets/js/admin-variants.js', array( 'jquery' ), REKLAMO_VERSION, true );
		$colours = self::chosen( $product, Reklamo_Product::COLOR_TAX );
		$sizes   = self::chosen( $product, Reklamo_Product::QTY_TAX );
		$photos  = self::photos( $product );
		$mixed   = self::mixed_prices( $product );
		$prices  = array();
		foreach ( self::by_size( $product ) as $size => $variations ) {
			foreach ( $variations as $v ) {
				if ( '' !== $v->get_regular_price( 'edit' ) ) {
					$prices[ $size ] = $v->get_regular_price( 'edit' );
					break;
				}
			}
		}
		?>
		<div id="reklamo_variants_data" class="panel woocommerce_options_panel hidden">
			<style>
				#reklamo_variants_data .rk-variants { padding: 12px 16px; }
				#reklamo_variants_data h4 { margin: 4px 0 6px; font-size: 14px; }
				#reklamo_variants_data .description { display: block; margin: 0 0 10px; }
				#reklamo_variants_data .rk-variants__colours { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 12px; }
				#reklamo_variants_data label.rk-variants__colour { display: inline-flex; align-items: center; gap: 6px; float: none; width: auto; margin: 0; padding: 4px 12px 4px 6px; border: 1px solid #dcdcde; border-radius: 999px; background: #fff; cursor: pointer; line-height: 1.4; }
				#reklamo_variants_data label.rk-variants__colour:has(input:checked) { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
				#reklamo_variants_data .rk-variants input[type="checkbox"] { float: none; margin: 0; }
				#reklamo_variants_data .rk-variants__swatch { flex: none; width: 22px; height: 22px; border-radius: 50%; border: 1px solid rgba(0,0,0,.2); }
				#reklamo_variants_data .rk-variants__photos { margin: 0 0 12px; padding: 10px 12px; border: 1px solid #dcdcde; border-radius: 6px; background: #fff; }
				#reklamo_variants_data .rk-variants__photos[hidden] { display: none; }
				#reklamo_variants_data .rk-variants__photos-title { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; padding: 0; font-weight: 600; }
				#reklamo_variants_data .rk-variants__sizes { display: grid; grid-template-columns: max-content 8em 6em minmax(10em, 22em); gap: 6px 14px; align-items: center; }
				#reklamo_variants_data .rk-variants__head { font-weight: 600; color: #50575e; }
				#reklamo_variants_data .rk-variants__sizes label { display: inline-flex; align-items: center; gap: 6px; float: none; width: auto; margin: 0; }
				#reklamo_variants_data .rk-variants__sizes input:not([type="checkbox"]) { width: 100%; float: none; margin: 0; }
			</style>
			<input type="hidden" name="reklamo_variants" value="1">
			<div class="options_group rk-variants">
				<h4><?php esc_html_e( 'Colours', 'reklamo-core' ); ?></h4>
				<span class="description"><?php esc_html_e( 'Tick the colours the product comes in: the customer sees each as a circle. Upload the photos of each colour once, the first is the main one; every quantity of that colour shows them. No colour ticked: one product with its own photos.', 'reklamo-core' ); ?></span>
				<div class="rk-variants__colours">
					<?php foreach ( self::terms( Reklamo_Product::COLOR_TAX ) as $term ) : ?>
						<?php $hex = Reklamo_Product::hex( $term->term_id ); ?>
						<label class="rk-variants__colour">
							<input type="checkbox" name="reklamo_colours[]" value="<?php echo esc_attr( $term->slug ); ?>" data-rk-colour <?php checked( in_array( $term->slug, $colours, true ) ); ?>>
							<span class="rk-variants__swatch" style="background:<?php echo esc_attr( $hex ? $hex : '#fff' ); ?>"></span>
							<?php echo esc_html( $term->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<?php foreach ( self::terms( Reklamo_Product::COLOR_TAX ) as $term ) : ?>
					<?php $hex = Reklamo_Product::hex( $term->term_id ); ?>
					<div class="rk-variants__photos" data-rk-photos="<?php echo esc_attr( $term->slug ); ?>"<?php echo in_array( $term->slug, $colours, true ) ? '' : ' hidden'; ?>>
						<p class="rk-variants__photos-title">
							<span class="rk-variants__swatch" style="background:<?php echo esc_attr( $hex ? $hex : '#fff' ); ?>"></span>
							<?php
							/* translators: %s: colour name */
							echo esc_html( sprintf( __( 'Photos: %s', 'reklamo-core' ), $term->name ) );
							?>
						</p>
						<?php Reklamo_Admin_Image::render_gallery( 'reklamo_colour_photos[' . $term->slug . ']', $photos[ $term->slug ] ?? array(), __( 'The first photo is the main one. Empty: the product image is shown.', 'reklamo-core' ) ); ?>
					</div>
				<?php endforeach; ?>
				<?php /* translators: %s: link to Products → Attributes → Цвят */ ?>
				<span class="description"><?php printf( esc_html__( 'A colour that is missing is added in %s, with the colour of its circle.', 'reklamo-core' ), '<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . Reklamo_Product::COLOR_TAX . '&post_type=product' ) ) . '">' . esc_html__( 'Products → Attributes → Colour', 'reklamo-core' ) . '</a>' ); ?></span>
			</div>
			<div class="options_group rk-variants">
				<h4><?php esc_html_e( 'Quantities and prices', 'reklamo-core' ); ?></h4>
				<?php if ( $mixed ) : ?>
					<span class="description"><?php esc_html_e( 'The colours of this product are priced differently, so each price is set in its own variation (Variations tab). Here you choose which quantities are offered; a new one takes the price of the same quantity in another colour.', 'reklamo-core' ); ?></span>
				<?php else : ?>
					<span class="description"><?php esc_html_e( 'Tick the quantities the product is sold in and give each its price, VAT included; every colour costs the same.', 'reklamo-core' ); ?></span>
				<?php endif; ?>
				<span class="description"><?php esc_html_e( 'A promotion or a gift can go with some quantities only, or differ from one to another: leave a field empty where there is none. The last day of the promotion and the photo with the gift are in the General tab.', 'reklamo-core' ); ?></span>
				<div class="rk-variants__sizes">
					<span class="rk-variants__head"><?php esc_html_e( 'Quantity', 'reklamo-core' ); ?></span>
					<span class="rk-variants__head"><?php echo $mixed ? '' : esc_html__( 'Price', 'reklamo-core' ); ?></span>
					<span class="rk-variants__head"><?php esc_html_e( 'Promotion, %', 'reklamo-core' ); ?></span>
					<span class="rk-variants__head"><?php esc_html_e( 'Gift', 'reklamo-core' ); ?></span>
					<?php foreach ( self::terms( Reklamo_Product::QTY_TAX ) as $term ) : ?>
						<label>
							<input type="checkbox" name="reklamo_sizes[]" value="<?php echo esc_attr( $term->slug ); ?>" data-rk-size <?php checked( in_array( $term->slug, $sizes, true ) ); ?>>
							<?php echo esc_html( $term->name ); ?>
						</label>
						<?php if ( $mixed ) : ?>
							<span></span>
						<?php else : ?>
							<input type="text" class="wc_input_price" name="reklamo_size_price[<?php echo esc_attr( $term->slug ); ?>]" value="<?php echo esc_attr( isset( $prices[ $term->slug ] ) ? wc_format_localized_price( $prices[ $term->slug ] ) : '' ); ?>" placeholder="<?php echo esc_attr( get_woocommerce_currency_symbol() ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: quantity, e.g. "50 бр." */ __( 'Price for %s', 'reklamo-core' ), $term->name ) ); ?>" data-rk-size-price="<?php echo esc_attr( $term->slug ); ?>">
						<?php endif; ?>
						<?php $tier = in_array( $term->slug, $sizes, true ) ? Reklamo_Product::tier_settings( $product, $term->slug ) : array_fill_keys( array( 'pct', 'gift' ), '' ); ?>
						<input type="number" min="1" max="90" step="1" name="reklamo_tier_promo[<?php echo esc_attr( $term->slug ); ?>]" value="<?php echo esc_attr( $tier['pct'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: quantity, e.g. "50 бр." */ __( 'Promotion for %s, percent off', 'reklamo-core' ), $term->name ) ); ?>" data-rk-size-field="<?php echo esc_attr( $term->slug ); ?>">
						<input type="text" name="reklamo_tier_gift[<?php echo esc_attr( $term->slug ); ?>]" value="<?php echo esc_attr( $tier['gift'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Satin Black lighter', 'reklamo-core' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: quantity, e.g. "50 бр." */ __( 'Gift with %s', 'reklamo-core' ), $term->name ) ); ?>" data-rk-size-field="<?php echo esc_attr( $term->slug ); ?>">
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/** A product given quantities here is a variable product, whatever the type select says. */
	public static function force_variable(): void {
		// WooCommerce verified the product editor's nonce before this hook.
		if ( ! empty( $_POST['reklamo_variants'] ) && ! empty( $_POST['reklamo_sizes'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['product-type'] = 'variable';
		}
	}

	private static function attribute( string $taxonomy, array $slugs, int $position ): WC_Product_Attribute {
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( array_map( static fn( $slug ) => (int) get_term_by( 'slug', $slug, $taxonomy )->term_id, $slugs ) );
		$attribute->set_position( $position );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		return $attribute;
	}

	public static function save( WC_Product $product ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product editor's nonce.
		if ( empty( $_POST['reklamo_variants'] ) || ! $product->is_type( 'variable' ) ) {
			return;
		}
		$valid   = static fn( string $taxonomy, array $slugs ) => array_values( array_intersect( wp_list_pluck( self::terms( $taxonomy ), 'slug' ), array_map( 'sanitize_title', $slugs ) ) );
		$colours = $valid( Reklamo_Product::COLOR_TAX, isset( $_POST['reklamo_colours'] ) ? (array) wp_unslash( $_POST['reklamo_colours'] ) : array() );
		$sizes   = $valid( Reklamo_Product::QTY_TAX, isset( $_POST['reklamo_sizes'] ) ? (array) wp_unslash( $_POST['reklamo_sizes'] ) : array() );
		$posted  = isset( $_POST['reklamo_size_price'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['reklamo_size_price'] ) ) : array();
		$albums  = isset( $_POST['reklamo_colour_photos'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['reklamo_colour_photos'] ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $sizes ) {
			if ( $colours ) {
				WC_Admin_Meta_Boxes::add_error( __( 'Colours and quantities: tick at least one quantity, with its price. Nothing was changed there.', 'reklamo-core' ) );
			}
			return;
		}
		$mixed  = self::mixed_prices( $product );
		$prices = array();
		if ( ! $mixed ) {
			foreach ( $sizes as $size ) {
				$prices[ $size ] = wc_format_decimal( $posted[ $size ] ?? '' );
				if ( '' === $prices[ $size ] ) {
					/* translators: %s: quantity, e.g. "50 бр." */
					WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Colours and quantities: %s has no price, so it is not offered.', 'reklamo-core' ), get_term_by( 'slug', $size, Reklamo_Product::QTY_TAX )->name ) );
				}
			}
		}
		$photos = array();
		foreach ( $colours as $colour ) {
			$photos[ $colour ] = array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $albums[ $colour ] ?? '' ) ) ), 'wp_attachment_is_image' ) );
		}

		$attributes = $product->get_attributes( 'edit' );
		unset( $attributes[ Reklamo_Product::COLOR_TAX ], $attributes[ Reklamo_Product::QTY_TAX ] );
		$ours = array();
		if ( $colours ) {
			$ours[ Reklamo_Product::COLOR_TAX ] = self::attribute( Reklamo_Product::COLOR_TAX, $colours, 0 );
		}
		$ours[ Reklamo_Product::QTY_TAX ] = self::attribute( Reklamo_Product::QTY_TAX, $sizes, 1 );
		$product->set_attributes( $ours + $attributes );
		$defaults = (array) $product->get_default_attributes( 'edit' );
		if ( $colours && ! in_array( $defaults[ Reklamo_Product::COLOR_TAX ] ?? '', $colours, true ) ) {
			$defaults[ Reklamo_Product::COLOR_TAX ] = $colours[0];
		}
		if ( ! $colours ) {
			unset( $defaults[ Reklamo_Product::COLOR_TAX ] );
		}
		if ( ! in_array( $defaults[ Reklamo_Product::QTY_TAX ] ?? '', $sizes, true ) ) {
			$defaults[ Reklamo_Product::QTY_TAX ] = $sizes[0];
		}
		$product->set_default_attributes( $defaults );
		$product->update_meta_data( self::PHOTOS_META, $photos );
		$first = $colours ? ( $photos[ $colours[0] ][0] ?? 0 ) : 0;
		if ( ! $product->get_image_id( 'edit' ) && $first ) {
			$product->set_image_id( $first );
		}

		// Once, after WooCommerce has saved the product: sync() saves it again, which would land here anew.
		$build = static function ( $id ) use ( $product, $colours, $sizes, $prices, $photos, $mixed, &$build ) {
			if ( (int) $id === $product->get_id() ) {
				remove_action( 'woocommerce_update_product', $build );
				self::build( (int) $id, $colours, $sizes, $prices, $photos, $mixed );
			}
		};
		add_action( 'woocommerce_update_product', $build );
	}

	/**
	 * One variation per colour and quantity: the ones that fit are kept, the missing ones made,
	 * the rest deleted; then each gets its quantity's price and its colour's photos.
	 */
	private static function build( int $product_id, array $colours, array $sizes, array $prices, array $photos, bool $per_colour ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		$wanted = array();
		foreach ( $colours ? $colours : array( '' ) as $colour ) {
			foreach ( $sizes as $size ) {
				$wanted[ $colour . '|' . $size ] = array_filter(
					array(
						Reklamo_Product::COLOR_TAX => $colour,
						Reklamo_Product::QTY_TAX   => $size,
					),
					'strlen'
				);
			}
		}
		$have = array();
		$seen = array(); // quantity slug => a price it already has, for new colours when prices differ.
		foreach ( $product->get_children() as $child ) {
			$v = wc_get_product( $child );
			if ( ! $v ) {
				continue;
			}
			$at  = $v->get_attributes( 'edit' );
			$key = ( $colours ? (string) ( $at[ Reklamo_Product::COLOR_TAX ] ?? '' ) : '' ) . '|' . (string) ( $at[ Reklamo_Product::QTY_TAX ] ?? '' );
			$fit = isset( $wanted[ $key ] ) && ! isset( $have[ $key ] ) && count( $at ) === count( $wanted[ $key ] );
			if ( $fit ) {
				$have[ $key ] = $v;
				if ( '' !== $v->get_regular_price( 'edit' ) ) {
					$seen[ $wanted[ $key ][ Reklamo_Product::QTY_TAX ] ] ??= $v->get_regular_price( 'edit' );
				}
			} else {
				$v->delete( true );
			}
		}
		foreach ( $wanted as $key => $at ) {
			$v = $have[ $key ] ?? null;
			if ( ! $v ) {
				$v = new WC_Product_Variation();
				$v->set_parent_id( $product_id );
				$v->set_attributes( $at );
				$v->set_status( 'publish' );
			}
			$size = $at[ Reklamo_Product::QTY_TAX ];
			if ( ! $per_colour ) {
				$v->set_regular_price( $prices[ $size ] ?? '' );
			} elseif ( ! $v->get_id() ) {
				$v->set_regular_price( $seen[ $size ] ?? '' );
			}
			if ( $colours ) {
				$ids = $photos[ $at[ Reklamo_Product::COLOR_TAX ] ] ?? array();
				$v->set_image_id( $ids[0] ?? 0 );
				$rest = implode( ',', array_slice( $ids, 1 ) );
				if ( '' === $rest ) {
					$v->delete_meta_data( Reklamo_Product::GALLERY_META );
				} else {
					$v->update_meta_data( Reklamo_Product::GALLERY_META, $rest );
				}
			}
			$v->save();
		}
		WC_Product_Variable::sync( $product_id );
		wc_delete_product_transients( $product_id );
	}
}

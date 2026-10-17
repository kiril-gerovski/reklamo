<?php
/**
 * The cart: a customer collects products and packages and sends them as ONE request. They go
 * in with a plain "Add to cart". In the cart, step 1: the customer uploads one or more logos,
 * each with a note to the designer, and marks the products each one is for; nobody gets to
 * step 2 (details, consents, send) until every product has a logo and a note. Sending the cart creates
 * the order directly (no checkout, no payment): one line per product, each line carrying its
 * logo, so every product gets its own mockup and approval (Reklamo_Items).
 *
 * WooCommerce's cart session holds the products; the logo stays an unclaimed upload until
 * the request is sent, and Reklamo_Cleanup removes it if the cart is abandoned.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Cart {

	const NONCE      = 'reklamo_add_to_cart';
	const CART_NONCE = 'reklamo_cart';
	const META_FILE  = '_reklamo_file_token';
	const LOGO_AGE   = 2 * DAY_IN_SECONDS; // as long as Reklamo_Cleanup keeps an unclaimed upload.
	const LOGOS      = 'reklamo_logos'; // session: the customer's logos, notes and the lines each is for.
	const STEP       = 'reklamo_cart_step'; // session: 'logos' or 'details'.
	const ADDED      = 'reklamo_just_added'; // session: name of the product just added, for the dialog.

	public static function init(): void {
		add_action( 'wp_loaded', array( __CLASS__, 'require_package' ), 19 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'back_after_add' ), 10, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'added_dialog' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'create_order_line_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'claim_files' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'claim_files' ), 10, 1 );
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'admin_item_file' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hide_token_meta' ) );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( 'Reklamo_Items', 'hidden_meta' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_cart_post' ) );
		add_action( 'template_redirect', array( __CLASS__, 'checkout_to_cart' ) );
		add_filter( 'the_content', array( __CLASS__, 'cart_page_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets(): void {
		if ( ! is_product() && ! is_front_page() && ! is_page() ) {
			return;
		}
		$path = REKLAMO_PATH . 'assets/css/reklamo.css';
		wp_enqueue_style( 'reklamo-core', REKLAMO_URL . 'assets/css/reklamo.css', array(), file_exists( $path ) ? (string) filemtime( $path ) : REKLAMO_VERSION );
		Reklamo_Request::enqueue_uploader();
		if ( is_product() ) {
			$js = REKLAMO_PATH . 'assets/js/product.js';
			wp_enqueue_script( 'reklamo-product', REKLAMO_URL . 'assets/js/product.js', array(), file_exists( $js ) ? (string) filemtime( $js ) : REKLAMO_VERSION, true );
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$js = REKLAMO_PATH . 'assets/js/cart.js';
			wp_enqueue_script( 'reklamo-cart', REKLAMO_URL . 'assets/js/cart.js', array(), file_exists( $js ) ? (string) filemtime( $js ) : REKLAMO_VERSION, true );
		}
	}

	public static function count(): int {
		return function_exists( 'WC' ) && WC()->cart ? count( WC()->cart->get_cart() ) : 0;
	}

	public static function url(): string {
		return wc_get_cart_url();
	}

	/* ------------------------------------------------------------ product page */

	/** Drop zone + markup shared by the product form and each cart line. */
	private static function drop_zone( string $id, string $name, string $label, bool $required, string $token_name = '' ): string {
		$accept = '.' . implode( ',.', Reklamo_Storage::LOGO_EXTENSIONS );
		ob_start();
		?>
		<div class="rq-field rq-field--file">
			<label class="rq-drop" for="<?php echo esc_attr( $id ); ?>" data-rq-drop>
				<span class="rq-drop__icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17a4 4 0 0 1 .8-7.9A6 6 0 0 1 16.4 7 4.5 4.5 0 0 1 19 15.5"/><path d="M12 12v9"/><path d="m8.5 15.5 3.5-3.5 3.5 3.5"/></svg></span>
				<span class="rq-drop__text">
					<strong><?php echo esc_html( $label ); ?></strong>
					<small><?php echo esc_html( strtoupper( implode( ', ', array_diff( Reklamo_Storage::LOGO_EXTENSIONS, array( 'jpeg' ) ) ) ) ); ?></small>
				</span>
				<input type="file" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" accept="<?php echo esc_attr( $accept ); ?>"<?php echo $required ? ' required' : ''; ?><?php echo '' !== $token_name ? ' data-token-name="' . esc_attr( $token_name ) . '" data-name-name="' . esc_attr( str_replace( 'file_token', 'file_name', $token_name ) ) . '"' : ''; ?>>
			</label>
			<div class="rq-file" data-rq-file hidden>
				<span class="rq-file__badge" data-rq-file-ext></span>
				<span class="rq-file__name" data-rq-file-name></span>
				<span class="rq-file__size" data-rq-file-size></span>
				<span class="rq-file__ok" aria-hidden="true">✓</span>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The product page's order box: "Add to cart"; the logo follows in the cart. A product sold
	 * by the piece also gets its colours (WooCommerce variations) and a quantity from its minimum.
	 */
	public static function product_form( WC_Product $product ): string {
		if ( ! $product->is_purchasable() ) {
			return '';
		}
		$piece = Reklamo_Product::by_the_piece( $product );
		$min   = Reklamo_Product::min_qty( $product );
		$vars  = array();
		if ( $product instanceof WC_Product_Variable ) {
			foreach ( $product->get_available_variations( 'objects' ) as $v ) {
				if ( ! $v->is_purchasable() ) {
					continue;
				}
				$vars[] = array(
					'id'      => $v->get_id(),
					'attrs'   => $v->get_variation_attributes(),
					'price'   => (float) wc_get_price_to_display( $v ),
					'gallery' => self::gallery_html( $v ),
				);
			}
		}
		$options  = $product instanceof WC_Product_Variable ? $product->get_variation_attributes() : array();
		$defaults = $product->get_default_attributes();
		$price    = $vars ? min( wp_list_pluck( $vars, 'price' ) ) : (float) wc_get_price_to_display( $product );
		ob_start();
		?>
		<form class="rq-form rq-form--product cart<?php echo $piece ? ' is-piece' : ''; ?>" method="post" action="<?php echo esc_url( $product->get_permalink() ); ?>" data-rk-product
			data-variations="<?php echo esc_attr( (string) wp_json_encode( $vars ) ); ?>"
			data-price="<?php echo esc_attr( (string) $price ); ?>"
			data-gallery="<?php echo esc_attr( $vars ? self::gallery_html( $product ) : '' ); ?>"
			data-currency="<?php echo esc_attr( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ); ?>">
			<?php wp_nonce_field( self::NONCE, 'reklamo_nonce' ); ?>
			<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( (string) $product->get_id() ); ?>">
			<?php if ( $vars ) : ?>
				<input type="hidden" name="variation_id" value="" data-rk-variation>
			<?php endif; ?>

			<?php foreach ( $options as $attribute => $values ) : ?>
				<?php
				$taxonomy = taxonomy_exists( $attribute ) ? $attribute : '';
				$terms    = $taxonomy ? wc_get_product_terms( $product->get_id(), $taxonomy, array( 'fields' => 'all' ) ) : array();
				$name     = 'attribute_' . sanitize_title( $attribute );
				// Only values some purchasable variation has: a size without a price is not offered.
				$values  = array_values( array_filter( $values, static fn( $v ) => (bool) array_filter( $vars, static fn( $x ) => in_array( (string) ( $x['attrs'][ $name ] ?? '' ), array( '', (string) $v ), true ) ) ) );
				$default = (string) ( $defaults[ sanitize_title( $attribute ) ] ?? '' );
				$first   = '' === $default || ! in_array( $default, $values, true );
				?>
				<fieldset class="rk-options" data-attribute="<?php echo esc_attr( $name ); ?>">
					<legend class="rk-options__label"><?php echo esc_html( wc_attribute_label( $attribute, $product ) ); ?>: <span data-rk-chosen></span></legend>
					<div class="rk-options__list">
						<?php foreach ( $terms ? $terms : $values as $option ) : ?>
							<?php
							$slug  = $option instanceof WP_Term ? $option->slug : (string) $option;
							$label = $option instanceof WP_Term ? $option->name : (string) $option;
							$hex   = $option instanceof WP_Term ? Reklamo_Product::hex( $option->term_id ) : '';
							if ( ! in_array( $slug, $values, true ) ) {
								continue;
							}
							?>
							<label class="rk-option<?php echo $hex ? ' rk-option--swatch' : ''; ?>" title="<?php echo esc_attr( $label ); ?>">
								<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $slug ); ?>" data-label="<?php echo esc_attr( $label ); ?>" required <?php checked( $first || $slug === $default ); ?>>
								<?php if ( $hex ) : ?>
									<span class="rk-option__swatch" style="--swatch: <?php echo esc_attr( $hex ); ?>"></span><span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
								<?php else : ?>
									<span class="rk-option__text"><?php echo esc_html( $label ); ?></span>
								<?php endif; ?>
							</label>
							<?php $first = false; ?>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endforeach; ?>

			<?php if ( $piece ) : ?>
				<div class="rk-qty">
					<label class="rk-qty__label" for="rk-qty-<?php echo esc_attr( (string) $product->get_id() ); ?>"><?php esc_html_e( 'Quantity', 'reklamo-core' ); ?></label>
					<div class="rk-qty__field" data-rk-stepper>
						<button type="button" class="rk-qty__step" data-step="-1" aria-label="<?php esc_attr_e( 'Fewer', 'reklamo-core' ); ?>">−</button>
						<input type="number" id="rk-qty-<?php echo esc_attr( (string) $product->get_id() ); ?>" name="quantity" value="<?php echo esc_attr( (string) $min ); ?>" min="<?php echo esc_attr( (string) $min ); ?>" step="1" inputmode="numeric" data-rk-qty>
						<button type="button" class="rk-qty__step" data-step="1" aria-label="<?php esc_attr_e( 'More', 'reklamo-core' ); ?>">+</button>
					</div>
					<?php if ( $min > 1 ) : ?>
						<p class="rk-qty__min">
							<?php
							/* translators: %d: minimum quantity */
							echo esc_html( sprintf( __( 'Minimum quantity: %d pieces', 'reklamo-core' ), $min ) );
							?>
						</p>
					<?php endif; ?>
				</div>
				<p class="rk-total"><?php esc_html_e( 'Total', 'reklamo-core' ); ?>: <strong data-rk-total><?php echo wp_kses_post( wc_price( $price * $min ) ); ?></strong> <span><?php esc_html_e( 'incl. VAT', 'reklamo-core' ); ?></span></p>
			<?php endif; ?>

			<button type="submit" class="btn btn--primary rq-add"><?php esc_html_e( 'Add to cart', 'reklamo-core' ); ?></button>
			<p class="rq-add-hint"><?php esc_html_e( 'You upload your logo in the cart — one per product, or one for all of them.', 'reklamo-core' ); ?></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The product gallery's slides for a colour: its own image and photos when it has any,
	 * otherwise the product's. Rendered by WooCommerce's own gallery function, so the product
	 * page script only swaps the slides and starts WooCommerce's gallery again.
	 */
	private static function gallery_html( WC_Product $product ): string {
		$ids = array();
		if ( $product->is_type( 'variation' ) ) {
			$gallery = Reklamo_Product::variation_gallery( $product );
			// WooCommerce 11.2 keeps no variation image that equals the parent's; the colour then
			// opens on the product's own photo, as WooCommerce shows it.
			$own = (int) $product->get_image_id( 'edit' );
			$own = $own ? $own : ( $gallery ? (int) $product->get_image_id() : 0 );
			$ids = array_merge( $own ? array( $own ) : array(), $gallery );
			if ( ! $ids ) {
				return '';
			}
		} else {
			$ids = array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) );
		}
		$html = '';
		foreach ( array_values( array_filter( array_unique( $ids ) ) ) as $i => $id ) {
			$html .= wc_get_gallery_image_html( $id, 0 === $i, $i );
		}
		return $html;
	}

	/**
	 * Runs just before WooCommerce's add-to-cart handler. WooCommerce ignores an add-to-cart
	 * without a product: our forms say so instead. And the forms without a colour picker (the
	 * request page, the homepage) add a package in its default colour, which the cart shows
	 * and the product page can change.
	 */
	public static function require_package(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the add-to-cart verifies the nonce; this only fills in the defaults.
		if ( ! isset( $_POST['reklamo_nonce'] ) || ! function_exists( 'wc_add_notice' ) ) {
			return;
		}
		if ( empty( $_POST['add-to-cart'] ) ) {
			wc_add_notice( __( 'Please choose a package.', 'reklamo-core' ), 'error' );
			return;
		}
		$product = wc_get_product( absint( $_POST['add-to-cart'] ) );
		if ( ! $product instanceof WC_Product_Variable || ! empty( $_POST['variation_id'] ) ) {
			return;
		}
		$attrs = array();
		foreach ( $product->get_default_attributes() as $name => $value ) {
			$key = 'attribute_' . sanitize_title( $name );
			if ( empty( $_POST[ $key ] ) ) {
				$_POST[ $key ]    = $value;
				$_REQUEST[ $key ] = $value;
			}
			$attrs[ $key ] = sanitize_title( wp_unslash( $_POST[ $key ] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$variation = ( new WC_Product_Data_Store_CPT() )->find_matching_product_variation( $product, $attrs );
		if ( $variation ) {
			$_POST['variation_id']    = $variation;
			$_REQUEST['variation_id'] = $variation;
		}
	}

	/**
	 * Our forms carry a nonce; an add-to-cart from elsewhere (a link, the Store API) needs none,
	 * since adding to the cart changes nothing but the visitor's own session.
	 *
	 * @param bool $passed     Validation so far.
	 * @param int  $product_id Product being added.
	 * @param int  $quantity   Quantity.
	 */
	public static function validate( bool $passed, int $product_id, int $quantity ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( isset( $_POST['reklamo_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['reklamo_nonce'] ) ), self::NONCE ) ) {
			wc_add_notice( __( 'The form has expired. Please reload the page and try again.', 'reklamo-core' ), 'error' );
			return false;
		}
		return $passed;
	}

	public static function add_cart_item_data( array $data, int $product_id, int $variation_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// The same product twice may get two different logos: never merge into quantity 2.
		$data['unique_key'] = md5( microtime() . wp_rand() );
		return $data;
	}

	/**
	 * After adding: back to the page the customer was on, where a dialog offers the cart or
	 * more shopping. WooCommerce's own "added to your cart" notice is dropped: the dialog
	 * says it, and the notice would otherwise surface later, on the cart page.
	 *
	 * @param string|false    $url     WooCommerce's target (false = stay).
	 * @param WC_Product|null $product The product just added.
	 */
	public static function back_after_add( $url, $product = null ) {
		if ( wc_notice_count( 'error' ) > 0 ) {
			return $url;
		}
		$notices = wc_get_notices();
		unset( $notices['success'] );
		wc_set_notices( $notices );
		if ( $product instanceof WC_Product && WC()->session ) {
			WC()->session->set( self::ADDED, $product->get_name() );
		}
		$back = remove_query_arg( array( 'add-to-cart', 'quantity' ), home_url( add_query_arg( array() ) ) );
		return wp_validate_redirect( $back, self::url() );
	}

	/** The dialog after an add-to-cart: shown once, on the page the customer came back to. */
	public static function added_dialog(): void {
		$name = WC()->session ? (string) WC()->session->get( self::ADDED, '' ) : '';
		if ( '' === $name || ( function_exists( 'is_cart' ) && is_cart() ) ) {
			return;
		}
		WC()->session->set( self::ADDED, '' );
		?>
		<dialog class="rk-added" data-rk-added aria-labelledby="rk-added-title">
			<form method="dialog" class="rk-added__box">
				<p class="rk-added__icon" aria-hidden="true">✓</p>
				<h2 class="rk-added__title" id="rk-added-title">
					<?php
					/* translators: %s: product name */
					echo esc_html( sprintf( __( '„%s“ is in your cart', 'reklamo-core' ), $name ) );
					?>
				</h2>
				<p class="rk-added__text"><?php esc_html_e( 'Add more products, or go to the cart to upload your logos and notes to the designer.', 'reklamo-core' ); ?></p>
				<div class="rk-added__actions">
					<a class="btn btn--primary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Go to the cart', 'reklamo-core' ); ?></a>
					<button type="submit" class="btn btn--outline" value="close" autofocus><?php esc_html_e( 'Continue shopping', 'reklamo-core' ); ?></button>
				</div>
			</form>
		</dialog>
		<script>
			( function () {
				var d = document.querySelector( '[data-rk-added]' );
				if ( d && d.showModal ) { d.showModal(); } else if ( d ) { d.setAttribute( 'open', '' ); }
				if ( d ) { d.addEventListener( 'click', function ( e ) { if ( e.target === d ) { d.close(); } } ); }
			} )();
		</script>
		<?php
	}

	/** Plain text only; the block cart escapes HTML. */
	public static function display_item_data( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item['reklamo'] ) ) {
			return $item_data;
		}
		$file        = Reklamo_Storage::by_token( $cart_item['reklamo']['file_token'] );
		$item_data[] = array(
			'key'     => __( 'Logo', 'reklamo-core' ),
			'display' => $file ? $file->orig_name : '—',
		);
		if ( ! empty( $cart_item['reklamo']['note'] ) ) {
			$item_data[] = array(
				'key'     => __( 'Note to designer', 'reklamo-core' ),
				'display' => $cart_item['reklamo']['note'],
			);
		}
		return $item_data;
	}

	/* ---------------------------------------------------------------- the cart */

	/** WooCommerce's checkout takes payment details we never ask for: the cart sends the request itself. */
	public static function checkout_to_cart(): void {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url() ) {
			wp_safe_redirect( self::url() );
			exit;
		}
	}

	/** The cart page shows our cart, whatever blocks its content holds. */
	public static function cart_page_content( $content ) {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		return self::render_cart();
	}

	/** @return array<string,array> cart item key => item, our products only */
	private static function lines(): array {
		$lines = array();
		foreach ( WC()->cart ? WC()->cart->get_cart() : array() as $key => $item ) {
			if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
				$lines[ $key ] = $item;
			}
		}
		return $lines;
	}

	/** Price of a cart line with VAT: the price per piece (or per set) times its quantity. */
	public static function line_total( array $line ): float {
		return (float) wc_get_price_to_display( $line['data'] ) * max( 1, (int) ( $line['quantity'] ?? 1 ) );
	}

	private static function logo_by_token( string $token ): ?object {
		return '' !== $token ? Reklamo_Storage::unclaimed_by_token( $token, 'logo', self::LOGO_AGE ) : null;
	}

	/**
	 * The customer's logos, kept in the session: each a file, a note for the designer, and
	 * the cart lines it is for. Lines no longer in the cart drop out.
	 *
	 * @return array<string,array{token:string,note:string,items:string[]}>
	 */
	private static function logos(): array {
		$logos = WC()->session ? (array) WC()->session->get( self::LOGOS, array() ) : array();
		$keys  = array_keys( self::lines() );
		foreach ( $logos as $id => $logo ) {
			$logos[ $id ] = array(
				'token' => (string) ( $logo['token'] ?? '' ),
				'note'  => (string) ( $logo['note'] ?? '' ),
				'items' => array_values( array_intersect( (array) ( $logo['items'] ?? array() ), $keys ) ),
			);
		}
		return $logos;
	}

	private static function save_logos( array $logos ): void {
		WC()->session->set( self::LOGOS, $logos );
	}

	/**
	 * What each cart line ends up with: the first logo marked for it.
	 *
	 * @return array<string,array{id:string,file:?object,note:string}> cart key => its logo, for the lines that have one
	 */
	private static function assignments( array $logos ): array {
		$out = array();
		foreach ( $logos as $id => $logo ) {
			foreach ( $logo['items'] as $key ) {
				if ( ! isset( $out[ $key ] ) ) {
					$out[ $key ] = array(
						'id'   => (string) $id,
						'file' => self::logo_by_token( $logo['token'] ),
						'note' => trim( $logo['note'] ),
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Every product needs a logo and a note before the next step.
	 *
	 * @return string[] what is missing, one line per product
	 */
	private static function missing( array $lines, array $assigned, array $logos = array() ): array {
		$missing = array();
		$marks   = array_count_values( array_merge( array(), ...array_values( array_map( static fn( $l ) => $l['items'], $logos ) ) ) );
		foreach ( $lines as $key => $line ) {
			$name = $line['data']->get_name();
			$a    = $assigned[ $key ] ?? null;
			if ( ( $marks[ $key ] ?? 0 ) > 1 ) {
				/* translators: %s: product name */
				$missing[] = sprintf( __( '%s: it is marked under more than one logo — leave it under one.', 'reklamo-core' ), $name );
			} elseif ( ! $a ) {
				/* translators: %s: product name */
				$missing[] = sprintf( __( '%s: no logo is marked for this product.', 'reklamo-core' ), $name );
			} elseif ( ! $a['file'] ) {
				/* translators: %s: product name */
				$missing[] = sprintf( __( '%s: its logo is not uploaded yet.', 'reklamo-core' ), $name );
			} elseif ( '' === $a['note'] ) {
				/* translators: %s: product name */
				$missing[] = sprintf( __( '%s: its note to the designer is empty.', 'reklamo-core' ), $name );
			}
		}
		return $missing;
	}

	private static function step(): string {
		return WC()->session && 'details' === WC()->session->get( self::STEP ) ? 'details' : 'logos';
	}

	public static function render_cart(): string {
		$state    = Reklamo_Request::flash();
		$errors   = $state['errors'] ?? array();
		$values   = $state['values'] ?? array();
		$lines    = self::lines();
		$logos    = self::logos();
		$assigned = self::assignments( $logos );
		$missing  = self::missing( $lines, $assigned, $logos );
		$step     = $lines && ! $missing ? self::step() : 'logos';
		$max      = (int) Reklamo_Settings::get( 'note_max', '300' );
		if ( ! $logos ) {
			// A first, empty logo marked for every product: the common case is one logo for all.
			$logos = array(
				'new' => array(
					'token' => '',
					'note'  => '',
					'items' => array_keys( $lines ),
				),
			);
		}
		self::assets();
		ob_start();
		?>
		<div class="rk-cart" data-step="<?php echo esc_attr( $step ); ?>">
			<?php
			if ( function_exists( 'wc_print_notices' ) ) {
				wc_print_notices();
			}
			?>
			<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a message only. ?>
				<div class="woocommerce-message" role="status"><?php esc_html_e( 'The cart is empty now.', 'reklamo-core' ); ?></div>
			<?php endif; ?>
			<?php if ( $errors ) : ?>
				<div class="rq-errors" role="alert"><ul>
					<?php foreach ( $errors as $e ) : ?>
						<li><?php echo esc_html( $e ); ?></li>
					<?php endforeach; ?>
				</ul></div>
			<?php endif; ?>

			<?php if ( ! $lines ) : ?>
				<div class="rk-cart__empty">
					<p><?php esc_html_e( 'Your cart is empty. Add the products and packages you want; then, here, you upload your logo and a note to the designer for each of them.', 'reklamo-core' ); ?></p>
					<p><a class="btn btn--primary" href="<?php echo esc_url( (string) get_permalink( wc_get_page_id( 'shop' ) ) ); ?>"><?php esc_html_e( 'Browse the packages', 'reklamo-core' ); ?></a></p>
				</div>
			<?php else : ?>
				<ol class="rk-cart__steps">
					<li class="<?php echo 'logos' === $step ? 'is-current' : 'is-done'; ?>"><?php esc_html_e( 'Logos and notes', 'reklamo-core' ); ?></li>
					<li class="<?php echo 'details' === $step ? 'is-current' : ''; ?>"><?php esc_html_e( 'Your details and sending', 'reklamo-core' ); ?></li>
				</ol>

				<form class="rq-form rk-cart__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( self::url() ); ?>" novalidate data-rk-cart
					data-msg-unmarked="<?php /* translators: %s: product name */ echo esc_attr( __( '%s: not marked under any logo.', 'reklamo-core' ) ); ?>"
					data-msg-nofile="<?php /* translators: 1: product name, 2: logo title such as "Logo 2" */ echo esc_attr( __( '%1$s: the file of %2$s is not uploaded yet.', 'reklamo-core' ) ); ?>"
					data-msg-nonote="<?php /* translators: 1: product name, 2: logo title such as "Logo 2" */ echo esc_attr( __( '%1$s: the note of %2$s is empty.', 'reklamo-core' ) ); ?>">
					<?php wp_nonce_field( self::CART_NONCE, '_rk_cart_nonce' ); ?>
					<div class="rq-hp" aria-hidden="true" style="position:absolute;left:-9999px"><label><?php esc_html_e( 'Website', 'reklamo-core' ); ?> <input type="text" name="rq_website" tabindex="-1" autocomplete="off"></label></div>

					<ul class="rk-cart__items">
						<?php foreach ( $lines as $key => $line ) : ?>
							<?php
							$product = $line['data'];
							$a       = $assigned[ $key ] ?? null;
							$ok      = $a && $a['file'] && '' !== $a['note'];
							?>
							<li class="rk-cart__item<?php echo $ok ? ' is-ready' : ''; ?>" data-cart-item="<?php echo esc_attr( $key ); ?>">
								<a class="rk-cart__thumb" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes. ?></a>
								<div class="rk-cart__body">
									<div class="rk-cart__head">
										<h3 class="rk-cart__name"><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
										<span class="rk-cart__price"><?php echo wp_kses_post( wc_price( self::line_total( $line ) ) ); ?></span>
									</div>
									<?php $variant = wc_get_formatted_cart_item_data( $line, true ); ?>
									<?php if ( '' !== trim( $variant ) ) : ?>
										<p class="rk-cart__variant" data-cart-variant><?php echo esc_html( trim( str_replace( "\n", ' · ', $variant ) ) ); ?></p>
									<?php endif; ?>
									<?php if ( Reklamo_Product::by_the_piece( $product ) ) : ?>
										<?php $min = Reklamo_Product::min_qty( $product ); ?>
										<p class="rk-cart__qty">
											<?php if ( 'logos' === $step ) : ?>
												<label>
													<?php esc_html_e( 'Quantity', 'reklamo-core' ); ?>
													<input type="number" name="qty[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $line['quantity'] ); ?>" min="<?php echo esc_attr( (string) $min ); ?>" step="1" inputmode="numeric" data-cart-qty>
												</label>
											<?php else : ?>
												<?php esc_html_e( 'Quantity', 'reklamo-core' ); ?>: <strong><?php echo esc_html( (string) $line['quantity'] ); ?></strong>
											<?php endif; ?>
											<span class="rk-cart__unit">
												<?php
												/* translators: %s: price per piece */
												echo wp_kses_post( sprintf( __( '%s per piece', 'reklamo-core' ), wc_price( (float) wc_get_price_to_display( $product ) ) ) );
												if ( $min > 1 ) {
													/* translators: %d: minimum quantity */
													echo ' · ' . esc_html( sprintf( __( 'minimum %d', 'reklamo-core' ), $min ) );
												}
												?>
											</span>
										</p>
									<?php endif; ?>
									<p class="rk-cart__logo<?php echo $ok ? '' : ' is-missing'; ?>" data-cart-status data-ready-text="<?php esc_attr_e( 'Logo and note chosen ✓', 'reklamo-core' ); ?>">
										<?php if ( $ok ) : ?>
											<?php esc_html_e( 'Logo', 'reklamo-core' ); ?>: <strong data-cart-logo><?php echo esc_html( Reklamo_Storage::describe( $a['file'] ) ); ?></strong>
											<br><?php esc_html_e( 'Note', 'reklamo-core' ); ?>: <span data-cart-note><?php echo esc_html( $a['note'] ); ?></span>
										<?php else : ?>
											<?php esc_html_e( 'Needs a logo and a note — mark it under a logo below.', 'reklamo-core' ); ?>
										<?php endif; ?>
									</p>
									<?php if ( 'logos' === $step ) : ?>
										<button type="submit" class="rk-cart__remove" name="cart_action" value="remove:<?php echo esc_attr( $key ); ?>" formnovalidate><?php esc_html_e( 'Remove', 'reklamo-core' ); ?></button>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>

					<div class="rk-cart__summary">
						<p class="rk-cart__total"><?php esc_html_e( 'Total', 'reklamo-core' ); ?>: <strong><?php echo wp_kses_post( wc_price( array_sum( array_map( array( __CLASS__, 'line_total' ), $lines ) ) ) ); ?></strong> <span><?php esc_html_e( 'incl. VAT', 'reklamo-core' ); ?></span></p>
						<a class="rk-cart__more" href="<?php echo esc_url( (string) get_permalink( wc_get_page_id( 'shop' ) ) ); ?>"><?php esc_html_e( 'Continue shopping', 'reklamo-core' ); ?></a>
						<button type="submit" class="rk-cart__clear" name="cart_action" value="clear" formnovalidate data-rk-confirm="<?php esc_attr_e( 'Remove every product, logo and note from the cart?', 'reklamo-core' ); ?>"><?php esc_html_e( 'Empty the cart', 'reklamo-core' ); ?></button>
					</div>

					<?php if ( 'logos' === $step ) : ?>
						<h2 class="rk-cart__title"><?php esc_html_e( 'Logos and notes to the designer', 'reklamo-core' ); ?></h2>
						<p class="rk-cart__hint"><?php esc_html_e( 'Upload a logo, write what the designer should do with it, and mark the products it is for. Different products need a different logo? Add another one.', 'reklamo-core' ); ?></p>
						<div class="rk-logos">
							<?php $n = 0; ?>
							<?php foreach ( $logos as $id => $logo ) : ?>
								<?php
								++$n;
								$file = self::logo_by_token( $logo['token'] );
								$uid  = 'rk-logo-' . sanitize_key( (string) $id );
								?>
								<fieldset class="rk-logo" data-logo="<?php echo esc_attr( (string) $id ); ?>">
									<legend class="rk-logo__title">
										<?php
										/* translators: %d: logo number */
										echo esc_html( sprintf( __( 'Logo %d', 'reklamo-core' ), $n ) );
										?>
									</legend>
									<p class="rk-logo__warn" data-rk-nofile hidden><?php esc_html_e( 'Upload a file for this logo — the products marked below have no logo yet.', 'reklamo-core' ); ?></p>
									<?php if ( $file ) : ?>
										<p class="rk-cart__logo" data-logo-file><?php esc_html_e( 'File', 'reklamo-core' ); ?>: <strong><?php echo esc_html( Reklamo_Storage::describe( $file ) ); ?></strong></p>
									<?php endif; ?>
									<?php echo self::drop_zone( $uid . '-file', 'reklamo_logo_file[' . $id . ']', $file ? __( 'Replace the logo', 'reklamo-core' ) : __( 'Upload the logo', 'reklamo-core' ), false, 'logos[' . $id . '][file_token]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
									<div class="rq-field rq-field--note">
										<label for="<?php echo esc_attr( $uid ); ?>-note"><?php esc_html_e( 'Note to the designer', 'reklamo-core' ); ?> <span class="rk-required" aria-hidden="true">*</span></label>
										<textarea id="<?php echo esc_attr( $uid ); ?>-note" name="logos[<?php echo esc_attr( (string) $id ); ?>][note]" rows="2" maxlength="<?php echo esc_attr( (string) $max ); ?>" data-rq-counter required placeholder="<?php esc_attr_e( 'Example: gold logo, centered on the front cover of the notebook and engraved on the pen.', 'reklamo-core' ); ?>"><?php echo esc_textarea( $logo['note'] ); ?></textarea>
										<span class="rq-counter"><span data-rq-count>0</span>/<?php echo esc_html( (string) $max ); ?></span>
									</div>
									<div class="rk-logo__items">
										<p class="rk-logo__label"><?php esc_html_e( 'For these products:', 'reklamo-core' ); ?></p>
										<?php foreach ( $lines as $key => $line ) : ?>
											<?php
											// A product already marked under another logo is left out here; unticking it there brings it back.
											$taken = ! in_array( $key, $logo['items'], true ) && in_array( $key, array_merge( array(), ...array_values( array_map( static fn( $l ) => $l['items'], array_diff_key( $logos, array( $id => 1 ) ) ) ) ), true );
											?>
											<label class="rk-logo__item"<?php echo $taken ? ' hidden' : ''; ?>>
												<input type="checkbox" name="logos[<?php echo esc_attr( (string) $id ); ?>][items][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $logo['items'], true ) ); ?>>
												<span><?php echo esc_html( $line['data']->get_name() ); ?></span>
											</label>
										<?php endforeach; ?>
										<p class="rk-logo__none" data-rk-none hidden><?php esc_html_e( 'Every product already has a logo. Untick one under another logo to move it here.', 'reklamo-core' ); ?></p>
									</div>
									<?php if ( 'new' !== $id ) : ?>
										<button type="submit" class="rk-cart__remove" name="cart_action" value="drop:<?php echo esc_attr( (string) $id ); ?>" formnovalidate><?php esc_html_e( 'Remove this logo', 'reklamo-core' ); ?></button>
									<?php endif; ?>
								</fieldset>
							<?php endforeach; ?>
						</div>
						<div class="rk-cart__actions">
							<button type="submit" class="rk-cart__update" name="cart_action" value="add_logo" formnovalidate>+ <?php esc_html_e( 'Add another logo', 'reklamo-core' ); ?></button>
							<button type="submit" class="rk-cart__update" name="cart_action" value="save" formnovalidate><?php esc_html_e( 'Save changes', 'reklamo-core' ); ?></button>
						</div>
						<div class="rq-submit">
							<button type="submit" class="rq-button" name="cart_action" value="continue" data-rk-continue><?php esc_html_e( 'Continue to your details', 'reklamo-core' ); ?> <span aria-hidden="true">→</span></button>
							<p class="rk-cart__blocked" data-rk-blocked><?php esc_html_e( 'Every product needs a logo and a note before you can continue.', 'reklamo-core' ); ?></p>
						</div>
					<?php else : ?>
						<p><button type="submit" class="rk-cart__back" name="cart_action" value="back" formnovalidate>← <?php esc_html_e( 'Back to logos and notes', 'reklamo-core' ); ?></button></p>
						<h2 class="rk-cart__title"><?php esc_html_e( 'Your details', 'reklamo-core' ); ?></h2>
						<?php echo Reklamo_Request::contact_fields( $values, 'rk-contact' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
						<div class="rq-submit">
							<button type="submit" class="rq-button" name="cart_action" value="send" data-rk-send><?php esc_html_e( 'Send and request the mockups', 'reklamo-core' ); ?> <span aria-hidden="true">→</span></button>
							<p class="rk-cart__blocked" data-rk-send-blocked hidden><?php esc_html_e( 'Fill in your name, email and phone and tick both boxes above to send the request.', 'reklamo-core' ); ?></p>
							<p class="rq-nopay"><?php esc_html_e( 'No payment is taken at this stage. Each product gets its own mockup; we ask for the deposit once you have approved them all.', 'reklamo-core' ); ?></p>
						</div>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * A logo from the posted form: a finished chunked upload (its token) or a plain file.
	 *
	 * @return string|WP_Error|null Token; error; null when nothing was sent.
	 */
	private static function posted_logo( string $token, ?array $file ) {
		$prestored = '' !== $token ? Reklamo_Storage::unclaimed_by_token( $token, 'logo' ) : null;
		if ( $prestored ) {
			return $prestored->token;
		}
		if ( ! $file || UPLOAD_ERR_NO_FILE === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return null;
		}
		$stored = Reklamo_Storage::store_upload( $file, 'logo', Reklamo_Storage::LOGO_EXTENSIONS );
		return is_wp_error( $stored ) ? $stored : $stored['token'];
	}

	/** Save the posted logos: files, notes, marked products. Returns errors. */
	private static function apply_edits(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nonce checked by the caller; fields sanitised below, files validated in store_upload().
		$posted = isset( $_POST['logos'] ) && is_array( $_POST['logos'] ) ? wp_unslash( $_POST['logos'] ) : array();
		$files  = isset( $_FILES['reklamo_logo_file'] ) && is_array( $_FILES['reklamo_logo_file']['name'] ?? null ) ? $_FILES['reklamo_logo_file'] : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$errors = array();
		$max    = (int) Reklamo_Settings::get( 'note_max', '300' );
		$keys   = array_keys( self::lines() );
		$logos  = self::logos();
		$n      = 0;
		$qtys   = isset( $_POST['qty'] ) && is_array( $_POST['qty'] ) ? wp_unslash( $_POST['qty'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by the caller; cast to int below.
		foreach ( self::lines() as $key => $line ) {
			if ( ! isset( $qtys[ $key ] ) || ! Reklamo_Product::by_the_piece( $line['data'] ) ) {
				continue;
			}
			$want = absint( $qtys[ $key ] );
			$min  = Reklamo_Product::min_qty( $line['data'] );
			if ( $want < $min ) {
				/* translators: 1: product name, 2: minimum quantity */
				$errors[] = sprintf( __( '%1$s: the minimum quantity is %2$d pieces.', 'reklamo-core' ), $line['data']->get_name(), $min );
				$want     = $min;
			}
			if ( $want !== (int) $line['quantity'] ) {
				WC()->cart->set_quantity( $key, $want );
			}
		}
		WC()->cart->set_session();
		foreach ( $posted as $id => $in ) {
			++$n;
			if ( ! is_array( $in ) ) {
				continue;
			}
			$id   = sanitize_key( (string) $id );
			$note = sanitize_textarea_field( (string) ( $in['note'] ?? '' ) );
			if ( mb_strlen( $note ) > $max ) {
				/* translators: 1: logo number, 2: character limit */
				$errors[] = sprintf( __( 'Logo %1$d: the note is too long (maximum %2$d characters).', 'reklamo-core' ), $n, $max );
				$note     = mb_substr( $note, 0, $max );
			}
			$file = null;
			if ( $files && UPLOAD_ERR_NO_FILE !== (int) ( $files['error'][ $id ] ?? UPLOAD_ERR_NO_FILE ) ) {
				$file = array();
				foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $f ) {
					$file[ $f ] = $files[ $f ][ $id ] ?? '';
				}
			}
			$token = self::posted_logo( sanitize_text_field( (string) ( $in['file_token'] ?? '' ) ), $file );
			if ( is_wp_error( $token ) ) {
				/* translators: 1: logo number, 2: error message */
				$errors[] = sprintf( __( 'Logo %1$d: %2$s', 'reklamo-core' ), $n, $token->get_error_message() );
				$token    = null;
			}
			$items = array_values( array_intersect( array_map( 'sanitize_text_field', (array) ( $in['items'] ?? array() ) ), $keys ) );
			$token = $token ? $token : (string) ( $logos[ $id ]['token'] ?? '' );
			if ( 'new' === $id ) {
				if ( '' === $token && '' === $note && ! $items ) {
					continue;
				}
				$id = substr( md5( wp_rand() . microtime() ), 0, 8 );
			}
			$logos[ $id ] = array(
				'token' => $token,
				'note'  => $note,
				'items' => $items,
			);
		}
		self::save_logos( $logos );
		return $errors;
	}

	/** The cart form's buttons: remove a product or a logo, add a logo, save, continue, back, empty the cart, send. */
	public static function handle_cart_post(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) || ! isset( $_POST['_rk_cart_nonce'] ) || ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		$back = self::url();
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_rk_cart_nonce'] ) ), self::CART_NONCE ) ) {
			Reklamo_Request::fail( array( __( 'The form has expired. Please try again.', 'reklamo-core' ) ), array(), $back );
		}
		$action = isset( $_POST['cart_action'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_action'] ) ) : 'save';
		$values = Reklamo_Request::contact_values();

		if ( str_starts_with( $action, 'remove:' ) ) {
			self::apply_edits();
			$key = sanitize_key( substr( $action, 7 ) );
			if ( isset( self::lines()[ $key ] ) ) {
				WC()->cart->remove_cart_item( $key );
			}
			wp_safe_redirect( $back );
			exit;
		}
		if ( 'clear' === $action ) {
			WC()->cart->empty_cart();
			WC()->session->set( self::LOGOS, array() );
			WC()->session->set( self::STEP, 'logos' );
			// Not a WooCommerce notice: on the cart page WooCommerce's block hydration clears those before we print them.
			wp_safe_redirect( add_query_arg( 'cleared', '1', $back ) );
			exit;
		}
		if ( 'back' === $action ) {
			WC()->session->set( self::STEP, 'logos' );
			wp_safe_redirect( $back );
			exit;
		}
		if ( 'send' !== $action ) {
			$errors = self::apply_edits();
			$logos  = self::logos();
			if ( str_starts_with( $action, 'drop:' ) ) {
				unset( $logos[ sanitize_key( substr( $action, 5 ) ) ] );
				self::save_logos( $logos );
			} elseif ( 'add_logo' === $action ) {
				$logos[ substr( md5( wp_rand() . microtime() ), 0, 8 ) ] = array(
					'token' => '',
					'note'  => '',
					'items' => array(),
				);
				self::save_logos( $logos );
			} elseif ( 'continue' === $action ) {
				$errors = array_merge( $errors, self::missing( self::lines(), self::assignments( self::logos() ), self::logos() ) );
				if ( ! $errors ) {
					WC()->session->set( self::STEP, 'details' );
				}
			}
			if ( $errors ) {
				Reklamo_Request::fail( $errors, array(), $back );
			}
			wp_safe_redirect( $back );
			exit;
		}

		// Send: the logos are checked again — the cart may have changed in another tab.
		$lines    = self::lines();
		$assigned = self::assignments( self::logos() );
		$missing  = self::missing( $lines, $assigned, self::logos() );
		if ( $missing || ! $lines ) {
			WC()->session->set( self::STEP, 'logos' );
			Reklamo_Request::fail( $missing ? $missing : array( __( 'Your cart is empty.', 'reklamo-core' ) ), $values, $back );
		}
		$errors = array_merge( Reklamo_Request::abuse_errors(), Reklamo_Request::contact_errors( $values ) );
		if ( $errors ) {
			Reklamo_Request::fail( $errors, $values, $back );
		}
		$order_lines = array();
		foreach ( $lines as $key => $line ) {
			$order_lines[] = array(
				'product' => $line['data'],
				'qty'     => max( 1, (int) $line['quantity'] ),
				'token'   => $assigned[ $key ]['file']->token,
				'note'    => $assigned[ $key ]['note'],
			);
		}
		$order = Reklamo_Request::create_order( $order_lines, $values, 'reklamo_cart' );
		if ( ! $order ) {
			Reklamo_Request::fail( array( __( 'The request could not be saved. Please try again or contact us.', 'reklamo-core' ) ), $values, $back );
		}
		WC()->cart->empty_cart();
		WC()->session->set( self::LOGOS, array() );
		WC()->session->set( self::STEP, 'logos' );
		Reklamo_Request::redirect_to_order( $order );
	}

	/* ------------------------------------------------------ WooCommerce checkout */

	/** A Store API checkout still lands its lines here; ours sends from the cart. */
	public static function create_order_line_item( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( empty( $values['reklamo'] ) ) {
			return;
		}
		$item->add_meta_data( self::META_FILE, $values['reklamo']['file_token'], true );
		$item->add_meta_data( Reklamo_Items::META_STATE, Reklamo_Items::WAITING, true );
		if ( ! empty( $values['reklamo']['note'] ) ) {
			$item->add_meta_data( Reklamo_Items::META_NOTE, $values['reklamo']['note'], true );
			$item->add_meta_data( __( 'Note to designer', 'reklamo-core' ), $values['reklamo']['note'], true );
		}
	}

	/** @param int|WC_Order $order_or_id */
	public static function claim_files( $order_or_id ): void {
		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item_id => $item ) {
			$token = (string) $item->get_meta( self::META_FILE );
			if ( $token ) {
				Reklamo_Storage::claim( $token, $order->get_id(), (int) $item_id );
			}
		}
	}

	public static function hide_token_meta( array $hidden ): array {
		$hidden[] = self::META_FILE;
		return $hidden;
	}

	/** Download link under the line item on the admin order screen (HPOS and legacy). */
	public static function admin_item_file( int $item_id, $item ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! is_admin() || ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$token = (string) $item->get_meta( self::META_FILE );
		$file  = $token ? Reklamo_Storage::by_token( $token ) : null;
		if ( ! $file ) {
			return;
		}
		printf(
			'<div class="reklamo-item-file"><strong>%s:</strong> <a href="%s">%s</a></div>',
			esc_html__( 'Logo', 'reklamo-core' ),
			esc_url( Reklamo_Storage::download_url( (int) $file->id ) ),
			esc_html( Reklamo_Storage::describe( $file ) )
		);
	}
}

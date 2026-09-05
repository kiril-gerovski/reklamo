<?php
/**
 * Logo upload + designer note on the product page, carried through the cart onto the
 * order LINE ITEM (a logo belongs to a package, not to an order — two packages in one
 * order need two logos), and claimed for the order at checkout.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Cart {

	const NOTE_MAX  = 2000;
	const NONCE     = 'reklamo_add_to_cart';
	const META_FILE = '_reklamo_file_token';

	/** Token of the file stored during validation, handed to add_cart_item_data. */
	private static string $pending_token = '';

	public static function init(): void {
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_fields' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'create_order_line_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'claim_files' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'claim_files' ), 10, 1 );
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'admin_item_file' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect_to_checkout' ) );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hide_token_meta' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets(): void {
		if ( ! is_product() && ! is_front_page() && ! is_page() ) {
			return;
		}
		$path = REKLAMO_PATH . 'assets/css/reklamo.css';
		wp_enqueue_style( 'reklamo', REKLAMO_URL . 'assets/css/reklamo.css', array(), file_exists( $path ) ? (string) filemtime( $path ) : REKLAMO_VERSION );
		Reklamo_Request::enqueue_uploader();
	}

	public static function render_fields(): void {
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
			return;
		}
		$accept = '.' . implode( ',.', Reklamo_Storage::LOGO_EXTENSIONS );
		?>
		<div class="reklamo-fields">
			<p class="reklamo-field reklamo-field--logo">
				<label for="reklamo_logo">
					<?php esc_html_e( 'Your logo', 'reklamo-core' ); ?>
					<span class="reklamo-hint"><?php echo esc_html( strtoupper( implode( ', ', Reklamo_Storage::LOGO_EXTENSIONS ) ) ); ?></span>
				</label>
				<input type="file" id="reklamo_logo" name="reklamo_logo" accept="<?php echo esc_attr( $accept ); ?>" required>
			</p>
			<p class="reklamo-field reklamo-field--note">
				<label for="reklamo_note"><?php esc_html_e( 'Instructions for the designer (optional)', 'reklamo-core' ); ?></label>
				<textarea id="reklamo_note" name="reklamo_note" rows="3" maxlength="<?php echo esc_attr( (string) self::NOTE_MAX ); ?>" placeholder="<?php esc_attr_e( 'Example: gold logo, centered on the front cover of the notebook and engraved on the pen.', 'reklamo-core' ); ?>"></textarea>
			</p>
			<p class="reklamo-nopay-hint"><?php esc_html_e( 'No payment is taken at this stage.', 'reklamo-core' ); ?></p>
			<?php wp_nonce_field( self::NONCE, 'reklamo_nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Store the upload here so a rejected file blocks the add-to-cart with a notice.
	 *
	 * @param bool $passed     Validation so far.
	 * @param int  $product_id Product being added.
	 * @param int  $quantity   Quantity.
	 */
	public static function validate( bool $passed, int $product_id, int $quantity ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! isset( $_POST['reklamo_nonce'] ) ) {
			// Not our form (e.g. shop-loop add-to-cart): the package needs a logo, send them to the product page.
			$product = wc_get_product( $product_id );
			if ( $product ) {
				wc_add_notice(
					sprintf(
						/* translators: %s: product page link */
						__( 'Please upload your logo on the product page: %s', 'reklamo-core' ),
						'<a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a>'
					),
					'error'
				);
			}
			return false;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['reklamo_nonce'] ) ), self::NONCE ) ) {
			wc_add_notice( __( 'The form has expired. Please reload the page and try again.', 'reklamo-core' ), 'error' );
			return false;
		}
		$note = isset( $_POST['reklamo_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reklamo_note'] ) ) : '';
		if ( mb_strlen( $note ) > self::NOTE_MAX ) {
			/* translators: %d: character limit */
			wc_add_notice( sprintf( __( 'The note is too long (maximum %d characters).', 'reklamo-core' ), self::NOTE_MAX ), 'error' );
			return false;
		}
		$token     = isset( $_POST['reklamo_file_token'] ) ? sanitize_text_field( wp_unslash( $_POST['reklamo_file_token'] ) ) : '';
		$prestored = '' !== $token ? Reklamo_Storage::unclaimed_by_token( $token, 'logo' ) : null;
		if ( $prestored ) {
			self::$pending_token = $prestored->token;
			return $passed;
		}
		if ( empty( $_FILES['reklamo_logo'] ) || UPLOAD_ERR_NO_FILE === (int) ( $_FILES['reklamo_logo']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			wc_add_notice( __( 'Please upload your logo file.', 'reklamo-core' ), 'error' );
			return false;
		}
		$stored = Reklamo_Storage::store_upload( $_FILES['reklamo_logo'], 'logo', Reklamo_Storage::LOGO_EXTENSIONS ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated inside store_upload().
		if ( is_wp_error( $stored ) ) {
			wc_add_notice( $stored->get_error_message(), 'error' );
			return false;
		}
		self::$pending_token = $stored['token'];
		return $passed;
	}

	public static function add_cart_item_data( array $data, int $product_id, int $variation_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( '' === self::$pending_token ) {
			return $data;
		}
		$data['reklamo'] = array(
			'file_token' => self::$pending_token,
			'note'       => isset( $_POST['reklamo_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reklamo_note'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in validate().
		);
		// Two different logos for the same product must not merge into quantity 2.
		$data['unique_key']  = md5( microtime() . wp_rand() );
		self::$pending_token = '';
		return $data;
	}

	/** Cart, checkout (classic and blocks) — plain text only; the block cart escapes HTML. */
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

	/** Fires for classic and Store API checkout alike. */
	public static function create_order_line_item( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( empty( $values['reklamo'] ) ) {
			return;
		}
		$item->add_meta_data( self::META_FILE, $values['reklamo']['file_token'], true );
		if ( ! empty( $values['reklamo']['note'] ) ) {
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

	/** The package flow has one step after upload: straight to checkout. */
	public static function redirect_to_checkout( $url ) {
		if ( isset( $_POST['reklamo_nonce'] ) && wc_notice_count( 'error' ) === 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in validate().
			return wc_get_checkout_url();
		}
		return $url;
	}
}

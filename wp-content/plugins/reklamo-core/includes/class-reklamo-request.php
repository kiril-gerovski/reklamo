<?php
/**
 * The request form (request page and homepage): package → "Add to cart". The
 * contact fields, consents and order creation live here too, shared with the cart, which
 * sends the request (Reklamo_Cart).
 *
 * Public, logged-out-writable, and it creates orders — so: nonce, honeypot, per-IP
 * rate limit, strict validation, and the file goes through Reklamo_Storage.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Request {

	const RATE_LIMIT = 10; // requests per IP per hour.
	const PAGE_OPT   = 'reklamo_request_page_id';

	public static function init(): void {
		add_shortcode( 'reklamo_request_form', array( __CLASS__, 'shortcode' ) );
	}

	/** URL of the request page, optionally pre-selecting a package. */
	public static function url( $product = null ): string {
		$page_id = (int) get_option( self::PAGE_OPT );
		$base    = $page_id ? get_permalink( $page_id ) : home_url( '/kachi-logo/' );
		if ( $product instanceof WC_Product ) {
			return add_query_arg( 'paket', $product->get_slug(), $base );
		}
		return $base;
	}

	/** Product chosen via ?paket=<slug> (or ?product_id=), if any. */
	public static function current_product(): ?WC_Product {
		$slug = isset( $_GET['paket'] ) ? sanitize_title( wp_unslash( $_GET['paket'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'product' );
			if ( $post ) {
				$product = wc_get_product( $post->ID );
				if ( $product && $product->is_purchasable() ) {
					return $product;
				}
			}
		}
		return null;
	}

	/** @return WC_Product[] purchasable packages, in catalogue order — not the products sold by the piece, whose quantity is chosen on their page */
	public static function packages(): array {
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 20,
				'orderby' => 'menu_order',
				'order'   => 'ASC',
			)
		);
		return array_values( array_filter( $products, static fn( $p ) => ! Reklamo_Product::by_the_piece( $p ) ) );
	}

	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'compact' => '0' ), $atts );
		return self::render( array( 'compact' => '1' === (string) $atts['compact'] ) );
	}

	/**
	 * Render the form: the package and "Add to cart" — the logo, the note, the contact details
	 * and the request itself follow in the cart (Reklamo_Cart). Full variant for the request
	 * page; compact for the homepage, with a package picker.
	 *
	 * @param array{compact?:bool, product?:WC_Product|null} $args Options.
	 */
	public static function render( array $args = array() ): string {
		$compact = ! empty( $args['compact'] );
		if ( ! $compact ) {
			return self::render_design( $args['product'] ?? self::current_product() );
		}
		$product = $args['product'] ?? self::current_product();
		$uid     = wp_unique_id( 'rq' );
		$picked  = isset( $_POST['reklamo_nonce'], $_POST['add-to-cart'] ) ? absint( $_POST['add-to-cart'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- redisplay only.

		ob_start();
		?>
		<form class="rq-form <?php echo $compact ? 'rq-form--compact' : 'rq-form--full'; ?>" method="post" action="<?php echo esc_url( self::current_url() ); ?>">
			<?php wp_nonce_field( Reklamo_Cart::NONCE, 'reklamo_nonce' ); ?>

			<?php if ( isset( $_POST['reklamo_nonce'] ) && function_exists( 'wc_get_notices' ) && wc_notice_count( 'error' ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Missing -- redisplay only. ?>
				<div class="rq-errors" role="alert">
					<ul>
						<?php foreach ( wc_get_notices( 'error' ) as $e ) : ?>
							<li><?php echo wp_kses_post( is_array( $e ) ? $e['notice'] : $e ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php wc_clear_notices(); ?>
			<?php endif; ?>

			<?php if ( $product ) : ?>
				<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( (string) $product->get_id() ); ?>">
			<?php else : ?>
				<div class="rq-field rq-field--package">
					<label for="<?php echo esc_attr( $uid ); ?>-pkg"><?php esc_html_e( 'Package', 'reklamo-core' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-pkg" name="add-to-cart" required>
						<option value=""><?php esc_html_e( 'Choose a package…', 'reklamo-core' ); ?></option>
						<?php foreach ( self::packages() as $p ) : ?>
							<option value="<?php echo esc_attr( (string) $p->get_id() ); ?>" <?php selected( $picked, $p->get_id() ); ?>>
								<?php echo esc_html( $p->get_name() . ' — ' . wp_strip_all_tags( wc_price( (float) $p->get_price() ) ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="rq-submit">
				<button type="submit" class="rq-button"><?php esc_html_e( 'Add to cart', 'reklamo-core' ); ?> <span aria-hidden="true">→</span></button>
				<p class="rq-nopay"><?php esc_html_e( 'You upload your logo in the cart — one per product, or one for all of them. No payment is taken at this stage.', 'reklamo-core' ); ?></p>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Every product the request page offers, with its colours and quantities from WooCommerce,
	 * the variation each pair leads to (price and photo) and the product's main photo.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function catalogue(): array {
		$out = array();
		foreach ( self::packages() as $p ) {
			$attrs = array();
			$vars  = array();
			if ( $p instanceof WC_Product_Variable ) {
				foreach ( $p->get_available_variations( 'objects' ) as $v ) {
					if ( $v->is_purchasable() ) {
						$vars[] = array(
							'id'    => $v->get_id(),
							'attrs' => $v->get_variation_attributes(),
							'price' => wp_strip_all_tags( wc_price( (float) wc_get_price_to_display( $v ) ) ),
							'image' => (string) wp_get_attachment_image_url( (int) $v->get_image_id(), 'woocommerce_single' ),
						);
					}
				}
				$defaults = $p->get_default_attributes();
				foreach ( $p->get_variation_attributes() as $attribute => $values ) {
					$name  = 'attribute_' . sanitize_title( $attribute );
					$terms = taxonomy_exists( $attribute ) ? wc_get_product_terms( $p->get_id(), $attribute, array( 'fields' => 'all' ) ) : array();
					$opts  = array();
					foreach ( $terms ? $terms : $values as $o ) {
						$slug = $o instanceof WP_Term ? $o->slug : (string) $o;
						// Only values a purchasable variation has: a size without a price is not offered.
						if ( in_array( $slug, $values, true ) && array_filter( $vars, static fn( $x ) => in_array( (string) ( $x['attrs'][ $name ] ?? '' ), array( '', $slug ), true ) ) ) {
							$opts[] = array(
								'value' => $slug,
								'label' => $o instanceof WP_Term ? $o->name : $slug,
							);
						}
					}
					$attrs[] = array(
						'name'    => $name,
						'label'   => wc_attribute_label( $attribute, $p ),
						'options' => $opts,
						'default' => (string) ( $defaults[ sanitize_title( $attribute ) ] ?? '' ),
					);
				}
			}
			$out[] = array(
				'id'    => $p->get_id(),
				'image' => (string) wp_get_attachment_image_url( (int) $p->get_image_id(), 'woocommerce_single' ),
				'price' => wp_strip_all_tags( wc_price( (float) wc_get_price_to_display( $p ) ) ),
				'attrs' => $attrs,
				'vars'  => $vars,
			);
		}
		return $out;
	}

	/**
	 * The request page: a product, its colour and quantity, then either "I have a logo" (the
	 * logo follows in the cart) or an inscription, the print colour and extra instructions.
	 */
	private static function render_design( ?WC_Product $product ): string {
		$uid    = wp_unique_id( 'rk-design' );
		$picked = isset( $_POST['reklamo_nonce'], $_POST['add-to-cart'] ) ? absint( $_POST['add-to-cart'] ) : ( $product ? $product->get_id() : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- redisplay only.
		// phpcs:disable WordPress.Security.NonceVerification -- which tab opens, and the values again after a refused add.
		$again = isset( $_POST['reklamo_nonce'], $_POST['rk_mode'] );
		$mode  = $again ? ( 'text' === sanitize_key( wp_unslash( $_POST['rk_mode'] ) ) ? 'text' : 'logo' ) : ( isset( $_GET['logo'] ) ? 'logo' : 'text' );
		// A package chosen by the link opens with the logo: it takes no inscription.
		$mode   = ! $again && $product && Reklamo_Product::is_package( $product ) ? 'logo' : $mode;
		$text   = $again && isset( $_POST['rk_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rk_text'] ) ) : '';
		$note   = $again && isset( $_POST['rk_note'] ) ? sanitize_text_field( wp_unslash( $_POST['rk_note'] ) ) : '';
		$colour = $again && isset( $_POST['rk_colour'] ) ? sanitize_key( wp_unslash( $_POST['rk_colour'] ) ) : 'black';
		$colour = isset( Reklamo_Cart::PRINT_HEX[ $colour ] ) ? $colour : 'black';
		// phpcs:enable WordPress.Security.NonceVerification
		$max = (int) Reklamo_Settings::get( 'note_max', '300' );
		wp_enqueue_script( 'reklamo-design', REKLAMO_URL . 'assets/js/design.js', array(), (string) filemtime( REKLAMO_PATH . 'assets/js/design.js' ), true );
		self::enqueue_uploader();
		ob_start();
		?>
		<form class="rq-form rq-form--full rk-design" method="post" enctype="multipart/form-data" action="<?php echo esc_url( self::current_url() ); ?>" data-rk-design data-mode="<?php echo esc_attr( $mode ); ?>" data-vat="<?php esc_attr_e( 'incl. VAT', 'reklamo-core' ); ?>"
			data-catalogue="<?php echo esc_attr( (string) wp_json_encode( self::catalogue() ) ); ?>">
			<?php wp_nonce_field( Reklamo_Cart::NONCE, 'reklamo_nonce' ); ?>
			<?php if ( isset( $_POST['reklamo_nonce'] ) && function_exists( 'wc_get_notices' ) && wc_notice_count( 'error' ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Missing -- redisplay only. ?>
				<div class="rq-errors" role="alert">
					<ul>
						<?php foreach ( wc_get_notices( 'error' ) as $e ) : ?>
							<li><?php echo wp_kses_post( is_array( $e ) ? $e['notice'] : $e ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php wc_clear_notices(); ?>
			<?php endif; ?>

			<fieldset class="rk-mode">
				<legend class="screen-reader-text"><?php esc_html_e( 'What do we print?', 'reklamo-core' ); ?></legend>
				<label class="rk-mode__option"><input type="radio" name="rk_mode" value="text" <?php checked( 'text', $mode ); ?>><span><?php esc_html_e( 'An inscription', 'reklamo-core' ); ?></span></label>
				<label class="rk-mode__option"><input type="radio" name="rk_mode" value="logo" <?php checked( 'logo', $mode ); ?>><span><?php esc_html_e( 'I have a logo', 'reklamo-core' ); ?></span></label>
			</fieldset>

			<div class="rq-field">
				<label for="<?php echo esc_attr( $uid ); ?>-product"><?php esc_html_e( 'Product', 'reklamo-core' ); ?> <span class="rk-required" aria-hidden="true">*</span></label>
				<select id="<?php echo esc_attr( $uid ); ?>-product" name="add-to-cart" required data-rk-product-select>
					<option value=""><?php esc_html_e( 'Choose a product…', 'reklamo-core' ); ?></option>
					<?php foreach ( self::packages() as $p ) : ?>
						<?php $is_package = Reklamo_Product::is_package( $p ); ?>
						<option value="<?php echo esc_attr( (string) $p->get_id() ); ?>"<?php echo $is_package ? ' data-package' : ''; ?> <?php selected( $picked, $p->get_id() ); ?>><?php echo esc_html( $p->get_name() ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="rk-design__attrs" data-rk-attrs></div>
			<input type="hidden" name="variation_id" value="" data-rk-variation>
			<p class="rk-design__price" data-rk-price hidden></p>

			<div class="rk-design__logo" data-rk-when="logo"<?php echo 'logo' === $mode ? '' : ' hidden'; ?>>
				<?php echo Reklamo_Cart::drop_zone( $uid . '-logo', 'rk_logo_file', __( 'Upload your logo', 'reklamo-core' ), false, 'rk_logo[file_token]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
				<p class="rk-design__hint"><?php esc_html_e( 'Optional here: you can also upload it later in the cart.', 'reklamo-core' ); ?></p>
			</div>

			<div class="rq-field" data-rk-when="text"<?php echo 'text' === $mode ? '' : ' hidden'; ?>>
				<label for="<?php echo esc_attr( $uid ); ?>-text"><?php esc_html_e( 'Your inscription', 'reklamo-core' ); ?> <span class="rk-required" aria-hidden="true">*</span></label>
				<textarea id="<?php echo esc_attr( $uid ); ?>-text" name="rk_text" rows="3" maxlength="150" placeholder="<?php esc_attr_e( 'e.g. A team with character', 'reklamo-core' ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
				<p class="rk-design__hint"><?php esc_html_e( 'We print the text exactly as written.', 'reklamo-core' ); ?></p>
			</div>

			<fieldset class="rk-print">
				<legend class="rk-print__label"><?php esc_html_e( 'Print colour', 'reklamo-core' ); ?>: <span data-rk-print-chosen><?php echo esc_html( Reklamo_Cart::print_colours()[ $colour ] ); ?></span></legend>
				<div class="rk-print__list">
					<?php foreach ( Reklamo_Cart::print_colours() as $slug => $label ) : ?>
						<label class="rk-option rk-option--swatch" title="<?php echo esc_attr( $label ); ?>">
							<input type="radio" name="rk_colour" value="<?php echo esc_attr( $slug ); ?>" data-label="<?php echo esc_attr( $label ); ?>" <?php checked( $colour, $slug ); ?>>
							<span class="rk-option__swatch rk-print__swatch--<?php echo esc_attr( $slug ); ?>" style="--swatch: <?php echo esc_attr( Reklamo_Cart::PRINT_HEX[ $slug ] ); ?>"></span><span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<div class="rq-field">
				<label for="<?php echo esc_attr( $uid ); ?>-note"><?php esc_html_e( 'Extra instructions', 'reklamo-core' ); ?> <span class="rk-optional"><?php esc_html_e( '(optional)', 'reklamo-core' ); ?></span></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-note" name="rk_note" maxlength="<?php echo esc_attr( (string) $max ); ?>" value="<?php echo esc_attr( $note ); ?>" placeholder="<?php esc_attr_e( 'e.g. a smaller inscription, printed on both sides…', 'reklamo-core' ); ?>">
			</div>

			<div class="rq-submit">
				<button type="submit" class="rq-button"><?php esc_html_e( 'Add to cart', 'reklamo-core' ); ?> <span aria-hidden="true">→</span></button>
				<p class="rq-nopay" data-rk-when="logo"<?php echo 'logo' === $mode ? '' : ' hidden'; ?>><?php esc_html_e( 'The logo is saved with the chosen product. No payment is taken at this stage.', 'reklamo-core' ); ?></p>
				<p class="rq-nopay" data-rk-when="text"<?php echo 'text' === $mode ? '' : ' hidden'; ?>><?php esc_html_e( 'The inscription is saved with the chosen product. No payment is taken at this stage.', 'reklamo-core' ); ?></p>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Name, email, phone and the two consents — shared by the request form and the cart.
	 *
	 * @param array  $values  Previous values after a failed submission.
	 * @param string $uid     Unique prefix for the field IDs.
	 * @param bool   $compact The homepage variant's shorter labels.
	 */
	public static function contact_fields( array $values, string $uid, bool $compact = false ): string {
		$terms   = (int) get_option( 'woocommerce_terms_page_id' );
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		ob_start();
		?>
			<div class="rq-row">
				<div class="rq-field">
					<label for="<?php echo esc_attr( $uid ); ?>-name"><?php $compact ? esc_html_e( 'Name / Company', 'reklamo-core' ) : esc_html_e( 'Your name', 'reklamo-core' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-name" name="rq_name" value="<?php echo esc_attr( $values['name'] ?? '' ); ?>" required autocomplete="name" placeholder="<?php esc_attr_e( 'Ivan Ivanov', 'reklamo-core' ); ?>">
				</div>
				<div class="rq-field">
					<label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Contact email', 'reklamo-core' ); ?></label>
					<input type="email" id="<?php echo esc_attr( $uid ); ?>-email" name="rq_email" value="<?php echo esc_attr( $values['email'] ?? '' ); ?>" required autocomplete="email" placeholder="office@company.bg">
				</div>
				<div class="rq-field">
					<label for="<?php echo esc_attr( $uid ); ?>-phone"><?php esc_html_e( 'Contact phone', 'reklamo-core' ); ?></label>
					<input type="tel" id="<?php echo esc_attr( $uid ); ?>-phone" name="rq_phone" value="<?php echo esc_attr( $values['phone'] ?? '' ); ?>" required autocomplete="tel" placeholder="0888 123 456">
				</div>
			</div>

			<div class="rq-field rq-field--consent">
				<label>
					<input type="checkbox" name="rq_consent" value="1" required <?php checked( ! empty( $values['consent'] ) ); ?>>
					<span>
					<?php
					$terms_link   = $terms ? '<a href="' . esc_url( get_permalink( $terms ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terms and Conditions', 'reklamo-core' ) . '</a>' : esc_html__( 'Terms and Conditions', 'reklamo-core' );
					$privacy_link = $privacy ? '<a href="' . esc_url( get_permalink( $privacy ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy Policy', 'reklamo-core' ) . '</a>' : esc_html__( 'Privacy Policy', 'reklamo-core' );
					/* translators: 1: terms link, 2: privacy policy link */
					echo wp_kses_post( sprintf( __( 'I agree to the %1$s and the %2$s.', 'reklamo-core' ), $terms_link, $privacy_link ) );
					?>
					</span>
				</label>
			</div>
			<div class="rq-field rq-field--consent">
				<label>
					<input type="checkbox" name="rq_waiver" value="1" required <?php checked( ! empty( $values['waiver'] ) ); ?>>
					<span><?php esc_html_e( 'I understand that the products are made to my specification with my logo, so the 14-day right of withdrawal does not apply once production has started (Art. 57(3) of the Consumer Protection Act).', 'reklamo-core' ); ?></span>
				</label>
			</div>

		<?php
		return (string) ob_get_clean();
	}

	/** Chunked uploader script + its config; safe to call more than once per page. */
	public static function enqueue_uploader(): void {
		if ( wp_script_is( 'reklamo-uploader', 'enqueued' ) ) {
			return;
		}
		$path = REKLAMO_PATH . 'assets/js/uploader.js';
		wp_enqueue_script( 'reklamo-uploader', REKLAMO_URL . 'assets/js/uploader.js', array(), file_exists( $path ) ? (string) filemtime( $path ) : REKLAMO_VERSION, true );
		wp_localize_script(
			'reklamo-uploader',
			'reklamoUpload',
			array(
				'restUrl'  => untrailingslashit( rest_url( Reklamo_Upload::NS ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'maxBytes' => Reklamo_Storage::max_bytes(),
				'i18n'     => array(
					'starting'  => __( 'Preparing upload…', 'reklamo-core' ),
					/* translators: 1: bytes sent, 2: total bytes */
					'uploading' => __( 'Uploading %1$s of %2$s…', 'reklamo-core' ),
					'checking'  => __( 'Checking the file…', 'reklamo-core' ),
					/* translators: %s: file name and size */
					'done'      => __( 'Uploaded: %s', 'reklamo-core' ),
					'failed'    => __( 'The upload failed.', 'reklamo-core' ),
					'fallback'  => __( 'The file will be sent with the form instead.', 'reklamo-core' ),
					/* translators: %s: size limit */
					'tooLarge'  => __( 'The file must be smaller than %s.', 'reklamo-core' ),
				),
			)
		);
	}

	/** Errors + previous values from a failed submission, keyed by ?rq=. One read, then gone. */
	private static function flash_state(): array {
		$key = isset( $_GET['rq'] ) ? sanitize_key( wp_unslash( $_GET['rq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// Own namespace and strict shape: this key comes from the URL and must never be able
		// to address another transient (the per-IP rate counter lives under reklamo_rq_ip_*).
		if ( ! preg_match( '/^[a-z0-9]{12}$/', $key ) ) {
			return array();
		}
		$state = get_transient( 'reklamo_rqf_' . $key );
		delete_transient( 'reklamo_rqf_' . $key );
		return is_array( $state ) ? $state : array();
	}

	private static function current_url(): string {
		return remove_query_arg( 'rq', home_url( add_query_arg( array() ) ) );
	}

	/** Send the visitor back to the form with its errors and values (read once by flash_state()). */
	public static function fail( array $errors, array $values, string $back ): void {
		$key = strtolower( wp_generate_password( 12, false, false ) );
		set_transient(
			'reklamo_rqf_' . $key,
			array(
				'errors' => $errors,
				'values' => $values,
			),
			5 * MINUTE_IN_SECONDS
		);
		$back = wp_validate_redirect( $back, home_url( '/' ) );
		wp_safe_redirect( add_query_arg( 'rq', $key, $back ) );
		exit;
	}

	/** Errors + previous values for a form that renders itself (the cart). */
	public static function flash(): array {
		return self::flash_state();
	}

	/** The contact fields as posted. */
	public static function contact_values(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- every caller verifies its own nonce first.
		return array(
			'name'    => isset( $_POST['rq_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rq_name'] ) ) : '',
			'email'   => isset( $_POST['rq_email'] ) ? sanitize_email( wp_unslash( $_POST['rq_email'] ) ) : '',
			'phone'   => isset( $_POST['rq_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rq_phone'] ) ) : '',
			'consent' => ! empty( $_POST['rq_consent'] ),
			'waiver'  => ! empty( $_POST['rq_waiver'] ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/** Honeypot and the per-IP limit, shared by every form that creates an order. */
	public static function abuse_errors(): array {
		if ( ! empty( $_POST['rq_website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the callers verify their nonce first.
			return array( __( 'Something went wrong. Please try again.', 'reklamo-core' ) );
		}
		if ( self::rate_limited() ) {
			return array( __( 'Too many requests from this connection. Please try again in an hour or contact us by email.', 'reklamo-core' ) );
		}
		return array();
	}

	/** @return string[] errors in the name, email, phone and consent fields */
	public static function contact_errors( array $values ): array {
		$errors = array();
		if ( '' === $values['name'] ) {
			$errors[] = __( 'Please enter your name.', 'reklamo-core' );
		}
		if ( ! is_email( $values['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'reklamo-core' );
		} elseif ( ! Reklamo_Email_Check::has_mail_host( Reklamo_Email_Check::domain( $values['email'] ) ) ) {
			// Every later step (mockup, approval, bank details) travels to this address; a dead domain means a dead order.
			/* translators: %s: email domain */
			$errors[] = sprintf( __( 'The email domain "%s" does not seem to exist. Please check the address.', 'reklamo-core' ), Reklamo_Email_Check::domain( $values['email'] ) );
		}
		if ( ! WC_Validation::is_phone_format( $values['phone'] ) || strlen( preg_replace( '/\D/', '', $values['phone'] ) ) < 6 ) {
			$errors[] = __( 'Please enter a valid phone number.', 'reklamo-core' );
		}
		if ( ! $values['consent'] ) {
			$errors[] = __( 'Please accept the Terms and Conditions and the Privacy Policy.', 'reklamo-core' );
		}
		if ( ! $values['waiver'] ) {
			$errors[] = __( 'Please confirm that you understand the right of withdrawal does not apply to products made to your specification.', 'reklamo-core' );
		}
		return $errors;
	}

	/**
	 * Create the order: one line per product, each with its own logo and note, then the
	 * customer and consent, the no-payment gateway, the files claimed to their lines and
	 * the initial status (which sends the "request received" emails).
	 *
	 * @param array  $lines       Each: product (WC_Product, a variation for a colour), qty, token (logo file token), note.
	 * @param array  $values      Contact values.
	 * @param string $created_via Origin recorded on the order.
	 * @return WC_Order|null Null when the order could not be saved; no file is lost then.
	 */
	public static function create_order( array $lines, array $values, string $created_via = 'reklamo_request' ): ?WC_Order {
		$order = wc_create_order(
			array(
				'customer_id' => get_current_user_id(),
				'created_via' => $created_via,
			)
		);
		if ( is_wp_error( $order ) ) {
			return null;
		}

		// WC_Data setters and add_product() throw; a fatal here would leave a half-built order
		// nobody is told about. Keep the uploaded files (the tokens stay valid) and show the form again.
		$claims = array();
		try {
			foreach ( $lines as $line ) {
				$item_id = $order->add_product( $line['product'], max( 1, (int) ( $line['qty'] ?? 1 ) ) );
				$item    = $order->get_item( $item_id );
				if ( ! $item ) {
					continue;
				}
				if ( '' !== $line['token'] ) {
					$item->add_meta_data( Reklamo_Cart::META_FILE, $line['token'], true );
				}
				$item->add_meta_data( Reklamo_Items::META_STATE, Reklamo_Items::WAITING, true );
				if ( '' !== ( $line['text'] ?? '' ) ) {
					$item->add_meta_data( Reklamo_Items::META_TEXT, $line['text'], true );
					$item->add_meta_data( __( 'Inscription', 'reklamo-core' ), $line['text'], true );
				}
				if ( '' !== ( $line['colour'] ?? '' ) ) {
					$item->add_meta_data( Reklamo_Items::META_COLOUR, $line['colour'], true );
					$item->add_meta_data( __( 'Print colour', 'reklamo-core' ), Reklamo_Cart::print_colours()[ $line['colour'] ] ?? $line['colour'], true );
				}
				// The gift as promised when the order was placed, for the shop to pack.
				if ( '' !== Reklamo_Product::gift( $line['product'] ) ) {
					$item->add_meta_data( __( 'Gift', 'reklamo-core' ), Reklamo_Product::gift( $line['product'] ), true );
				}
				if ( '' !== $line['note'] ) {
					$item->add_meta_data( Reklamo_Items::META_NOTE, $line['note'], true );
					$item->add_meta_data( __( 'Note to designer', 'reklamo-core' ), $line['note'], true );
				}
				$item->save();
				if ( '' !== $line['token'] ) {
					$claims[ (int) $item_id ] = $line['token'];
				}
			}

			$parts = preg_split( '/\s+/', trim( $values['name'] ), 2 );
			$order->set_billing_first_name( $parts[0] );
			$order->set_billing_last_name( $parts[1] ?? '' );
			$order->set_billing_email( $values['email'] );
			$order->set_billing_phone( $values['phone'] );
			$order->set_billing_country( 'BG' );
			$order->set_customer_ip_address( Reklamo_Storage::client_ip() );
			$order->set_customer_user_agent( wc_get_user_agent() );
			Reklamo_Privacy::record_consent( $order, $values['waiver'] );

			$gateways = WC()->payment_gateways()->payment_gateways();
			if ( isset( $gateways[ Reklamo_Gateway::ID ] ) ) {
				$order->set_payment_method( $gateways[ Reklamo_Gateway::ID ] );
			}
			$order->calculate_totals( true );
			$order->save();
		} catch ( Exception $e ) {
			$order->delete( true );
			return null;
		}

		// One logo for all products is one file: it belongs to the first of them, the others point to it.
		foreach ( array_unique( $claims ) as $item_id => $token ) {
			Reklamo_Storage::claim( $token, $order->get_id(), $item_id );
		}
		self::bump_rate_limit();

		// Triggers the "request received" emails and the admin "new order" email.
		$order->update_status( Reklamo_Statuses::RECEIVED, __( 'Request submitted from the website form. No payment taken.', 'reklamo-core' ) );
		return $order;
	}

	/** The customer's order page is the confirmation page; WooCommerce's order-received stays as fallback. */
	public static function redirect_to_order( WC_Order $order ): void {
		$track = Reklamo_Tracking::url( $order );
		wp_safe_redirect( $track ? add_query_arg( 'new', '1', $track ) : $order->get_checkout_order_received_url() );
		exit;
	}

	private static function rate_key(): string {
		return 'reklamo_rq_ip_' . md5( Reklamo_Storage::client_ip() );
	}


	/** All public rate limiters honour REKLAMO_DISABLE_RATE_LIMITS (local/E2E only — never in production). */
	private static function limits_enabled(): bool {
		return ! ( defined( 'REKLAMO_DISABLE_RATE_LIMITS' ) && REKLAMO_DISABLE_RATE_LIMITS );
	}

	private static function rate_limited(): bool {
		return self::limits_enabled() && (int) get_transient( self::rate_key() ) >= self::RATE_LIMIT;
	}

	public static function bump_rate_limit(): void {
		set_transient( self::rate_key(), (int) get_transient( self::rate_key() ) + 1, HOUR_IN_SECONDS );
	}
}

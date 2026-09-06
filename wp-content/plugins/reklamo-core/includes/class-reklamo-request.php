<?php
/**
 * The request form: logo + note + name + email + consent → one button. Creates the
 * WooCommerce order directly (no checkout UI), exactly as the approved design shows.
 *
 * Public, logged-out-writable, and it creates orders — so: nonce, honeypot, per-IP
 * rate limit, strict validation, and the file goes through Reklamo_Storage.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Request {

	const ACTION     = 'reklamo_request';
	const NONCE      = 'reklamo_request_form';
	const RATE_LIMIT = 10; // requests per IP per hour.
	const PAGE_OPT   = 'reklamo_request_page_id';

	public static function init(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
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

	/** @return WC_Product[] purchasable packages, in catalogue order */
	public static function packages(): array {
		return wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 20,
				'orderby' => 'menu_order',
				'order'   => 'ASC',
			)
		);
	}

	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'compact' => '0' ), $atts );
		return self::render( array( 'compact' => '1' === (string) $atts['compact'] ) );
	}

	/**
	 * Render the form. Full variant for the request page; compact for the homepage.
	 *
	 * @param array{compact?:bool, product?:WC_Product|null} $args Options.
	 */
	public static function render( array $args = array() ): string {
		$compact = ! empty( $args['compact'] );
		$product = $args['product'] ?? self::current_product();
		$state   = self::flash_state();
		$errors  = $state['errors'] ?? array();
		$values  = $state['values'] ?? array();
		$max     = (int) Reklamo_Settings::get( 'note_max', '300' );
		$accept  = '.' . implode( ',.', Reklamo_Storage::LOGO_EXTENSIONS );
		$terms   = (int) get_option( 'woocommerce_terms_page_id' );
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		$uid     = wp_unique_id( 'rq' );

		self::enqueue_uploader();

		ob_start();
		?>
		<form class="rq-form <?php echo $compact ? 'rq-form--compact' : 'rq-form--full'; ?>" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<?php wp_nonce_field( self::NONCE, '_rq_nonce' ); ?>
			<input type="hidden" name="rq_return" value="<?php echo esc_url( self::current_url() ); ?>">
			<!-- honeypot: bots fill every field -->
			<div class="rq-hp" aria-hidden="true"><label>Website <input type="text" name="rq_website" tabindex="-1" autocomplete="off"></label></div>

			<?php if ( $errors ) : ?>
				<div class="rq-errors" role="alert">
					<ul>
						<?php foreach ( $errors as $e ) : ?>
							<li><?php echo esc_html( $e ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $product ) : ?>
				<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product->get_id() ); ?>">
			<?php else : ?>
				<div class="rq-field rq-field--package">
					<label for="<?php echo esc_attr( $uid ); ?>-pkg"><?php esc_html_e( 'Package', 'reklamo-core' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-pkg" name="product_id" required>
						<option value=""><?php esc_html_e( 'Choose a package…', 'reklamo-core' ); ?></option>
						<?php foreach ( self::packages() as $p ) : ?>
							<option value="<?php echo esc_attr( (string) $p->get_id() ); ?>" <?php selected( (int) ( $values['product_id'] ?? 0 ), $p->get_id() ); ?>>
								<?php echo esc_html( $p->get_name() . ' — ' . wp_strip_all_tags( wc_price( (float) $p->get_price() ) ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ( ! $compact ) : ?>
				<h3 class="rq-step-title"><?php esc_html_e( '1. Upload your company logo', 'reklamo-core' ); ?></h3>
			<?php endif; ?>
			<div class="rq-field rq-field--file">
				<label class="rq-drop" for="<?php echo esc_attr( $uid ); ?>-file" data-rq-drop>
					<span class="rq-drop__icon" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17a4 4 0 0 1 .8-7.9A6 6 0 0 1 16.4 7 4.5 4.5 0 0 1 19 15.5"/><path d="M12 12v9"/><path d="m8.5 15.5 3.5-3.5 3.5 3.5"/></svg></span>
					<span class="rq-drop__text">
						<strong><?php $compact ? esc_html_e( 'Logo', 'reklamo-core' ) : esc_html_e( 'Drag the file here or click to choose', 'reklamo-core' ); ?></strong>
						<small><?php echo esc_html( strtoupper( implode( ', ', array_diff( Reklamo_Storage::LOGO_EXTENSIONS, array( 'jpeg' ) ) ) ) ); ?></small>
					</span>
					<input type="file" id="<?php echo esc_attr( $uid ); ?>-file" name="reklamo_logo" accept="<?php echo esc_attr( $accept ); ?>" required>
				</label>
				<div class="rq-file" data-rq-file hidden>
					<span class="rq-file__badge" data-rq-file-ext></span>
					<span class="rq-file__name" data-rq-file-name></span>
					<span class="rq-file__size" data-rq-file-size></span>
					<span class="rq-file__ok" aria-hidden="true">✓</span>
				</div>
			</div>

			<?php if ( ! $compact ) : ?>
				<h3 class="rq-step-title"><?php esc_html_e( '2. Instructions for the designer (optional)', 'reklamo-core' ); ?></h3>
			<?php endif; ?>
			<div class="rq-field rq-field--note">
				<?php if ( $compact ) : ?>
					<label for="<?php echo esc_attr( $uid ); ?>-note"><?php esc_html_e( 'Note to the designer (optional)', 'reklamo-core' ); ?></label>
				<?php endif; ?>
				<textarea id="<?php echo esc_attr( $uid ); ?>-note" name="reklamo_note" rows="3" maxlength="<?php echo esc_attr( (string) $max ); ?>" data-rq-counter placeholder="<?php esc_attr_e( 'Example: gold logo, centered on the front cover of the notebook and engraved on the pen.', 'reklamo-core' ); ?>"><?php echo esc_textarea( $values['note'] ?? '' ); ?></textarea>
				<span class="rq-counter"><span data-rq-count>0</span>/<?php echo esc_html( (string) $max ); ?></span>
			</div>

			<div class="rq-row">
				<div class="rq-field">
					<label for="<?php echo esc_attr( $uid ); ?>-name"><?php $compact ? esc_html_e( 'Name / Company', 'reklamo-core' ) : esc_html_e( 'Your name', 'reklamo-core' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-name" name="rq_name" value="<?php echo esc_attr( $values['name'] ?? '' ); ?>" required autocomplete="name" placeholder="<?php esc_attr_e( 'Ivan Ivanov', 'reklamo-core' ); ?>">
				</div>
				<div class="rq-field">
					<label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Contact email', 'reklamo-core' ); ?></label>
					<input type="email" id="<?php echo esc_attr( $uid ); ?>-email" name="rq_email" value="<?php echo esc_attr( $values['email'] ?? '' ); ?>" required autocomplete="email" placeholder="office@company.bg">
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

			<div class="rq-submit">
				<button type="submit" class="rq-button"><?php $compact ? esc_html_e( 'Send for a mockup', 'reklamo-core' ) : esc_html_e( 'Send and request a mockup', 'reklamo-core' ); ?> <span aria-hidden="true">→</span></button>
				<p class="rq-nopay"><?php esc_html_e( 'No payment is taken at this stage.', 'reklamo-core' ); ?></p>
			</div>
		</form>
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
		if ( ! $key ) {
			return array();
		}
		$state = get_transient( 'reklamo_rq_' . $key );
		delete_transient( 'reklamo_rq_' . $key );
		return is_array( $state ) ? $state : array();
	}

	private static function current_url(): string {
		return remove_query_arg( 'rq', home_url( add_query_arg( array() ) ) );
	}

	private static function fail( array $errors, array $values, string $back ): void {
		$key = wp_generate_password( 12, false, false );
		set_transient(
			'reklamo_rq_' . $key,
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

	public static function handle(): void {
		$return = isset( $_POST['rq_return'] ) ? esc_url_raw( wp_unslash( $_POST['rq_return'] ) ) : home_url( '/' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked next.
		$values = array(
			'product_id' => isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'name'       => isset( $_POST['rq_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rq_name'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'email'      => isset( $_POST['rq_email'] ) ? sanitize_email( wp_unslash( $_POST['rq_email'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'note'       => isset( $_POST['reklamo_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reklamo_note'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'consent'    => ! empty( $_POST['rq_consent'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( ! isset( $_POST['_rq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_rq_nonce'] ) ), self::NONCE ) ) {
			self::fail( array( __( 'The form has expired. Please try again.', 'reklamo-core' ) ), $values, $return );
		}
		if ( ! empty( $_POST['rq_website'] ) ) { // honeypot
			self::fail( array( __( 'Something went wrong. Please try again.', 'reklamo-core' ) ), $values, $return );
		}
		if ( self::rate_limited() ) {
			self::fail( array( __( 'Too many requests from this connection. Please try again in an hour or contact us by email.', 'reklamo-core' ) ), $values, $return );
		}

		$errors  = array();
		$product = $values['product_id'] ? wc_get_product( $values['product_id'] ) : null;
		if ( ! $product || ! $product->is_purchasable() || 'publish' !== $product->get_status() ) {
			$errors[] = __( 'Please choose a package.', 'reklamo-core' );
		}
		if ( '' === $values['name'] ) {
			$errors[] = __( 'Please enter your name.', 'reklamo-core' );
		}
		if ( ! is_email( $values['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'reklamo-core' );
		}
		$max = (int) Reklamo_Settings::get( 'note_max', '300' );
		if ( mb_strlen( $values['note'] ) > $max ) {
			/* translators: %d: character limit */
			$errors[] = sprintf( __( 'The note is too long (maximum %d characters).', 'reklamo-core' ), $max );
		}
		if ( ! $values['consent'] ) {
			$errors[] = __( 'Please accept the Terms and Conditions and the Privacy Policy.', 'reklamo-core' );
		}
		// Chunked path hands us a token of a finished upload; otherwise a plain file post.
		$token     = isset( $_POST['reklamo_file_token'] ) ? sanitize_text_field( wp_unslash( $_POST['reklamo_file_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$prestored = '' !== $token ? Reklamo_Storage::unclaimed_by_token( $token, 'logo' ) : null;
		$has_file  = ! empty( $_FILES['reklamo_logo'] ) && UPLOAD_ERR_NO_FILE !== (int) ( $_FILES['reklamo_logo']['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( ! $prestored && ! $has_file ) {
			$errors[] = __( 'Please upload your logo file.', 'reklamo-core' );
		}
		if ( $errors ) {
			self::fail( $errors, $values, $return );
		}

		if ( $prestored ) {
			$stored = array(
				'token'     => $prestored->token,
				'orig_name' => $prestored->orig_name,
			);
		} else {
			$stored = Reklamo_Storage::store_upload( $_FILES['reklamo_logo'], 'logo', Reklamo_Storage::LOGO_EXTENSIONS ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated inside store_upload().
			if ( is_wp_error( $stored ) ) {
				self::fail( array( $stored->get_error_message() ), $values, $return );
			}
		}

		$order = wc_create_order(
			array(
				'customer_id' => get_current_user_id(),
				'created_via' => 'reklamo_request',
			)
		);
		if ( is_wp_error( $order ) ) {
			self::fail( array( __( 'The request could not be saved. Please try again or contact us.', 'reklamo-core' ) ), $values, $return );
		}

		$item_id = $order->add_product( $product, 1 );
		$item    = $order->get_item( $item_id );
		if ( $item ) {
			$item->add_meta_data( Reklamo_Cart::META_FILE, $stored['token'], true );
			if ( '' !== $values['note'] ) {
				$item->add_meta_data( __( 'Note to designer', 'reklamo-core' ), $values['note'], true );
			}
			$item->save();
		}

		$parts = preg_split( '/\s+/', trim( $values['name'] ), 2 );
		$order->set_billing_first_name( $parts[0] );
		$order->set_billing_last_name( $parts[1] ?? '' );
		$order->set_billing_email( $values['email'] );
		$order->set_billing_country( 'BG' );
		$order->set_customer_ip_address( Reklamo_Storage::client_ip() );
		$order->set_customer_user_agent( wc_get_user_agent() );

		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways[ Reklamo_Gateway::ID ] ) ) {
			$order->set_payment_method( $gateways[ Reklamo_Gateway::ID ] );
		}
		$order->calculate_totals( true );
		$order->save();

		Reklamo_Storage::claim( $stored['token'], $order->get_id(), (int) $item_id );
		self::bump_rate_limit();

		// Triggers the "request received" emails and the admin "new order" email.
		$order->update_status( Reklamo_Statuses::RECEIVED, __( 'Request submitted from the website form. No payment taken.', 'reklamo-core' ) );

		// The customer's order page is the confirmation page; WooCommerce's order-received stays as fallback.
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

	private static function bump_rate_limit(): void {
		set_transient( self::rate_key(), (int) get_transient( self::rate_key() ) + 1, HOUR_IN_SECONDS );
	}
}

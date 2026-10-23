<?php
/**
 * The inquiry dialog (Reklamo_Inquiry): opened by any [data-inquiry-open] button or a link to
 * #zapitvane. Printed once a page through reklamo_inquiry_dialog().
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( class_exists( 'Reklamo_Inquiry' ) ) : ?>
	<?php
	$reklamo_privacy  = (int) get_option( 'wp_page_for_privacy_policy' );
	$reklamo_products = function_exists( 'wc_get_products' ) ? wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => 100,
			'orderby' => 'menu_order',
			'order'   => 'ASC',
		)
	) : array();
	?>
	<dialog class="inquiry" data-inquiry aria-labelledby="inquiry-title">
		<form class="rq-form inquiry__box" method="post" action="<?php echo esc_url( Reklamo_Inquiry::url() ); ?>" novalidate data-inquiry-form data-failed="<?php esc_attr_e( 'The inquiry could not be sent. Please write to us by email.', 'reklamo' ); ?>">
			<button type="button" class="inquiry__close" data-inquiry-close aria-label="<?php esc_attr_e( 'Close', 'reklamo' ); ?>">×</button>
			<h2 class="inquiry__title" id="inquiry-title"><?php esc_html_e( 'Inquiry for a tailored offer', 'reklamo' ); ?></h2>
			<p class="inquiry__lead"><?php esc_html_e( 'Tell us what you need and we will send you an offer within one business day.', 'reklamo' ); ?></p>
			<input type="hidden" name="action" value="<?php echo esc_attr( Reklamo_Inquiry::ACTION ); ?>">
			<?php wp_nonce_field( Reklamo_Inquiry::NONCE, '_inq_nonce', false ); ?>
			<div class="rq-hp" aria-hidden="true" style="position:absolute;left:-9999px"><label><?php esc_html_e( 'Website', 'reklamo' ); ?> <input type="text" name="rq_website" tabindex="-1" autocomplete="off"></label></div>
			<div class="inquiry__grid">
				<div class="rq-field"><label for="inq-name"><?php esc_html_e( 'Name / Company', 'reklamo' ); ?> <span class="rk-required" aria-hidden="true">*</span></label><input type="text" id="inq-name" name="inq_name" required autocomplete="name"></div>
				<div class="rq-field"><label for="inq-email"><?php esc_html_e( 'Email', 'reklamo' ); ?> <span class="rk-required" aria-hidden="true">*</span></label><input type="email" id="inq-email" name="inq_email" required autocomplete="email" placeholder="office@company.bg"></div>
				<div class="rq-field"><label for="inq-phone"><?php esc_html_e( 'Phone', 'reklamo' ); ?> <span class="rk-required" aria-hidden="true">*</span></label><input type="tel" id="inq-phone" name="inq_phone" required autocomplete="tel" placeholder="0888 123 456"></div>
				<div class="rq-field"><label for="inq-product"><?php esc_html_e( 'Product or package', 'reklamo' ); ?> <span class="rk-required" aria-hidden="true">*</span></label><input type="text" id="inq-product" name="inq_product" required list="inq-products"></div>
				<div class="rq-field"><label for="inq-quantity"><?php esc_html_e( 'Quantity', 'reklamo' ); ?> <span class="rk-required" aria-hidden="true">*</span></label><input type="text" id="inq-quantity" name="inq_quantity" required placeholder="<?php esc_attr_e( 'e.g. 300 pcs.', 'reklamo' ); ?>"></div>
				<div class="rq-field"><label for="inq-date"><?php esc_html_e( 'Wanted by', 'reklamo' ); ?> <span class="rk-optional"><?php esc_html_e( '(optional)', 'reklamo' ); ?></span></label><input type="text" id="inq-date" name="inq_date" placeholder="<?php esc_attr_e( 'e.g. by 15 December', 'reklamo' ); ?>"></div>
			</div>
			<datalist id="inq-products">
				<?php foreach ( $reklamo_products as $reklamo_p ) : ?>
					<option value="<?php echo esc_attr( $reklamo_p->get_name() ); ?>"></option>
				<?php endforeach; ?>
			</datalist>
			<div class="rq-field"><label for="inq-message"><?php esc_html_e( 'Message', 'reklamo' ); ?> <span class="rk-optional"><?php esc_html_e( '(optional)', 'reklamo' ); ?></span></label><textarea id="inq-message" name="inq_message" rows="3" maxlength="2000"></textarea></div>
			<div class="rq-field rq-field--consent">
				<label>
					<input type="checkbox" name="inq_consent" value="1" required>
					<span>
						<?php
						$reklamo_policy = $reklamo_privacy ? '<a href="' . esc_url( get_permalink( $reklamo_privacy ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy Policy', 'reklamo' ) . '</a>' : esc_html__( 'Privacy Policy', 'reklamo' );
						/* translators: %s: privacy policy link */
						echo wp_kses_post( sprintf( __( 'I agree that my details are used to answer this inquiry, as described in the %s.', 'reklamo' ), $reklamo_policy ) );
						?>
					</span>
				</label>
			</div>
			<ul class="rq-errors inquiry__errors" data-inquiry-errors hidden></ul>
			<button type="submit" class="btn btn--primary inquiry__send" data-inquiry-send data-busy="<?php esc_attr_e( 'Sending…', 'reklamo' ); ?>"><?php esc_html_e( 'Send the inquiry', 'reklamo' ); ?></button>
			<p class="inquiry__blocked" data-inquiry-blocked hidden><?php esc_html_e( 'Fill in the fields marked * and tick the consent to send the inquiry.', 'reklamo' ); ?></p>
			<div class="inquiry__done" data-inquiry-done hidden>
				<p class="rk-added__icon" aria-hidden="true">✓</p>
				<h2 class="inquiry__title"><?php esc_html_e( 'Thank you — your inquiry is on its way', 'reklamo' ); ?></h2>
				<p class="inquiry__lead"><?php esc_html_e( 'We will reply within one business day. A copy is in your inbox.', 'reklamo' ); ?></p>
				<button type="button" class="btn btn--outline" data-inquiry-close><?php esc_html_e( 'Close', 'reklamo' ); ?></button>
			</div>
		</form>
	</dialog>
<?php endif; ?>

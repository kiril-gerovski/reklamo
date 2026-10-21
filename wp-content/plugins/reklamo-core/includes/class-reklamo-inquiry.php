<?php
/**
 * An inquiry for a tailored offer, from the dialog behind the offer band's button. Nothing is
 * stored: the shop gets an email (to every administrator too, reply goes to the customer) and
 * the customer a confirmation. Honeypot and a per-IP limit, as on the request form.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Inquiry {

	const ACTION     = 'reklamo_inquiry';
	const NONCE      = 'reklamo_inquiry';
	const RATE_LIMIT = 5;
	const FIELDS     = array( 'name', 'email', 'phone', 'product', 'quantity', 'date', 'message' );

	public static function init(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function url(): string {
		return admin_url( 'admin-post.php' );
	}

	/** @return array<string,string|bool> */
	public static function values(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- handle() verifies the nonce first.
		$v = array();
		foreach ( self::FIELDS as $f ) {
			$raw     = isset( $_POST[ 'inq_' . $f ] ) ? wp_unslash( $_POST[ 'inq_' . $f ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on the next line.
			$v[ $f ] = 'message' === $f ? sanitize_textarea_field( (string) $raw ) : sanitize_text_field( (string) $raw );
		}
		$v['email']   = sanitize_email( $v['email'] );
		$v['consent'] = ! empty( $_POST['inq_consent'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $v;
	}

	/** @return string[] */
	public static function errors( array $v ): array {
		$errors = array();
		if ( '' === $v['name'] ) {
			$errors[] = __( 'Please enter your name.', 'reklamo-core' );
		}
		if ( ! is_email( $v['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'reklamo-core' );
		} elseif ( ! Reklamo_Email_Check::has_mail_host( Reklamo_Email_Check::domain( $v['email'] ) ) ) {
			/* translators: %s: email domain */
			$errors[] = sprintf( __( 'The email domain "%s" does not seem to exist. Please check the address.', 'reklamo-core' ), Reklamo_Email_Check::domain( $v['email'] ) );
		}
		if ( ! WC_Validation::is_phone_format( $v['phone'] ) || strlen( preg_replace( '/\D/', '', $v['phone'] ) ) < 6 ) {
			$errors[] = __( 'Please enter a valid phone number.', 'reklamo-core' );
		}
		if ( '' === $v['product'] ) {
			$errors[] = __( 'Please tell us which product or package.', 'reklamo-core' );
		}
		if ( '' === $v['quantity'] ) {
			$errors[] = __( 'Please tell us the quantity.', 'reklamo-core' );
		}
		if ( mb_strlen( $v['message'] ) > 2000 ) {
			$errors[] = __( 'The message is too long (maximum 2000 characters).', 'reklamo-core' );
		}
		if ( ! $v['consent'] ) {
			$errors[] = __( 'Please agree to the processing of your details for the reply.', 'reklamo-core' );
		}
		return $errors;
	}

	/** label => value, the filled ones, for both emails. */
	public static function rows( array $v ): array {
		return array_filter(
			array(
				__( 'Name', 'reklamo-core' )               => $v['name'],
				__( 'Email', 'reklamo-core' )              => $v['email'],
				__( 'Phone', 'reklamo-core' )              => $v['phone'],
				__( 'Product or package', 'reklamo-core' ) => $v['product'],
				__( 'Quantity', 'reklamo-core' )           => $v['quantity'],
				__( 'Wanted by', 'reklamo-core' )          => $v['date'],
				__( 'Message', 'reklamo-core' )            => $v['message'],
			),
			static fn( $x ) => '' !== (string) $x
		);
	}

	public static function handle(): void {
		if ( ! check_ajax_referer( self::NONCE, '_inq_nonce', false ) ) {
			self::reply( array( __( 'The page has expired. Please reload it and try again.', 'reklamo-core' ) ), 400 );
		}
		if ( ! empty( $_POST['rq_website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
			self::reply( array( __( 'Something went wrong. Please try again.', 'reklamo-core' ) ), 400 );
		}
		if ( self::rate_limited() ) {
			self::reply( array( __( 'Too many requests from this connection. Please try again in an hour or contact us by email.', 'reklamo-core' ) ), 429 );
		}
		$v      = self::values();
		$errors = self::errors( $v );
		if ( $errors ) {
			self::reply( $errors, 422 );
		}
		$emails = WC()->mailer()->get_emails();
		if ( ! isset( $emails['Reklamo_Email_Admin_Inquiry'] ) || ! $emails['Reklamo_Email_Admin_Inquiry']->trigger( $v ) ) {
			self::reply( array( __( 'Your inquiry could not be sent. Please write to us by email.', 'reklamo-core' ) ), 500 );
		}
		if ( isset( $emails['Reklamo_Email_Inquiry_Received'] ) ) {
			$emails['Reklamo_Email_Inquiry_Received']->trigger( $v );
		}
		self::bump_rate_limit();
		self::reply( array() );
	}

	/** @param string[] $errors Empty on success. */
	private static function reply( array $errors, int $status = 200 ): void {
		wp_send_json(
			array(
				'ok'     => ! $errors,
				'errors' => $errors,
			),
			$status
		);
	}

	private static function rate_key(): string {
		return 'reklamo_inq_ip_' . md5( Reklamo_Storage::client_ip() );
	}

	private static function rate_limited(): bool {
		$off = defined( 'REKLAMO_DISABLE_RATE_LIMITS' ) && REKLAMO_DISABLE_RATE_LIMITS;
		return ! $off && (int) get_transient( self::rate_key() ) >= self::RATE_LIMIT;
	}

	private static function bump_rate_limit(): void {
		set_transient( self::rate_key(), (int) get_transient( self::rate_key() ) + 1, HOUR_IN_SECONDS );
	}
}

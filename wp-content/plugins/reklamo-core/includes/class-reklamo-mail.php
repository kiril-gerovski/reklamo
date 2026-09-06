<?php
/**
 * Mail transport and sender defaults — no plugin needed.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Mail {

	/** @var string Message of the most recent wp_mail() failure in this request. */
	private static string $last_error = '';

	public static function init(): void {
		add_action( 'phpmailer_init', array( __CLASS__, 'smtp' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'remember_failure' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'from_address' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
	}

	/**
	 * SMTP from REKLAMO_SMTP_* constants in wp-config.php. Locally they point at
	 * Mailpit; in production at the host's mailbox. Same code path both places.
	 * Does nothing when no host is configured.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer instance.
	 */
	public static function smtp( $phpmailer ): void {
		if ( ! defined( 'REKLAMO_SMTP_HOST' ) || '' === REKLAMO_SMTP_HOST ) {
			return;
		}
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer API.
		$phpmailer->isSMTP();
		$phpmailer->Host    = REKLAMO_SMTP_HOST;
		$phpmailer->Port    = defined( 'REKLAMO_SMTP_PORT' ) ? (int) REKLAMO_SMTP_PORT : 587;
		$phpmailer->CharSet = 'UTF-8';

		$user = defined( 'REKLAMO_SMTP_USER' ) ? (string) REKLAMO_SMTP_USER : '';
		if ( '' !== $user ) {
			$phpmailer->SMTPAuth   = true;
			$phpmailer->Username   = $user;
			$phpmailer->Password   = defined( 'REKLAMO_SMTP_PASS' ) ? (string) REKLAMO_SMTP_PASS : '';
			$phpmailer->SMTPSecure = defined( 'REKLAMO_SMTP_SECURE' ) && '' !== REKLAMO_SMTP_SECURE ? REKLAMO_SMTP_SECURE : 'tls';
		} else {
			// Mailpit / unauthenticated relay: no auth, no opportunistic TLS.
			$phpmailer->SMTPAuth    = false;
			$phpmailer->SMTPAutoTLS = false;
		}
		// phpcs:enable
	}

	/**
	 * WordPress defaults to wordpress@<host>, which on a container or a shared host's
	 * internal hostname is an address PHPMailer rejects — password resets then silently
	 * fail. WooCommerce sets its own From; core does not. Reuse the WooCommerce sender.
	 */
	public static function from_address( string $from ): string {
		if ( ! str_starts_with( $from, 'wordpress@' ) ) {
			return $from;
		}
		$wc_from = (string) get_option( 'woocommerce_email_from_address', '' );
		return is_email( $wc_from ) ? $wc_from : $from;
	}

	public static function from_name( string $name ): string {
		if ( 'WordPress' !== $name ) {
			return $name;
		}
		$wc_name = (string) get_option( 'woocommerce_email_from_name', '' );
		return '' !== $wc_name ? $wc_name : get_bloginfo( 'name' );
	}

	public static function remember_failure( WP_Error $error ): void {
		self::$last_error = $error->get_error_message();
	}

	/** Why the last send failed, or a generic reason when the transport gave none. */
	public static function last_error(): string {
		return '' !== self::$last_error ? self::$last_error : __( 'the mail server rejected the message', 'reklamo-core' );
	}

	/**
	 * Send a prepared WC_Email and record the outcome on the order. wp_mail() returns
	 * false on SMTP errors but callers historically ignored it, so the shop saw "sent"
	 * for mail that never left the server. Every failure becomes an order note.
	 *
	 * @return bool True when the transport accepted the message.
	 */
	public static function deliver( WC_Email $email, WC_Order $order ): bool {
		self::$last_error = '';
		if ( ! $email->is_enabled() ) {
			/* translators: %s: email name */
			$order->add_order_note( sprintf( __( 'Email "%s" NOT sent: it is disabled under WooCommerce → Settings → Emails.', 'reklamo-core' ), $email->get_title() ) );
			self::$last_error = __( 'this email is disabled under WooCommerce → Settings → Emails', 'reklamo-core' );
			return false;
		}
		$to = $email->get_recipient();
		if ( ! $to ) {
			/* translators: %s: email name */
			$order->add_order_note( sprintf( __( 'Email "%s" NOT sent: no recipient address.', 'reklamo-core' ), $email->get_title() ) );
			self::$last_error = __( 'no recipient address', 'reklamo-core' );
			return false;
		}
		$sent = (bool) $email->send( $to, $email->get_subject(), $email->get_content(), $email->get_headers(), $email->get_attachments() );
		if ( ! $sent ) {
			/* translators: 1: email name, 2: recipient, 3: error message */
			$order->add_order_note( sprintf( __( 'Email "%1$s" to %2$s could NOT be sent: %3$s', 'reklamo-core' ), $email->get_title(), $to, self::last_error() ) );
		}
		return $sent;
	}

	/**
	 * Diagnostics: send a plain test message through the configured transport.
	 *
	 * @return true|string True on success, otherwise the failure reason.
	 */
	public static function send_test( string $to ) {
		self::$last_error = '';
		$ok = wp_mail(
			$to,
			/* translators: %s: site name */
			sprintf( __( 'Test email from %s', 'reklamo-core' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			__( 'If you are reading this, outgoing email from the shop works.', 'reklamo-core' )
		);
		return $ok ? true : self::last_error();
	}

	/** Human-readable description of the transport in use, for the diagnostics screen. */
	public static function transport(): string {
		if ( defined( 'REKLAMO_SMTP_HOST' ) && '' !== REKLAMO_SMTP_HOST ) {
			$port = defined( 'REKLAMO_SMTP_PORT' ) ? (int) REKLAMO_SMTP_PORT : 587;
			$auth = defined( 'REKLAMO_SMTP_USER' ) && '' !== REKLAMO_SMTP_USER ? __( 'authenticated', 'reklamo-core' ) : __( 'no authentication', 'reklamo-core' );
			return sprintf( 'SMTP %s:%d (%s)', REKLAMO_SMTP_HOST, $port, $auth );
		}
		return __( 'PHP mail() — REKLAMO_SMTP_HOST is not defined in wp-config.php', 'reklamo-core' );
	}
}

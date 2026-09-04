<?php
/**
 * Mail transport and sender defaults — no plugin needed.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Mail {

	public static function init(): void {
		add_action( 'phpmailer_init', array( __CLASS__, 'smtp' ) );
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
}

<?php
/**
 * Plugin Name:       Reklamo Core
 * Plugin URI:        https://reklamo.bg
 * Description:       Бизнес логика за Reklamo.bg — статуси на поръчки, качване на лога, визуализации и одобрения, имейли. Темата е само презентация; всичко, което пипа поръчки, живее тук.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Nocturn AI
 * License:           GPL-2.0-or-later
 * Text Domain:       reklamo
 * WC requires at least: 11.0
 * WC tested up to:   11.1
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

define( 'REKLAMO_VERSION', '0.1.0' );
define( 'REKLAMO_FILE', __FILE__ );
define( 'REKLAMO_PATH', plugin_dir_path( __FILE__ ) );
define( 'REKLAMO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features.
 *
 * Both are mandatory: HPOS is the default order storage, and without the
 * cart_checkout_blocks declaration WooCommerce flags the plugin as
 * incompatible with the block checkout and may force the classic fallback.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * SMTP without a plugin.
 *
 * Reads REKLAMO_SMTP_* constants from wp-config.php. Locally they point at
 * Mailpit; in production at the host's mailbox. Same code path both places,
 * so mail is exercised the way it will really run. Does nothing if no host
 * is configured, leaving wp_mail() on the PHP mail() default.
 */
add_action(
	'phpmailer_init',
	static function ( $phpmailer ): void {
		if ( ! defined( 'REKLAMO_SMTP_HOST' ) || '' === REKLAMO_SMTP_HOST ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host    = REKLAMO_SMTP_HOST; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port    = defined( 'REKLAMO_SMTP_PORT' ) ? (int) REKLAMO_SMTP_PORT : 587; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->CharSet = 'UTF-8'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$user = defined( 'REKLAMO_SMTP_USER' ) ? (string) REKLAMO_SMTP_USER : '';
		if ( '' !== $user ) {
			$phpmailer->SMTPAuth   = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->Username   = $user; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->Password   = defined( 'REKLAMO_SMTP_PASS' ) ? (string) REKLAMO_SMTP_PASS : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->SMTPSecure = defined( 'REKLAMO_SMTP_SECURE' ) && '' !== REKLAMO_SMTP_SECURE ? REKLAMO_SMTP_SECURE : 'tls'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		} else {
			// Mailpit / unauthenticated relay: no auth, no opportunistic TLS.
			$phpmailer->SMTPAuth    = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->SMTPAutoTLS = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}
);

/**
 * Sane default sender for core emails.
 *
 * WordPress defaults to wordpress@<host>, which on a container ("localhost")
 * or a shared host's internal hostname is an address PHPMailer rejects —
 * password resets and admin notices then silently fail. WooCommerce sets its
 * own From; core does not. Reuse the WooCommerce sender the owner already
 * configures under WooCommerce → Настройки → Имейли.
 */
add_filter(
	'wp_mail_from',
	static function ( string $from ): string {
		if ( ! str_starts_with( $from, 'wordpress@' ) ) {
			return $from;
		}
		$wc_from = (string) get_option( 'woocommerce_email_from_address', '' );
		return is_email( $wc_from ) ? $wc_from : $from;
	}
);
add_filter(
	'wp_mail_from_name',
	static function ( string $name ): string {
		if ( 'WordPress' !== $name ) {
			return $name;
		}
		$wc_name = (string) get_option( 'woocommerce_email_from_name', '' );
		return '' !== $wc_name ? $wc_name : get_bloginfo( 'name' );
	}
);

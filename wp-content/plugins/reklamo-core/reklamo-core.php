<?php
/**
 * Plugin Name:       Reklamo Core
 * Plugin URI:        https://reklamo.bg
 * Description:       Business logic for Reklamo.bg — order statuses, logo uploads, mockups and approvals, emails. The theme is presentation only; everything that touches orders lives here.
 * Version:           0.2.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Nocturn AI
 * License:           GPL-2.0-or-later
 * Text Domain:       reklamo-core
 * Domain Path:       /languages
 * WC requires at least: 11.0
 * WC tested up to:   11.1
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

define( 'REKLAMO_VERSION', '0.2.0' );
define( 'REKLAMO_FILE', __FILE__ );
define( 'REKLAMO_PATH', plugin_dir_path( __FILE__ ) );
define( 'REKLAMO_URL', plugin_dir_url( __FILE__ ) );

// Classes with no WooCommerce parent can load now. Gateway and email classes extend
// WooCommerce classes and are required later, once WooCommerce has loaded them.
require REKLAMO_PATH . 'includes/class-reklamo-install.php';
require REKLAMO_PATH . 'includes/class-reklamo-mail.php';
require REKLAMO_PATH . 'includes/class-reklamo-token.php';
require REKLAMO_PATH . 'includes/class-reklamo-statuses.php';
require REKLAMO_PATH . 'includes/class-reklamo-storage.php';
require REKLAMO_PATH . 'includes/class-reklamo-cart.php';
require REKLAMO_PATH . 'includes/class-reklamo-approval.php';
require REKLAMO_PATH . 'includes/class-reklamo-admin-order.php';
require REKLAMO_PATH . 'includes/class-reklamo-emails.php';
require REKLAMO_PATH . 'includes/class-reklamo-settings.php';
require REKLAMO_PATH . 'includes/class-reklamo-request.php';

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

Reklamo_Mail::init();

register_activation_hook( __FILE__, array( 'Reklamo_Install', 'activate' ) );

/**
 * Boot once every plugin is loaded. Priority 20 so WooCommerce (10) is fully included.
 */
function reklamo_boot(): void {
	load_plugin_textdomain( 'reklamo-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Reklamo Core needs WooCommerce to be active.', 'reklamo-core' ) . '</p></div>';
			}
		);
		return;
	}

	require_once REKLAMO_PATH . 'includes/class-reklamo-gateway.php';

	Reklamo_Install::maybe_upgrade();
	Reklamo_Statuses::init();
	Reklamo_Storage::init();
	Reklamo_Cart::init();
	Reklamo_Gateway::init();
	Reklamo_Approval::init();
	Reklamo_Admin_Order::init();
	Reklamo_Emails::init();
	Reklamo_Settings::init();
	Reklamo_Request::init();
}
add_action( 'plugins_loaded', 'reklamo_boot', 20 );

// The block checkout registry fires on this hook, which WooCommerce documents as the
// only reliable one — plugins_loaded ordering is not guaranteed. Attach at include time.
add_action(
	'woocommerce_blocks_loaded',
	static function (): void {
		require_once REKLAMO_PATH . 'includes/class-reklamo-blocks-gateway.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ): void {
				$registry->register( new Reklamo_Blocks_Gateway() );
			}
		);
	}
);

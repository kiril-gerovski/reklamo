<?php
/**
 * Block checkout integration for the request gateway.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Reklamo_Blocks_Gateway extends AbstractPaymentMethodType {

	/** @var string Must match Reklamo_Gateway::ID. */
	protected $name = 'reklamo_request';

	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_reklamo_request_settings', array() );
	}

	public function is_active(): bool {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/** Vanilla JS, no build step. */
	public function get_payment_method_script_handles(): array {
		$path = REKLAMO_PATH . 'assets/js/gateway-blocks.js';
		wp_register_script(
			'reklamo-gateway-blocks',
			REKLAMO_URL . 'assets/js/gateway-blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			file_exists( $path ) ? (string) filemtime( $path ) : REKLAMO_VERSION,
			true
		);
		wp_set_script_translations( 'reklamo-gateway-blocks', 'reklamo-core', REKLAMO_PATH . 'languages' );
		return array( 'reklamo-gateway-blocks' );
	}

	public function get_payment_method_data(): array {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
		);
	}
}

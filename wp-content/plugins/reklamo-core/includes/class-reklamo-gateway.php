<?php
/**
 * "Request without payment" gateway.
 *
 * Checkout needs a payment method whenever the total is above zero; this one takes
 * nothing, puts the order into `rq-received` and never calls payment_complete().
 * Bank details go out later, after the customer approves the mockup.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Gateway extends WC_Payment_Gateway {

	const ID = 'reklamo_request';

	public static function init(): void {
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );
		// Belt and braces: classic checkout, block checkout, and process_payment() itself.
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'force_from_hook' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'force_from_hook' ), 20, 1 );
		add_filter( 'woocommerce_thankyou_order_received_text', array( __CLASS__, 'thankyou_text' ), 10, 2 );
	}

	public static function add_gateway( array $gateways ): array {
		$gateways[] = __CLASS__;
		return $gateways;
	}

	public function __construct() {
		$this->id                 = self::ID;
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'Request without payment (Reklamo)', 'reklamo-core' );
		$this->method_description = __( 'The order is accepted without payment. Bank details and a 50% deposit request are sent after the customer approves the mockup.', 'reklamo-core' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
	}

	/** Everything here is owner-editable under WooCommerce → Settings → Payments. */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'Enable/Disable', 'reklamo-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable request without payment', 'reklamo-core' ),
				'default' => 'yes',
			),
			'title'        => array(
				'title'       => __( 'Title', 'reklamo-core' ),
				'type'        => 'text',
				'description' => __( 'Shown next to the option at checkout.', 'reklamo-core' ),
				'default'     => __( 'Request without payment', 'reklamo-core' ),
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => __( 'Description', 'reklamo-core' ),
				'type'        => 'textarea',
				'description' => __( 'Shown under the option at checkout.', 'reklamo-core' ),
				'default'     => __( 'No payment is taken at this stage. We prepare a mockup for your approval and send bank details afterwards.', 'reklamo-core' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Thank-you page text', 'reklamo-core' ),
				'type'        => 'textarea',
				'description' => __( 'Shown on the order-received page.', 'reklamo-core' ),
				'default'     => __( 'Thank you! You will receive a mockup for approval within 24 business hours.', 'reklamo-core' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * @param int $order_id Order ID.
	 * @return array{result:string, redirect:string}
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}
		// Never payment_complete(), never on-hold, never reduce stock.
		Reklamo_Statuses::force_initial( $order, __( 'Request received. No payment taken.', 'reklamo-core' ) );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	public function thankyou_page( int $order_id ): void {
		$text = $this->get_option( 'instructions' );
		if ( $text ) {
			echo wp_kses_post( wpautop( wptexturize( $text ) ) );
		}
	}

	/**
	 * @param int|WC_Order $order_or_id Classic passes the ID (plus posted data and the order); Store API passes the order.
	 */
	public static function force_from_hook( $order_or_id ): void {
		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
		if ( $order && self::ID === $order->get_payment_method() ) {
			Reklamo_Statuses::force_initial( $order, __( 'Request received. No payment taken.', 'reklamo-core' ) );
		}
	}

	public static function thankyou_text( string $text, $order ): string {
		if ( $order instanceof WC_Order && self::ID === $order->get_payment_method() ) {
			return __( 'Your request has been received. No payment is taken at this stage — our designer will send you a mockup for approval within 24 business hours.', 'reklamo-core' );
		}
		return $text;
	}
}

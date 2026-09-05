<?php
/**
 * Customer: deposit received, production has started.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Production_Started extends Reklamo_Email {

	public function __construct() {
		$this->id             = 'reklamo_production_started';
		$this->customer_email = true;
		$this->title          = __( 'Production started (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent when the order moves to "In production".', 'reklamo-core' );
		add_action( 'woocommerce_order_status_' . Reklamo_Statuses::PRODUCTION . '_notification', array( $this, 'trigger' ), 10, 2 );
		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'Your order {order_number} is in production — {site_title}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'We are producing your order', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Hello {customer_first_name}, we received your deposit of {deposit_amount} — thank you. Production of your package has started. We will let you know as soon as it is ready and send the details for the remaining {balance_amount}.', 'reklamo-core' );
	}

	public function get_default_additional_content() {
		return __( 'Questions? Just reply to this email.', 'reklamo-core' );
	}

	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();
		if ( $order_id && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof WC_Order ) {
			$this->prepare( $order );
			$this->recipient = $order->get_billing_email();
			$this->dispatch();
		}
		$this->restore_locale();
	}
}

<?php
/**
 * Customer: the order was cancelled. Only the shop can cancel (guests have no account),
 * so no admin copy is needed.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Cancelled extends Reklamo_Email {

	public function __construct() {
		$this->id             = 'reklamo_cancelled';
		$this->customer_email = true;
		$this->title          = __( 'Order cancelled (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent to the customer when the shop cancels an order at any stage.', 'reklamo-core' );
		add_action( 'woocommerce_order_status_cancelled_notification', array( $this, 'trigger' ), 10, 2 );
		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'Order {order_number} has been cancelled — {site_title}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'Your order was cancelled', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Hello {customer_first_name}, order {order_number} has been cancelled. If you have already transferred a deposit, we will return it to the account it came from. If this is unexpected, just reply to this email.', 'reklamo-core' );
	}

	public function get_default_additional_content() {
		return __( 'You are welcome to place a new request at any time.', 'reklamo-core' );
	}

	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();
		if ( $order_id && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		// Only orders that went through our flow; a cancelled test order from wp-admin with no email is skipped by deliver().
		if ( $order instanceof WC_Order ) {
			$this->prepare( $order );
			$this->recipient = $order->get_billing_email();
			$this->dispatch();
		}
		$this->restore_locale();
	}
}

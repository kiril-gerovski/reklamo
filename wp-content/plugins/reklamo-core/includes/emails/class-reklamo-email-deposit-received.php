<?php
/**
 * Customer: we received your deposit.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Deposit_Received extends Reklamo_Email {

	public function __construct() {
		$this->id             = 'reklamo_deposit_received';
		$this->customer_email = true;
		$this->title          = __( 'Deposit received (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent when the shop marks the deposit as received.', 'reklamo-core' );
		add_action( 'woocommerce_order_status_' . Reklamo_Statuses::DEPOSIT_PAID . '_notification', array( $this, 'trigger' ), 10, 2 );
		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'Deposit received for order {order_number} — {site_title}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'Thank you — your deposit is in', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Hello {customer_first_name}, we received your deposit of {deposit_amount} for order {order_number}. Production is being scheduled and you will get an email the moment it starts. The balance of {balance_amount} is due before delivery.', 'reklamo-core' );
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

<?php
/**
 * Customer: order is ready — pay the balance before delivery.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Final_Payment extends Reklamo_Email {

	protected $with_bank = true;

	public function __construct() {
		$this->id             = 'reklamo_final_payment';
		$this->customer_email = true;
		$this->title          = __( 'Final payment due (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent when the order is ready: the remaining amount and bank details.', 'reklamo-core' );
		add_action( 'woocommerce_order_status_' . Reklamo_Statuses::FINAL_DUE . '_notification', array( $this, 'trigger' ), 10, 2 );
		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'Your order {order_number} is ready — final payment — {site_title}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'Your order is ready!', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Hello {customer_first_name}, your package is produced and ready to ship. Please transfer the remaining {balance_amount} to the account below, quoting the order number, and we dispatch it the same day we see the payment.', 'reklamo-core' );
	}

	public function get_default_additional_content() {
		return __( 'The invoice for the full amount accompanies the delivery.', 'reklamo-core' );
	}

	protected function amount_label(): string {
		return __( 'Balance due', 'reklamo-core' );
	}

	protected function amount_value(): string {
		return $this->placeholders['{balance_amount}'];
	}

	/**
	 * @param int            $order_id Order ID.
	 * @param WC_Order|false $order    Order.
	 * @param bool           $reminder Prefix the subject as a reminder.
	 */
	public function trigger( $order_id, $order = false, bool $reminder = false ) {
		$this->setup_locale();
		$sent = false;
		if ( $order_id && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof WC_Order ) {
			$this->prepare( $order );
			$this->recipient = $order->get_billing_email();
			$this->reminder  = $reminder;
			$sent            = $this->dispatch();
		}
		$this->restore_locale();
		return $sent;
	}
}

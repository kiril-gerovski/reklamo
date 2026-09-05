<?php
/**
 * Customer: mockup approved — fill in your details and pay the deposit. Bank details go
 * out HERE, never earlier.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Deposit_Request extends Reklamo_Email {

	protected $with_bank   = true;
	protected $with_button = true;

	public function __construct() {
		$this->id             = 'reklamo_deposit_request';
		$this->customer_email = true;
		$this->title          = __( 'Deposit request (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent when the customer approves a mockup: bank details, the deposit amount and a link to fill in invoice and delivery details.', 'reklamo-core' );
		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'Approved — deposit and details for order {order_number} — {site_title}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'Thank you for approving the mockup!', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Hello {customer_first_name}, your mockup is approved. Two short steps remain: fill in your invoice and delivery details using the button below, and transfer the {deposit_pct}% deposit of {deposit_amount} to the account below, quoting the order number. Production starts as soon as it arrives; the balance of {balance_amount} is due before delivery.', 'reklamo-core' );
	}

	public function get_default_button_label(): string {
		return __( 'Fill in invoice and delivery details', 'reklamo-core' );
	}

	public function get_default_additional_content() {
		return __( 'Prices include VAT. We will issue the invoice from the details you provide.', 'reklamo-core' );
	}

	protected function amount_label(): string {
		return __( 'Deposit due', 'reklamo-core' );
	}

	protected function amount_value(): string {
		return $this->placeholders['{deposit_amount}'];
	}

	/**
	 * @param int            $order_id    Order ID.
	 * @param WC_Order|false $order       Order.
	 * @param string         $details_url Signed link to the details form.
	 * @param bool           $reminder    Reminder send.
	 */
	public function trigger( $order_id, $order = false, string $details_url = '', bool $reminder = false ) {
		$this->setup_locale();
		if ( $order_id && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof WC_Order && $details_url ) {
			$this->prepare( $order );
			$this->recipient  = $order->get_billing_email();
			$this->button_url = $details_url;
			$this->reminder   = $reminder;
			$this->dispatch();
		}
		$this->restore_locale();
	}
}

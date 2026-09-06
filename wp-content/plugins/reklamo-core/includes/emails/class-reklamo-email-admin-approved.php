<?php
/**
 * Admin: the customer approved the mockup — a deposit is now expected.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Admin_Approved extends Reklamo_Email {

	protected $with_button = true;

	public function __construct() {
		$this->id             = 'reklamo_admin_approved';
		$this->customer_email = false;
		$this->title          = __( 'Mockup approved — admin (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent to the shop when a customer approves a mockup: watch the bank account for the deposit.', 'reklamo-core' );
		add_action( 'woocommerce_order_status_' . Reklamo_Statuses::APPROVED . '_notification', array( $this, 'trigger' ), 10, 2 );
		parent::__construct();
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['recipient'] = array(
			'title'       => __( 'Recipient(s)', 'reklamo-core' ),
			'type'        => 'text',
			/* translators: %s: admin email */
			'description' => sprintf( __( 'Comma-separated. Defaults to %s.', 'reklamo-core' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ),
			'default'     => '',
			'desc_tip'    => true,
		);
	}

	public function get_default_subject() {
		return __( '[{site_title}] Mockup approved for order {order_number} — deposit expected', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'The customer approved the mockup', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Order {order_number}: mockup #{revision} was approved. The customer has been asked for a {deposit_pct}% deposit of {deposit_amount} and for their invoice and delivery details. When the transfer arrives, mark it in the order.', 'reklamo-core' );
	}

	public function get_default_button_label(): string {
		return __( 'Open the order', 'reklamo-core' );
	}

	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();
		if ( $order_id && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof WC_Order ) {
			$this->prepare( $order );
			$this->recipient                  = $this->get_option( 'recipient', get_option( 'admin_email' ) );
			$this->button_url                 = $order->get_edit_order_url();
			$this->placeholders['{revision}'] = (string) $order->get_meta( '_reklamo_approved_revision' );
			$this->dispatch();
		}
		$this->restore_locale();
	}
}

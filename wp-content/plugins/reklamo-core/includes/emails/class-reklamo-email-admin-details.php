<?php
/**
 * Admin: the customer submitted invoice and delivery details — time to issue the proforma.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Admin_Details extends Reklamo_Email {

	protected $with_details = true;

	public function __construct() {
		$this->id             = 'reklamo_admin_details';
		$this->customer_email = false;
		$this->title          = __( 'Invoice details received — admin (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent to the shop when the customer submits company, invoice and delivery details after approval.', 'reklamo-core' );
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
		return __( '[{site_title}] Invoice details for order {order_number}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'Invoice and delivery details received', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Order {order_number} (deposit {deposit_amount}) — the customer filled in the details below. Issue the proforma and watch for the transfer.', 'reklamo-core' );
	}

	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();
		if ( $order_id && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof WC_Order ) {
			$this->prepare( $order );
			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
			$this->dispatch();
		}
		$this->restore_locale();
	}
}

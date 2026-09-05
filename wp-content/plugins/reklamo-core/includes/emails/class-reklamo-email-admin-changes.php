<?php
/**
 * Admin: the customer asked for changes to the mockup.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Admin_Changes extends Reklamo_Email {

	public function __construct() {
		$this->id             = 'reklamo_admin_changes';
		$this->customer_email = false;
		$this->title          = __( 'Changes requested — admin (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent to the shop when a customer requests changes to a mockup.', 'reklamo-core' );
		$this->placeholders   = array( '{change_request}' => '' );
		add_action( 'woocommerce_order_status_' . Reklamo_Statuses::CHANGES . '_notification', array( $this, 'trigger' ), 10, 2 );
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
		return __( '[{site_title}] Changes requested for order {order_number}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'The customer wants changes', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Order {order_number}: the customer asked for changes to mockup #{revision}. Their message: {change_request}', 'reklamo-core' );
	}

	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();
		if ( $order_id && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof WC_Order ) {
			$this->prepare( $order );
			$this->recipient                        = $this->get_option( 'recipient', get_option( 'admin_email' ) );
			$this->placeholders['{revision}']       = (string) Reklamo_Approval::latest_revision( $order->get_id() );
			$this->placeholders['{change_request}'] = (string) $order->get_meta( '_reklamo_last_change_request' );
			$this->dispatch();
		}
		$this->restore_locale();
	}
}

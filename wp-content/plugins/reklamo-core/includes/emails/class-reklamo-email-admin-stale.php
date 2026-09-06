<?php
/**
 * Admin: the customer ignored every reminder — the order needs a human.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Admin_Stale extends Reklamo_Email {

	protected $with_button = true;

	public function __construct() {
		$this->id             = 'reklamo_admin_stale';
		$this->customer_email = false;
		$this->title          = __( 'Order waiting — admin (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent to the shop when a customer has not reacted to any reminder (mockup approval, deposit or final payment).', 'reklamo-core' );
		$this->placeholders   = array(
			'{status}'       => '',
			'{waiting_days}' => '',
		);
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
		return __( '[{site_title}] Order {order_number} is waiting for the customer ({waiting_days} days)', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'No reaction from the customer', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Order {order_number} has been in "{status}" for {waiting_days} days and the customer has not responded to any reminder. Consider calling them, or cancel the order from the order screen.', 'reklamo-core' );
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
			$this->recipient                      = $this->get_option( 'recipient', get_option( 'admin_email' ) );
			$this->button_url                     = $order->get_edit_order_url();
			$this->placeholders['{status}']       = wc_get_order_status_name( $order->get_status() );
			$modified                             = $order->get_date_modified();
			$this->placeholders['{waiting_days}'] = (string) ( $modified ? max( 0, (int) floor( ( time() - $modified->getTimestamp() ) / DAY_IN_SECONDS ) ) : 0 );
			$sent                                 = $this->dispatch();
		}
		$this->restore_locale();
		return ! empty( $sent );
	}
}

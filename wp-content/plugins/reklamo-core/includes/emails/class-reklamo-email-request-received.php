<?php
/**
 * Customer email: request received, no payment taken, mockup within 24 business hours.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Request_Received extends WC_Email {

	public function __construct() {
		$this->id             = 'reklamo_request_received';
		$this->customer_email = true;
		$this->title          = __( 'Request received (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent to the customer when a request with a logo is placed. Confirms that no payment is taken yet.', 'reklamo-core' );
		$this->template_html  = 'emails/reklamo-request-received.php';
		$this->template_plain = 'emails/plain/reklamo-request-received.php';
		$this->template_base  = REKLAMO_PATH . 'templates/';
		$this->placeholders   = array(
			'{order_number}'        => '',
			'{order_date}'          => '',
			'{customer_first_name}' => '',
		);

		add_action( 'woocommerce_order_status_' . Reklamo_Statuses::RECEIVED . '_notification', array( $this, 'trigger' ), 10, 2 );

		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'We received your request {order_number} — {site_title}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'Thank you, {customer_first_name}!', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'We received your request and your logo. Our designer is now preparing a mockup of the selected package — you will get it by email for approval within 24 business hours.', 'reklamo-core' );
	}

	public function get_default_additional_content() {
		return __( 'No payment is taken at this stage. After you approve the mockup we will send our bank details and a request for a 50% deposit.', 'reklamo-core' );
	}

	/** Core gives us enabled/subject/heading/additional_content; add the body text. */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['intro_text'] = array(
			'title'       => __( 'Main text', 'reklamo-core' ),
			'type'        => 'textarea',
			'css'         => 'width:400px; height:120px;',
			'description' => __( 'Placeholders: {order_number}, {order_date}, {customer_first_name}, {site_title}', 'reklamo-core' ),
			'default'     => $this->get_default_intro_text(),
			'desc_tip'    => true,
		);
	}

	/**
	 * @param int            $order_id Order ID.
	 * @param WC_Order|false $order    Order.
	 */
	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();

		if ( $order_id && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof WC_Order ) {
			$this->object                                = $order;
			$this->recipient                             = $order->get_billing_email();
			$this->placeholders['{order_number}']        = $order->get_order_number();
			$this->placeholders['{order_date}']          = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{customer_first_name}'] = $order->get_billing_first_name();
		}

		// The order returns to rq-received after every "request changes"; this email is for the
		// first arrival only. Mark the order so re-entries stay silent.
		if ( $order instanceof WC_Order && $order->get_meta( '_reklamo_received_email_sent' ) ) {
			$this->restore_locale();
			return;
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			if ( $order instanceof WC_Order ) {
				$order->update_meta_data( '_reklamo_received_email_sent', current_time( 'mysql', true ) );
				$order->save();
			}
		}

		$this->restore_locale();
	}

	private function template_args( bool $plain ): array {
		return array(
			'order'              => $this->object,
			'email_heading'      => $this->get_heading(),
			'intro_text'         => $this->format_string( $this->get_option( 'intro_text', $this->get_default_intro_text() ) ),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain,
			'email'              => $this,
		);
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}
}

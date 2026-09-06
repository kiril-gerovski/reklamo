<?php
/**
 * Base for our emails: one generic template pair, an owner-editable "main text", and
 * optional blocks (button, bank details, customer-submitted details) each subclass opts
 * into. Subclasses only define ids, defaults and the trigger.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

abstract class Reklamo_Email extends WC_Email {

	/** @var string */
	protected $button_url = '';

	/** @var bool Prefix the subject as a reminder. */
	protected $reminder = false;

	/** @var bool Print bank details + amount block. */
	protected $with_bank = false;

	/** @var bool Print the customer's invoice/delivery details block. */
	protected $with_details = false;

	/** @var bool Show a button (label owner-editable). */
	protected $with_button = false;

	public function __construct() {
		$this->template_html  = 'emails/reklamo-generic.php';
		$this->template_plain = 'emails/plain/reklamo-generic.php';
		$this->template_base  = REKLAMO_PATH . 'templates/';
		$this->placeholders   = array_merge(
			array(
				'{order_number}'        => '',
				'{order_date}'          => '',
				'{customer_first_name}' => '',
				'{deposit_amount}'      => '',
				'{balance_amount}'      => '',
				'{order_total}'         => '',
				'{deposit_pct}'         => '',
				'{revision}'            => '',
			),
			$this->placeholders
		);
		parent::__construct();
	}

	abstract public function get_default_intro_text(): string;

	public function get_default_button_label(): string {
		return '';
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['intro_text'] = array(
			'title'       => __( 'Main text', 'reklamo-core' ),
			'type'        => 'textarea',
			'css'         => 'width:400px; height:120px;',
			'description' => __( 'Placeholders: {order_number}, {order_date}, {customer_first_name}, {site_title}, {deposit_amount}, {balance_amount}, {order_total}, {deposit_pct}, {revision}', 'reklamo-core' ),
			'default'     => $this->get_default_intro_text(),
			'desc_tip'    => true,
		);
		if ( $this->with_button ) {
			$this->form_fields['button_label'] = array(
				'title'   => __( 'Button label', 'reklamo-core' ),
				'type'    => 'text',
				'default' => $this->get_default_button_label(),
			);
		}
	}

	/** Fill placeholders from the order; call from trigger() before send. */
	protected function prepare( WC_Order $order ): void {
		$deposit = (float) $order->get_meta( '_reklamo_deposit_amount' );
		if ( $deposit <= 0 ) {
			$deposit = Reklamo_Money::deposit( (float) $order->get_total(), (int) Reklamo_Settings::get( 'deposit_pct', '50' ) );
		}
		$this->object                                = $order;
		$this->placeholders['{order_number}']        = $order->get_order_number();
		$this->placeholders['{order_date}']          = wc_format_datetime( $order->get_date_created() );
		$this->placeholders['{customer_first_name}'] = $order->get_billing_first_name();
		$this->placeholders['{deposit_amount}']      = wp_strip_all_tags( wc_price( $deposit, array( 'currency' => $order->get_currency() ) ) );
		$this->placeholders['{balance_amount}']      = wp_strip_all_tags( wc_price( Reklamo_Money::balance( (float) $order->get_total(), $deposit ), array( 'currency' => $order->get_currency() ) ) );
		$this->placeholders['{order_total}']         = wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
		$this->placeholders['{deposit_pct}']         = Reklamo_Settings::get( 'deposit_pct', '50' );
	}

	public function get_subject() {
		$subject = parent::get_subject();
		return $this->reminder ? sprintf( /* translators: %s: original subject */ __( 'Reminder: %s', 'reklamo-core' ), $subject ) : $subject;
	}

	/** @return bool True when the transport accepted the message; failures are noted on the order. */
	protected function dispatch(): bool {
		return $this->object instanceof WC_Order && Reklamo_Mail::deliver( $this, $this->object );
	}

	private function template_args( bool $plain ): array {
		$order = $this->object;
		return array(
			'order'              => $order,
			'email_heading'      => $this->get_heading(),
			'intro_text'         => $this->format_string( $this->get_option( 'intro_text', $this->get_default_intro_text() ) ),
			'button_label'       => $this->with_button ? $this->get_option( 'button_label', $this->get_default_button_label() ) : '',
			'button_url'         => $this->button_url,
			'bank_html'          => $this->with_bank && $order ? Reklamo_Settings::bank_details_html( sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) ) : '',
			'bank_rows'          => $this->with_bank ? Reklamo_Settings::bank_details() : array(),
			'amount_label'       => $this->amount_label(),
			'amount'             => $this->amount_value(),
			'details'            => $this->with_details && $order ? Reklamo_Approval::details( $order ) : array(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => ! $this->customer_email,
			'plain_text'         => $plain,
			'email'              => $this,
		);
	}

	/** Which amount the bank block asks for; subclasses override. */
	protected function amount_label(): string {
		return '';
	}

	protected function amount_value(): string {
		return '';
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}
}

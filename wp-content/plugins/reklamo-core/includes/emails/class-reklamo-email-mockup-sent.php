<?php
/**
 * Customer email: mockups are ready — one product or several sent together, each with its
 * own one-time approval link.
 *
 * Triggered directly by the admin "send mockup" action (the approval secret only exists
 * in that request), not by a status hook.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Mockup_Sent extends WC_Email {

	/** @var string */
	protected $approval_url = '';

	/** @var int */
	protected $revision = 1;

	/** @var array<int,array{name:string,revision:int,url:string}> */
	protected $entries = array();

	/** @var bool */
	protected $reminder = false;

	public function __construct() {
		$this->id             = 'reklamo_mockup_sent';
		$this->customer_email = true;
		$this->title          = __( 'Mockup sent (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent to the customer when the designer uploads a mockup. Contains the one-time approval link.', 'reklamo-core' );
		$this->template_html  = 'emails/reklamo-mockup-sent.php';
		$this->template_plain = 'emails/plain/reklamo-mockup-sent.php';
		$this->template_base  = REKLAMO_PATH . 'templates/';
		$this->placeholders   = array(
			'{order_number}'        => '',
			'{customer_first_name}' => '',
			'{revision}'            => '',
			'{products}'            => '',
			'{approval_url}'        => '',
			'{deposit_pct}'         => '',
		);

		parent::__construct();
	}

	public function get_subject() {
		$subject = parent::get_subject();
		return $this->reminder ? sprintf( /* translators: %s: original subject */ __( 'Reminder: %s', 'reklamo-core' ), $subject ) : $subject;
	}

	public function get_default_subject() {
		return __( 'Your mockup for order {order_number} is ready — {site_title}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'Your mockup is ready', 'reklamo-core' );
	}

	public function get_default_intro_text(): string {
		return __( 'Hello {customer_first_name}, our designer has prepared a mockup of {products}. Please take a look and approve it, or tell us what to change. The links are personal and valid for 14 days.', 'reklamo-core' );
	}

	public function get_default_button_label(): string {
		return __( 'View and approve the mockup', 'reklamo-core' );
	}

	public function get_default_additional_content() {
		return __( 'No payment is taken at this stage. After approval we will send our bank details and a request for a {deposit_pct}% deposit.', 'reklamo-core' );
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['intro_text']   = array(
			'title'       => __( 'Main text', 'reklamo-core' ),
			'type'        => 'textarea',
			'css'         => 'width:400px; height:120px;',
			'description' => __( 'Placeholders: {order_number}, {customer_first_name}, {products}, {revision}, {site_title}', 'reklamo-core' ),
			'default'     => $this->get_default_intro_text(),
			'desc_tip'    => true,
		);
		$this->form_fields['button_label'] = array(
			'title'   => __( 'Button label', 'reklamo-core' ),
			'type'    => 'text',
			'default' => $this->get_default_button_label(),
		);
	}

	/**
	 * @param int            $order_id Order ID.
	 * @param WC_Order|false $order    Order.
	 * @param array          $entries  One per product: name, revision, signed one-time URL.
	 * @param bool           $reminder Prefix the subject as a reminder.
	 * @return bool True when the transport accepted the message.
	 */
	public function trigger( $order_id, $order = false, array $entries = array(), bool $reminder = false ) {
		$this->setup_locale();

		if ( $order_id && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof WC_Order ) {
			$this->object                                = $order;
			$this->recipient                             = $order->get_billing_email();
			$this->entries                               = array_values( $entries );
			$this->approval_url                          = (string) ( $this->entries[0]['url'] ?? '' );
			$this->revision                              = (int) ( $this->entries[0]['revision'] ?? 1 );
			$this->reminder                              = $reminder;
			$this->placeholders['{order_number}']        = $order->get_order_number();
			$this->placeholders['{customer_first_name}'] = $order->get_billing_first_name();
			$this->placeholders['{revision}']            = (string) $this->revision;
			$this->placeholders['{products}']            = implode(
				', ',
				array_map(
					/* translators: 1: product name, 2: mockup revision */
					static fn( array $e ): string => sprintf( __( '%1$s (mockup #%2$d)', 'reklamo-core' ), $e['name'], $e['revision'] ),
					$this->entries
				)
			);
			$this->placeholders['{approval_url}'] = $this->approval_url;
			$this->placeholders['{deposit_pct}']  = Reklamo_Settings::get( 'deposit_pct', '100' );
		}

		$sent = $order instanceof WC_Order && $this->approval_url && Reklamo_Mail::deliver( $this, $order );

		$this->restore_locale();
		return $sent;
	}

	private function template_args( bool $plain ): array {
		return array(
			'order'              => $this->object,
			'email_heading'      => $this->get_heading(),
			'intro_text'         => $this->format_string( $this->get_option( 'intro_text', $this->get_default_intro_text() ) ),
			'button_label'       => $this->get_option( 'button_label', $this->get_default_button_label() ),
			'approval_url'       => $this->approval_url,
			'revision'           => $this->revision,
			'entries'            => $this->entries,
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

<?php
/**
 * Customer: we received your inquiry, with a copy of what they sent.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Inquiry_Received extends WC_Email {

	/** @var array<string,string> label => value */
	protected $rows = array();

	public function __construct() {
		$this->id             = 'reklamo_inquiry_received';
		$this->customer_email = true;
		$this->title          = __( 'Inquiry received (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent to the visitor after the offer inquiry form, with a copy of the inquiry.', 'reklamo-core' );
		$this->template_html  = 'emails/reklamo-inquiry.php';
		$this->template_plain = 'emails/plain/reklamo-inquiry.php';
		$this->template_base  = REKLAMO_PATH . 'templates/';
		$this->placeholders   = array( '{name}' => '' );
		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'We received your inquiry — {site_title}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'Thank you for your inquiry!', 'reklamo-core' );
	}

	/** @param array $v Inquiry values. */
	public function trigger( array $v ): bool {
		$this->setup_locale();
		$this->rows                   = Reklamo_Inquiry::rows( $v );
		$this->placeholders['{name}'] = $v['name'];
		$this->recipient              = $v['email'];
		$sent                         = $this->is_enabled() && $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), array() );
		$this->restore_locale();
		return (bool) $sent;
	}

	private function args( bool $plain ): array {
		return array(
			'email_heading' => $this->get_heading(),
			/* translators: %s: customer name */
			'intro'         => sprintf( __( 'Hello %s, we received your inquiry and will send you an offer within one business day. Here is what you sent us:', 'reklamo-core' ), $this->placeholders['{name}'] ),
			'rows'          => $this->rows,
			'outro'         => __( 'If you need to add something, just reply to this email.', 'reklamo-core' ),
			'sent_to_admin' => false,
			'plain_text'    => $plain,
			'email'         => $this,
		);
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->args( false ), '', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->args( true ), '', $this->template_base );
	}
}

<?php
/**
 * Admin: an inquiry for a tailored offer from the site. "Reply" goes to the customer.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Email_Admin_Inquiry extends WC_Email {

	/** @var array<string,string> label => value */
	protected $rows = array();

	/** @var string */
	protected $reply_to = '';

	public function __construct() {
		$this->id             = 'reklamo_admin_inquiry';
		$this->customer_email = false;
		$this->title          = __( 'Inquiry for an offer — admin (Reklamo)', 'reklamo-core' );
		$this->description    = __( 'Sent to the shop when a visitor sends the offer inquiry form. Replying goes to the visitor.', 'reklamo-core' );
		$this->template_html  = 'emails/reklamo-inquiry.php';
		$this->template_plain = 'emails/plain/reklamo-inquiry.php';
		$this->template_base  = REKLAMO_PATH . 'templates/';
		$this->placeholders   = array( '{name}' => '' );
		parent::__construct();
		$this->recipient = $this->get_option( 'recipient', get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ) );
	}

	public function get_default_subject() {
		return __( '[{site_title}] Inquiry for an offer from {name}', 'reklamo-core' );
	}

	public function get_default_heading() {
		return __( 'New inquiry for an offer', 'reklamo-core' );
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields = array_merge(
			array(
				'recipient' => array(
					'title'       => __( 'Recipient(s)', 'reklamo-core' ),
					'type'        => 'text',
					/* translators: %s: admin email */
					'description' => sprintf( __( 'Comma-separated. Defaults to %s.', 'reklamo-core' ), '<code>' . esc_html( get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ) ) . '</code>' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			),
			$this->form_fields
		);
	}

	/** Replying answers the visitor: WooCommerce's own Reply-To (the shop) is replaced, not doubled. */
	public function get_headers() {
		$headers = (string) parent::get_headers();
		if ( ! $this->reply_to ) {
			return $headers;
		}
		$headers = (string) preg_replace( '/^Reply-to:.*\r?\n/mi', '', $headers );
		return $headers . 'Reply-To: ' . $this->reply_to . "\r\n";
	}

	/** @param array $v Inquiry values. */
	public function trigger( array $v ): bool {
		$this->setup_locale();
		$this->rows                   = Reklamo_Inquiry::rows( $v );
		$this->reply_to               = $v['name'] ? sprintf( '%s <%s>', str_replace( array( '<', '>', '"', "\r", "\n" ), '', $v['name'] ), $v['email'] ) : $v['email'];
		$this->placeholders['{name}'] = $v['name'];
		$this->recipient              = $this->get_option( 'recipient', '' ) ? $this->get_option( 'recipient' ) : get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );
		$to                           = $this->get_recipient();
		$sent                         = $this->is_enabled() && $to && $this->send( $to, $this->get_subject(), $this->get_content(), $this->get_headers(), array() );
		$this->restore_locale();
		return (bool) $sent;
	}

	private function args( bool $plain ): array {
		return array(
			'email_heading' => $this->get_heading(),
			'intro'         => __( 'A visitor asked for a tailored offer. Reply to this email to answer them directly.', 'reklamo-core' ),
			'rows'          => $this->rows,
			'outro'         => '',
			'sent_to_admin' => true,
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

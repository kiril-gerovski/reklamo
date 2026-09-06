<?php
/**
 * Mockup sent — HTML. Override: yourtheme/woocommerce/emails/reklamo-mockup-sent.php
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $intro_text
 * @var string   $button_label
 * @var string   $approval_url
 * @var int      $revision
 * @var string   $additional_content
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php echo wp_kses_post( wpautop( wptexturize( $intro_text ) ) ); ?></p>

<p style="text-align:center; margin: 28px 0;">
	<a href="<?php echo esc_url( $approval_url ); ?>" style="display:inline-block; background:#b8892b; color:#ffffff; text-decoration:none; font-weight:600; letter-spacing:.06em; text-transform:uppercase; font-size:13px; padding:14px 26px; border-radius:4px;"><?php echo esc_html( $button_label ); ?></a>
</p>

<p style="font-size:12px; color:#555;">
	<?php esc_html_e( 'If the button does not work, copy this link into your browser:', 'reklamo-core' ); ?><br>
	<a href="<?php echo esc_url( $approval_url ); ?>"><?php echo esc_html( $approval_url ); ?></a>
</p>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );

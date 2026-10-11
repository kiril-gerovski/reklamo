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
 * @var array    $entries  One per product: name, revision, url.
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

<?php foreach ( $entries as $reklamo_entry ) : ?>
	<?php if ( count( $entries ) > 1 ) : ?>
		<p style="margin: 24px 0 0; font-weight:600;"><?php echo esc_html( sprintf( /* translators: 1: product name, 2: mockup revision */ __( '%1$s (mockup #%2$d)', 'reklamo-core' ), $reklamo_entry['name'], $reklamo_entry['revision'] ) ); ?></p>
	<?php endif; ?>
	<p style="text-align:center; margin: <?php echo count( $entries ) > 1 ? '12px' : '28px'; ?> 0 28px;">
		<a href="<?php echo esc_url( $reklamo_entry['url'] ); ?>" style="display:inline-block; background:#b8892b; color:#ffffff; text-decoration:none; font-weight:600; letter-spacing:.06em; text-transform:uppercase; font-size:13px; padding:14px 26px; border-radius:4px;"><?php echo esc_html( $button_label ); ?></a>
	</p>
	<p style="font-size:12px; color:#555;">
		<?php esc_html_e( 'If the button does not work, copy this link into your browser:', 'reklamo-core' ); ?><br>
		<a href="<?php echo esc_url( $reklamo_entry['url'] ); ?>"><?php echo esc_html( $reklamo_entry['url'] ); ?></a>
	</p>
<?php endforeach; ?>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );

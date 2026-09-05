<?php
/**
 * Generic Reklamo email — HTML. Override: yourtheme/woocommerce/emails/reklamo-generic.php
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $intro_text
 * @var string   $button_label
 * @var string   $button_url
 * @var string   $bank_html
 * @var array    $bank_rows
 * @var string   $amount_label
 * @var string   $amount
 * @var array    $details
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

<?php if ( $button_label && $button_url ) : ?>
	<p style="text-align:center; margin: 28px 0;">
		<a href="<?php echo esc_url( $button_url ); ?>" style="display:inline-block; background:#b8892b; color:#ffffff; text-decoration:none; font-weight:600; letter-spacing:.06em; text-transform:uppercase; font-size:13px; padding:14px 26px; border-radius:4px;"><?php echo esc_html( $button_label ); ?></a>
	</p>
	<p style="font-size:12px; color:#555;">
		<?php esc_html_e( 'If the button does not work, copy this link into your browser:', 'reklamo-core' ); ?><br>
		<a href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_url ); ?></a>
	</p>
<?php endif; ?>

<?php if ( $bank_rows ) : ?>
	<table cellspacing="0" cellpadding="8" border="0" style="width:100%; margin: 16px 0 24px; border:1px solid #e8e2d6; border-radius:6px; background:#faf8f4; font-size:14px;">
		<?php if ( $amount_label && $amount ) : ?>
			<tr><th align="left" style="color:#555;"><?php echo esc_html( $amount_label ); ?></th><td style="font-size:18px; font-weight:600; color:#9a7020;"><?php echo esc_html( $amount ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $bank_rows as $reklamo_label => $reklamo_value ) : ?>
			<tr><th align="left" style="color:#555;"><?php echo esc_html( $reklamo_label ); ?></th><td><?php echo esc_html( $reklamo_value ); ?></td></tr>
		<?php endforeach; ?>
		<tr><th align="left" style="color:#555;"><?php esc_html_e( 'Payment reference', 'reklamo-core' ); ?></th><td><strong><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) ); ?></strong></td></tr>
	</table>
<?php endif; ?>

<?php if ( $details ) : ?>
	<table cellspacing="0" cellpadding="6" border="0" style="width:100%; margin: 16px 0 24px; font-size:14px;">
		<?php
		$reklamo_labels = array(
			'company'   => __( 'Company', 'reklamo-core' ),
			'eik'       => __( 'Company ID (ЕИК)', 'reklamo-core' ),
			'vat'       => __( 'VAT no.', 'reklamo-core' ),
			'mol'       => __( 'Responsible person (МОЛ)', 'reklamo-core' ),
			'phone'     => __( 'Phone', 'reklamo-core' ),
			'address_1' => __( 'Address', 'reklamo-core' ),
			'city'      => __( 'City', 'reklamo-core' ),
			'postcode'  => __( 'Postcode', 'reklamo-core' ),
			'note'      => __( 'Delivery note', 'reklamo-core' ),
		);
		foreach ( $reklamo_labels as $reklamo_key => $reklamo_label ) :
			if ( empty( $details[ $reklamo_key ] ) ) {
				continue;
			}
			?>
			<tr><th align="left" style="color:#555; width:40%;"><?php echo esc_html( $reklamo_label ); ?></th><td><?php echo esc_html( $details[ $reklamo_key ] ); ?></td></tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );

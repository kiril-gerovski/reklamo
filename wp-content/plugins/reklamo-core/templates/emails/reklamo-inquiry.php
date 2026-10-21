<?php
/**
 * Inquiry for an offer — HTML, for both the shop and the visitor.
 *
 * @var string   $email_heading
 * @var string   $intro
 * @var array    $rows  label => value
 * @var string   $outro
 * @var WC_Email $email
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php echo esc_html( $intro ); ?></p>

<table cellspacing="0" cellpadding="8" border="0" style="width:100%; margin: 16px 0 24px; border:1px solid #e8e2d6; border-radius:6px; background:#faf8f4; font-size:14px;">
	<?php foreach ( $rows as $reklamo_label => $reklamo_value ) : ?>
		<tr><th align="left" valign="top" style="color:#555; width:35%;"><?php echo esc_html( $reklamo_label ); ?></th><td><?php echo nl2br( esc_html( $reklamo_value ) ); ?></td></tr>
	<?php endforeach; ?>
</table>

<?php if ( $outro ) : ?>
	<p><?php echo esc_html( $outro ); ?></p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_footer', $email );

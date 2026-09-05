<?php
/**
 * Generic Reklamo email — plain text.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
echo esc_html( wp_strip_all_tags( $intro_text ) ) . "\n\n";

if ( $button_label && $button_url ) {
	echo esc_html( wp_strip_all_tags( $button_label ) ) . ":\n" . esc_url( $button_url ) . "\n\n";
}
if ( $bank_rows ) {
	if ( $amount_label && $amount ) {
		echo esc_html( $amount_label . ': ' . $amount ) . "\n";
	}
	foreach ( $bank_rows as $reklamo_label => $reklamo_value ) {
		echo esc_html( $reklamo_label . ': ' . $reklamo_value ) . "\n";
	}
	echo esc_html( __( 'Payment reference', 'reklamo-core' ) . ': ' . sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) ) . "\n\n";
}
if ( $details ) {
	foreach ( $details as $reklamo_key => $reklamo_value ) {
		if ( $reklamo_value && 'submitted_at' !== $reklamo_key ) {
			echo esc_html( $reklamo_key . ': ' . $reklamo_value ) . "\n";
		}
	}
	echo "\n";
}

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n\n----------------------------------------\n\n";
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}
echo esc_html( wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );

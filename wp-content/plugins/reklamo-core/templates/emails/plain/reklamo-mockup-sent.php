<?php
/**
 * Mockup sent — plain text.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
echo esc_html( wp_strip_all_tags( $intro_text ) ) . "\n\n";
foreach ( $entries as $reklamo_entry ) {
	if ( count( $entries ) > 1 ) {
		/* translators: 1: product name, 2: mockup revision */
		echo esc_html( sprintf( __( '%1$s (mockup #%2$d)', 'reklamo-core' ), $reklamo_entry['name'], $reklamo_entry['revision'] ) ) . "\n";
	}
	echo esc_html( wp_strip_all_tags( $button_label ) ) . ":\n" . esc_url( $reklamo_entry['url'] ) . "\n\n";
}

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
echo "\n";
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

echo "\n\n----------------------------------------\n\n";
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}
echo esc_html( wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );

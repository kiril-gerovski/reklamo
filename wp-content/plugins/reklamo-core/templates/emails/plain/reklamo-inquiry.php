<?php
/**
 * Inquiry for an offer — plain text.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
echo esc_html( $intro ) . "\n\n";
foreach ( $rows as $reklamo_label => $reklamo_value ) {
	echo esc_html( $reklamo_label . ': ' . $reklamo_value ) . "\n";
}
echo "\n";
if ( $outro ) {
	echo esc_html( $outro ) . "\n\n";
}
echo esc_html( wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );

<?php
/**
 * Dispatch: the waybill number and the invoice the shop enters when it completes an order.
 * Both ride on WooCommerce's own "Completed order" email (the last one the customer gets):
 * the waybill with a tracking link, the invoice as an attachment. The order page shows them too.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Dispatch {

	const META_WAYBILL       = '_reklamo_waybill';
	const META_COURIER       = '_reklamo_waybill_courier';
	const INVOICE_KIND       = 'invoice';
	const INVOICE_EXTENSIONS = array( 'pdf', 'png', 'jpg', 'jpeg' );

	/** Public tracking pages; a courier without one (Pigeon) gets the number only. */
	const TRACKING = array(
		'speedy' => 'https://www.speedy.bg/bg/track-shipment?shipmentNumber=%s',
		'econt'  => 'https://www.econt.com/services/track-shipment/%s',
		'boxnow' => 'https://boxnow.bg/?track=%s',
	);

	/** @var array<string,string> attachment path => file name the customer sees */
	private static $names = array();

	public static function init(): void {
		add_filter( 'woocommerce_email_attachments', array( __CLASS__, 'attach_invoice' ), 10, 4 );
		add_action( 'phpmailer_init', array( __CLASS__, 'name_attachments' ), 20 );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'email_block' ), 5, 4 );
	}

	/** Letters, digits, spaces and dashes, 4–40 characters; '' when empty, null when invalid. */
	public static function clean_waybill( string $raw ): ?string {
		$w = strtoupper( trim( preg_replace( '/\s+/', ' ', $raw ) ) );
		if ( '' === $w ) {
			return '';
		}
		return preg_match( '/^[A-Z0-9][A-Z0-9 -]{2,38}[A-Z0-9]$/', $w ) ? $w : null;
	}

	public static function tracking_url( string $courier, string $waybill ): string {
		return isset( self::TRACKING[ $courier ] ) && '' !== $waybill ? sprintf( self::TRACKING[ $courier ], rawurlencode( str_replace( array( ' ', '-' ), '', $waybill ) ) ) : '';
	}

	/** @return array{courier:string,courier_name:string,waybill:string,url:string}|null */
	public static function shipment( WC_Order $order ): ?array {
		$waybill = (string) $order->get_meta( self::META_WAYBILL );
		if ( '' === $waybill ) {
			return null;
		}
		$courier = (string) $order->get_meta( self::META_COURIER );
		return array(
			'courier'      => $courier,
			'courier_name' => Reklamo_Approval::COURIERS[ $courier ] ?? '',
			'waybill'      => $waybill,
			'url'          => self::tracking_url( $courier, $waybill ),
		);
	}

	/** The newest invoice file still on disk. */
	public static function invoice( WC_Order $order ): ?object {
		foreach ( array_reverse( Reklamo_Storage::for_order( $order->get_id(), self::INVOICE_KIND ) ) as $row ) {
			if ( '' !== (string) $row->path && is_readable( Reklamo_Storage::abs_path( $row ) ) ) {
				return $row;
			}
		}
		return null;
	}

	public static function invoice_name( WC_Order $order, object $row ): string {
		/* translators: %s: order number */
		return sprintf( __( 'Invoice-%s', 'reklamo-core' ), $order->get_order_number() ) . '.' . $row->ext;
	}

	/**
	 * @param array          $attachments Paths.
	 * @param string         $email_id    Email ID.
	 * @param WC_Order|mixed $order       Order.
	 */
	public static function attach_invoice( $attachments, $email_id, $order, $email = null ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$attachments = (array) $attachments;
		if ( 'customer_completed_order' !== $email_id || ! $order instanceof WC_Order ) {
			return $attachments;
		}
		$row = self::invoice( $order );
		if ( $row ) {
			$path                 = Reklamo_Storage::abs_path( $row );
			$attachments[]        = $path;
			self::$names[ $path ] = self::invoice_name( $order, $row );
		}
		return $attachments;
	}

	/** Stored files have random names; give the invoice a readable one in the email. */
	public static function name_attachments( $phpmailer ): void {
		if ( ! self::$names ) {
			return;
		}
		$attached = $phpmailer->getAttachments();
		$phpmailer->clearAttachments();
		foreach ( $attached as $a ) {
			$phpmailer->addAttachment( $a[0], self::$names[ $a[0] ] ?? $a[2], $a[3], $a[4], $a[6] );
		}
		self::$names = array();
	}

	public static function email_block( $order, $sent_to_admin, $plain_text, $email = null ): void {
		if ( ! $order instanceof WC_Order || ! $email || 'customer_completed_order' !== $email->id ) {
			return;
		}
		$ship    = self::shipment( $order );
		$invoice = self::invoice( $order );
		if ( ! $ship && ! $invoice ) {
			return;
		}
		$lines = array();
		if ( $ship ) {
			$lines[] = $ship['courier_name']
				/* translators: 1: courier, 2: waybill number */
				? sprintf( __( 'Your order is on its way with %1$s, waybill no. %2$s.', 'reklamo-core' ), $ship['courier_name'], $ship['waybill'] )
				/* translators: %s: waybill number */
				: sprintf( __( 'Your order is on its way, waybill no. %s.', 'reklamo-core' ), $ship['waybill'] );
		}
		if ( $invoice ) {
			$lines[] = __( 'The invoice is attached to this email.', 'reklamo-core' );
		}
		if ( $plain_text ) {
			echo esc_html( implode( ' ', $lines ) ) . "\n";
			if ( $ship && $ship['url'] ) {
				echo esc_html__( 'Track the parcel', 'reklamo-core' ) . ': ' . esc_url( $ship['url'] ) . "\n";
			}
			echo "\n";
			return;
		}
		echo '<div style="margin: 0 0 24px; padding: 12px 14px; border-left: 3px solid #b8892b; background: #faf8f4;"><p style="margin: 0;"><strong>' . esc_html( implode( ' ', $lines ) ) . '</strong></p>';
		if ( $ship && $ship['url'] ) {
			echo '<p style="margin: 8px 0 0;"><a href="' . esc_url( $ship['url'] ) . '" style="color: #9a7020; font-weight: 600;">' . esc_html__( 'Track the parcel', 'reklamo-core' ) . ' →</a></p>';
		}
		echo '</div>';
	}
}

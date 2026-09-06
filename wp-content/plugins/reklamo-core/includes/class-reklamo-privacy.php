<?php
/**
 * GDPR tooling (Tools → Export / Erase Personal Data) for what WooCommerce does not know
 * about: the customer's logo and mockup files, the emailed links, and the tracking URL
 * kept in order meta. WooCommerce handles the order itself.
 *
 * Erasure touches only closed orders (completed / cancelled / refunded); files of a live
 * order are needed to fulfil it and are reported as retained.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Privacy {

	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function register_exporter( array $exporters ): array {
		$exporters['reklamo-files'] = array(
			'exporter_friendly_name' => __( 'Reklamo order files and links', 'reklamo-core' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( array $erasers ): array {
		$erasers['reklamo-files'] = array(
			'eraser_friendly_name' => __( 'Reklamo order files and links', 'reklamo-core' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/** @return int[] */
	private static function orders_for( string $email ): array {
		return wc_get_orders(
			array(
				'billing_email' => $email,
				'limit'         => -1,
				'return'        => 'ids',
			)
		);
	}

	public static function export( string $email, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$items = array();
		foreach ( self::orders_for( $email ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$data = array();
			foreach ( Reklamo_Storage::for_order( $order_id ) as $f ) {
				$data[] = array(
					'name'  => 'logo' === $f->kind ? __( 'Logo file', 'reklamo-core' ) : sprintf( /* translators: %d: revision */ __( 'Mockup #%d', 'reklamo-core' ), (int) $f->revision ),
					'value' => sprintf( '%s — %s, %s (%s)', $f->orig_name, strtoupper( $f->ext ), size_format( (int) $f->bytes ), '' === (string) $f->path ? __( 'deleted', 'reklamo-core' ) : $f->created_at ),
				);
				if ( $f->created_ip ) {
					$data[] = array(
						'name'  => __( 'Upload IP address', 'reklamo-core' ),
						'value' => $f->created_ip,
					);
				}
			}
			foreach ( Reklamo_Approval::for_order( $order_id ) as $t ) {
				$data[] = array(
					'name'  => __( 'Emailed link', 'reklamo-core' ),
					'value' => sprintf( '%s — %s%s', $t->purpose, $t->created_at, $t->used_at ? ', ' . __( 'used', 'reklamo-core' ) . ' ' . $t->used_at : '' ),
				);
			}
			if ( $data ) {
				$items[] = array(
					'group_id'    => 'reklamo_orders',
					'group_label' => __( 'Reklamo order files and links', 'reklamo-core' ),
					'item_id'     => 'reklamo-order-' . $order_id,
					'data'        => array_merge(
						array(
							array(
								'name'  => __( 'Order', 'reklamo-core' ),
								'value' => $order->get_order_number(),
							),
						),
						$data
					),
				);
			}
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	public static function erase( string $email, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		global $wpdb;
		$removed  = false;
		$retained = false;
		$messages = array();
		foreach ( self::orders_for( $email ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			if ( ! $order->has_status( array( 'completed', 'cancelled', 'refunded' ) ) ) {
				$retained = true;
				/* translators: %s: order number */
				$messages[] = sprintf( __( 'Order %s is still open; its files are kept until it is closed.', 'reklamo-core' ), $order->get_order_number() );
				continue;
			}
			foreach ( Reklamo_Storage::for_order( $order_id ) as $f ) {
				Reklamo_Storage::delete( $f, false );
				$removed = true;
			}
			$wpdb->delete( $wpdb->prefix . 'reklamo_tokens', array( 'order_id' => $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$order->delete_meta_data( Reklamo_Tracking::META_URL );
			$order->delete_meta_data( '_reklamo_change_requests' );
			$order->delete_meta_data( '_reklamo_last_change_request' );
			$order->save();
			$removed = true;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}
}

<?php
/**
 * Personal data the plugin adds on top of WooCommerce, and the three ways it leaves:
 *  - Tools → Export / Erase Personal Data (exporter + eraser registered here),
 *  - anonymisation of old closed orders (Reklamo_Cleanup calls WooCommerce's own
 *    remove_order_personal_data(), which fires the hook this class strips its meta on),
 *  - deletion of an order (Reklamo_Cleanup::purge_order()).
 *
 * Erasure and anonymisation touch only closed orders (completed / cancelled / refunded);
 * a live order needs its data to be fulfilled and is reported as retained.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Privacy {

	/** Order meta that identifies or was written by the customer. Removed on anonymisation and erasure. */
	const PERSONAL_META = array(
		'_reklamo_track_url',
		'_reklamo_change_requests',
		'_reklamo_last_change_request',
		'_reklamo_consent_ip',
		'_reklamo_eik',
		'_reklamo_vat',
		'_reklamo_mol',
		'_reklamo_delivery_note',
		'_reklamo_customer_type',
	);

	/** Comment meta marking an order note that quotes the customer or records their IP. */
	const NOTE_FLAG = '_reklamo_personal';

	const CLOSED = array( 'completed', 'cancelled', 'refunded' );

	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'woocommerce_privacy_before_remove_order_personal_data', array( __CLASS__, 'strip_order' ) );
	}

	public static function register_exporter( array $exporters ): array {
		$exporters['reklamo-files'] = array(
			'exporter_friendly_name' => __( 'Reklamo order files, consent and links', 'reklamo-core' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( array $erasers ): array {
		$erasers['reklamo-files'] = array(
			'eraser_friendly_name' => __( 'Reklamo order files, consent and links', 'reklamo-core' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/** Keyed hash of the client IP: proves "same connection" without keeping the address. */
	public static function ip_hash( string $ip ): string {
		return '' === $ip ? '' : substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 16 );
	}

	/** Consent moment, text version and connection hash, written once when the request is made. */
	public static function record_consent( WC_Order $order, bool $waiver ): void {
		$now = current_time( 'mysql', true );
		$order->update_meta_data( '_reklamo_consent_at', $now );
		$order->update_meta_data( '_reklamo_consent_version', Reklamo_Settings::get( 'legal_version' ) );
		$order->update_meta_data( '_reklamo_consent_ip', self::ip_hash( Reklamo_Storage::client_ip() ) );
		if ( $waiver ) {
			$order->update_meta_data( '_reklamo_waiver_at', $now );
		}
	}

	/** An order note that carries customer text or an IP, flagged so anonymisation can find it. */
	public static function personal_note( WC_Order $order, string $text ): void {
		$id = $order->add_order_note( $text );
		if ( $id ) {
			add_comment_meta( $id, self::NOTE_FLAG, '1', true );
		}
	}

	/** Replace IPv4/IPv6 literals in free text. Pure, unit-tested. */
	public static function mask_ips( string $text ): string {
		$v4 = '(?<![\d.])(?:\d{1,3}\.){3}\d{1,3}(?![\d.])';
		$v6 = '(?<![:\w])(?:[0-9a-f]{0,4}:){2,7}[0-9a-f]{0,4}(?![:\w])';
		return (string) preg_replace( "/$v4|$v6/i", '[ip]', $text );
	}

	/**
	 * Remove what this plugin knows about a person from one order: tokens, tracking link,
	 * our meta, the flagged notes. Files are the caller's business (retention vs erasure
	 * differ). Runs on WooCommerce's own anonymisation hook too.
	 */
	public static function strip_order( $order ): void {
		$order = $order instanceof WC_Order ? $order : wc_get_order( (int) $order );
		if ( ! $order ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'reklamo_tokens', array( 'order_id' => $order->get_id() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( self::PERSONAL_META as $key ) {
			$order->delete_meta_data( $key );
		}
		$order->save();
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'limit'    => -1,
			)
		);
		foreach ( $notes as $note ) {
			if ( get_comment_meta( $note->id, self::NOTE_FLAG, true ) ) {
				wp_update_comment(
					array(
						'comment_ID'      => $note->id,
						'comment_content' => __( '[removed: personal data]', 'reklamo-core' ),
					)
				);
				continue;
			}
			$masked = self::mask_ips( $note->content );
			if ( $masked !== $note->content ) {
				wp_update_comment(
					array(
						'comment_ID'      => $note->id,
						'comment_content' => $masked,
					)
				);
			}
		}
	}

	/** WooCommerce's anonymiser for a closed order, plus ours through the hook. Idempotent. */
	public static function anonymize_order( WC_Order $order ): bool {
		if ( 'yes' === $order->get_meta( '_anonymized' ) ) {
			return false;
		}
		foreach ( Reklamo_Storage::for_order( $order->get_id() ) as $f ) {
			Reklamo_Storage::delete( $f, true );
		}
		if ( ! class_exists( 'WC_Privacy_Erasers' ) ) {
			include_once WC_ABSPATH . 'includes/class-wc-privacy-erasers.php';
		}
		WC_Privacy_Erasers::remove_order_personal_data( $order );
		return true;
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
			if ( $order->get_meta( '_reklamo_consent_at' ) ) {
				$data[] = array(
					'name'  => __( 'Consent to Terms and Privacy Policy', 'reklamo-core' ),
					'value' => sprintf( '%s UTC, %s %s', $order->get_meta( '_reklamo_consent_at' ), __( 'version', 'reklamo-core' ), $order->get_meta( '_reklamo_consent_version' ) ? $order->get_meta( '_reklamo_consent_version' ) : '-' ),
				);
			}
			if ( $order->get_meta( '_reklamo_waiver_at' ) ) {
				$data[] = array(
					'name'  => __( 'Withdrawal-right waiver (goods made to specification)', 'reklamo-core' ),
					'value' => $order->get_meta( '_reklamo_waiver_at' ) . ' UTC',
				);
			}
			if ( $order->get_meta( '_reklamo_approved_at' ) ) {
				$data[] = array(
					'name'  => __( 'Mockup approved', 'reklamo-core' ),
					'value' => sprintf( '%s UTC, #%d', $order->get_meta( '_reklamo_approved_at' ), (int) $order->get_meta( '_reklamo_approved_revision' ) ),
				);
			}
			foreach ( (array) $order->get_meta( '_reklamo_change_requests' ) as $rev => $message ) {
				$data[] = array(
					/* translators: %d: mockup revision */
					'name'  => sprintf( __( 'Change request to mockup #%d', 'reklamo-core' ), (int) $rev ),
					'value' => (string) $message,
				);
			}
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
					'group_label' => __( 'Reklamo order files, consent and links', 'reklamo-core' ),
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
		$removed  = false;
		$retained = false;
		$messages = array();
		foreach ( self::orders_for( $email ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			if ( ! $order->has_status( self::CLOSED ) ) {
				$retained = true;
				/* translators: %s: order number */
				$messages[] = sprintf( __( 'Order %s is still open; its data is kept until it is closed.', 'reklamo-core' ), $order->get_order_number() );
				continue;
			}
			foreach ( Reklamo_Storage::for_order( $order_id ) as $f ) {
				Reklamo_Storage::delete( $f, false );
			}
			self::anonymize_order( $order );
			self::strip_order( $order );
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

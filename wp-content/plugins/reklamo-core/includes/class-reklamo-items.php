<?php
/**
 * Each product of an order runs its own mockup loop: its own logo and note, its own
 * revisions, its own approval or change request. The state lives on the order LINE ITEM;
 * the order status is derived from the items (order_status_for), so the deposit is asked
 * for only once every product is approved.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Items {

	const META_STATE    = '_reklamo_state';
	const META_REVISION = '_reklamo_revision';
	const META_SENT_AT  = '_reklamo_sent_at';
	const META_NOTE     = '_reklamo_note';

	const WAITING  = 'waiting';  // no mockup yet: the designer's turn.
	const SENT     = 'sent';     // a mockup awaits the customer.
	const CHANGES  = 'changes';  // the customer asked for changes: the designer's turn.
	const APPROVED = 'approved';

	/** The statuses the item loop may move an order between; later ones belong to the payment flow. */
	const LOOP_STATUSES = array( Reklamo_Statuses::RECEIVED, Reklamo_Statuses::MOCKUP_SENT, Reklamo_Statuses::CHANGES, Reklamo_Statuses::APPROVED );

	/**
	 * Pure: the order status for a set of item states. An open change request keeps the
	 * order with the designer even while other mockups await the customer.
	 *
	 * @param string[] $states Item states.
	 */
	public static function order_status_for( array $states ): string {
		if ( ! $states ) {
			return Reklamo_Statuses::RECEIVED;
		}
		if ( in_array( self::CHANGES, $states, true ) ) {
			return Reklamo_Statuses::CHANGES;
		}
		if ( count( array_filter( $states, static fn( $s ) => self::APPROVED === $s ) ) === count( $states ) ) {
			return Reklamo_Statuses::APPROVED;
		}
		if ( in_array( self::SENT, $states, true ) || in_array( self::APPROVED, $states, true ) ) {
			return Reklamo_Statuses::MOCKUP_SENT;
		}
		return Reklamo_Statuses::RECEIVED;
	}

	/** @return array<int,WC_Order_Item_Product> keyed by item ID */
	public static function products( WC_Order $order ): array {
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$items[ (int) $item_id ] = $item;
			}
		}
		return $items;
	}

	public static function item( WC_Order $order, int $item_id ): ?WC_Order_Item_Product {
		return self::products( $order )[ $item_id ] ?? null;
	}

	public static function state( WC_Order_Item_Product $item ): string {
		$state = (string) $item->get_meta( self::META_STATE );
		return in_array( $state, array( self::SENT, self::CHANGES, self::APPROVED ), true ) ? $state : self::WAITING;
	}

	public static function revision( WC_Order_Item_Product $item ): int {
		return (int) $item->get_meta( self::META_REVISION );
	}

	/** Corrections asked for so far: every mockup after the first. Our own fixes are counted too; the shop decides what to charge. */
	public static function corrections( WC_Order_Item_Product $item ): int {
		return max( 0, self::revision( $item ) - 1 );
	}

	/** The rule for the customer: first mockup plus N free corrections, each further one at a fee. */
	public static function corrections_rule(): string {
		$free = (int) Reklamo_Settings::get( 'free_corrections', '2' );
		$fee  = (string) Reklamo_Settings::get( 'correction_fee', '15' );
		return sprintf(
			/* translators: 1: number of free corrections, 2: fee for each further correction */
			_n( 'Included: the first mockup and %1$d free correction. Each further correction costs %2$s €; fixing our own mistakes is always free.', 'Included: the first mockup and %1$d free corrections. Each further correction costs %2$s €; fixing our own mistakes is always free.', $free, 'reklamo-core' ),
			$free,
			$fee
		);
	}

	/** @return array<int,string> item ID => state */
	public static function states( WC_Order $order ): array {
		return array_map( array( __CLASS__, 'state' ), self::products( $order ) );
	}

	/** @return array{total:int,waiting:int,sent:int,changes:int,approved:int} */
	public static function counts( WC_Order $order ): array {
		$counts = array_fill_keys( array( self::WAITING, self::SENT, self::CHANGES, self::APPROVED ), 0 );
		foreach ( self::states( $order ) as $state ) {
			++$counts[ $state ];
		}
		return array( 'total' => array_sum( $counts ) ) + $counts;
	}

	public static function all_approved( WC_Order $order ): bool {
		return Reklamo_Statuses::APPROVED === self::order_status_for( array_values( self::states( $order ) ) );
	}

	public static function note( WC_Order_Item_Product $item ): string {
		$note = (string) $item->get_meta( self::META_NOTE );
		// Orders placed before the note got its own key carry it under the translated label.
		return '' !== $note ? $note : (string) $item->get_meta( __( 'Note to designer', 'reklamo-core' ) );
	}

	public static function logo( WC_Order_Item_Product $item ): ?object {
		$token = (string) $item->get_meta( Reklamo_Cart::META_FILE );
		return '' !== $token ? Reklamo_Storage::by_token( $token ) : null;
	}

	/** @return object[] the item's mockups, oldest first */
	public static function mockups( int $order_id, int $item_id ): array {
		return array_values( array_filter( Reklamo_Storage::for_order( $order_id, 'mockup' ), static fn( $m ) => (int) $m->order_item_id === $item_id ) );
	}

	public static function mockup( int $order_id, int $item_id, int $revision ): ?object {
		foreach ( self::mockups( $order_id, $item_id ) as $m ) {
			if ( (int) $m->revision === $revision ) {
				return $m;
			}
		}
		return null;
	}

	/** @return array<int,array<int,string>> item ID => revision => the customer's message */
	public static function change_requests( WC_Order $order ): array {
		$all = (array) $order->get_meta( '_reklamo_change_requests' );
		$out = array();
		foreach ( $all as $item_id => $by_rev ) {
			if ( is_array( $by_rev ) ) {
				$out[ (int) $item_id ] = array_map( 'strval', $by_rev );
			}
		}
		return $out;
	}

	public static function add_change_request( WC_Order $order, int $item_id, int $revision, string $message ): void {
		$all                          = self::change_requests( $order );
		$all[ $item_id ][ $revision ] = $message;
		$order->update_meta_data( '_reklamo_change_requests', $all );
	}

	/** A new mockup went out for the item. */
	public static function mark_sent( WC_Order_Item_Product $item, int $revision ): void {
		$item->update_meta_data( self::META_STATE, self::SENT );
		$item->update_meta_data( self::META_REVISION, $revision );
		$item->update_meta_data( self::META_SENT_AT, current_time( 'mysql', true ) );
		$item->save();
	}

	public static function mark_decided( WC_Order_Item_Product $item, string $action ): void {
		$item->update_meta_data( self::META_STATE, 'approve' === $action ? self::APPROVED : self::CHANGES );
		$item->save();
	}

	/**
	 * Move the order to the status its items call for, as long as it is still in the
	 * mockup loop. Reaching APPROVED is the approval path's job (Reklamo_Approval::decide),
	 * which also sets the deposit; this never does it on its own.
	 */
	public static function sync_status( WC_Order $order, string $note = '' ): void {
		$order = wc_get_order( $order->get_id() );
		if ( ! $order || ! $order->has_status( self::LOOP_STATUSES ) ) {
			return;
		}
		$target = self::order_status_for( array_values( self::states( $order ) ) );
		if ( Reklamo_Statuses::APPROVED === $target || $order->has_status( $target ) ) {
			if ( '' !== $note ) {
				$order->add_order_note( $note );
			}
			return;
		}
		$order->update_status( $target, $note );
	}

	/** Item meta the order screen would otherwise list under every product. */
	public static function hidden_meta( array $hidden ): array {
		return array_merge( $hidden, array( self::META_STATE, self::META_REVISION, self::META_SENT_AT, self::META_NOTE ) );
	}

	/**
	 * Orders from before the item loop: mockups, revision and outcome were order-wide and
	 * there was always one product. Hand all of it to that product. Safe to run again.
	 */
	public static function migrate(): void {
		global $wpdb;
		$order_ids = $wpdb->get_col( "SELECT DISTINCT order_id FROM {$wpdb->prefix}reklamo_files WHERE kind = 'mockup' AND order_id IS NOT NULL AND order_item_id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( array_map( 'intval', (array) $order_ids ) as $order_id ) {
			$order = wc_get_order( $order_id );
			$items = $order ? self::products( $order ) : array();
			if ( ! $items ) {
				continue;
			}
			$item_id = (int) array_key_first( $items );
			$item    = $items[ $item_id ];
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}reklamo_files SET order_item_id = %d WHERE order_id = %d AND kind = 'mockup' AND order_item_id IS NULL", $item_id, $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}reklamo_tokens SET order_item_id = %d WHERE order_id = %d AND purpose = 'approval' AND order_item_id IS NULL", $item_id, $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$revision = 0;
			foreach ( self::mockups( $order_id, $item_id ) as $m ) {
				$revision = max( $revision, (int) $m->revision );
			}
			$state = self::WAITING;
			if ( $order->has_status( Reklamo_Statuses::MOCKUP_SENT ) ) {
				$state = self::SENT;
			} elseif ( $order->has_status( Reklamo_Statuses::CHANGES ) ) {
				$state = self::CHANGES;
			} elseif ( $revision > 0 && ! $order->has_status( Reklamo_Statuses::RECEIVED ) ) {
				$state = self::APPROVED;
			}
			$item->update_meta_data( self::META_STATE, $state );
			$item->update_meta_data( self::META_REVISION, $revision );
			$item->save();

			$flat = (array) $order->get_meta( '_reklamo_change_requests' );
			if ( $flat && ! is_array( reset( $flat ) ) ) {
				$order->update_meta_data( '_reklamo_change_requests', array( $item_id => array_map( 'strval', $flat ) ) );
				$order->save();
			}
		}
	}
}

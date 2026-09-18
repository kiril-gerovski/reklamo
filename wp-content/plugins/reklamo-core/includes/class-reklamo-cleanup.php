<?php
/**
 * Housekeeping via the bundled Action Scheduler (hourly):
 *  - abandoned chunk sessions (tmp/<ticket>) older than the ticket TTL,
 *  - finished uploads never attached to an order within 48 h,
 *  - retention: logos/mockups of orders completed or cancelled more than N months ago
 *    (files removed, rows kept blank for the audit trail) and, with them, the customer's
 *    tracking link,
 *  - anonymisation: closed orders older than M months lose name, address, contact details,
 *    notes and links through WooCommerce's own anonymiser; totals and dates stay.
 * Deleting an order from wp-admin takes its files, tokens, notes and reminders with it.
 * Both periods are settings and are disclosed in the Privacy Policy.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Cleanup {

	const HOOK = 'reklamo_cleanup';

	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ), 20 );
		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'purge_order' ), 10, 1 );
	}

	public static function ensure_scheduled(): void {
		if ( function_exists( 'as_next_scheduled_action' ) && false === as_next_scheduled_action( self::HOOK, array(), 'reklamo' ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, self::HOOK, array(), 'reklamo' );
		}
	}

	/** @return array{tmp:int, unclaimed:int, retired:int, anonymized:int} */
	public static function run(): array {
		return array(
			'tmp'        => self::sweep_tmp(),
			'unclaimed'  => self::sweep_unclaimed(),
			'retired'    => self::apply_retention(),
			'anonymized' => self::apply_anonymization(),
		);
	}

	/** Everything the plugin holds for an order that WooCommerce is about to delete for good. */
	public static function purge_order( int $order_id ): void {
		foreach ( Reklamo_Storage::for_order( $order_id ) as $row ) {
			Reklamo_Storage::delete( $row, false );
		}
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'reklamo_tokens', array( 'order_id' => $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		Reklamo_Reminders::unschedule_for_order( $order_id );
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order_id,
				'limit'    => -1,
			)
		);
		foreach ( $notes as $note ) {
			wp_delete_comment( $note->id, true );
		}
	}

	private static function sweep_tmp(): int {
		$n    = 0;
		$cut  = time() - Reklamo_Upload::TICKET_TTL;
		$dirs = glob( Reklamo_Storage::base_dir() . '/tmp/*', GLOB_ONLYDIR );
		foreach ( is_array( $dirs ) ? $dirs : array() as $dir ) {
			if ( filemtime( $dir ) < $cut ) {
				Reklamo_Upload::remove_dir( $dir );
				++$n;
			}
		}
		return $n;
	}

	private static function sweep_unclaimed(): int {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}reklamo_files WHERE order_id IS NULL AND created_at < %s LIMIT 200", gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $row ) {
			Reklamo_Storage::delete( $row, false );
		}
		return count( $rows );
	}

	/** @return int[] closed orders whose last change is older than $months */
	private static function closed_before( int $months, array $extra = array() ): array {
		if ( $months <= 0 ) {
			return array();
		}
		return wc_get_orders(
			array_merge(
				array(
					'status'        => Reklamo_Privacy::CLOSED,
					'date_modified' => '<' . ( time() - $months * 30 * DAY_IN_SECONDS ),
					'limit'         => 50,
					'return'        => 'ids',
				),
				$extra
			)
		);
	}

	private static function apply_retention(): int {
		$n = 0;
		foreach ( self::closed_before( (int) Reklamo_Settings::get( 'retention_months', '12' ) ) as $order_id ) {
			foreach ( Reklamo_Storage::for_order( (int) $order_id ) as $row ) {
				if ( '' !== (string) $row->path ) {
					Reklamo_Storage::delete( $row, true );
					++$n;
				}
			}
			Reklamo_Tracking::expire_for_order( (int) $order_id );
		}
		return $n;
	}

	private static function apply_anonymization(): int {
		$n      = 0;
		$months = max( (int) Reklamo_Settings::get( 'anonymize_months', '36' ), (int) Reklamo_Settings::get( 'retention_months', '12' ) );
		$ids    = self::closed_before(
			(int) Reklamo_Settings::get( 'anonymize_months', '36' ) > 0 ? $months : 0,
			array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_anonymized',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		foreach ( $ids as $order_id ) {
			$order = wc_get_order( (int) $order_id );
			if ( $order && Reklamo_Privacy::anonymize_order( $order ) ) {
				++$n;
			}
		}
		return $n;
	}
}

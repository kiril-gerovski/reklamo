<?php
/**
 * Housekeeping via the bundled Action Scheduler (hourly):
 *  - abandoned chunk sessions (tmp/<ticket>) older than the ticket TTL,
 *  - finished uploads never attached to an order within 48 h,
 *  - retention: logos/mockups of orders completed or cancelled more than N months ago
 *    (files removed, rows kept blank for the audit trail) and, with them, the customer's
 *    tracking link. Disclosed in the terms.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Cleanup {

	const HOOK = 'reklamo_cleanup';

	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ), 20 );
	}

	public static function ensure_scheduled(): void {
		if ( function_exists( 'as_next_scheduled_action' ) && false === as_next_scheduled_action( self::HOOK, array(), 'reklamo' ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, self::HOOK, array(), 'reklamo' );
		}
	}

	/** @return array{tmp:int, unclaimed:int, retired:int} */
	public static function run(): array {
		return array(
			'tmp'       => self::sweep_tmp(),
			'unclaimed' => self::sweep_unclaimed(),
			'retired'   => self::apply_retention(),
		);
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

	private static function apply_retention(): int {
		$months = (int) Reklamo_Settings::get( 'retention_months', '12' );
		if ( $months <= 0 ) {
			return 0;
		}
		$orders = wc_get_orders(
			array(
				'status'        => array( 'completed', 'cancelled' ),
				'date_modified' => '<' . ( time() - $months * 30 * DAY_IN_SECONDS ),
				'limit'         => 50,
				'return'        => 'ids',
			)
		);
		$n      = 0;
		foreach ( $orders as $order_id ) {
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
}

<?php
/**
 * Customer nudges while a mockup awaits approval or a deposit is unpaid, via the Action
 * Scheduler bundled with WooCommerce (so this respects the one-plugin rule). WP-Cron
 * on a low-traffic site simply does not fire — production runs a real cron (see
 * docs/DEPLOYMENT.md), locally page loads are enough.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Reminders {

	const HOOK_APPROVAL = 'reklamo_remind_approval';
	const HOOK_DEPOSIT  = 'reklamo_remind_deposit';
	const GROUP         = 'reklamo';

	public static function init(): void {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_change' ), 20, 3 );
		add_action( self::HOOK_APPROVAL, array( __CLASS__, 'remind_approval' ), 10, 2 );
		add_action( self::HOOK_DEPOSIT, array( __CLASS__, 'remind_deposit' ), 10, 1 );
	}

	private static function available(): bool {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_unschedule_all_actions' );
	}

	public static function on_status_change( int $order_id, string $from, string $to ): void {
		if ( ! self::available() ) {
			return;
		}
		// Any change cancels what was pending FOR THIS ORDER; the new status decides what to schedule.
		self::unschedule_for_order( $order_id );

		if ( Reklamo_Statuses::MOCKUP_SENT === $to ) {
			$rev = Reklamo_Approval::latest_revision( $order_id );
			foreach ( Reklamo_Settings::reminder_days() as $day ) {
				as_schedule_single_action( time() + $day * DAY_IN_SECONDS, self::HOOK_APPROVAL, array( $order_id, $rev ), self::GROUP );
			}
		} elseif ( Reklamo_Statuses::APPROVED === $to ) {
			foreach ( Reklamo_Settings::reminder_days() as $day ) {
				as_schedule_single_action( time() + $day * DAY_IN_SECONDS, self::HOOK_DEPOSIT, array( $order_id ), self::GROUP );
			}
		}
	}

	private static function unschedule_for_order( int $order_id ): void {
		foreach ( as_get_scheduled_actions(
			array(
				'group'    => self::GROUP,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 100,
			),
			'ids'
		) as $action_id ) {
			$action = ActionScheduler::store()->fetch_action( $action_id );
			$args   = $action->get_args();
			if ( isset( $args[0] ) && (int) $args[0] === $order_id ) {
				ActionScheduler::store()->cancel_action( $action_id );
			}
		}
	}

	/** Resend the mockup email with a fresh link, if the same revision is still awaiting a decision. */
	public static function remind_approval( int $order_id, int $revision ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( Reklamo_Statuses::MOCKUP_SENT ) || Reklamo_Approval::latest_revision( $order_id ) !== $revision ) {
			return;
		}
		$file = null;
		foreach ( Reklamo_Storage::for_order( $order_id, 'mockup' ) as $m ) {
			if ( (int) $m->revision === $revision ) {
				$file = $m;
			}
		}
		if ( ! $file ) {
			return;
		}
		$url = Reklamo_Approval::issue( $order, (int) $file->id, $revision, 'approval' );
		Reklamo_Emails::send_mockup( $order, $url, $revision, true );
		/* translators: %d: mockup revision */
		$order->add_order_note( sprintf( __( 'Reminder sent: mockup #%d still awaits approval.', 'reklamo-core' ), $revision ) );
	}

	/** Resend the deposit request with a fresh details link, if the deposit is still unpaid. */
	public static function remind_deposit( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( Reklamo_Statuses::APPROVED ) ) {
			return;
		}
		$url = Reklamo_Approval::issue( $order, 0, (int) $order->get_meta( '_reklamo_approved_revision' ), 'details' );
		Reklamo_Emails::send_deposit_request( $order, $url, true );
		$order->add_order_note( __( 'Reminder sent: deposit still unpaid.', 'reklamo-core' ) );
	}
}

<?php
/**
 * Customer nudges while a mockup awaits approval, a deposit or the balance is unpaid —
 * and, when the nudges run out, an alert to the shop. Via the Action Scheduler bundled
 * with WooCommerce (so this respects the one-plugin rule). WP-Cron on a low-traffic site
 * simply does not fire — production runs a real cron (see docs/DEPLOYMENT.md), locally
 * page loads are enough.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Reminders {

	const HOOK_APPROVAL = 'reklamo_remind_approval';
	const HOOK_DEPOSIT  = 'reklamo_remind_deposit';
	const HOOK_FINAL    = 'reklamo_remind_final';
	const HOOK_STALE    = 'reklamo_stale_alert';
	const GROUP         = 'reklamo';

	public static function init(): void {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_change' ), 20, 3 );
		add_action( self::HOOK_APPROVAL, array( __CLASS__, 'remind_approval' ), 10, 2 );
		add_action( self::HOOK_DEPOSIT, array( __CLASS__, 'remind_deposit' ), 10, 1 );
		add_action( self::HOOK_FINAL, array( __CLASS__, 'remind_final' ), 10, 1 );
		add_action( self::HOOK_STALE, array( __CLASS__, 'alert_stale' ), 10, 2 );
	}

	private static function available(): bool {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_unschedule_all_actions' );
	}

	public static function on_status_change( int $order_id, string $from, string $to ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::schedule_for( $order );
		}
	}

	/**
	 * (Re)build the reminder schedule from the order's current status. Called on every
	 * status change AND after every mockup (re)send — a re-sent mockup does not change the
	 * status, yet the pending reminders carry the old revision and would fall silent.
	 */
	public static function schedule_for( WC_Order $order ): void {
		if ( ! self::available() ) {
			return;
		}
		$order_id = $order->get_id();
		// Any change cancels what was pending FOR THIS ORDER; the current status decides what to schedule.
		self::unschedule_for_order( $order_id );

		$days = Reklamo_Settings::reminder_days();
		$hook = '';
		$args = array( $order_id );
		if ( $order->has_status( Reklamo_Statuses::MOCKUP_SENT ) ) {
			$hook = self::HOOK_APPROVAL;
			$args = array( $order_id, Reklamo_Approval::latest_revision( $order_id ) );
		} elseif ( $order->has_status( Reklamo_Statuses::APPROVED ) ) {
			$hook = self::HOOK_DEPOSIT;
		} elseif ( $order->has_status( Reklamo_Statuses::FINAL_DUE ) ) {
			$hook = self::HOOK_FINAL;
		}
		if ( '' === $hook ) {
			return;
		}
		foreach ( $days as $day ) {
			as_schedule_single_action( time() + $day * DAY_IN_SECONDS, $hook, $args, self::GROUP );
		}
		// After the last nudge: tell the shop, not the customer again.
		$stale = (int) Reklamo_Settings::get( 'stale_days', '7' );
		if ( $stale > 0 ) {
			$last = $days ? max( $days ) : 0;
			as_schedule_single_action( time() + ( $last + $stale ) * DAY_IN_SECONDS, self::HOOK_STALE, array( $order_id, $order->get_status() ), self::GROUP );
		}
	}

	private static function unschedule_for_order( int $order_id ): void {
		foreach ( as_get_scheduled_actions(
			array(
				'group'    => self::GROUP,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 500,
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
		if ( Reklamo_Emails::send_mockup( $order, $url, $revision, true ) ) {
			/* translators: %d: mockup revision */
			$order->add_order_note( sprintf( __( 'Reminder sent: mockup #%d still awaits approval.', 'reklamo-core' ), $revision ) );
		}
	}

	/** Resend the deposit request with a fresh details link, if the deposit is still unpaid. */
	public static function remind_deposit( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( Reklamo_Statuses::APPROVED ) ) {
			return;
		}
		$url = Reklamo_Approval::issue( $order, 0, (int) $order->get_meta( '_reklamo_approved_revision' ), 'details' );
		if ( Reklamo_Emails::send_deposit_request( $order, $url, true ) ) {
			$order->add_order_note( __( 'Reminder sent: deposit still unpaid.', 'reklamo-core' ) );
		}
	}

	/** Resend the final-payment email, if the balance is still unpaid. */
	public static function remind_final( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( Reklamo_Statuses::FINAL_DUE ) ) {
			return;
		}
		if ( Reklamo_Emails::send_final_payment( $order, true ) ) {
			$order->add_order_note( __( 'Reminder sent: final payment still outstanding.', 'reklamo-core' ) );
		}
	}

	/** The customer has been silent through every reminder: alert the shop once. */
	public static function alert_stale( int $order_id, string $status ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( $status ) ) {
			return;
		}
		if ( Reklamo_Emails::send_admin_stale( $order ) ) {
			$order->add_order_note( __( 'Shop alerted: the customer has not responded to any reminder.', 'reklamo-core' ) );
		}
	}
}

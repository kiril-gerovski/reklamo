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
	const HOOK_EXPIRE   = 'reklamo_payment_expire';
	const GROUP         = 'reklamo';

	public static function init(): void {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_change' ), 20, 3 );
		add_action( self::HOOK_APPROVAL, array( __CLASS__, 'remind_approval' ), 10, 3 );
		add_action( self::HOOK_DEPOSIT, array( __CLASS__, 'remind_deposit' ), 10, 1 );
		add_action( self::HOOK_FINAL, array( __CLASS__, 'remind_final' ), 10, 1 );
		add_action( self::HOOK_STALE, array( __CLASS__, 'alert_stale' ), 10, 2 );
		add_action( self::HOOK_EXPIRE, array( 'Reklamo_Payment_Deadline', 'expire' ), 10, 1 );
		add_filter( 'action_scheduler_retention_period', array( __CLASS__, 'retention' ) );
	}

	/** Finished actions are kept a week, not WooCommerce's month. */
	public static function retention(): int {
		return WEEK_IN_SECONDS;
	}

	/** as_schedule_single_action(), written to the audit log. */
	private static function plan( int $time, string $hook, array $args, string $group ): void {
		as_schedule_single_action( $time, $hook, $args, $group );
		Reklamo_Log::planned( $hook, $time, (int) ( $args[0] ?? 0 ) );
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
	 * Approval nudges run per product, counted from the day its mockup went out, so
	 * rebuilding the schedule never restarts a product's clock.
	 */
	public static function schedule_for( WC_Order $order ): void {
		if ( ! self::available() ) {
			return;
		}
		$order_id = $order->get_id();
		// Any change cancels what was pending FOR THIS ORDER; the current status decides what to schedule.
		self::unschedule_for_order( $order_id );

		$days = Reklamo_Settings::reminder_days();
		if ( $order->has_status( array( Reklamo_Statuses::MOCKUP_SENT, Reklamo_Statuses::CHANGES ) ) ) {
			foreach ( Reklamo_Items::products( $order ) as $item_id => $item ) {
				if ( Reklamo_Items::SENT !== Reklamo_Items::state( $item ) ) {
					continue;
				}
				$sent = strtotime( (string) $item->get_meta( Reklamo_Items::META_SENT_AT ) . ' UTC' );
				$sent = $sent ? $sent : time();
				foreach ( $days as $day ) {
					if ( $sent + $day * DAY_IN_SECONDS > time() ) {
						self::plan( $sent + $day * DAY_IN_SECONDS, self::HOOK_APPROVAL, array( $order_id, $item_id, Reklamo_Items::revision( $item ) ), self::GROUP );
					}
				}
			}
		}

		$deadline = $order->has_status( Reklamo_Statuses::APPROVED ) && Reklamo_Payment_Deadline::enabled() ? Reklamo_Payment_Deadline::for_order( $order ) : null;
		if ( $deadline ) {
			if ( $deadline['remind'] > time() ) {
				self::plan( $deadline['remind'], self::HOOK_DEPOSIT, array( $order_id ), self::GROUP );
			}
			// No shop alert: the order cancels itself and that email tells the shop.
			self::plan( max( $deadline['cancel'], time() ), self::HOOK_EXPIRE, array( $order_id ), self::GROUP );
			return;
		}

		$hook = '';
		if ( $order->has_status( Reklamo_Statuses::APPROVED ) ) {
			$hook = self::HOOK_DEPOSIT;
		} elseif ( $order->has_status( Reklamo_Statuses::FINAL_DUE ) ) {
			$hook = self::HOOK_FINAL;
		} elseif ( ! $order->has_status( Reklamo_Statuses::MOCKUP_SENT ) ) {
			return;
		}
		if ( '' !== $hook ) {
			foreach ( $days as $day ) {
				self::plan( time() + $day * DAY_IN_SECONDS, $hook, array( $order_id ), self::GROUP );
			}
		}
		// After the last nudge: tell the shop, not the customer again.
		$stale = (int) Reklamo_Settings::get( 'stale_days', '7' );
		if ( $stale > 0 ) {
			$last = $days ? max( $days ) : 0;
			self::plan( time() + ( $last + $stale ) * DAY_IN_SECONDS, self::HOOK_STALE, array( $order_id, $order->get_status() ), self::GROUP );
		}
	}

	public static function unschedule_for_order( int $order_id ): void {
		if ( ! self::available() ) {
			return;
		}
		$cancelled = array();
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
				$cancelled[] = $action->get_hook();
			}
		}
		if ( $cancelled ) {
			/* translators: %s: action hooks */
			Reklamo_Log::info( sprintf( __( 'Cancelled the pending %s.', 'reklamo-core' ), Reklamo_Log::jobs( $cancelled ) ), $order_id );
		}
	}

	/**
	 * Resend one product's mockup email with a fresh link, if that revision still awaits a
	 * decision. Actions queued before the item loop carry [order, revision] only; they
	 * belong to the order's single product.
	 */
	public static function remind_approval( int $order_id, int $item_id, ?int $revision = null ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( null === $revision ) {
			$revision = $item_id;
			$item_id  = (int) array_key_first( Reklamo_Items::products( $order ) );
		}
		$item = Reklamo_Items::item( $order, $item_id );
		$file = Reklamo_Items::mockup( $order_id, $item_id, $revision );
		if ( ! $file || ! Reklamo_Approval::awaits_decision( $order, $item, $revision ) ) {
			return;
		}
		$url = Reklamo_Approval::issue( $order, (int) $file->id, $revision, 'approval', $item_id );
		if ( Reklamo_Emails::send_mockup(
			$order,
			array(
				array(
					'name'     => $item->get_name(),
					'revision' => $revision,
					'url'      => $url,
				),
			),
			true
		) ) {
			/* translators: 1: mockup revision, 2: product name */
			$order->add_order_note( sprintf( __( 'Reminder sent: mockup #%1$d of %2$s still awaits approval.', 'reklamo-core' ), $revision, $item->get_name() ) );
		}
	}

	/** Resend the deposit request with a fresh details link, if the deposit is still unpaid. */
	public static function remind_deposit( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( Reklamo_Statuses::APPROVED ) ) {
			return;
		}
		$url = Reklamo_Approval::issue( $order, 0, 0, 'details' );
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

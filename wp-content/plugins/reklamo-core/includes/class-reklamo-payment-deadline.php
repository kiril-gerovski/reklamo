<?php
/**
 * The payment deadline after approval: the customer is reminded when the payment hours run
 * out, and an order still unpaid at the cancel hour is cancelled automatically. Both count
 * calendar hours from the moment every mockup was approved (`_reklamo_approved_at`). An
 * order cancelled this way carries META_EXPIRED, and the shop can still mark its late
 * payment as received.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Payment_Deadline {

	const META_EXPIRED = '_reklamo_payment_expired_at';

	/**
	 * @return array{remind:int,cancel:int} Unix times; 0 when that step is switched off.
	 */
	public static function times( int $approved_at, int $payment_hours, int $cancel_hours ): array {
		$cancel = $cancel_hours > 0 ? $approved_at + $cancel_hours * 3600 : 0;
		$remind = $payment_hours > 0 ? $approved_at + $payment_hours * 3600 : 0;
		if ( $cancel && $remind >= $cancel ) {
			$remind = 0; // a reminder at or after the cancellation would never be read in time
		}
		return array(
			'remind' => $remind,
			'cancel' => $cancel,
		);
	}

	public static function payment_hours(): int {
		return absint( Reklamo_Settings::get( 'payment_hours', '48' ) );
	}

	public static function cancel_hours(): int {
		return absint( Reklamo_Settings::get( 'cancel_hours', '72' ) );
	}

	/** Auto-cancellation is on: the deposit follows the hour schedule, not the reminder days. */
	public static function enabled(): bool {
		return self::cancel_hours() > 0;
	}

	/** @return array{remind:int,cancel:int}|null Null when the order has no approval time. */
	public static function for_order( WC_Order $order ): ?array {
		$approved = strtotime( (string) $order->get_meta( '_reklamo_approved_at' ) . ' UTC' );
		if ( ! $approved ) {
			return null;
		}
		return self::times( $approved, self::payment_hours(), self::cancel_hours() );
	}

	public static function format( int $time ): string {
		return wp_date( get_option( 'date_format', 'd.m.Y' ) . ', ' . get_option( 'time_format', 'H:i' ), $time );
	}

	/**
	 * The deadline paragraph for an email or the order page, or '' when it does not apply.
	 *
	 * @param string $kind 'request' (with the payment request), 'reminder', 'cancelled' or
	 *                     'terms' (the general rule, before approval).
	 */
	public static function notice( ?WC_Order $order, string $kind ): string {
		if ( ! self::enabled() ) {
			return '';
		}
		if ( 'terms' === $kind ) {
			return sprintf(
				/* translators: 1: hours to pay, 2: hours until an unpaid order is cancelled */
				__( 'After you approve the mockups you have %1$d hours to pay; an unpaid order is cancelled automatically %2$d hours after the approval.', 'reklamo-core' ),
				self::payment_hours(),
				self::cancel_hours()
			);
		}
		$t = $order ? self::for_order( $order ) : null;
		if ( ! $t ) {
			return '';
		}
		$cancel = self::format( $t['cancel'] );
		switch ( $kind ) {
			case 'cancelled':
				return $order->get_meta( self::META_EXPIRED )
					/* translators: %s: date and time */
					? sprintf( __( 'The order was cancelled automatically because the payment did not arrive by %s.', 'reklamo-core' ), $cancel )
					: '';
			case 'reminder':
				return $t['remind']
					/* translators: 1: order number, 2: payment deadline, 3: cancellation time */
					? sprintf( __( 'The payment deadline for order %1$s passed on %2$s and we have not received your transfer yet. If it does not arrive by %3$s, the order will be cancelled automatically. If you have already paid, reply to this email with the payment order.', 'reklamo-core' ), $order->get_order_number(), self::format( $t['remind'] ), $cancel )
					/* translators: %s: cancellation time */
					: sprintf( __( 'We have not received your transfer yet. If it does not arrive by %s, the order will be cancelled automatically.', 'reklamo-core' ), $cancel );
			default:
				$due = $t['remind']
					/* translators: 1: payment deadline, 2: hours to pay, 3: cancellation time */
					? sprintf( __( 'Please pay by %1$s (within %2$d hours). If the payment has not arrived by %3$s, the order is cancelled automatically.', 'reklamo-core' ), self::format( $t['remind'] ), self::payment_hours(), $cancel )
					/* translators: %s: cancellation time */
					: sprintf( __( 'If the payment has not arrived by %s, the order is cancelled automatically.', 'reklamo-core' ), $cancel );
				return $due . ' ' . __( 'If a product runs out before we make your order, we will return your payment or agree a new delivery date with you.', 'reklamo-core' );
		}
	}

	/** The cancel hour has come: cancel the order if it is still waiting for its payment. */
	public static function expire( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( Reklamo_Statuses::APPROVED ) ) {
			return;
		}
		$order->update_meta_data( self::META_EXPIRED, current_time( 'mysql', true ) );
		$order->save();
		$order->update_status(
			'cancelled',
			/* translators: %d: hours */
			sprintf( __( 'Cancelled automatically: the payment did not arrive within %d hours of the approval.', 'reklamo-core' ), self::cancel_hours() )
		);
		// Core's admin "Cancelled order" email only fires from processing/on-hold.
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['WC_Email_Cancelled_Order'] ) ) {
			$emails['WC_Email_Cancelled_Order']->trigger( $order_id, wc_get_order( $order_id ) );
		}
	}
}

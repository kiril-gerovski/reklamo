<?php
/**
 * The customer-facing progress line: six steps, matching "How it works" on the homepage.
 * Maps every order status onto a step so the tracking page can highlight where the
 * order is. Pure PHP — unit-tested without WordPress.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Progress {

	const CANCELLED = -1;
	const REFUNDED  = -2;
	const DONE      = 6;

	/** Step keys in order; index + 1 is the step number shown to the customer. */
	const STEPS = array( 'request', 'mockup', 'approval', 'deposit', 'production', 'delivery' );

	/**
	 * Step reached for a status, 1-based. DONE (6 = all complete) for `completed`,
	 * CANCELLED for cancelled/failed, REFUNDED for refunded, 1 for anything unknown (a fresh order).
	 */
	public static function step_for_status( string $status ): int {
		$status = str_replace( 'wc-', '', $status );
		switch ( $status ) {
			case 'rq-received':
				return 1;
			case 'rq-mockup-sent':
			case 'rq-changes':
				return 2;
			case 'rq-approved':
				return 3;
			case 'rq-deposit-paid':
				return 4;
			case 'rq-production':
				return 5;
			case 'rq-final-due':
				return 6;
			case 'completed':
				return self::DONE;
			case 'refunded':
				return self::REFUNDED;
			case 'cancelled':
			case 'failed':
				return self::CANCELLED;
			default:
				return 1;
		}
	}

	/** Whether a step (1-based) is finished, given the current step and status. */
	public static function is_step_done( int $step, int $current, bool $completed ): bool {
		if ( $completed ) {
			return true;
		}
		return $step < $current;
	}
}

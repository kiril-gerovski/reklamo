<?php
/**
 * The customer-facing progress line: six steps, matching "How it works" on the homepage.
 * Maps every order status onto a step so the customer pages can highlight where the
 * order is. The highlighted step is the one IN PROGRESS — what is being worked on or
 * waited for — and every step before it is done: a fresh request shows "Mockup" as the
 * current step, an approved mockup shows "Deposit". Pure PHP — unit-tested without WordPress.
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
	 * Step in progress for a status, 1-based. DONE (6 = all complete) for `completed`,
	 * CANCELLED for cancelled/failed, REFUNDED for refunded, 2 for anything unknown (a fresh
	 * order: the request is in, the mockup is being prepared).
	 */
	public static function step_for_status( string $status ): int {
		$status = str_replace( 'wc-', '', $status );
		switch ( $status ) {
			case 'rq-received':
			case 'rq-changes': // a revised mockup is being prepared
				return 2;
			case 'rq-mockup-sent':
				return 3;
			case 'rq-approved':
				return 4;
			case 'rq-deposit-paid':
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
				return 2;
		}
	}

	/** Whether a step (1-based) is finished, given the step in progress and whether the order is completed. */
	public static function is_step_done( int $step, int $current, bool $completed ): bool {
		if ( $completed ) {
			return true;
		}
		return $step < $current;
	}
}

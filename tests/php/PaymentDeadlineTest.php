<?php
/**
 * The payment deadline: reminder at the payment hour, cancellation at the cancel hour,
 * both counted in calendar hours from the approval.
 */

use PHPUnit\Framework\TestCase;

final class PaymentDeadlineTest extends TestCase {

	private const APPROVED = 1760000000;

	public function test_reminder_at_48_and_cancellation_at_72_hours(): void {
		$t = Reklamo_Payment_Deadline::times( self::APPROVED, 48, 72 );
		$this->assertSame( self::APPROVED + 48 * 3600, $t['remind'] );
		$this->assertSame( self::APPROVED + 72 * 3600, $t['cancel'] );
	}

	public function test_weekends_count_like_any_other_hours(): void {
		$friday = strtotime( '2026-10-23 15:00:00 UTC' );
		$t      = Reklamo_Payment_Deadline::times( $friday, 48, 72 );
		$this->assertSame( '2026-10-25 15:00', gmdate( 'Y-m-d H:i', $t['remind'] ), 'Sunday' );
		$this->assertSame( '2026-10-26 15:00', gmdate( 'Y-m-d H:i', $t['cancel'] ), 'Monday' );
	}

	public function test_zero_switches_a_step_off(): void {
		$this->assertSame( 0, Reklamo_Payment_Deadline::times( self::APPROVED, 0, 72 )['remind'] );
		$this->assertSame( 0, Reklamo_Payment_Deadline::times( self::APPROVED, 48, 0 )['cancel'] );
		$this->assertSame( self::APPROVED + 48 * 3600, Reklamo_Payment_Deadline::times( self::APPROVED, 48, 0 )['remind'] );
	}

	public function test_no_reminder_at_or_after_the_cancellation(): void {
		$this->assertSame( 0, Reklamo_Payment_Deadline::times( self::APPROVED, 72, 72 )['remind'] );
		$this->assertSame( 0, Reklamo_Payment_Deadline::times( self::APPROVED, 96, 72 )['remind'] );
	}
}

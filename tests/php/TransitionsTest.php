<?php
/**
 * The order state machine: every legal path is reachable, illegal jumps are refused.
 */

use PHPUnit\Framework\TestCase;

final class TransitionsTest extends TestCase {

	public function test_happy_path_is_fully_allowed(): void {
		$path = array( 'rq-received', 'rq-mockup-sent', 'rq-approved', 'rq-deposit-paid', 'rq-production', 'rq-final-due', 'completed' );
		for ( $i = 1; $i < count( $path ); $i++ ) {
			$this->assertTrue( Reklamo_Statuses::can_transition( $path[ $i - 1 ], $path[ $i ] ), "{$path[$i-1]} → {$path[$i]}" );
		}
	}

	public function test_changes_loop(): void {
		$this->assertTrue( Reklamo_Statuses::can_transition( 'rq-mockup-sent', 'rq-changes' ) );
		$this->assertTrue( Reklamo_Statuses::can_transition( 'rq-changes', 'rq-mockup-sent' ) );
		$this->assertTrue( Reklamo_Statuses::can_transition( 'rq-approved', 'rq-mockup-sent' ), 'a new revision after approval is allowed' );
	}

	public function test_illegal_jumps_are_refused(): void {
		$this->assertFalse( Reklamo_Statuses::can_transition( 'rq-received', 'completed' ) );
		$this->assertFalse( Reklamo_Statuses::can_transition( 'rq-received', 'rq-approved' ), 'cannot approve without a mockup' );
		$this->assertFalse( Reklamo_Statuses::can_transition( 'rq-approved', 'rq-production' ), 'production needs the deposit first' );
		$this->assertFalse( Reklamo_Statuses::can_transition( 'rq-deposit-paid', 'rq-received' ) );
		$this->assertFalse( Reklamo_Statuses::can_transition( 'rq-final-due', 'rq-approved' ) );
	}

	public function test_every_custom_status_can_be_cancelled_and_stay_put(): void {
		foreach ( array_keys( Reklamo_Statuses::TRANSITIONS ) as $from ) {
			$this->assertTrue( Reklamo_Statuses::can_transition( $from, 'cancelled' ), "$from → cancelled" );
			$this->assertTrue( Reklamo_Statuses::can_transition( $from, $from ), "$from unchanged" );
		}
	}

	public function test_core_statuses_are_unrestricted(): void {
		$this->assertTrue( Reklamo_Statuses::can_transition( 'pending', 'rq-received' ) );
		$this->assertTrue( Reklamo_Statuses::can_transition( 'on-hold', 'completed' ) );
	}
}

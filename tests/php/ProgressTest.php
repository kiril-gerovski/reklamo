<?php
/**
 * Customer progress line: every status lands on exactly one of the six steps.
 */

use PHPUnit\Framework\TestCase;

final class ProgressTest extends TestCase {

	public function test_every_custom_status_maps_to_a_step(): void {
		$expected = array(
			Reklamo_Statuses::RECEIVED     => 1,
			Reklamo_Statuses::MOCKUP_SENT  => 2,
			Reklamo_Statuses::CHANGES      => 2,
			Reklamo_Statuses::APPROVED     => 3,
			Reklamo_Statuses::DEPOSIT_PAID => 4,
			Reklamo_Statuses::PRODUCTION   => 5,
			Reklamo_Statuses::FINAL_DUE    => 6,
		);
		foreach ( Reklamo_Statuses::slugs() as $slug ) {
			$this->assertArrayHasKey( $slug, $expected, "status $slug has no step" );
			$this->assertSame( $expected[ $slug ], Reklamo_Progress::step_for_status( $slug ) );
		}
	}

	public function test_prefixed_and_core_statuses(): void {
		$this->assertSame( 2, Reklamo_Progress::step_for_status( 'wc-rq-mockup-sent' ) );
		$this->assertSame( Reklamo_Progress::DONE, Reklamo_Progress::step_for_status( 'completed' ) );
		$this->assertSame( Reklamo_Progress::CANCELLED, Reklamo_Progress::step_for_status( 'cancelled' ) );
		$this->assertSame( Reklamo_Progress::CANCELLED, Reklamo_Progress::step_for_status( 'wc-refunded' ) );
		$this->assertSame( 1, Reklamo_Progress::step_for_status( 'pending' ) );
	}

	public function test_steps_before_current_are_done_and_completed_finishes_all(): void {
		$this->assertTrue( Reklamo_Progress::is_step_done( 1, 3, false ) );
		$this->assertTrue( Reklamo_Progress::is_step_done( 2, 3, false ) );
		$this->assertFalse( Reklamo_Progress::is_step_done( 3, 3, false ) );
		$this->assertFalse( Reklamo_Progress::is_step_done( 6, 6, false ) );
		$this->assertTrue( Reklamo_Progress::is_step_done( 6, 6, true ) );
		$this->assertCount( 6, Reklamo_Progress::STEPS );
	}
}

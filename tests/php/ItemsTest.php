<?php
/**
 * The order status derived from its products' mockup states.
 */

use PHPUnit\Framework\TestCase;

final class ItemsTest extends TestCase {

	public function test_priority(): void {
		$this->assertSame( 'rq-received', Reklamo_Items::order_status_for( array() ) );
		$this->assertSame( 'rq-received', Reklamo_Items::order_status_for( array( 'waiting', 'waiting' ) ) );
		$this->assertSame( 'rq-mockup-sent', Reklamo_Items::order_status_for( array( 'sent', 'waiting' ) ) );
		$this->assertSame( 'rq-mockup-sent', Reklamo_Items::order_status_for( array( 'approved', 'waiting' ) ), 'an approved product keeps the order past "received"' );
		$this->assertSame( 'rq-changes', Reklamo_Items::order_status_for( array( 'changes', 'sent' ) ), 'an open change request wins over a pending mockup' );
		$this->assertSame( 'rq-mockup-sent', Reklamo_Items::order_status_for( array( 'approved', 'sent' ) ) );
		$this->assertSame( 'rq-approved', Reklamo_Items::order_status_for( array( 'approved', 'approved' ) ) );
		$this->assertSame( 'rq-approved', Reklamo_Items::order_status_for( array( 'approved' ) ) );
	}

	/** Every move a product can make — the designer sends, the customer decides — lands on a legal status change. */
	public function test_every_reachable_change_is_a_legal_transition(): void {
		$states = array( 'waiting', 'sent', 'changes', 'approved' );
		$moves  = array(
			'waiting'  => array( 'sent' ),
			'sent'     => array( 'sent', 'approved', 'changes' ),
			'changes'  => array( 'sent' ),
			'approved' => array( 'sent' ),
		);
		foreach ( array( 1, 2, 3 ) as $count ) {
			foreach ( $this->combinations( $states, $count ) as $before ) {
				$from = Reklamo_Items::order_status_for( $before );
				foreach ( $before as $i => $state ) {
					foreach ( $moves[ $state ] as $next ) {
						$after     = $before;
						$after[ $i ] = $next;
						$to        = Reklamo_Items::order_status_for( $after );
						$this->assertTrue(
							Reklamo_Statuses::can_transition( $from, $to ),
							sprintf( '[%s] → [%s]: %s → %s', implode( ',', $before ), implode( ',', $after ), $from, $to )
						);
					}
				}
			}
		}
	}

	/** @return array<int,string[]> */
	private function combinations( array $states, int $count ): array {
		if ( 0 === $count ) {
			return array( array() );
		}
		$out = array();
		foreach ( $this->combinations( $states, $count - 1 ) as $rest ) {
			foreach ( $states as $s ) {
				$out[] = array_merge( $rest, array( $s ) );
			}
		}
		return $out;
	}
}

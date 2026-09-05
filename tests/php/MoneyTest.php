<?php
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase {

	public function test_deposit_rounds_to_cents_half_up(): void {
		$this->assertSame( 50.0, Reklamo_Money::deposit( 100.0, 50 ) );
		$this->assertSame( 59.5, Reklamo_Money::deposit( 119.0, 50 ) );
		$this->assertSame( 74.5, Reklamo_Money::deposit( 149.0, 50 ) );
		$this->assertSame( 33.0, Reklamo_Money::deposit( 99.99, 33 ), '33% of 99.99 = 32.9967 → 33.00' );
	}

	public function test_deposit_percentage_is_clamped(): void {
		$this->assertSame( 0.0, Reklamo_Money::deposit( 100.0, -10 ) );
		$this->assertSame( 100.0, Reklamo_Money::deposit( 100.0, 250 ) );
		$this->assertSame( 0, Reklamo_Money::pct( 'garbage' ) );
	}

	public function test_balance_never_negative(): void {
		$this->assertSame( 50.0, Reklamo_Money::balance( 100.0, 50.0 ) );
		$this->assertSame( 0.0, Reklamo_Money::balance( 100.0, 150.0 ) );
		$this->assertSame( 59.5, Reklamo_Money::balance( 119.0, 59.5 ) );
	}
}

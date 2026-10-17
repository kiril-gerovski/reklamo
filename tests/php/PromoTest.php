<?php
/**
 * The promotion price: percent off, then down to the nearest ,99.
 */

use PHPUnit\Framework\TestCase;

final class PromoTest extends TestCase {

	public function test_percent_off_rounded_down_to_99(): void {
		$this->assertSame( 296.99, Reklamo_Product::promo_price( 349.99, 15 ), '349,99 − 15 % = 297,49 → 296,99' );
		$this->assertSame( 347.99, Reklamo_Product::promo_price( 409.99, 15 ) );
		$this->assertSame( 38.99, Reklamo_Product::promo_price( 45.99, 15 ) );
	}

	public function test_a_price_already_on_99_stays(): void {
		$this->assertSame( 89.99, Reklamo_Product::promo_price( 99.99, 10 ), '89,991 rounds to 89,99, already on ,99' );
		$this->assertSame( 1799.99, Reklamo_Product::promo_price( 1999.99, 10 ) );
	}

	public function test_never_below_99_cents(): void {
		$this->assertSame( 0.99, Reklamo_Product::promo_price( 1.5, 90 ) );
	}

	public function test_always_cheaper_than_the_regular_price(): void {
		foreach ( array( 41.99, 137.99, 349.99, 539.99, 2115.99 ) as $regular ) {
			foreach ( array( 1, 5, 10, 15, 25, 50 ) as $pct ) {
				$this->assertLessThan( $regular, Reklamo_Product::promo_price( $regular, $pct ), "$regular at $pct %" );
			}
		}
	}
}

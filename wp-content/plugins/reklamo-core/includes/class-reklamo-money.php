<?php
/**
 * Deposit / balance arithmetic. Pure PHP so it is unit-tested; never trust a percentage
 * from options without clamping it.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Money {

	/** Clamp a stored percentage to 0–100. */
	public static function pct( $value ): int {
		$n = (int) $value;
		return max( 0, min( 100, $n ) );
	}

	/** Deposit due for an order total, rounded to cents (half up). */
	public static function deposit( float $total, int $pct ): float {
		return round( $total * self::pct( $pct ) / 100, 2 );
	}

	/** What remains after the deposit; never negative. */
	public static function balance( float $total, float $deposit ): float {
		return max( 0.0, round( $total - $deposit, 2 ) );
	}
}

<?php
/**
 * Approval-link tokens: selector/verifier, like WordPress password resets.
 *
 * Pure PHP on purpose — no WordPress functions — so it is unit-testable on its own.
 * The secret (43 chars, 256 bits) travels only in the emailed URL; the database holds
 * its SHA-256. A leaked dump therefore cannot approve anything. An unkeyed hash is
 * sufficient at this entropy and, unlike wp_hash(), survives a salt rotation.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Token {

	const SELECTOR_LENGTH = 16;
	const SECRET_BYTES    = 32;
	const ALPHABET        = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

	/**
	 * @return array{selector:string, secret:string, hash:string}
	 */
	public static function mint(): array {
		$secret = rtrim( strtr( base64_encode( random_bytes( self::SECRET_BYTES ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe encoding, not obfuscation.
		return array(
			'selector' => self::random_string( self::SELECTOR_LENGTH ),
			'secret'   => $secret,
			'hash'     => self::hash( $secret ),
		);
	}

	public static function hash( string $secret ): string {
		return hash( 'sha256', $secret );
	}

	/** Constant-time comparison; never `==` on hex strings. */
	public static function verify( string $secret, string $stored_hash ): bool {
		return hash_equals( $stored_hash, self::hash( $secret ) );
	}

	public static function is_valid_selector( string $s ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9]{16}$/', $s );
	}

	public static function is_valid_secret( string $s ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/', $s );
	}

	/**
	 * @param string $expires_at_utc MySQL datetime, UTC.
	 * @param int    $now            Unix timestamp.
	 */
	public static function is_expired( string $expires_at_utc, int $now ): bool {
		$ts = strtotime( $expires_at_utc . ' UTC' );
		return false === $ts || $ts <= $now;
	}

	private static function random_string( int $length ): string {
		$out = '';
		$max = strlen( self::ALPHABET ) - 1;
		for ( $i = 0; $i < $length; $i++ ) {
			$out .= self::ALPHABET[ random_int( 0, $max ) ];
		}
		return $out;
	}
}

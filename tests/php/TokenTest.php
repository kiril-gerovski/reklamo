<?php
/**
 * Approval-link token: the one piece of crypto in the project, so it gets real tests.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase {

	public function test_mint_produces_well_formed_parts(): void {
		$t = Reklamo_Token::mint();
		$this->assertTrue( Reklamo_Token::is_valid_selector( $t['selector'] ) );
		$this->assertTrue( Reklamo_Token::is_valid_secret( $t['secret'] ) );
		$this->assertSame( 64, strlen( $t['hash'] ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $t['hash'] );
	}

	public function test_secret_never_equals_hash_and_verify_roundtrips(): void {
		$t = Reklamo_Token::mint();
		$this->assertNotSame( $t['secret'], $t['hash'] );
		$this->assertTrue( Reklamo_Token::verify( $t['secret'], $t['hash'] ) );
	}

	public function test_tampered_secret_fails(): void {
		$t         = Reklamo_Token::mint();
		$tampered  = $t['secret'];
		$tampered[0] = 'A' === $tampered[0] ? 'B' : 'A';
		$this->assertFalse( Reklamo_Token::verify( $tampered, $t['hash'] ) );
		$this->assertFalse( Reklamo_Token::verify( '', $t['hash'] ) );
		$this->assertFalse( Reklamo_Token::verify( $t['secret'], strrev( $t['hash'] ) ) );
	}

	public function test_two_mints_differ(): void {
		$a = Reklamo_Token::mint();
		$b = Reklamo_Token::mint();
		$this->assertNotSame( $a['selector'], $b['selector'] );
		$this->assertNotSame( $a['secret'], $b['secret'] );
	}

	public function test_secret_has_expected_entropy_encoding(): void {
		// 32 random bytes → 43 URL-safe base64 chars, no padding.
		$t = Reklamo_Token::mint();
		$this->assertSame( 43, strlen( $t['secret'] ) );
		$this->assertDoesNotMatchRegularExpression( '/[+\/=]/', $t['secret'] );
	}

	#[DataProvider( 'bad_selectors' )]
	public function test_invalid_selectors_rejected( string $s ): void {
		$this->assertFalse( Reklamo_Token::is_valid_selector( $s ) );
	}

	public static function bad_selectors(): array {
		return array(
			'empty'      => array( '' ),
			'short'      => array( 'abc' ),
			'long'       => array( str_repeat( 'a', 17 ) ),
			'symbols'    => array( 'abcdefghijklmno-' ),
			'sql'        => array( "' OR 1=1 --     " ),
		);
	}

	#[DataProvider( 'bad_secrets' )]
	public function test_invalid_secrets_rejected( string $k ): void {
		$this->assertFalse( Reklamo_Token::is_valid_secret( $k ) );
	}

	public static function bad_secrets(): array {
		return array(
			'empty'   => array( '' ),
			'short'   => array( str_repeat( 'a', 42 ) ),
			'long'    => array( str_repeat( 'a', 44 ) ),
			'padding' => array( str_repeat( 'a', 42 ) . '=' ),
			'slash'   => array( str_repeat( 'a', 42 ) . '/' ),
		);
	}

	public function test_expiry_boundaries(): void {
		$now = 1_800_000_000;
		$this->assertFalse( Reklamo_Token::is_expired( gmdate( 'Y-m-d H:i:s', $now + 60 ), $now ) );
		$this->assertTrue( Reklamo_Token::is_expired( gmdate( 'Y-m-d H:i:s', $now ), $now ), 'expires exactly now counts as expired' );
		$this->assertTrue( Reklamo_Token::is_expired( gmdate( 'Y-m-d H:i:s', $now - 1 ), $now ) );
		$this->assertTrue( Reklamo_Token::is_expired( 'not a date', $now ), 'garbage is treated as expired' );
	}

	public function test_verify_is_constant_time_shape(): void {
		// hash_equals requires same-length strings to be meaningful; make sure we always compare 64-char hex.
		$t = Reklamo_Token::mint();
		$this->assertSame( strlen( $t['hash'] ), strlen( Reklamo_Token::hash( 'anything' ) ) );
	}
}

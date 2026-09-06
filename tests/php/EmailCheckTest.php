<?php
/**
 * Email domain extraction feeding the DNS sanity check.
 */

use PHPUnit\Framework\TestCase;

final class EmailCheckTest extends TestCase {

	public function test_domain_is_lowercased_and_trimmed(): void {
		$this->assertSame( 'abv.bg', Reklamo_Email_Check::domain( 'Ivan@ABV.BG' ) );
		$this->assertSame( 'abv.bg', Reklamo_Email_Check::domain( 'ivan@abv.bg.' ) );
		$this->assertSame( 'sub.firma.bg', Reklamo_Email_Check::domain( 'a@sub.firma.bg' ) );
	}

	public function test_no_domain_gives_empty_string(): void {
		$this->assertSame( '', Reklamo_Email_Check::domain( 'nobody' ) );
		$this->assertSame( '', Reklamo_Email_Check::domain( 'nobody@' ) );
		$this->assertSame( '', Reklamo_Email_Check::domain( '' ) );
	}

	public function test_last_at_sign_wins(): void {
		$this->assertSame( 'example.com', Reklamo_Email_Check::domain( '"a@b"@example.com' ) );
	}

	public function test_idn_domain_is_punycoded_when_intl_is_available(): void {
		if ( ! function_exists( 'idn_to_ascii' ) ) {
			$this->markTestSkipped( 'intl extension not available' );
		}
		$this->assertSame( 'xn--80a2af2a7a.xn--90ae', Reklamo_Email_Check::domain( 'иван@фирма.бг' ) );
	}
}

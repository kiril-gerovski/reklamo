<?php
/**
 * Where a counted view came from, and what is not counted.
 */

use PHPUnit\Framework\TestCase;

final class StatsTest extends TestCase {

	public function test_sources(): void {
		$this->assertSame( 'direct', Reklamo_Stats::source( '', 'reklamo.bg' ) );
		$this->assertSame( 'internal', Reklamo_Stats::source( 'www.reklamo.bg', 'reklamo.bg' ) );
		$this->assertSame( 'google', Reklamo_Stats::source( 'www.google.bg', 'reklamo.bg' ) );
		$this->assertSame( 'google', Reklamo_Stats::source( 'www.google.com', 'reklamo.bg' ) );
		$this->assertSame( 'google', Reklamo_Stats::source( 'com.google.android.googlequicksearchbox', 'reklamo.bg' ) );
		$this->assertSame( 'facebook', Reklamo_Stats::source( 'l.facebook.com', 'reklamo.bg' ) );
		$this->assertSame( 'facebook', Reklamo_Stats::source( 'm.facebook.com', 'reklamo.bg' ) );
		$this->assertSame( 'instagram', Reklamo_Stats::source( 'l.instagram.com', 'reklamo.bg' ) );
		$this->assertSame( 'twitter', Reklamo_Stats::source( 't.co', 'reklamo.bg' ) );
		$this->assertSame( 'abv.bg', Reklamo_Stats::source( 'www.abv.bg', 'reklamo.bg' ) );
		$this->assertSame( 'googlefake.example', Reklamo_Stats::source( 'googlefake.example', 'reklamo.bg' ) );
	}

	public function test_paths(): void {
		$this->assertSame( '/', Reklamo_Stats::clean_path( '/' ) );
		$this->assertSame( '/product/kalendar-mono/', Reklamo_Stats::clean_path( '/product/kalendar-mono/' ) );
		$this->assertSame( '', Reklamo_Stats::clean_path( '/<script>/' ) );
		$this->assertSame( '', Reklamo_Stats::clean_path( '/' . str_repeat( 'a', 200 ) ) );
	}

	public function test_robots_are_not_counted(): void {
		$this->assertTrue( Reklamo_Stats::is_bot( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ) );
		$this->assertTrue( Reklamo_Stats::is_bot( 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 HeadlessChrome/140.0 Safari/537.36' ) );
		$this->assertTrue( Reklamo_Stats::is_bot( '' ) );
		$this->assertFalse( Reklamo_Stats::is_bot( 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1' ) );
	}
}

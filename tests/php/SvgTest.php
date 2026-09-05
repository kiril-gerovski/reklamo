<?php
/**
 * SVG sanitiser: drawing survives, everything executable or external does not.
 */

use PHPUnit\Framework\TestCase;

final class SvgTest extends TestCase {

	private function clean( string $svg ): string {
		$out = Reklamo_Svg::sanitize( $svg );
		$this->assertNotFalse( $out, 'sanitizer refused a file it should have cleaned' );
		return $out;
	}

	public function test_plain_drawing_survives(): void {
		$out = $this->clean( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10z" fill="#b8892b"/><circle cx="5" cy="5" r="2"/></svg>' );
		$this->assertStringContainsString( '<path d="M0 0h10v10z" fill="#b8892b"/>', $out );
		$this->assertStringContainsString( '<circle', $out );
	}

	public function test_script_and_handlers_removed(): void {
		$out = $this->clean( '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><rect width="1" height="1" onclick="x()"/><foreignObject><body/></foreignObject></svg>' );
		$this->assertStringNotContainsString( '<script', $out );
		$this->assertStringNotContainsString( 'onload', $out );
		$this->assertStringNotContainsString( 'onclick', $out );
		$this->assertStringNotContainsString( 'foreignObject', $out );
		$this->assertStringContainsString( '<rect', $out );
	}

	public function test_external_references_removed_local_kept(): void {
		$out = $this->clean( '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><defs><linearGradient id="g"/></defs><rect fill="url(#g)"/><use xlink:href="#g"/><image xlink:href="https://evil.example/x.svg"/><image href="data:image/png;base64,iVBORw0KGgo="/><a href="javascript:alert(1)">x</a></svg>' );
		$this->assertStringContainsString( 'xlink:href="#g"', $out );
		$this->assertStringContainsString( 'data:image/png;base64', $out );
		$this->assertStringNotContainsString( 'evil.example', $out );
		$this->assertStringNotContainsString( 'javascript:', $out );
		$this->assertStringNotContainsString( '<a', $out, 'anchors are not drawing instructions' );
	}

	public function test_style_with_url_or_import_removed(): void {
		$out = $this->clean( '<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(https://x.y/a.css);</style><rect style="fill:red;background:url(javascript:1)"/><rect style="fill:blue"/></svg>' );
		$this->assertStringNotContainsString( '@import', $out );
		$this->assertStringNotContainsString( 'javascript', $out );
		$this->assertStringContainsString( 'style="fill:blue"', $out );
	}

	public function test_entities_and_doctype_refused(): void {
		$this->assertFalse( Reklamo_Svg::sanitize( '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>' ) );
		$this->assertFalse( Reklamo_Svg::sanitize( '<?xml version="1.0"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg/>' ), 'even a harmless-looking DOCTYPE is refused: no parser entity processing at all' );
	}

	public function test_non_svg_and_garbage_refused(): void {
		$this->assertFalse( Reklamo_Svg::sanitize( '<html><body>x</body></html>' ) );
		$this->assertFalse( Reklamo_Svg::sanitize( 'not xml at all' ) );
		$this->assertFalse( Reklamo_Svg::sanitize( str_repeat( 'a', Reklamo_Svg::MAX_BYTES + 1 ) ) );
	}

	public function test_foreign_namespace_elements_removed(): void {
		$out = $this->clean( '<svg xmlns="http://www.w3.org/2000/svg" xmlns:h="http://www.w3.org/1999/xhtml"><h:iframe src="x"/><rect/></svg>' );
		$this->assertStringNotContainsString( 'iframe', $out );
		$this->assertStringContainsString( '<rect', $out );
	}
}

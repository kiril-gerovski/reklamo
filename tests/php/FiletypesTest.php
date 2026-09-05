<?php
/**
 * Magic-byte sniffer: the extension says one thing, the bytes must agree.
 */

use PHPUnit\Framework\TestCase;

final class FiletypesTest extends TestCase {

	public static function good(): array {
		return array(
			'pdf'        => array( 'pdf', "%PDF-1.5\n%\xe2\xe3\xcf\xd3\n" ),
			'ai modern'  => array( 'ai', "%PDF-1.5\n" ),        // .ai files are PDF-wrapped — the trap
			'ai legacy'  => array( 'ai', "%!PS-Adobe-3.0\n" ),
			'eps text'   => array( 'eps', "%!PS-Adobe-3.0 EPSF-3.0\n" ),
			'eps binary' => array( 'eps', "\xC5\xD0\xD3\xC6" . str_repeat( "\0", 28 ) ),
			'psd'        => array( 'psd', "8BPS\x00\x01" ),
			'cdr riff'   => array( 'cdr', "RIFF\x00\x00\x01\x00CDRAvrsn" ),
			'cdr zip'    => array( 'cdr', "PK\x03\x04\x14\x00" ),   // CorelDRAW X4+
			'png'        => array( 'png', "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR" ),
			'jpg'        => array( 'jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF" ),
			'jpeg'       => array( 'jpeg', "\xFF\xD8\xFF\xE1" ),
			'svg plain'  => array( 'svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>' ),
			'svg xml'    => array( 'svg', "<?xml version=\"1.0\"?>\n<!-- x -->\n<svg viewBox=\"0 0 1 1\"/>" ),
			'svg bom'    => array( 'svg', "\xEF\xBB\xBF<svg></svg>" ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'good' )]
	public function test_matching_content_passes( string $ext, string $head ): void {
		$this->assertTrue( Reklamo_Filetypes::sniff_bytes( $head, $ext ) );
	}

	public static function bad(): array {
		return array(
			'exe as ai'        => array( 'ai', "MZ\x90\x00" ),
			'html as svg'      => array( 'svg', '<html><body>x</body></html>' ),
			'php as png'       => array( 'png', '<?php echo 1;' ),
			'pdf as psd'       => array( 'psd', '%PDF-1.4' ),
			'riff wav as cdr'  => array( 'cdr', "RIFF\x00\x00\x01\x00WAVEfmt " ),
			'zip as png'       => array( 'png', "PK\x03\x04" ),
			'empty'            => array( 'pdf', '' ),
			'unknown ext'      => array( 'exe', "MZ\x90\x00" ),
			'svg with doctype first then html' => array( 'svg', '<!DOCTYPE html><svg/>' ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'bad' )]
	public function test_mismatch_fails( string $ext, string $head ): void {
		$this->assertFalse( Reklamo_Filetypes::sniff_bytes( $head, $ext ) );
	}

	public function test_sniff_reads_real_files(): void {
		$f = tempnam( sys_get_temp_dir(), 'rk' );
		file_put_contents( $f, "8BPS\x00\x01" . random_bytes( 100 ) );
		$this->assertTrue( Reklamo_Filetypes::sniff( $f, 'psd' ) );
		$this->assertFalse( Reklamo_Filetypes::sniff( $f, 'pdf' ) );
		unlink( $f );
		$this->assertFalse( Reklamo_Filetypes::sniff( '/nonexistent/file', 'pdf' ) );
	}
}

<?php
/**
 * Real-content check per accepted format. finfo mislabels most design formats
 * (.ai is a PDF, CorelDRAW X4+ is a ZIP, .psd is often octet-stream), and WordPress's own
 * check rejects those mismatches — so we look at the magic bytes ourselves.
 *
 * Pure PHP (no WordPress functions) so it is unit-tested against generated fixtures.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Filetypes {

	/** Canonical MIME per extension, as stored with the file row. */
	const CANONICAL = array(
		'ai'   => 'application/postscript',
		'eps'  => 'application/postscript',
		'pdf'  => 'application/pdf',
		'psd'  => 'image/vnd.adobe.photoshop',
		'cdr'  => 'application/vnd.corel-draw',
		'svg'  => 'image/svg+xml',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
	);

	/**
	 * Does the file's content match what its extension claims?
	 *
	 * @param string $path Absolute path to the file.
	 * @param string $ext  Lower-case extension without dot.
	 */
	public static function sniff( string $path, string $ext ): bool {
		$fh = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $fh ) {
			return false;
		}
		$head = (string) fread( $fh, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return self::sniff_bytes( $head, $ext );
	}

	/** Same check on the first bytes (≥ 12 needed; 4 KB recommended for SVG). */
	public static function sniff_bytes( string $head, string $ext ): bool {
		$starts = static fn( string $sig ): bool => str_starts_with( $head, $sig );
		switch ( strtolower( $ext ) ) {
			case 'pdf':
				return $starts( '%PDF-' );
			case 'ai':
				// Modern .ai files are PDF-wrapped; legacy ones are PostScript.
				return $starts( '%PDF-' ) || $starts( '%!PS-Adobe' );
			case 'eps':
				return $starts( '%!PS-Adobe' ) || $starts( "\xC5\xD0\xD3\xC6" ); // DOS EPS binary header.
			case 'psd':
				return $starts( '8BPS' );
			case 'cdr':
				// ≤ X3: RIFF container with "CDR" form type; X4+: ZIP package.
				return ( $starts( 'RIFF' ) && 'CDR' === substr( $head, 8, 3 ) ) || $starts( "PK\x03\x04" );
			case 'png':
				return $starts( "\x89PNG\r\n\x1a\n" );
			case 'jpg':
			case 'jpeg':
				return $starts( "\xFF\xD8\xFF" );
			case 'svg':
				$text = ltrim( preg_replace( '/^\xEF\xBB\xBF/', '', $head ) ); // strip BOM
				// XML declaration / comments may precede the root; require an <svg root within the head.
				return 1 === preg_match( '/^(<\?xml[^>]*\?>\s*)?(<!--.*?-->\s*)*<svg[\s>]/is', $text );
			default:
				return false;
		}
	}
}

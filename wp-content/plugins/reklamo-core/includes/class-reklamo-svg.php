<?php
/**
 * SVG sanitiser. An SVG is XML that executes script when rendered, so an uploaded logo
 * would be stored XSS against whoever views it. Our storage never serves SVG inline
 * (that alone closes the hole); this is defence in depth: strip everything that is not
 * a drawing instruction and refuse anything with external entities or references.
 *
 * Only DOMDocument/DOMXPath — no WordPress dependencies, unit-tested.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument's own property names.

final class Reklamo_Svg {

	const MAX_BYTES = 2 * 1024 * 1024;
	const MAX_NODES = 50000;

	const ALLOWED_ELEMENTS = array(
		'svg',
		'g',
		'defs',
		'path',
		'rect',
		'circle',
		'ellipse',
		'line',
		'polyline',
		'polygon',
		'text',
		'tspan',
		'textPath',
		'title',
		'desc',
		'lineargradient',
		'radialgradient',
		'stop',
		'clippath',
		'mask',
		'pattern',
		'use',
		'symbol',
		'marker',
		'metadata',
		'style',
		'image',
	);

	/** Attributes that may carry a URL; only fragment refs and data:image PNG/JPEG survive. */
	const URL_ATTRIBUTES = array( 'href', 'xlink:href' );

	/** Why the last sanitize() call returned false: size | entities | invalid | ''. */
	public static string $reason = '';

	/**
	 * @param string $svg Raw file contents.
	 * @return string|false Sanitised XML, or false when the file must be refused.
	 */
	public static function sanitize( string $svg ) {
		self::$reason = '';
		if ( strlen( $svg ) > self::MAX_BYTES ) {
			self::$reason = 'size';
			return false;
		}
		// XXE / billion laughs: refuse any internal subset or entity declaration before the
		// parser sees it. A bare external DOCTYPE (Illustrator writes one by default) is harmless
		// with LIBXML_NONET and is simply dropped.
		if ( preg_match( '/<!ENTITY/i', $svg ) || preg_match( '/<!DOCTYPE[^>]*\[/i', $svg ) ) {
			self::$reason = 'entities';
			return false;
		}
		$svg = (string) preg_replace( '/<!DOCTYPE[^>\[]*>/i', '', $svg );

		$prev = libxml_use_internal_errors( true );
		$dom  = new DOMDocument();
		$ok   = $dom->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING ); // never LIBXML_NOENT
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			self::$reason = 'invalid';
			return false;
		}

		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//*' );
		if ( ! $nodes || $nodes->length > self::MAX_NODES ) {
			self::$reason = 'invalid';
			return false;
		}

		// Iterate over a static list: removing while walking a live NodeList skips siblings.
		$all = array();
		foreach ( $nodes as $n ) {
			$all[] = $n;
		}
		foreach ( $all as $el ) {
			if ( ! $el->parentNode ) {
				continue; // already removed with an ancestor
			}
			$ns   = (string) $el->namespaceURI;
			$name = strtolower( $el->localName );
			if ( ( '' !== $ns && 'http://www.w3.org/2000/svg' !== $ns ) || ! in_array( $name, self::ALLOWED_ELEMENTS, true ) ) {
				$el->parentNode->removeChild( $el );
				continue;
			}
			// Attributes: drop event handlers, scripts in style, and any non-local URL.
			foreach ( iterator_to_array( $el->attributes ) as $attr ) {
				$aname  = strtolower( $attr->localName ); // 'href' for xlink:href, xl:href, href alike
				$avalue = trim( $attr->value );
				$css    = self::css_decode( $avalue );
				$remove = false;
				if ( str_starts_with( $aname, 'on' ) ) {
					$remove = true;
				} elseif ( 'href' === $aname ) {
					$remove = ! self::safe_url( $avalue );
				} elseif ( 'style' === $aname ) {
					$remove = ! self::safe_css( $css );
				} elseif ( preg_match( '/url\s*\(/i', $css ) ) {
					// fill, stroke, clip-path, mask, filter, marker-*: url(...) must stay local.
					$remove = ! self::safe_css( $css );
				} elseif ( preg_match( '/javascript:|data:(?!image\/(png|jpe?g);)/i', $css ) && 'd' !== $aname ) {
					$remove = true;
				}
				if ( $remove ) {
					$el->removeAttribute( $attr->nodeName );
				}
			}
			if ( 'style' === $name && ! self::safe_css( self::css_decode( (string) $el->textContent ) ) ) {
				$el->parentNode->removeChild( $el );
			}
		}

		$out = $dom->saveXML();
		return is_string( $out ) && '' !== $out ? $out : false;
	}

	/** Fragment references (#id) and embedded raster data only. */
	private static function safe_url( string $url ): bool {
		return '' === $url || str_starts_with( $url, '#' ) || 1 === preg_match( '#^data:image/(png|jpe?g);base64,[A-Za-z0-9+/=\s]+$#', $url );
	}

	/**
	 * CSS (an attribute or a whole <style> sheet) may only reference local fragments:
	 * every url(...) must be url(#id); no imports, expressions or script schemes.
	 */
	private static function safe_css( string $css ): bool {
		if ( preg_match( '/(expression\s*\(|@import|javascript:|data:(?!image\/(png|jpe?g);))/i', $css ) ) {
			return false;
		}
		if ( preg_match_all( '/url\s*\(\s*([\'"]?)([^)\'"]*)\1\s*\)/i', $css, $m ) ) {
			foreach ( $m[2] as $target ) {
				if ( ! str_starts_with( trim( $target ), '#' ) ) {
					return false;
				}
			}
		}
		return true;
	}

	/** Undo CSS escapes (\75 rl, u\72 l) and comment splitting so the checks above cannot be dodged. */
	private static function css_decode( string $css ): string {
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		return (string) preg_replace_callback(
			'/\\\\([0-9a-fA-F]{1,6})\s?/',
			static function ( array $m ): string {
				$ch = mb_chr( (int) hexdec( $m[1] ), 'UTF-8' );
				return false === $ch ? '' : $ch;
			},
			$css
		);
	}
}

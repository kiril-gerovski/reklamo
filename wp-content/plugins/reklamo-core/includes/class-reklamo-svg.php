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

	/**
	 * @param string $svg Raw file contents.
	 * @return string|false Sanitised XML, or false when the file must be refused.
	 */
	public static function sanitize( string $svg ) {
		if ( strlen( $svg ) > self::MAX_BYTES ) {
			return false;
		}
		// XXE / billion laughs: refuse before the parser ever sees an entity declaration.
		if ( preg_match( '/<!(DOCTYPE|ENTITY)/i', $svg ) ) {
			return false;
		}

		$prev = libxml_use_internal_errors( true );
		$dom  = new DOMDocument();
		$ok   = $dom->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING ); // never LIBXML_NOENT
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return false;
		}

		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//*' );
		if ( ! $nodes || $nodes->length > self::MAX_NODES ) {
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
				$aname  = strtolower( $attr->name );
				$avalue = trim( $attr->value );
				$remove = false;
				if ( str_starts_with( $aname, 'on' ) ) {
					$remove = true;
				} elseif ( in_array( $aname, self::URL_ATTRIBUTES, true ) || 'xlink:href' === strtolower( $attr->nodeName ) ) {
					$remove = ! self::safe_url( $avalue );
				} elseif ( 'style' === $aname && preg_match( '/(url\s*\(|expression\s*\(|@import|javascript:)/i', $avalue ) ) {
					$remove = true;
				} elseif ( preg_match( '/javascript:|data:(?!image\/(png|jpe?g);)/i', $avalue ) && 'd' !== $aname ) {
					$remove = true;
				}
				if ( $remove ) {
					$el->removeAttribute( $attr->nodeName );
				}
			}
			if ( 'style' === $name && preg_match( '/(url\s*\(|expression\s*\(|@import|javascript:)/i', (string) $el->textContent ) ) {
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
}

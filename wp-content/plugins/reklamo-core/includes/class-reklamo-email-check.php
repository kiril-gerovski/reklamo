<?php
/**
 * Basic sanity check of the customer's email domain. A mistyped address (abv.bh) would
 * send the tracking and approval links to nobody — or to a stranger — with no way for the
 * real customer to recover. We do not verify the mailbox; we only refuse domains that
 * cannot receive mail at all (no MX and no A record, RFC 5321 §5.1).
 *
 * domain() is pure and unit-tested; has_mail_host() talks to DNS.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Email_Check {

	/** Lower-cased, ASCII (punycode) domain part of an address, or '' when there is none. */
	public static function domain( string $email ): string {
		$at = strrpos( $email, '@' );
		if ( false === $at || $at === strlen( $email ) - 1 ) {
			return '';
		}
		$domain = strtolower( trim( substr( $email, $at + 1 ), " \t\n\r\0\x0B." ) );
		if ( '' === $domain ) {
			return '';
		}
		if ( function_exists( 'idn_to_ascii' ) && preg_match( '/[^\x00-\x7F]/', $domain ) ) {
			$ascii = idn_to_ascii( $domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
			if ( is_string( $ascii ) && '' !== $ascii ) {
				$domain = $ascii;
			}
		}
		return $domain;
	}

	/**
	 * Does anything answer for this domain? MX first, A/AAAA as the RFC fallback. Cached a
	 * day per domain so a burst of requests costs one lookup. A DNS outage on the host
	 * would refuse everyone, so an unresolvable lookup is retried, never cached.
	 */
	public static function has_mail_host( string $domain ): bool {
		if ( '' === $domain || ! preg_match( '/^[a-z0-9.-]+\.[a-z0-9-]{2,}$/', $domain ) ) {
			return false;
		}
		$key    = 'reklamo_mx_' . md5( $domain );
		$cached = get_transient( $key );
		if ( 'yes' === $cached ) {
			return true;
		}
		$ok = checkdnsrr( $domain . '.', 'MX' ) || checkdnsrr( $domain . '.', 'A' ) || checkdnsrr( $domain . '.', 'AAAA' );
		if ( $ok ) {
			set_transient( $key, 'yes', DAY_IN_SECONDS );
		}
		return $ok;
	}
}

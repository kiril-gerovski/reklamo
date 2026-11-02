<?php
/**
 * Visits without cookies: each page view sends a beacon with its path and referrer, counted per
 * day, path and source. No IP, no identifier and nothing per visitor is stored, so it needs no
 * consent banner. A view arriving from outside the site counts as a visit.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Stats {

	const TABLE       = 'reklamo_stats';
	const SLUG        = 'reklamo-stats';
	const RATE        = 300; // views per IP per hour.
	const KEEP_MONTHS = 25;
	const PERIODS     = array( 7, 30, 90, 365 );
	const INTERNAL    = 'internal';
	const DIRECT      = 'direct';
	const BOTS        = '/bot|crawl|spider|slurp|headless|lighthouse|facebookexternalhit|preview|monitor|curl|wget|python|java\//i';

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_footer', array( __CLASS__, 'beacon' ), 99 );
		add_action( Reklamo_Cleanup::HOOK, array( __CLASS__, 'purge' ) );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		}
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function routes(): void {
		register_rest_route(
			Reklamo_Upload::NS,
			'/hit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'hit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Not for the shop's own staff, error pages or previews. */
	private static function counts_this_page(): bool {
		return ! is_404() && ! is_preview() && ! is_feed() && ! current_user_can( 'edit_posts' );
	}

	public static function beacon(): void {
		if ( ! self::counts_this_page() ) {
			return;
		}
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/', PHP_URL_PATH );
		printf(
			'<script>navigator.sendBeacon&&navigator.sendBeacon(%s,new URLSearchParams({p:%s,r:document.referrer}));</script>' . "\n",
			wp_json_encode( rest_url( Reklamo_Upload::NS . '/hit' ) ),
			wp_json_encode( self::clean_path( $path ) )
		);
	}

	public static function hit( WP_REST_Request $request ): WP_REST_Response {
		$path = self::clean_path( (string) $request->get_param( 'p' ) );
		$ua   = (string) $request->get_header( 'user_agent' );
		if ( '' !== $path && ! self::is_bot( $ua ) && self::within_rate() ) {
			$host   = (string) wp_parse_url( (string) $request->get_param( 'r' ), PHP_URL_HOST );
			$source = self::source( $host, (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic counter in our own table.
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (day, path, source, views) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE views = views + 1', self::table(), current_time( 'Y-m-d' ), $path, $source ) );
		}
		return new WP_REST_Response( null, 204 );
	}

	/** A site path, or '' for anything that is not one. */
	public static function clean_path( string $path ): string {
		$path = '/' . ltrim( $path, '/' );
		return 1 === preg_match( '#^/[A-Za-z0-9/_.%-]{0,159}$#', $path ) ? $path : '';
	}

	public static function is_bot( string $user_agent ): bool {
		return '' === trim( $user_agent ) || 1 === preg_match( self::BOTS, $user_agent );
	}

	/** Where a view came from: our own site, nowhere, one of the big names, or the referring host. */
	public static function source( string $host, string $own_host ): string {
		$host = strtolower( preg_replace( '/^www\./i', '', $host ) );
		if ( '' === $host ) {
			return self::DIRECT;
		}
		if ( strtolower( preg_replace( '/^www\./i', '', $own_host ) ) === $host ) {
			return self::INTERNAL;
		}
		foreach ( array( 'google', 'facebook', 'instagram', 'bing', 'yahoo', 'linkedin', 'viber', 'duckduckgo' ) as $name ) {
			if ( preg_match( '/(^|\.)' . $name . '\.[a-z.]+$/', $host ) || preg_match( '/^(com|android-app)\.' . $name . '\./', $host ) ) {
				return $name;
			}
		}
		if ( preg_match( '/(^|\.)(fb\.me|fb\.com|t\.co)$/', $host ) ) {
			return 't.co' === substr( $host, -4 ) ? 'twitter' : 'facebook';
		}
		return substr( $host, 0, 48 );
	}

	private static function within_rate(): bool {
		if ( defined( 'REKLAMO_DISABLE_RATE_LIMITS' ) && REKLAMO_DISABLE_RATE_LIMITS ) {
			return true;
		}
		$key = 'reklamo_hit_' . md5( Reklamo_Storage::client_ip() );
		$n   = (int) get_transient( $key );
		if ( $n >= self::RATE ) {
			return false;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	public static function purge(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', self::table(), gmdate( 'Y-m-d', strtotime( '-' . self::KEEP_MONTHS . ' months' ) ) ) );
	}

	public static function menu(): void {
		add_menu_page( __( 'Visits', 'reklamo-core' ), __( 'Visits', 'reklamo-core' ), 'edit_shop_orders', self::SLUG, array( __CLASS__, 'render' ), 'dashicons-chart-bar', '2.7' );
	}

	private static function label( string $source ): string {
		$names = array(
			self::DIRECT => __( 'Direct (typed address, bookmark, an app or email)', 'reklamo-core' ),
			'google'     => 'Google',
			'facebook'   => 'Facebook',
			'instagram'  => 'Instagram',
			'bing'       => 'Bing',
			'yahoo'      => 'Yahoo',
			'linkedin'   => 'LinkedIn',
			'viber'      => 'Viber',
			'duckduckgo' => 'DuckDuckGo',
			'twitter'    => 'X (Twitter)',
		);
		return $names[ $source ] ?? $source;
	}

	/** The page's title where WordPress knows the path, else the path itself. */
	private static function page_name( string $path ): string {
		if ( '/' === $path ) {
			return __( 'Home', 'reklamo-core' );
		}
		$page = get_page_by_path( trim( $path, '/' ) );
		$id   = $page instanceof WP_Post ? $page->ID : url_to_postid( home_url( $path ) );
		if ( $id ) {
			return get_the_title( $id );
		}
		$term = get_term_by( 'slug', basename( $path ), 'product_cat' );
		return $term instanceof WP_Term ? $term->name : urldecode( $path );
	}

	public static function render(): void {
		global $wpdb;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only view switch.
		$days = isset( $_GET['period'] ) ? absint( $_GET['period'] ) : 30;
		$days = in_array( $days, self::PERIODS, true ) ? $days : 30;
		$from = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . ( $days - 1 ) . ' days' ) );
		$t    = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$totals  = $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(views), 0) AS views, COALESCE(SUM(IF(source <> %s, views, 0)), 0) AS visits FROM %i WHERE day >= %s', self::INTERNAL, $t, $from ) );
		$by_day  = $wpdb->get_results( $wpdb->prepare( 'SELECT day, SUM(IF(source <> %s, views, 0)) AS visits, SUM(views) AS views FROM %i WHERE day >= %s GROUP BY day', self::INTERNAL, $t, $from ), OBJECT_K );
		$sources = $wpdb->get_results( $wpdb->prepare( 'SELECT source, SUM(views) AS visits FROM %i WHERE day >= %s AND source <> %s GROUP BY source ORDER BY visits DESC LIMIT 15', $t, $from, self::INTERNAL ) );
		$pages   = $wpdb->get_results( $wpdb->prepare( 'SELECT path, SUM(views) AS views FROM %i WHERE day >= %s GROUP BY path ORDER BY views DESC LIMIT 15', $t, $from ) );
		// phpcs:enable

		$series = array();
		if ( 365 === $days ) {
			for ( $m = 11; $m >= 0; $m-- ) {
				$key            = gmdate( 'Y-m', strtotime( gmdate( 'Y-m-01', strtotime( current_time( 'Y-m-d' ) ) ) . " -{$m} months" ) );
				$series[ $key ] = array( wp_date( 'M Y', strtotime( $key . '-01' ) ), 0, 0 );
			}
			foreach ( $by_day as $day => $row ) {
				$key = substr( $day, 0, 7 );
				if ( isset( $series[ $key ] ) ) {
					$series[ $key ][1] += (int) $row->visits;
					$series[ $key ][2] += (int) $row->views;
				}
			}
		} else {
			for ( $d = $days - 1; $d >= 0; $d-- ) {
				$key            = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . " -{$d} days" ) );
				$series[ $key ] = array( wp_date( 'j M', strtotime( $key ) ), (int) ( $by_day[ $key ]->visits ?? 0 ), (int) ( $by_day[ $key ]->views ?? 0 ) );
			}
		}
		$peak = max( 1, max( array_column( $series, 1 ) ) );

		include REKLAMO_PATH . 'templates/admin-stats.php';
	}
}

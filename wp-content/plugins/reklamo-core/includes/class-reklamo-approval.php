<?php
/**
 * Public mockup approval page at /odobrenie/?s=<selector>&k=<secret>.
 *
 * GET is strictly idempotent — email scanners (Gmail, Defender ATP, corporate proxies)
 * prefetch every link in an email, so a GET must never approve anything. Approval is a
 * POST, made single-use by one atomic UPDATE so a double-click or a racing prefetch
 * cannot approve twice. Each mockup revision mints a fresh token, so an old email can
 * never approve a newer mockup.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Approval {

	const SLUG         = 'odobrenie';
	const QUERY_VAR    = 'reklamo_approval';
	const TTL_DAYS     = 14;
	const MAX_ATTEMPTS = 10;
	const RATE_LIMIT   = 20; // wrong secrets per IP per hour.

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
	}

	public static function rewrite(): void {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'reklamo_tokens';
	}

	/**
	 * Mint a token for one mockup revision and return the URL to email. The secret exists
	 * only in the returned URL; the table stores its hash.
	 */
	public static function issue( WC_Order $order, int $file_id, int $revision ): string {
		global $wpdb;
		$t = Reklamo_Token::mint();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'selector'   => $t['selector'],
				'order_id'   => $order->get_id(),
				'file_id'    => $file_id,
				'revision'   => $revision,
				'hash'       => $t['hash'],
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::TTL_DAYS * DAY_IN_SECONDS ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$order->update_meta_data( '_reklamo_approval_selector', $t['selector'] );
		$order->save();

		return add_query_arg(
			array(
				's' => $t['selector'],
				'k' => $t['secret'],
			),
			home_url( '/' . self::SLUG . '/' )
		);
	}

	/** @return object[] newest first */
	public static function for_order( int $order_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}reklamo_tokens WHERE order_id = %d ORDER BY revision DESC, id DESC", $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? $rows : array();
	}

	public static function handle(): void {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' ); // the secret is in the query string.

		$is_post  = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
		$src      = $is_post ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification -- token is the authenticator; nonce checked below for POST.
		$selector = isset( $src['s'] ) ? sanitize_text_field( wp_unslash( $src['s'] ) ) : '';
		$secret   = isset( $src['k'] ) ? sanitize_text_field( wp_unslash( $src['k'] ) ) : '';

		if ( ! Reklamo_Token::is_valid_selector( $selector ) || ! Reklamo_Token::is_valid_secret( $secret ) ) {
			self::not_found();
		}
		if ( self::rate_limited() ) {
			status_header( 429 );
			self::render( 'rate_limited', array() );
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}reklamo_tokens WHERE selector = %s", $selector ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $row || (int) $row->attempts >= self::MAX_ATTEMPTS ) {
			self::not_found();
		}
		if ( ! Reklamo_Token::verify( $secret, $row->hash ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}reklamo_tokens SET attempts = attempts + 1 WHERE selector = %s", $selector ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::bump_rate_limit();
			self::not_found();
		}

		$order = wc_get_order( (int) $row->order_id );
		$file  = Reklamo_Storage::by_id( (int) $row->file_id );
		if ( ! $order || ! $file ) {
			self::not_found();
		}

		$vars = array(
			'order'    => $order,
			'file'     => $file,
			'token'    => $row,
			'selector' => $selector,
			'secret'   => $secret,
		);

		if ( Reklamo_Token::is_expired( $row->expires_at, time() ) && ! $row->used_at ) {
			self::render( 'expired', $vars );
		}

		// Inline preview of the mockup for the token holder (raster only; PDF downloads).
		if ( ! $is_post && isset( $_GET['view'] ) && 'mockup' === $_GET['view'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			Reklamo_Storage::serve_file( $file, true );
		}

		if ( $row->used_at ) {
			self::render( 'used', $vars );
		}

		if ( ! $is_post ) {
			self::render( 'review', $vars );
		}

		// ---- POST: approve or request changes ----
		if ( ! isset( $_POST['_reklamo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_reklamo_nonce'] ) ), 'reklamo_decide_' . $selector ) ) {
			self::render( 'expired', $vars );
		}
		$action  = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( ! in_array( $action, array( 'approve', 'changes' ), true ) ) {
			self::render( 'review', $vars );
		}
		if ( 'changes' === $action && '' === trim( $message ) ) {
			$vars['error'] = __( 'Please describe the changes you would like.', 'reklamo-core' );
			self::render( 'review', $vars );
		}

		// Atomic single-use: exactly one request can flip used_at from NULL.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}reklamo_tokens SET used_at = %s, used_action = %s WHERE selector = %s AND used_at IS NULL",
				current_time( 'mysql', true ),
				$action,
				$selector
			)
		);
		if ( 1 !== (int) $wpdb->rows_affected ) {
			$row->used_at  = current_time( 'mysql', true );
			$vars['token'] = $row;
			self::render( 'used', $vars );
		}

		$ip = Reklamo_Storage::client_ip();
		if ( 'approve' === $action ) {
			$order->update_meta_data( '_reklamo_approved_at', current_time( 'mysql', true ) );
			$order->update_meta_data( '_reklamo_approved_revision', (int) $row->revision );
			$order->save();
			$order->update_status(
				Reklamo_Statuses::APPROVED,
				sprintf(
					/* translators: 1: mockup revision number, 2: IP address */
					__( 'Customer approved mockup #%1$d (IP %2$s).', 'reklamo-core' ),
					(int) $row->revision,
					'' !== $ip ? $ip : '-'
				)
			);
			$vars['token']->used_action = 'approve';
			self::render( 'approved', $vars );
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: mockup revision number, 2: customer message */
				__( 'Customer requested changes to mockup #%1$d: %2$s', 'reklamo-core' ),
				(int) $row->revision,
				$message
			)
		);
		$order->update_status( Reklamo_Statuses::RECEIVED, __( 'Changes requested — back to the designer.', 'reklamo-core' ) );
		$vars['token']->used_action = 'changes';
		self::render( 'changes', $vars );
	}

	private static function rate_key(): string {
		return 'reklamo_apr_' . md5( Reklamo_Storage::client_ip() );
	}

	private static function rate_limited(): bool {
		return (int) get_transient( self::rate_key() ) >= self::RATE_LIMIT;
	}

	private static function bump_rate_limit(): void {
		$n = (int) get_transient( self::rate_key() );
		set_transient( self::rate_key(), $n + 1, HOUR_IN_SECONDS );
	}

	private static function not_found(): void {
		status_header( 404 );
		self::render( 'not_found', array() );
	}

	/**
	 * Standalone page: no theme header/footer and ZERO third-party assets — any external
	 * request from this page would leak the secret in the Referer.
	 */
	private static function render( string $view, array $vars ): void {
		$vars['view'] = $view;
		// Closure scope: the template's variables never touch the global namespace.
		( static function ( array $vars ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- used by the included template.
			include REKLAMO_PATH . 'templates/approval.php';
		} )( $vars );
		exit;
	}
}

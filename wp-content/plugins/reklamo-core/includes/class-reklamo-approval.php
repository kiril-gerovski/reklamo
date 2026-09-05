<?php
/**
 * Public customer pages at /odobrenie/?s=<selector>&k=<secret>, keyed by token purpose:
 *
 *   approval — review a mockup; POST approves once or requests changes (single-use)
 *   details  — after approval: company / invoice / delivery details (re-editable while
 *              the deposit is pending; expires with the token)
 *
 * GET is strictly idempotent — email scanners prefetch every link, so a GET must never
 * change anything. Approval is a POST made single-use by one atomic UPDATE, so a
 * double-click or a racing prefetch cannot approve twice. Each mockup revision mints a
 * fresh token; an old email can never approve a newer mockup.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Approval {

	const SLUG         = 'odobrenie';
	const QUERY_VAR    = 'reklamo_approval';
	const TTL_DAYS     = 14;
	const DETAILS_TTL  = 45;
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

	/**
	 * Mint a token and return the URL to email. The secret exists only in the returned
	 * URL; the table stores its hash. Older live tokens with the same purpose are expired,
	 * so a reminder link supersedes the original.
	 */
	public static function issue( WC_Order $order, int $file_id, int $revision, string $purpose = 'approval' ): string {
		global $wpdb;
		$ttl = 'details' === $purpose ? self::DETAILS_TTL : self::TTL_DAYS;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}reklamo_tokens SET expires_at = %s WHERE order_id = %d AND purpose = %s AND used_at IS NULL AND expires_at > %s",
				current_time( 'mysql', true ),
				$order->get_id(),
				$purpose,
				current_time( 'mysql', true )
			)
		);

		$t = Reklamo_Token::mint();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'reklamo_tokens',
			array(
				'selector'   => $t['selector'],
				'order_id'   => $order->get_id(),
				'file_id'    => $file_id,
				'revision'   => $revision,
				'hash'       => $t['hash'],
				'purpose'    => $purpose,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $ttl * DAY_IN_SECONDS ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$order->update_meta_data( '_reklamo_' . $purpose . '_selector', $t['selector'] );
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
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}reklamo_tokens WHERE order_id = %d ORDER BY id DESC", $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? $rows : array();
	}

	/** Latest mockup revision number for an order (0 = none). */
	public static function latest_revision( int $order_id ): int {
		$rev = 0;
		foreach ( Reklamo_Storage::for_order( $order_id, 'mockup' ) as $m ) {
			$rev = max( $rev, (int) $m->revision );
		}
		return $rev;
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
		if ( ! $order ) {
			self::not_found();
		}

		$vars = array(
			'order'    => $order,
			'token'    => $row,
			'selector' => $selector,
			'secret'   => $secret,
		);

		if ( 'details' === $row->purpose ) {
			self::handle_details( $order, $row, $vars, $is_post );
		}
		self::handle_approval( $order, $row, $vars, $is_post );
	}

	/* ------------------------------------------------------------------ approval */

	private static function handle_approval( WC_Order $order, object $row, array $vars, bool $is_post ): void {
		global $wpdb;
		$file = Reklamo_Storage::by_id( (int) $row->file_id );
		if ( ! $file ) {
			self::not_found();
		}
		$vars['file'] = $file;

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

		if ( ! isset( $_POST['_reklamo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_reklamo_nonce'] ) ), 'reklamo_decide_' . $vars['selector'] ) ) {
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
				$vars['selector']
			)
		);
		if ( 1 !== (int) $wpdb->rows_affected ) {
			$row->used_at  = current_time( 'mysql', true );
			$vars['token'] = $row;
			self::render( 'used', $vars );
		}

		$ip = Reklamo_Storage::client_ip();
		if ( 'approve' === $action ) {
			$deposit = Reklamo_Money::deposit( (float) $order->get_total(), (int) Reklamo_Settings::get( 'deposit_pct', '50' ) );
			$order->update_meta_data( '_reklamo_approved_at', current_time( 'mysql', true ) );
			$order->update_meta_data( '_reklamo_approved_revision', (int) $row->revision );
			$order->update_meta_data( '_reklamo_deposit_amount', wc_format_decimal( $deposit, 2 ) );
			$order->save();
			$order->update_status(
				Reklamo_Statuses::APPROVED,
				sprintf(
					/* translators: 1: mockup revision number, 2: IP address */
					__( 'Customer approved mockup #%1$d (IP %2$s).', 'reklamo-core' ),
					(int) $row->revision,
					$ip ? $ip : '-'
				)
			);
			// Straight on to the details step; the deposit email (with this same link) goes out too.
			$details_url = self::issue( $order, (int) $row->file_id, (int) $row->revision, 'details' );
			Reklamo_Emails::send_deposit_request( $order, $details_url );
			wp_safe_redirect( $details_url );
			exit;
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: mockup revision number, 2: customer message */
				__( 'Customer requested changes to mockup #%1$d: %2$s', 'reklamo-core' ),
				(int) $row->revision,
				$message
			)
		);
		$order->update_meta_data( '_reklamo_last_change_request', $message );
		$order->save();
		$order->update_status( Reklamo_Statuses::CHANGES, __( 'Changes requested — back to the designer.', 'reklamo-core' ) );
		$vars['token']->used_action = 'changes';
		self::render( 'changes', $vars );
	}

	/* ------------------------------------------------------------------- details */

	/** Fields collected after approval, all stored on the order. */
	const DETAIL_FIELDS = array( 'customer_type', 'company', 'eik', 'vat', 'mol', 'phone', 'address_1', 'city', 'postcode', 'note' );

	public static function details( WC_Order $order ): array {
		return array(
			'customer_type' => (string) $order->get_meta( '_reklamo_customer_type' ),
			'company'       => $order->get_billing_company(),
			'eik'           => (string) $order->get_meta( '_reklamo_eik' ),
			'vat'           => (string) $order->get_meta( '_reklamo_vat' ),
			'mol'           => (string) $order->get_meta( '_reklamo_mol' ),
			'phone'         => $order->get_billing_phone(),
			'address_1'     => $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
			'city'          => $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city(),
			'postcode'      => $order->get_shipping_postcode() ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
			'note'          => (string) $order->get_meta( '_reklamo_delivery_note' ),
			'submitted_at'  => (string) $order->get_meta( '_reklamo_details_at' ),
		);
	}

	private static function handle_details( WC_Order $order, object $row, array $vars, bool $is_post ): void {
		if ( Reklamo_Token::is_expired( $row->expires_at, time() ) ) {
			self::render( 'expired', $vars );
		}
		$vars['details'] = self::details( $order );
		$vars['deposit'] = (float) $order->get_meta( '_reklamo_deposit_amount' );
		$vars['bank']    = Reklamo_Settings::bank_details_html( sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) );
		$vars['locked']  = ! $order->has_status( Reklamo_Statuses::APPROVED ); // deposit already received → read-only

		if ( ! $is_post ) {
			self::render( 'details', $vars );
		}
		if ( $vars['locked'] ) {
			self::render( 'details', $vars );
		}
		if ( ! isset( $_POST['_reklamo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_reklamo_nonce'] ) ), 'reklamo_details_' . $vars['selector'] ) ) {
			self::render( 'expired', $vars );
		}

		$in = array();
		foreach ( self::DETAIL_FIELDS as $f ) {
			$in[ $f ] = isset( $_POST[ 'd_' . $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'd_' . $f ] ) ) : '';
		}
		$in['customer_type'] = 'company' === $in['customer_type'] ? 'company' : 'person';

		$errors = array();
		if ( 'company' === $in['customer_type'] ) {
			if ( '' === $in['company'] ) {
				$errors[] = __( 'Please enter the company name.', 'reklamo-core' );
			}
			if ( ! preg_match( '/^\d{9}(\d{4})?$/', $in['eik'] ) ) {
				$errors[] = __( 'The company ID (ЕИК) must be 9 or 13 digits.', 'reklamo-core' );
			}
			if ( '' !== $in['vat'] && ! preg_match( '/^BG\d{9,10}$/i', str_replace( ' ', '', $in['vat'] ) ) ) {
				$errors[] = __( 'The VAT number should look like BG123456789.', 'reklamo-core' );
			}
			if ( '' === $in['mol'] ) {
				$errors[] = __( 'Please enter the responsible person (МОЛ).', 'reklamo-core' );
			}
		}
		foreach ( array( 'phone', 'address_1', 'city', 'postcode' ) as $req ) {
			if ( '' === $in[ $req ] ) {
				$errors[] = __( 'Please fill in phone and delivery address.', 'reklamo-core' );
				break;
			}
		}
		if ( $errors ) {
			$vars['errors']  = $errors;
			$vars['details'] = array_merge( $vars['details'], $in );
			self::render( 'details', $vars );
		}

		$first_time = '' === (string) $order->get_meta( '_reklamo_details_at' );
		$order->set_billing_company( 'company' === $in['customer_type'] ? $in['company'] : '' );
		$order->set_billing_phone( $in['phone'] );
		$order->set_billing_address_1( $in['address_1'] );
		$order->set_billing_city( $in['city'] );
		$order->set_billing_postcode( $in['postcode'] );
		$order->set_billing_country( 'BG' );
		$order->set_shipping_first_name( $order->get_billing_first_name() );
		$order->set_shipping_last_name( $order->get_billing_last_name() );
		$order->set_shipping_company( $order->get_billing_company() );
		$order->set_shipping_address_1( $in['address_1'] );
		$order->set_shipping_city( $in['city'] );
		$order->set_shipping_postcode( $in['postcode'] );
		$order->set_shipping_country( 'BG' );
		$order->update_meta_data( '_reklamo_customer_type', $in['customer_type'] );
		$order->update_meta_data( '_reklamo_eik', 'company' === $in['customer_type'] ? $in['eik'] : '' );
		$order->update_meta_data( '_reklamo_vat', 'company' === $in['customer_type'] ? strtoupper( str_replace( ' ', '', $in['vat'] ) ) : '' );
		$order->update_meta_data( '_reklamo_mol', 'company' === $in['customer_type'] ? $in['mol'] : '' );
		$order->update_meta_data( '_reklamo_delivery_note', $in['note'] );
		$order->update_meta_data( '_reklamo_details_at', current_time( 'mysql', true ) );
		$order->save();
		$order->add_order_note( $first_time ? __( 'Customer submitted invoice and delivery details.', 'reklamo-core' ) : __( 'Customer updated invoice and delivery details.', 'reklamo-core' ) );
		if ( $first_time ) {
			Reklamo_Emails::send_admin_details( $order );
		}

		$vars['details'] = self::details( $order );
		$vars['saved']   = true;
		self::render( 'details', $vars );
	}

	/* ------------------------------------------------------------------- helpers */

	private static function rate_key(): string {
		return 'reklamo_apr_' . md5( Reklamo_Storage::client_ip() );
	}


	/** All public rate limiters honour REKLAMO_DISABLE_RATE_LIMITS (local/E2E only — never in production). */
	private static function limits_enabled(): bool {
		return ! ( defined( 'REKLAMO_DISABLE_RATE_LIMITS' ) && REKLAMO_DISABLE_RATE_LIMITS );
	}

	private static function rate_limited(): bool {
		return self::limits_enabled() && (int) get_transient( self::rate_key() ) >= self::RATE_LIMIT;
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
		( static function ( array $vars ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			include REKLAMO_PATH . 'templates/approval.php';
		} )( $vars );
		exit;
	}
}

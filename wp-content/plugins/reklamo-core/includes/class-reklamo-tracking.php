<?php
/**
 * The customer's order page at /porachka/?s=<selector>&k=<secret>: progress, mockup
 * history, payments, and what happens next. Passwordless — the link in every email is
 * the login. View-only: approving and submitting details still need their own
 * single-use tokens, so a forwarded email can read but never act.
 *
 * The token lives in the same table as approval links (purpose `track`). Unlike them
 * its URL is kept in order meta — every later email must carry it, and the secret
 * exists nowhere else. A database dump already contains everything this link shows,
 * so storing it there adds no exposure. It stays valid until the retention sweep
 * removes the order's files, then the page reports that it has expired.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Tracking {

	const SLUG       = 'porachka';
	const QUERY_VAR  = 'reklamo_track';
	const PURPOSE    = 'track';
	const META_URL   = '_reklamo_track_url';
	const TTL_YEARS  = 10;
	const RESEND_GAP = 10 * MINUTE_IN_SECONDS;

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
		add_action( 'woocommerce_email_order_meta', array( __CLASS__, 'email_link' ), 5, 4 );
	}

	public static function rewrite(): void {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * The order's tracking URL, minted on first use — any order with a customer email
	 * gets one, including orders the owner types in by hand. Without an email there is
	 * nobody to send it to, so '' and the emails simply carry no link.
	 */
	public static function url( WC_Order $order ): string {
		$url = (string) $order->get_meta( self::META_URL );
		if ( '' !== $url ) {
			return $url;
		}
		if ( ! $order->get_id() || ! $order->get_billing_email() ) {
			return '';
		}
		global $wpdb;
		$t = Reklamo_Token::mint();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'reklamo_tokens',
			array(
				'selector'   => $t['selector'],
				'order_id'   => $order->get_id(),
				'file_id'    => 0,
				'revision'   => 0,
				'hash'       => $t['hash'],
				'purpose'    => self::PURPOSE,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::TTL_YEARS * YEAR_IN_SECONDS ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$url = add_query_arg(
			array(
				's' => $t['selector'],
				'k' => $t['secret'],
			),
			home_url( '/' . self::SLUG . '/' )
		);
		$order->update_meta_data( self::META_URL, $url );
		$order->save();
		return $url;
	}

	/** Retention sweep: the files are gone, so the page goes with them. */
	public static function expire_for_order( int $order_id ): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}reklamo_tokens SET expires_at = %s WHERE order_id = %d AND purpose = %s AND expires_at > %s",
				current_time( 'mysql', true ),
				$order_id,
				self::PURPOSE,
				current_time( 'mysql', true )
			)
		);
	}

	/** "Track your order" line in every customer email, ours and WooCommerce's alike. */
	public static function email_link( $order, bool $sent_to_admin, bool $plain_text, $email = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( $sent_to_admin || ! $order instanceof WC_Order ) {
			return;
		}
		$url = self::url( $order );
		if ( '' === $url ) {
			return;
		}
		$label = __( 'Track your order', 'reklamo-core' );
		$hint  = __( 'Progress, mockups and payments for this order, any time, without a password. Keep this email.', 'reklamo-core' );
		if ( $plain_text ) {
			echo esc_html( $label . ': ' ) . esc_url( $url ) . "\n" . esc_html( $hint ) . "\n\n";
			return;
		}
		printf(
			'<p style="margin:24px 0 8px; padding:14px 16px; background:#faf8f4; border:1px solid #e8e2d6; border-radius:6px; font-size:14px;"><strong>%s:</strong> <a href="%s">%s</a><br><span style="color:#555; font-size:12px;">%s</span></p>',
			esc_html( $label ),
			esc_url( $url ),
			esc_html( $url ),
			esc_html( $hint )
		);
	}

	/* ------------------------------------------------------------------ request */

	public static function handle(): void {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );

		$is_post  = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
		$src      = $is_post ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification -- the token authenticates; POST also checks a nonce below.
		$selector = isset( $src['s'] ) ? sanitize_text_field( wp_unslash( $src['s'] ) ) : '';
		$secret   = isset( $src['k'] ) ? sanitize_text_field( wp_unslash( $src['k'] ) ) : '';

		$row = Reklamo_Approval::lookup( $selector, $secret );
		if ( 'rate_limited' === $row ) {
			status_header( 429 );
			self::render( 'rate_limited', array() );
		}
		if ( ! is_object( $row ) || self::PURPOSE !== $row->purpose ) {
			status_header( 404 );
			self::render( 'not_found', array() );
		}
		$order = wc_get_order( (int) $row->order_id );
		if ( ! $order ) {
			status_header( 404 );
			self::render( 'not_found', array() );
		}
		$vars = array(
			'order'    => $order,
			'selector' => $selector,
			'secret'   => $secret,
			'url'      => (string) $order->get_meta( self::META_URL ),
		);
		if ( Reklamo_Token::is_expired( $row->expires_at, time() ) ) {
			self::render( 'expired', $vars );
		}

		// Inline view / download of this order's own files.
		if ( ! $is_post && isset( $_GET['view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$file = Reklamo_Storage::by_id( absint( $_GET['view'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! $file || (int) $file->order_id !== $order->get_id() ) {
				status_header( 404 );
				self::render( 'not_found', array() );
			}
			Reklamo_Storage::serve_file( $file, 'mockup' === $file->kind );
		}

		if ( $is_post ) {
			self::handle_resend( $order, $vars );
		}

		$vars['flash'] = isset( $_GET['sent'] ) ? sanitize_key( wp_unslash( $_GET['sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$vars['fresh'] = isset( $_GET['new'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		self::render( 'track', array_merge( $vars, self::view_data( $order ) ) );
	}

	/**
	 * "Send me the email again": re-issues the pending action link (approval or details)
	 * and re-sends its email. The only thing a POST here can do — and it only ever mails
	 * the address on the order, so a forwarded link cannot redirect anything.
	 */
	private static function handle_resend( WC_Order $order, array $vars ): void {
		$back = $vars['url'];
		if ( ! isset( $_POST['_reklamo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_reklamo_nonce'] ) ), 'reklamo_track_' . $vars['selector'] ) ) {
			wp_safe_redirect( add_query_arg( 'sent', 'expired', $back ) );
			exit;
		}
		$gap_key = 'reklamo_track_resend_' . $order->get_id();
		if ( get_transient( $gap_key ) ) {
			wp_safe_redirect( add_query_arg( 'sent', 'wait', $back ) );
			exit;
		}
		$ok = false;
		if ( $order->has_status( Reklamo_Statuses::MOCKUP_SENT ) ) {
			$rev  = Reklamo_Approval::latest_revision( $order->get_id() );
			$file = null;
			foreach ( Reklamo_Storage::for_order( $order->get_id(), 'mockup' ) as $m ) {
				if ( (int) $m->revision === $rev ) {
					$file = $m;
				}
			}
			if ( $file ) {
				$url = Reklamo_Approval::issue( $order, (int) $file->id, $rev, 'approval' );
				$ok  = Reklamo_Emails::send_mockup( $order, $url, $rev );
			}
		} elseif ( $order->has_status( Reklamo_Statuses::APPROVED ) ) {
			$url = Reklamo_Approval::issue( $order, 0, (int) $order->get_meta( '_reklamo_approved_revision' ), 'details' );
			$ok  = Reklamo_Emails::send_deposit_request( $order, $url );
		} else {
			wp_safe_redirect( add_query_arg( 'sent', 'none', $back ) );
			exit;
		}
		if ( $ok ) {
			set_transient( $gap_key, 1, self::RESEND_GAP );
			$order->add_order_note( __( 'Customer asked for the email to be sent again from the order page.', 'reklamo-core' ) );
		}
		wp_safe_redirect( add_query_arg( 'sent', $ok ? 'ok' : 'failed', $back ) );
		exit;
	}

	/* --------------------------------------------------------------- view data */

	/** Everything the template shows, computed once. */
	public static function view_data( WC_Order $order ): array {
		$status  = $order->get_status();
		$step    = Reklamo_Progress::step_for_status( $status );
		$deposit = (float) $order->get_meta( '_reklamo_deposit_amount' );
		$total   = (float) $order->get_total();
		$price   = static fn( float $v ): string => wp_strip_all_tags( wc_price( $v, array( 'currency' => $order->get_currency() ) ) );
		$date    = static fn( string $utc ): string => $utc ? wc_format_datetime( new WC_DateTime( $utc . ' UTC' ), 'd.m.Y' ) : '';

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = $item->get_name() . ( $item->get_quantity() > 1 ? ' × ' . (int) $item->get_quantity() : '' );
		}

		// Mockup revisions with their outcome (from the approval tokens) and the customer's comments.
		$outcomes = array();
		foreach ( Reklamo_Approval::for_order( $order->get_id() ) as $t ) {
			if ( 'approval' === $t->purpose && $t->used_at && ! isset( $outcomes[ (int) $t->revision ] ) ) {
				$outcomes[ (int) $t->revision ] = $t;
			}
		}
		$comments = (array) $order->get_meta( '_reklamo_change_requests' );
		$mockups  = array();
		foreach ( array_reverse( Reklamo_Storage::for_order( $order->get_id(), 'mockup' ) ) as $m ) {
			$rev       = (int) $m->revision;
			$t         = $outcomes[ $rev ] ?? null;
			$mockups[] = array(
				'revision' => $rev,
				'file'     => $m,
				'date'     => $date( (string) $m->created_at ),
				'outcome'  => $t ? (string) $t->used_action : '',
				'decided'  => $t ? $date( (string) $t->used_at ) : '',
				'comment'  => (string) ( $comments[ $rev ] ?? '' ),
				'gone'     => '' === (string) $m->path,
				'is_image' => in_array( $m->ext, array( 'png', 'jpg', 'jpeg' ), true ),
			);
		}

		$logo = null;
		foreach ( Reklamo_Storage::for_order( $order->get_id(), 'logo' ) as $l ) {
			$logo = $l;
		}

		$step_labels = array(
			__( 'Request received', 'reklamo-core' ),
			__( 'Mockup', 'reklamo-core' ),
			__( 'Approval', 'reklamo-core' ),
			__( 'Deposit', 'reklamo-core' ),
			__( 'Production', 'reklamo-core' ),
			__( 'Final payment & delivery', 'reklamo-core' ),
		);

		return array(
			'status'       => $status,
			'status_label' => wc_get_order_status_name( $status ),
			'step'         => $step,
			'completed'    => 'completed' === $status,
			'cancelled'    => Reklamo_Progress::CANCELLED === $step,
			'step_labels'  => $step_labels,
			'items'        => $items,
			'created'      => $order->get_date_created() ? wc_format_datetime( $order->get_date_created(), 'd.m.Y' ) : '',
			'completed_on' => $order->get_date_completed() ? wc_format_datetime( $order->get_date_completed(), 'd.m.Y' ) : '',
			'mockups'      => $mockups,
			'logo'         => $logo,
			'pending_rev'  => Reklamo_Approval::latest_revision( $order->get_id() ),
			'last_comment' => (string) $order->get_meta( '_reklamo_last_change_request' ),
			'total'        => $price( $total ),
			'deposit'      => $deposit > 0 ? $price( $deposit ) : '',
			'deposit_paid' => $date( (string) $order->get_meta( '_reklamo_deposit_paid_at' ) ),
			'balance'      => $deposit > 0 ? $price( Reklamo_Money::balance( $total, $deposit ) ) : '',
			'final_paid'   => $date( (string) $order->get_meta( '_reklamo_final_paid_at' ) ),
			'bank'         => Reklamo_Settings::bank_details_html( sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) ),
			'details'      => Reklamo_Approval::details( $order ),
			'deadline_h'   => Reklamo_Settings::get( 'mockup_deadline', '24' ),
			'contact'      => array_filter(
				array(
					'email' => Reklamo_Settings::get( 'email' ) ? Reklamo_Settings::get( 'email' ) : (string) get_option( 'woocommerce_email_from_address' ),
					'phone' => Reklamo_Settings::get( 'phone' ),
				)
			),
		);
	}

	/** Standalone page, inline CSS only — the secret is in the URL, so no third-party requests. */
	private static function render( string $view, array $vars ): void {
		$vars['view'] = $view;
		( static function ( array $vars ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			include REKLAMO_PATH . 'templates/tracking.php';
		} )( $vars );
		exit;
	}
}

<?php
/**
 * Custom order statuses for the request → mockup → approval flow.
 *
 * HPOS rules apply everywhere: read/write through the WC_Order object, compare with
 * has_status() (statuses are stored as `wc-rq-received`, returned as `rq-received`).
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Statuses {

	const RECEIVED    = 'rq-received';
	const MOCKUP_SENT = 'rq-mockup-sent';
	const APPROVED    = 'rq-approved';

	/**
	 * Allowed transitions. Free-form status dropdowns plus custom statuses is how
	 * these builds rot; illegal jumps are logged as an order note (Phase 3 enforces).
	 */
	const TRANSITIONS = array(
		self::RECEIVED    => array( self::MOCKUP_SENT, 'cancelled' ),
		self::MOCKUP_SENT => array( self::RECEIVED, self::APPROVED, 'cancelled' ),
		self::APPROVED    => array( self::MOCKUP_SENT, 'completed', 'cancelled' ),
	);

	public static function init(): void {
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( __CLASS__, 'register' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'labels' ) );
		add_filter( 'wc_order_is_editable', array( __CLASS__, 'keep_editable' ), 10, 2 );
		add_filter( 'woocommerce_valid_order_statuses_for_cancel', array( __CLASS__, 'cancellable' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'audit_transition' ), 10, 4 );
		// Core "New order" admin email only knows core statuses; fire it for ours.
		add_action( 'woocommerce_order_status_' . self::RECEIVED, array( __CLASS__, 'notify_admin_new_order' ), 10, 2 );
	}

	/** @return array<string,string> slug => label */
	public static function all(): array {
		return array(
			self::RECEIVED    => __( 'Request received', 'reklamo-core' ),
			self::MOCKUP_SENT => __( 'Mockup sent', 'reklamo-core' ),
			self::APPROVED    => __( 'Approved — awaiting deposit', 'reklamo-core' ),
		);
	}

	public static function slugs(): array {
		return array_keys( self::all() );
	}

	/** Feeds both HPOS and legacy storage; WooCommerce calls register_post_status() for us. */
	public static function register( array $statuses ): array {
		$counts = array(
			/* translators: %s: number of orders */
			self::RECEIVED    => _n_noop( 'Request received <span class="count">(%s)</span>', 'Request received <span class="count">(%s)</span>', 'reklamo-core' ),
			/* translators: %s: number of orders */
			self::MOCKUP_SENT => _n_noop( 'Mockup sent <span class="count">(%s)</span>', 'Mockup sent <span class="count">(%s)</span>', 'reklamo-core' ),
			/* translators: %s: number of orders */
			self::APPROVED    => _n_noop( 'Approved — awaiting deposit <span class="count">(%s)</span>', 'Approved — awaiting deposit <span class="count">(%s)</span>', 'reklamo-core' ),
		);
		foreach ( self::all() as $slug => $label ) {
			$statuses[ 'wc-' . $slug ] = array(
				'label'                     => $label,
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => $counts[ $slug ],
			);
		}
		return $statuses;
	}

	/** Dropdown / list-table labels, inserted after Pending so the list reads in workflow order. */
	public static function labels( array $statuses ): array {
		$ours = array();
		foreach ( self::all() as $slug => $label ) {
			$ours[ 'wc-' . $slug ] = $label;
		}
		$pos = array_search( 'wc-pending', array_keys( $statuses ), true );
		$pos = false === $pos ? 0 : $pos + 1;
		return array_slice( $statuses, 0, $pos, true ) + $ours + array_slice( $statuses, $pos, null, true );
	}

	/** Core returns editable only for pending/on-hold; without this the owner cannot touch our orders. */
	public static function keep_editable( bool $editable, WC_Order $order ): bool {
		return $editable || $order->has_status( array( self::RECEIVED, self::MOCKUP_SENT, self::APPROVED ) );
	}

	public static function cancellable( array $statuses ): array {
		return array_values( array_unique( array_merge( $statuses, self::slugs() ) ) );
	}

	public static function audit_transition( int $order_id, string $from, string $to, WC_Order $order ): void {
		if ( ! isset( self::TRANSITIONS[ $from ] ) ) {
			return;
		}
		if ( ! in_array( $to, self::TRANSITIONS[ $from ], true ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: previous status, 2: new status */
					__( 'Warning: status changed from "%1$s" to "%2$s", which is outside the normal flow.', 'reklamo-core' ),
					wc_get_order_status_name( $from ),
					wc_get_order_status_name( $to )
				)
			);
		}
	}

	public static function notify_admin_new_order( int $order_id, $order = null ): void {
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['WC_Email_New_Order'] ) ) {
			$emails['WC_Email_New_Order']->trigger( $order_id, $order );
		}
	}

	/**
	 * Put the order into the initial status, exactly once. Called from the gateway and
	 * from both checkout-processed hooks, because the Store API can re-save the order
	 * after the gateway returns.
	 */
	public static function force_initial( WC_Order $order, string $note = '' ): void {
		$fresh = wc_get_order( $order->get_id() );
		if ( ! $fresh || $fresh->has_status( self::slugs() ) ) {
			return;
		}
		$fresh->update_status( self::RECEIVED, '' !== $note ? $note : __( 'Request received. No payment taken.', 'reklamo-core' ) );
	}

	/**
	 * Analytics ignores statuses it does not know about, so revenue would read zero and
	 * the owner would conclude the site is broken. Legacy Reports cannot be fixed; use Analytics.
	 */
	public static function ensure_analytics_settings(): void {
		$actionable = (array) get_option( 'woocommerce_actionable_order_statuses', array( 'processing', 'on-hold' ) );
		update_option( 'woocommerce_actionable_order_statuses', array_values( array_unique( array_merge( $actionable, self::slugs() ) ) ) );

		$excluded = (array) get_option( 'woocommerce_excluded_report_order_statuses', array( 'pending', 'failed', 'cancelled' ) );
		update_option( 'woocommerce_excluded_report_order_statuses', array_values( array_diff( $excluded, self::slugs() ) ) );
	}
}

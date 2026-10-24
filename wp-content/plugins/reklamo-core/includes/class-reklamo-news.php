<?php
/**
 * "What's new" for the shop: every customer action flags the order for each user who works
 * on orders, and opening the order clears the flag for that user only. Shown as a count on
 * the Orders menu and in the admin bar (kept fresh by the Heartbeat), and on the order rows.
 * Kept in user meta, order id => [time, event], so counting never queries the orders.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_News {

	const USER_META = 'reklamo_news';

	/** @var array{0:int,1:string}|null The news cleared by opening the order in this request. */
	private static ?array $opened = null;

	public static function init(): void {
		add_action( 'woocommerce_order_status_' . Reklamo_Statuses::RECEIVED, array( __CLASS__, 'received' ), 20 );
		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'forget' ) );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'forget' ) );
		add_action( 'admin_init', array( __CLASS__, 'open' ) ); // before admin_menu draws the count
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
		add_filter( 'heartbeat_received', array( __CLASS__, 'heartbeat' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'bar_style' ) );
	}

	/** @return array<string,string> event => what the shop sees */
	public static function labels(): array {
		return array(
			'received'        => __( 'New request', 'reklamo-core' ),
			'approved'        => __( 'The customer approved a mockup', 'reklamo-core' ),
			'approved_all'    => __( 'The customer approved every mockup', 'reklamo-core' ),
			'changes'         => __( 'The customer requested changes', 'reklamo-core' ),
			'details'         => __( 'The customer submitted the invoice details', 'reklamo-core' ),
			'details_updated' => __( 'The customer changed the invoice details', 'reklamo-core' ),
			'expired'         => __( 'Cancelled automatically: the payment did not arrive', 'reklamo-core' ),
			'stale'           => __( 'The customer has not answered any reminder', 'reklamo-core' ),
		);
	}

	public static function received( int $order_id ): void {
		self::flag( $order_id, 'received' );
	}

	/** Flag the order for everyone who works on orders, except whoever caused it. */
	public static function flag( int $order_id, string $event ): void {
		$users = get_users(
			array(
				'capability' => 'edit_shop_orders',
				'fields'     => 'ID',
			)
		);
		foreach ( $users as $user_id ) {
			$user_id = (int) $user_id;
			if ( get_current_user_id() === $user_id ) {
				continue;
			}
			$news              = self::of_user( $user_id );
			$news[ $order_id ] = array( time(), $event );
			update_user_meta( $user_id, self::USER_META, $news );
		}
	}

	public static function forget( int $order_id ): void {
		foreach ( get_users( array( 'meta_key' => self::USER_META ) ) as $user ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$news = self::of_user( $user->ID );
			if ( isset( $news[ $order_id ] ) ) {
				unset( $news[ $order_id ] );
				update_user_meta( $user->ID, self::USER_META, $news );
			}
		}
	}

	/** @return array<int,array{0:int,1:string}> order id => [time, event], newest first */
	public static function of_user( int $user_id = 0 ): array {
		$news = get_user_meta( $user_id ? $user_id : get_current_user_id(), self::USER_META, true );
		$news = is_array( $news ) ? $news : array();
		uasort( $news, static fn( array $a, array $b ): int => $b[0] <=> $a[0] );
		return $news;
	}

	public static function count(): int {
		return count( self::of_user() );
	}

	/** @return array{0:int,1:string}|null The current user's unseen news on this order. */
	public static function of_order( int $order_id ): ?array {
		return self::of_user()[ $order_id ] ?? null;
	}

	/** The news this request cleared by opening the order, so the order screen can still show it. */
	public static function opened(): ?array {
		return self::$opened;
	}

	public static function describe( array $news ): string {
		return sprintf(
			/* translators: 1: what happened, 2: how long ago */
			__( '%1$s · %2$s ago', 'reklamo-core' ),
			self::labels()[ $news[1] ] ?? $news[1],
			human_time_diff( $news[0] )
		);
	}

	/** Opening an order is seeing it. */
	public static function open(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading which order is on screen.
		$id = 'wc-orders' === ( $_GET['page'] ?? '' ) && 'edit' === ( $_GET['action'] ?? '' ) ? absint( $_GET['id'] ?? 0 ) : 0;
		// phpcs:enable
		$news = $id ? self::of_user() : array();
		if ( isset( $news[ $id ] ) ) {
			self::$opened = $news[ $id ];
			unset( $news[ $id ] );
			update_user_meta( get_current_user_id(), self::USER_META, $news );
		}
	}

	public static function orders_url(): string {
		return admin_url( 'admin.php?page=wc-orders' );
	}

	/** Straight to the orders that need the shop, instead of the dashboard. */
	public static function login_redirect( string $to, string $requested, $user ): string {
		if ( $user instanceof WP_User && user_can( $user, 'edit_shop_orders' ) && ( '' === $requested || admin_url() === $requested ) ) {
			return self::orders_url();
		}
		return $to;
	}

	public static function badge( int $count ): string {
		return sprintf( ' <span class="awaiting-mod reklamo-news-badge count-%1$d"><span class="reklamo-news-count">%1$d</span></span>', $count );
	}

	public static function admin_bar( WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$count = self::count();
		if ( ! $count && ! is_admin() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'reklamo-news',
				'href'  => self::orders_url(),
				'title' => '<span class="ab-icon dashicons dashicons-bell" aria-hidden="true"></span><span class="ab-label reklamo-news-count">' . $count . '</span>',
				'meta'  => array(
					'class' => $count ? 'reklamo-news-on' : '',
					'title' => __( 'New in the orders', 'reklamo-core' ),
				),
			)
		);
	}

	public static function heartbeat( array $response, array $data ): array {
		if ( ! empty( $data['reklamo_news'] ) && current_user_can( 'edit_shop_orders' ) ) {
			$response['reklamo_news'] = self::count();
		}
		return $response;
	}

	public static function assets(): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		wp_enqueue_style( 'reklamo-admin', REKLAMO_URL . 'assets/css/admin.css', array(), REKLAMO_VERSION );
		wp_enqueue_script( 'reklamo-news', REKLAMO_URL . 'assets/js/admin-news.js', array( 'jquery', 'heartbeat' ), REKLAMO_VERSION, true );
	}

	/** The bell in the admin bar on the shop pages too. */
	public static function bar_style(): void {
		if ( is_admin_bar_showing() && current_user_can( 'edit_shop_orders' ) ) {
			wp_enqueue_style( 'reklamo-admin', REKLAMO_URL . 'assets/css/admin.css', array(), REKLAMO_VERSION );
		}
	}
}

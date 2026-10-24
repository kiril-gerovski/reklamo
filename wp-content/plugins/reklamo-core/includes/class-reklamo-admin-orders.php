<?php
/**
 * The orders list as a work queue: Orders is a top-level menu item with the news count, the
 * list opens on "Your turn", the status filter is five groups instead of a dozen statuses,
 * and every row says what the next step is. Menu items the shop never uses are hidden (the
 * pages still open by URL).
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Admin_Orders {

	const SCREEN = 'woocommerce_page_wc-orders';
	const MENU   = 'admin.php?page=wc-orders';

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 999 );
		add_action( 'admin_head', array( __CLASS__, 'drop_wc_orders_item' ) );
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( __CLASS__, 'menu_order' ), 99 );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'trim_admin_bar' ), 999 );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'trim_dashboard' ) );
		add_filter( 'views_' . self::SCREEN, array( __CLASS__, 'views' ) );
		add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( __CLASS__, 'query_args' ) );
		add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'columns' ) );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'woocommerce_shop_order_list_table_order_css_classes', array( __CLASS__, 'row_classes' ), 10, 2 );
		add_filter( 'woocommerce_admin_order_buyer_name', array( __CLASS__, 'buyer' ), 10, 2 );
		add_filter( 'get_user_option_meta-box-order_' . self::SCREEN, array( __CLASS__, 'box_order' ) );
		add_filter( 'default_hidden_meta_boxes', array( __CLASS__, 'hidden_boxes' ), 10, 2 );
	}

	/** @return array<string,array{0:string,1:string[]}> group => [label, statuses] */
	public static function groups(): array {
		return array(
			'todo'       => array( __( 'Your turn', 'reklamo-core' ), array( Reklamo_Statuses::RECEIVED, Reklamo_Statuses::CHANGES, Reklamo_Statuses::DEPOSIT_PAID ) ),
			'waiting'    => array( __( 'Waiting for the customer', 'reklamo-core' ), array( Reklamo_Statuses::MOCKUP_SENT, Reklamo_Statuses::APPROVED, Reklamo_Statuses::FINAL_DUE ) ),
			'production' => array( __( 'In production', 'reklamo-core' ), array( Reklamo_Statuses::PRODUCTION ) ),
			'done'       => array( __( 'Completed', 'reklamo-core' ), array( 'completed' ) ),
			'cancelled'  => array( __( 'Cancelled', 'reklamo-core' ), array( 'cancelled', 'refunded', 'failed' ) ),
			'all'        => array( __( 'All', 'reklamo-core' ), array() ),
		);
	}

	/** The group on screen: the one asked for, or "Your turn" when the list is opened plain. */
	public static function group(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading the list filter.
		$asked = sanitize_key( wp_unslash( $_GET['reklamo_group'] ?? '' ) );
		if ( isset( self::groups()[ $asked ] ) ) {
			return $asked;
		}
		foreach ( array( 'status', 's', 'm', '_customer_user', 'action', 'id', 'paged', 'orderby' ) as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				return '';
			}
		}
		// phpcs:enable
		return 'todo';
	}

	/** @return int[] Orders in the "your turn" statuses, plus every order with news for this user. */
	private static function todo_ids(): array {
		$ids = wc_get_orders(
			array(
				'status' => self::groups()['todo'][1],
				'limit'  => -1,
				'return' => 'ids',
			)
		);
		return array_values( array_unique( array_merge( array_map( 'intval', $ids ), array_keys( Reklamo_News::of_user() ) ) ) );
	}

	public static function query_args( array $args ): array {
		$group = self::group();
		if ( 'todo' === $group ) {
			$ids        = self::todo_ids();
			$args['id'] = $ids ? $ids : array( 0 );
		} elseif ( '' !== $group && 'all' !== $group ) {
			$args['status'] = self::groups()[ $group ][1];
		}
		return $args;
	}

	public static function views( array $views ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$counts  = \Automattic\WooCommerce\Utilities\OrderUtil::get_count_for_type( 'shop_order' );
		$current = self::group();
		$out     = array();
		foreach ( self::groups() as $key => $g ) {
			if ( 'todo' === $key ) {
				$n = count( self::todo_ids() );
			} elseif ( 'all' === $key ) {
				$n = array_sum( array_intersect_key( $counts, wc_get_order_statuses() ) );
			} else {
				$n = 0;
				foreach ( $g[1] as $status ) {
					$n += (int) ( $counts[ 'wc-' . $status ] ?? 0 );
				}
			}
			$out[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( admin_url( self::MENU . '&reklamo_group=' . $key ) ),
				$key === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $g[0] ),
				$n
			);
		}
		return $out;
	}

	public static function columns( array $columns ): array {
		return array(
			'cb'              => $columns['cb'] ?? '<input type="checkbox" />',
			'order_number'    => $columns['order_number'] ?? __( 'Order', 'reklamo-core' ),
			'reklamo_items'   => __( 'Products', 'reklamo-core' ),
			'reklamo_step'    => __( 'Stage and next step', 'reklamo-core' ),
			'order_total'     => $columns['order_total'] ?? __( 'Total', 'reklamo-core' ),
			'reklamo_changed' => __( 'Last change', 'reklamo-core' ),
		);
	}

	public static function column( string $column, WC_Order $order ): void {
		switch ( $column ) {
			case 'reklamo_items':
				$names = array();
				foreach ( Reklamo_Items::products( $order ) as $item ) {
					$names[] = $item->get_name() . ' ×' . $item->get_quantity();
				}
				echo esc_html( implode( ', ', $names ) );
				break;
			case 'reklamo_step':
				printf( '<mark class="order-status status-%1$s"><span>%2$s</span></mark>', esc_attr( sanitize_html_class( $order->get_status() ) ), esc_html( wc_get_order_status_name( $order->get_status() ) ) );
				$next = self::next_step( $order );
				if ( '' !== $next ) {
					echo '<span class="reklamo-next-step">→ ' . esc_html( $next ) . '</span>';
				}
				break;
			case 'reklamo_changed':
				$news = Reklamo_News::of_order( $order->get_id() );
				if ( $news ) {
					echo '<strong class="reklamo-news-line">● ' . esc_html( Reklamo_News::describe( $news ) ) . '</strong>';
					break;
				}
				$changed = $order->get_date_modified() ? $order->get_date_modified() : $order->get_date_created();
				if ( $changed ) {
					/* translators: %s: how long ago */
					echo esc_html( sprintf( __( '%s ago', 'reklamo-core' ), human_time_diff( $changed->getTimestamp() ) ) );
				}
				break;
		}
	}

	public static function row_classes( array $classes, WC_Order $order ): array {
		if ( Reklamo_News::of_order( $order->get_id() ) ) {
			$classes[] = 'reklamo-is-new';
		}
		return $classes;
	}

	public static function buyer( string $buyer, WC_Order $order ): string {
		$company = trim( $order->get_billing_company() );
		return '' !== $company && $company !== $buyer ? $buyer . ' · ' . $company : $buyer;
	}

	/** What the shop does next with this order, or '' when nothing is left. */
	public static function next_step( WC_Order $order ): string {
		$production = Reklamo_Money::balance( (float) $order->get_total(), (float) $order->get_meta( '_reklamo_deposit_amount' ) ) > 0
			? __( 'Produce it, then request the final payment', 'reklamo-core' )
			: __( 'Produce it, then dispatch', 'reklamo-core' );
		$steps      = array(
			Reklamo_Statuses::RECEIVED     => __( 'Make the mockup and send it', 'reklamo-core' ),
			Reklamo_Statuses::CHANGES      => __( 'Make the changes and send a new mockup', 'reklamo-core' ),
			Reklamo_Statuses::MOCKUP_SENT  => __( 'Waiting for the customer to decide on the mockup', 'reklamo-core' ),
			Reklamo_Statuses::APPROVED     => __( 'Waiting for the payment — check the bank account', 'reklamo-core' ),
			Reklamo_Statuses::DEPOSIT_PAID => __( 'Start production', 'reklamo-core' ),
			Reklamo_Statuses::PRODUCTION   => $production,
			Reklamo_Statuses::FINAL_DUE    => __( 'Waiting for the final payment, then dispatch', 'reklamo-core' ),
		);
		return $steps[ $order->get_status() ] ?? '';
	}

	public static function menu(): void {
		add_menu_page(
			__( 'Orders', 'reklamo-core' ),
			__( 'Orders', 'reklamo-core' ) . Reklamo_News::badge( Reklamo_News::count() ),
			'edit_shop_orders',
			self::MENU,
			'',
			'dashicons-clipboard',
			'2.5'
		);
		remove_menu_page( 'edit.php' );
		remove_menu_page( 'edit-comments.php' );
		remove_menu_page( 'woocommerce-marketing' );
		remove_menu_page( 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM' );
	}

	/** Products right under Orders; after WooCommerce's own menu_order, which moves it next to WooCommerce. */
	public static function menu_order( array $order ): array {
		$order = array_values( array_diff( $order, array( 'edit.php?post_type=product' ) ) );
		$at    = array_search( self::MENU, $order, true );
		if ( false !== $at ) {
			array_splice( $order, $at + 1, 0, array( 'edit.php?post_type=product' ) );
		}
		return $order;
	}

	/** WooCommerce's own Orders item duplicates ours. Removed after the access check, which looks it up. */
	public static function drop_wc_orders_item(): void {
		remove_submenu_page( 'woocommerce', 'wc-orders' );
	}

	public static function parent_file( $parent_file ) {
		global $plugin_page;
		return 'wc-orders' === $plugin_page ? self::MENU : $parent_file;
	}

	public static function trim_admin_bar( WP_Admin_Bar $bar ): void {
		$bar->remove_node( 'comments' );
		$bar->remove_node( 'new-post' );
	}

	/** Quick Draft writes blog posts, which the shop does not have. */
	public static function trim_dashboard(): void {
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	}

	/** Our box right under the order header, above the line items, until the user drags it elsewhere. */
	public static function box_order( $order ) {
		return $order ? $order : array(
			'normal' => 'woocommerce-order-data,reklamo_mockup,woocommerce-order-items',
			'side'   => 'woocommerce-order-actions,reklamo_details,woocommerce-order-notes',
		);
	}

	public static function hidden_boxes( array $hidden, WP_Screen $screen ): array {
		return self::SCREEN === $screen->id ? array_merge( $hidden, array( 'order_custom', 'woocommerce-order-downloads' ) ) : $hidden;
	}
}

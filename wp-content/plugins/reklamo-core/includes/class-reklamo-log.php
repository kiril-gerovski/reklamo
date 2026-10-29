<?php
/**
 * Audit log: who did what and when, in WooCommerce → Status → Logs, source "reklamo".
 * Every email, every order note, every scheduled job of ours and every change to a
 * Reklamo setting, each line with its actor: a user, the customer's link, or automatic.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Log {

	const SOURCE    = 'reklamo';
	const KEEP_DAYS = 365;

	/** @var string Hook of the scheduled action running in this request. */
	private static string $runner = '';

	/** @var string Status of the order the running action is about, before it ran. */
	private static string $before = '';

	public static function init(): void {
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'mail_sent' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'mail_failed' ) );
		add_action( 'woocommerce_order_note_added', array( __CLASS__, 'note' ), 10, 2 );
		add_action( 'action_scheduler_begin_execute', array( __CLASS__, 'begin' ), 10, 1 );
		add_action( 'action_scheduler_after_execute', array( __CLASS__, 'after' ), 10, 2 );
		add_action( 'action_scheduler_failed_execution', array( __CLASS__, 'failed' ), 10, 2 );
		add_action( 'updated_option', array( __CLASS__, 'setting' ), 10, 3 );
		add_action( 'added_option', array( __CLASS__, 'setting_added' ), 10, 2 );
		add_filter( 'woocommerce_logger_delete_expired_file', array( __CLASS__, 'keep' ), 10, 2 );
	}

	public static function info( string $message, int $order_id = 0 ): void {
		self::write( 'info', $message, $order_id );
	}

	public static function error( string $message, int $order_id = 0 ): void {
		self::write( 'error', $message, $order_id );
	}

	private static function write( string $level, string $message, int $order_id ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		$line = $order_id ? sprintf( /* translators: %d: order number */ __( 'Order %d', 'reklamo-core' ), $order_id ) . ' · ' : '';
		$line = '[' . self::actor() . '] ' . $line . Reklamo_Privacy::mask_ips( $message );
		wc_get_logger()->log( $level, $line, array( 'source' => self::SOURCE ) );
	}

	/** Who is acting in this request. */
	public static function actor(): string {
		if ( '' !== self::$runner ) {
			/* translators: %s: scheduled action hook */
			return sprintf( __( 'automatic: %s', 'reklamo-core' ), self::job( self::$runner ) );
		}
		if ( wp_doing_cron() ) {
			return __( 'automatic: WP-Cron', 'reklamo-core' );
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'WP-CLI';
		}
		$user = wp_get_current_user();
		if ( $user->exists() ) {
			return $user->user_login;
		}
		return __( 'customer or visitor', 'reklamo-core' );
	}

	/** A scheduled job in words, e.g. "payment reminder (reklamo_remind_deposit)". */
	public static function job( string $hook ): string {
		$names = array(
			'reklamo_remind_approval' => __( 'mockup approval reminder', 'reklamo-core' ),
			'reklamo_remind_deposit'  => __( 'payment reminder', 'reklamo-core' ),
			'reklamo_remind_final'    => __( 'final payment reminder', 'reklamo-core' ),
			'reklamo_stale_alert'     => __( 'alert to the shop that the customer is silent', 'reklamo-core' ),
			'reklamo_payment_expire'  => __( 'automatic cancellation for non-payment', 'reklamo-core' ),
			'reklamo_cleanup'         => __( 'file and data cleanup', 'reklamo-core' ),
		);
		return isset( $names[ $hook ] ) ? $names[ $hook ] . ' (' . $hook . ')' : $hook;
	}

	/** Several jobs in words, repeats counted: "mockup approval reminder (…) ×3, …". */
	public static function jobs( array $hooks ): string {
		$out = array();
		foreach ( array_count_values( $hooks ) as $hook => $n ) {
			$out[] = self::job( (string) $hook ) . ( $n > 1 ? ' ×' . $n : '' );
		}
		return implode( ', ', $out );
	}

	private static function recipients( $to ): string {
		return implode( ', ', array_map( 'trim', is_array( $to ) ? $to : explode( ',', (string) $to ) ) );
	}

	public static function mail_sent( array $mail ): void {
		$files = count( (array) ( $mail['attachments'] ?? array() ) );
		/* translators: 1: recipients, 2: subject */
		$line = sprintf( __( 'Email sent to %1$s: „%2$s“', 'reklamo-core' ), self::recipients( $mail['to'] ?? '' ), $mail['subject'] ?? '' );
		/* translators: %d: number of attachments */
		self::info( $files ? $line . ' ' . sprintf( _n( '(%d attachment)', '(%d attachments)', $files, 'reklamo-core' ), $files ) : $line );
	}

	public static function mail_failed( WP_Error $error ): void {
		$mail = (array) $error->get_error_data();
		/* translators: 1: recipients, 2: subject, 3: error message */
		self::error( sprintf( __( 'Email to %1$s NOT sent: „%2$s“ — %3$s', 'reklamo-core' ), self::recipients( $mail['to'] ?? '' ), $mail['subject'] ?? '', $error->get_error_message() ) );
	}

	/**
	 * @param int      $comment_id Note id.
	 * @param WC_Order $order      Order.
	 */
	public static function note( $comment_id, $order ): void {
		$comment = get_comment( $comment_id );
		if ( ! $comment || ! $order instanceof WC_Order ) {
			return;
		}
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $comment->comment_content ) ) );
		if ( get_comment_meta( $comment_id, 'is_customer_note', true ) ) {
			$text = __( 'Note to the customer:', 'reklamo-core' ) . ' ' . $text;
		}
		self::info( $text, $order->get_id() );
	}

	private static function action( int $action_id ): ?ActionScheduler_Action {
		$action = ActionScheduler::store()->fetch_action( $action_id );
		return $action instanceof ActionScheduler_NullAction ? null : $action;
	}

	/** The order a reklamo action is about, if any. */
	private static function order_of( ActionScheduler_Action $action ): ?WC_Order {
		$args = $action->get_args();
		if ( 'reklamo' !== $action->get_group() || ! isset( $args[0] ) || ! is_numeric( $args[0] ) ) {
			return null;
		}
		$order = wc_get_order( (int) $args[0] );
		return $order instanceof WC_Order ? $order : null;
	}

	public static function begin( $action_id ): void {
		$action       = self::action( (int) $action_id );
		self::$runner = $action ? $action->get_hook() : '';
		$order        = $action ? self::order_of( $action ) : null;
		self::$before = $order ? $order->get_status() : '';
	}

	public static function after( $action_id, $action ): void {
		if ( $action instanceof ActionScheduler_Action && 'reklamo' === $action->get_group() && Reklamo_Cleanup::HOOK !== $action->get_hook() ) {
			$order = self::order_of( $action );
			self::info(
				/* translators: 1: action hook, 2: order status when it ran */
				sprintf( __( 'Ran %1$s; the order was „%2$s“ at the time.', 'reklamo-core' ), self::job( $action->get_hook() ), '' !== self::$before ? wc_get_order_status_name( self::$before ) : '-' ),
				$order ? $order->get_id() : 0
			);
		}
		self::$runner = '';
		self::$before = '';
	}

	public static function failed( $action_id, $e ): void {
		$action = self::action( (int) $action_id );
		$order  = $action ? self::order_of( $action ) : null;
		/* translators: 1: action hook, 2: error message */
		self::error( sprintf( __( 'Scheduled action %1$s FAILED: %2$s', 'reklamo-core' ), $action ? self::job( $action->get_hook() ) : (string) $action_id, $e instanceof Throwable ? $e->getMessage() : '' ), $order ? $order->get_id() : 0 );
		self::$runner = '';
		self::$before = '';
	}

	/** A scheduled reminder or deadline, so the log shows why it fired when it did. */
	public static function planned( string $hook, int $time, int $order_id ): void {
		/* translators: 1: action hook, 2: date and time */
		self::info( sprintf( __( 'Scheduled %1$s for %2$s.', 'reklamo-core' ), self::job( $hook ), Reklamo_Payment_Deadline::format( $time ) ), $order_id );
	}

	/**
	 * @param string $option Option name.
	 * @param mixed  $old    Previous value.
	 * @param mixed  $value  New value.
	 */
	public static function setting( $option, $old, $value ): void {
		if ( ! array_key_exists( (string) $option, Reklamo_Settings::DEFAULTS ) || ! is_scalar( $old ?? '' ) || ! is_scalar( $value ?? '' ) ) {
			return;
		}
		/* translators: 1: option name, 2: old value, 3: new value */
		self::info( sprintf( __( 'Setting %1$s changed from „%2$s“ to „%3$s“.', 'reklamo-core' ), $option, (string) $old, (string) $value ) );
	}

	/**
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 */
	public static function setting_added( $option, $value ): void {
		self::setting( $option, '', $value );
	}

	/**
	 * Our log outlives WooCommerce's general retention: it is what answers "why did this happen".
	 *
	 * @param bool                                                  $delete Delete the file.
	 * @param Automattic\WooCommerce\Internal\Admin\Logging\FileV2\File $file   Log file.
	 */
	public static function keep( $delete, $file ): bool {
		if ( $delete && is_object( $file ) && method_exists( $file, 'get_source' ) && self::SOURCE === $file->get_source() ) {
			return $file->get_created_timestamp() < time() - self::KEEP_DAYS * DAY_IN_SECONDS;
		}
		return (bool) $delete;
	}
}

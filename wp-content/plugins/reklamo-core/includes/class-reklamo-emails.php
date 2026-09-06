<?php
/**
 * Registers our WC_Email classes. Subject, heading and body text are owner-editable
 * under WooCommerce → Settings → Emails; the defaults come from the translation file.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Emails {

	public static function init(): void {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register' ) );
		add_filter( 'woocommerce_email_actions', array( __CLASS__, 'actions' ) );
	}

	/** WC_Email exists only once the mailer loads, hence the lazy requires. */
	public static function register( array $emails ): array {
		require_once REKLAMO_PATH . 'includes/emails/class-reklamo-email.php';
		foreach ( array( 'request-received', 'mockup-sent', 'deposit-request', 'production-started', 'final-payment', 'admin-changes', 'admin-details' ) as $slug ) {
			require_once REKLAMO_PATH . 'includes/emails/class-reklamo-email-' . $slug . '.php';
		}
		$emails['Reklamo_Email_Request_Received']   = new Reklamo_Email_Request_Received();
		$emails['Reklamo_Email_Mockup_Sent']        = new Reklamo_Email_Mockup_Sent();
		$emails['Reklamo_Email_Deposit_Request']    = new Reklamo_Email_Deposit_Request();
		$emails['Reklamo_Email_Production_Started'] = new Reklamo_Email_Production_Started();
		$emails['Reklamo_Email_Final_Payment']      = new Reklamo_Email_Final_Payment();
		$emails['Reklamo_Email_Admin_Changes']      = new Reklamo_Email_Admin_Changes();
		$emails['Reklamo_Email_Admin_Details']      = new Reklamo_Email_Admin_Details();
		return $emails;
	}

	/**
	 * WC_Emails attaches its (possibly deferred) sender only to hooks in this list, and
	 * fires "<hook>_notification" for them. Skip this and the emails work in dev and
	 * silently stop the day background sending is enabled.
	 */
	public static function actions( array $actions ): array {
		$ours = array();
		foreach ( Reklamo_Statuses::slugs() as $slug ) {
			$ours[] = 'woocommerce_order_status_' . $slug;
		}
		return array_values( array_unique( array_merge( $actions, $ours ) ) );
	}

	private static function email( string $key ) {
		$emails = WC()->mailer()->get_emails();
		return $emails[ $key ] ?? null;
	}

	/**
	 * Sent directly, not from the status hook: the link secret exists only in this
	 * request, so the email cannot be reconstructed later from a queued status event.
	 * Returns whether the mail transport accepted the message so the admin screen can
	 * tell the shop the truth; failures are also written as order notes.
	 */
	public static function send_mockup( WC_Order $order, string $approval_url, int $revision, bool $reminder = false ): bool {
		$e = self::email( 'Reklamo_Email_Mockup_Sent' );
		return $e && $e->trigger( $order->get_id(), $order, $approval_url, $revision, $reminder );
	}

	public static function send_deposit_request( WC_Order $order, string $details_url, bool $reminder = false ): bool {
		$e = self::email( 'Reklamo_Email_Deposit_Request' );
		return $e && $e->trigger( $order->get_id(), $order, $details_url, $reminder );
	}

	public static function send_admin_details( WC_Order $order ): void {
		$e = self::email( 'Reklamo_Email_Admin_Details' );
		if ( $e ) {
			$e->trigger( $order->get_id(), $order );
		}
	}
}

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
		require_once REKLAMO_PATH . 'includes/emails/class-reklamo-email-request-received.php';
		require_once REKLAMO_PATH . 'includes/emails/class-reklamo-email-mockup-sent.php';
		$emails['Reklamo_Email_Request_Received'] = new Reklamo_Email_Request_Received();
		$emails['Reklamo_Email_Mockup_Sent']      = new Reklamo_Email_Mockup_Sent();
		return $emails;
	}

	/**
	 * WC_Emails attaches its (possibly deferred) sender only to hooks in this list, and
	 * fires "<hook>_notification" for them. Skip this and the emails work in dev and
	 * silently stop the day background sending is enabled.
	 */
	public static function actions( array $actions ): array {
		return array_values(
			array_unique(
				array_merge(
					$actions,
					array(
						'woocommerce_order_status_' . Reklamo_Statuses::RECEIVED,
						'woocommerce_order_status_' . Reklamo_Statuses::MOCKUP_SENT,
						'woocommerce_order_status_' . Reklamo_Statuses::APPROVED,
					)
				)
			)
		);
	}

	/**
	 * Sent directly, not from the status hook: the approval secret exists only in this
	 * request, so the email cannot be reconstructed later from a queued status event.
	 */
	public static function send_mockup( WC_Order $order, string $approval_url, int $revision ): void {
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['Reklamo_Email_Mockup_Sent'] ) ) {
			$emails['Reklamo_Email_Mockup_Sent']->trigger( $order->get_id(), $order, $approval_url, $revision );
		}
	}
}

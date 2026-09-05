<?php
/**
 * Translation gap-fillers and de-cluttering for customer-facing WooCommerce output.
 *
 * WooCommerce's bg_BG pack has thousands of untranslated strings. The ones customers
 * actually meet on this site are mapped in languages/woocommerce-overrides-bg.php and
 * applied through gettext — only when WooCommerce itself returned the string untranslated,
 * so upstream translations keep precedence and improve on their own.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_I18n {

	/** @var array<string,string>|null */
	private static ?array $map = null;

	public static function init(): void {
		add_filter( 'gettext_woocommerce', array( __CLASS__, 'fill_gap' ), 10, 2 );
		add_filter( 'ngettext_woocommerce', array( __CLASS__, 'fill_gap_plural' ), 10, 4 );
		// The admin "New order" email carries a WooCommerce mobile-app advert; the shop owner does not need it.
		add_action( 'woocommerce_email', array( __CLASS__, 'drop_mobile_promo' ) );
	}

	private static function map(): array {
		if ( null === self::$map ) {
			self::$map = array();
			if ( str_starts_with( determine_locale(), 'bg' ) ) {
				$file = REKLAMO_PATH . 'languages/woocommerce-overrides-bg.php';
				if ( file_exists( $file ) ) {
					self::$map = (array) include $file;
				}
			}
		}
		return self::$map;
	}

	/** Only when WooCommerce left it untranslated ($translated === $text). */
	public static function fill_gap( string $translated, string $text ): string {
		if ( $translated !== $text ) {
			return $translated;
		}
		$map = self::map();
		return $map[ $text ] ?? $translated;
	}

	public static function fill_gap_plural( string $translated, string $single, string $plural, int $number ): string {
		$text = 1 === $number ? $single : $plural;
		if ( $translated !== $text ) {
			return $translated;
		}
		$map = self::map();
		return $map[ $text ] ?? $translated;
	}

	public static function drop_mobile_promo( WC_Emails $mailer ): void {
		// Hooked by the New Order email on its own instance, so remove it with that instance.
		$emails = $mailer->get_emails();
		if ( isset( $emails['WC_Email_New_Order'] ) ) {
			remove_action( 'woocommerce_email_footer', array( $emails['WC_Email_New_Order'], 'mobile_messaging' ), 9 );
		}
	}
}

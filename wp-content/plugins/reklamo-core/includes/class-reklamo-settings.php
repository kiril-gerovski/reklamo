<?php
/**
 * Global values the owner edits in ONE place (WooCommerce → Settings → Reklamo):
 * contact details for header/footer/contact blocks, process promises, deposit %.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Settings {

	/** Option keys and their fallbacks. Content values are seeded (Bulgarian); code stays neutral. */
	const DEFAULTS = array(
		'reklamo_company_name'    => 'Reklamo.bg',
		'reklamo_phone'           => '',
		'reklamo_email'           => '',
		'reklamo_address'         => '',
		'reklamo_hours'           => '',
		'reklamo_facebook'        => '',
		'reklamo_instagram'       => '',
		'reklamo_linkedin'        => '',
		'reklamo_tagline'         => '',
		'reklamo_mockup_deadline' => '24',
		'reklamo_deposit_pct'     => '50',
		'reklamo_note_max'        => '300',
	);

	public static function init(): void {
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'add_page' ) );
	}

	public static function get( string $key, string $fallback = '' ): string {
		$full  = str_starts_with( $key, 'reklamo_' ) ? $key : 'reklamo_' . $key;
		$value = get_option( $full, null );
		if ( null === $value || '' === $value ) {
			return '' !== $fallback ? $fallback : (string) ( self::DEFAULTS[ $full ] ?? '' );
		}
		return (string) $value;
	}

	public static function add_page( array $pages ): array {
		require_once REKLAMO_PATH . 'includes/class-reklamo-settings-page.php';
		$pages[] = new Reklamo_Settings_Page();
		return $pages;
	}
}

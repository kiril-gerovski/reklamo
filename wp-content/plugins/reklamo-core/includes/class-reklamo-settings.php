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
		'reklamo_company_name'     => 'Reklamo.bg',
		'reklamo_phone'            => '',
		'reklamo_email'            => '',
		'reklamo_address'          => '',
		'reklamo_hours'            => '',
		'reklamo_facebook'         => '',
		'reklamo_instagram'        => '',
		'reklamo_linkedin'         => '',
		'reklamo_tagline'          => '',
		'reklamo_mockup_deadline'  => '24',
		'reklamo_deposit_pct'      => '50',
		'reklamo_note_max'         => '300',
		'reklamo_bank_name'        => '',
		'reklamo_iban'             => '',
		'reklamo_bic'              => '',
		'reklamo_account_holder'   => '',
		'reklamo_reminder_days'    => '3,7,14',
		'reklamo_max_upload_mb'    => '300',
		'reklamo_retention_months' => '12',
	);

	public static function init(): void {
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'add_page' ) );
		add_shortcode( 'reklamo_bank_details', array( __CLASS__, 'bank_details_shortcode' ) );
	}

	/** @return array<string,string> label => value, only the filled ones */
	public static function bank_details(): array {
		$rows = array(
			__( 'Bank', 'reklamo-core' )           => self::get( 'bank_name' ),
			__( 'IBAN', 'reklamo-core' )           => self::get( 'iban' ),
			__( 'BIC', 'reklamo-core' )            => self::get( 'bic' ),
			__( 'Account holder', 'reklamo-core' ) => self::get( 'account_holder' ),
		);
		return array_filter( $rows );
	}

	/** Bank details as a definition list — one source for pages and emails. */
	public static function bank_details_html( string $reference = '' ): string {
		$rows = self::bank_details();
		if ( $reference ) {
			$rows[ __( 'Payment reference', 'reklamo-core' ) ] = $reference;
		}
		if ( ! $rows ) {
			return '';
		}
		$out = '<dl class="bank-details">';
		foreach ( $rows as $label => $value ) {
			$out .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}
		return $out . '</dl>';
	}

	public static function bank_details_shortcode(): string {
		return self::bank_details_html();
	}

	/** Reminder offsets in days, sanitised and sorted. */
	public static function reminder_days(): array {
		$days = array_filter( array_map( 'absint', explode( ',', self::get( 'reminder_days', '3,7,14' ) ) ) );
		sort( $days );
		return array_values( array_unique( $days ) );
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

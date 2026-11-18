<?php
/**
 * Google Ads conversion tracking behind a consent banner.
 *
 * Off until the owner enters a Google tag ID and a conversion label (Settings → Reklamo → Google Ads):
 * then the storefront shows a banner, and Google's tag loads only after „Приемам“. The choice lives
 * in the visitor's browser (localStorage), never in a cookie of ours. A sent request counts as a
 * conversion from a hidden frame at a plain address, so the order page, whose address is the
 * customer's key, never loads anything from Google; a sent inquiry counts from the dialog itself.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

final class Reklamo_Ads {

	const OPT_ID    = 'reklamo_gads_id';
	const OPT_LABEL = 'reklamo_gads_label';
	const LEAD_VAR  = 'reklamo_lead';

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'lead_frame' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'banner' ), 5 );
		add_shortcode( 'reklamo_cookies', array( __CLASS__, 'cookies_shortcode' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPT_ID, array( __CLASS__, 'sanitize_id' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPT_LABEL, array( __CLASS__, 'sanitize_label' ) );
	}

	public static function id(): string {
		return self::sanitize_id( (string) get_option( self::OPT_ID, '' ) );
	}

	public static function label(): string {
		return self::sanitize_label( (string) get_option( self::OPT_LABEL, '' ) );
	}

	/** Both codes are needed: the tag alone would load Google with nothing to count. */
	public static function enabled(): bool {
		return '' !== self::id() && '' !== self::label();
	}

	/** „AW-1234567890“, or the bare number Google Ads shows as „Conversion ID“; anything else is dropped. */
	public static function sanitize_id( $value ): string {
		$value = strtoupper( preg_replace( '/\s+/', '', (string) $value ) );
		$value = preg_match( '/^\d{6,15}$/', $value ) ? 'AW-' . $value : $value;
		return preg_match( '/^AW-\d{6,15}$/', $value ) ? $value : '';
	}

	public static function sanitize_label( $value ): string {
		$value = trim( (string) $value );
		return preg_match( '/^[A-Za-z0-9_-]{4,64}$/', $value ) ? $value : '';
	}

	/** The address of the hidden frame that counts one sent request. */
	public static function lead_url( WC_Order $order ): string {
		return add_query_arg( self::LEAD_VAR, rawurlencode( (string) $order->get_order_number() ), home_url( '/' ) );
	}

	/** For the order page: the frame, only on its first view after the request was sent. */
	public static function lead_frame_html( ?WC_Order $order ): string {
		if ( ! $order || ! self::enabled() ) {
			return '';
		}
		return sprintf( '<iframe src="%s" title="" aria-hidden="true" tabindex="-1" referrerpolicy="no-referrer" style="position:absolute;width:1px;height:1px;border:0;left:-9999px"></iframe>', esc_url( self::lead_url( $order ) ) );
	}

	private static function config( string $mode, string $order_number = '' ): array {
		return array(
			'id'    => self::id(),
			'label' => self::label(),
			'mode'  => $mode,
			'order' => $order_number,
			'days'  => 365,
		);
	}

	private static function on_storefront(): bool {
		return ! is_admin() && ! is_customize_preview() && ! wp_doing_ajax();
	}

	public static function enqueue(): void {
		if ( ! self::enabled() || ! self::on_storefront() ) {
			return;
		}
		wp_enqueue_script( 'reklamo-consent', REKLAMO_URL . 'assets/js/consent.js', array(), REKLAMO_VERSION, true );
		wp_add_inline_script( 'reklamo-consent', 'window.reklamoAds=' . wp_json_encode( self::config( 'site' ) ) . ';', 'before' );
	}

	public static function banner(): void {
		if ( ! self::enabled() || ! self::on_storefront() ) {
			return;
		}
		$policy = get_privacy_policy_url();
		?>
		<section class="consent" data-consent hidden aria-label="<?php esc_attr_e( 'Cookies', 'reklamo-core' ); ?>">
			<div class="consent__text">
				<p><?php esc_html_e( 'We use Google cookies for advertising.', 'reklamo-core' ); ?></p>
				<?php if ( $policy ) : ?>
					<a class="consent__more" href="<?php echo esc_url( $policy ); ?>#biskvitki"><?php echo esc_html_x( 'Privacy Policy', 'cookie banner link', 'reklamo-core' ); ?></a>
				<?php endif; ?>
			</div>
			<div class="consent__actions">
				<button type="button" class="btn btn--outline" data-consent-choice="denied"><?php esc_html_e( 'Decline', 'reklamo-core' ); ?></button>
				<button type="button" class="btn btn--outline" data-consent-choice="granted"><?php esc_html_e( 'Accept', 'reklamo-core' ); ?></button>
			</div>
		</section>
		<?php
	}

	/** ?reklamo_lead=<order number>: a bare page whose only job is to count the conversion. */
	public static function lead_frame(): void {
		if ( ! isset( $_GET[ self::LEAD_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! self::enabled() ) {
			status_header( 404 );
			exit;
		}
		$number = substr( preg_replace( '/[^A-Za-z0-9-]/', '', sanitize_text_field( wp_unslash( $_GET[ self::LEAD_VAR ] ) ) ), 0, 32 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'Content-Type: text/html; charset=utf-8' );
		printf(
			'<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>%1$s</title><script>window.reklamoAds=%2$s;</script><script src="%3$s"></script></head><body></body></html>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- a bare page outside the theme, no wp_head().
			esc_html( get_bloginfo( 'name' ) ),
			wp_json_encode( self::config( 'lead', $number ) ),
			esc_url( add_query_arg( 'ver', REKLAMO_VERSION, REKLAMO_URL . 'assets/js/consent.js' ) )
		);
		exit;
	}

	/**
	 * [reklamo_cookies]: the Privacy Policy's cookie section, true whether the tag is on or off.
	 * The section heading stays in the page; this prints only its text.
	 */
	public static function cookies_shortcode(): string {
		$paras = array();
		if ( ! self::enabled() ) {
			$paras[] = esc_html__( 'The site uses no analytics, advertising or tracking cookies and loads no content from third parties. Strictly necessary technical cookies are created only when logging in to the administration panel. That is why there is no cookie banner.', 'reklamo-core' );
			return '<p>' . implode( '</p><p>', $paras ) . '</p>';
		}
		$paras[] = esc_html__( 'Strictly necessary technical cookies are created only when logging in to the administration panel. Your answer to the cookie banner is kept in your own browser (local storage, not a cookie) for 12 months, so that we do not ask again on every page; it is not sent to us.', 'reklamo-core' );
		$paras[] = esc_html__( 'We advertise on Google. Only if you press „Accept“ in the banner does the site load the Google Ads tag, which sets cookies (_gcl_au, _gcl_aw and similar, kept for up to 90 days) to tell us which ad brought you here and whether you then sent a request or an inquiry. Google receives your IP address, browser data, the click on the ad and the fact of the request, without your name, email, phone or files.', 'reklamo-core' );
		$paras[] = esc_html__( 'Legal basis: your consent (Art. 6(1)(a) GDPR). The recipient is Google Ireland Limited; the data may reach Google LLC in the USA, which is certified under the EU–U.S. Data Privacy Framework. If you press „Decline“ or do not answer, nothing is loaded from Google.', 'reklamo-core' );

		$out  = '<p>' . implode( '</p><p>', $paras ) . '</p>';
		$out .= '<p><button type="button" class="btn btn--outline" data-consent-open>' . esc_html__( 'Change your cookie choice', 'reklamo-core' ) . '</button></p>';
		return $out;
	}
}

<?php
/**
 * Single product, laid out as the client's design: gallery, summary with the final price,
 * what the package includes, the order card, the four steps and the product tabs.
 * There is no add-to-cart — every route leads to the request form.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
	return;
}

$reklamo_terms    = get_the_terms( $product->get_id(), 'product_cat' );
$reklamo_category = ( $reklamo_terms && ! is_wp_error( $reklamo_terms ) ) ? $reklamo_terms[0] : null;
$reklamo_short    = $product->get_short_description();
// The short description is a lead paragraph plus the list of what is in the package; the design
// shows them in two different places, so they are split rather than printed together.
$reklamo_lead     = '';
$reklamo_includes = '';
if ( $reklamo_short ) {
	if ( preg_match( '~(<ul\b.*</ul>)~is', $reklamo_short, $m ) ) {
		$reklamo_includes = $m[1];
		$reklamo_lead     = trim( str_replace( $m[1], '', $reklamo_short ) );
	} else {
		$reklamo_lead = $reklamo_short;
	}
}

$reklamo_usps = array(
	array( 'diamond', __( 'Premium quality', 'reklamo' ), __( 'Selected materials', 'reklamo' ) ),
	array( 'pen', __( 'Branding', 'reklamo' ), __( 'With your logo', 'reklamo' ) ),
	array( 'truck', __( 'Fast delivery', 'reklamo' ), __( 'Across Bulgaria', 'reklamo' ) ),
);

$reklamo_deposit  = (int) reklamo_setting( 'deposit_pct', '50' );
$reklamo_deadline = (int) reklamo_setting( 'mockup_deadline', '24' );

$reklamo_promises = array(
	__( 'A mockup prepared before production', 'reklamo' ),
	__( 'Free corrections to the design', 'reklamo' ),
	__( 'Single-colour branding included', 'reklamo' ),
	__( 'Further branding available on request', 'reklamo' ),
);

$reklamo_steps = array(
	array( 'upload', __( 'Upload your logo', 'reklamo' ), __( 'AI, EPS, PDF, SVG or a high-quality PNG.', 'reklamo' ) ),
	array(
		'monitor',
		__( 'Get a mockup', 'reklamo' ),
		sprintf(
			/* translators: %d: hours until the mockup is sent */
			__( 'We send you a mockup within %d working hours.', 'reklamo' ),
			$reklamo_deadline
		),
	),
	array( 'check', __( 'Approve the design', 'reklamo' ), __( 'Or ask for a correction if you need one.', 'reklamo' ) ),
	array(
		'card',
		sprintf(
			/* translators: %d: deposit percentage */
			__( 'Pay a %d%% deposit', 'reklamo' ),
			$reklamo_deposit
		),
		__( 'Production starts once you approve.', 'reklamo' ),
	),
);
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'product-single', $product ); ?>>

	<div class="container">
		<?php woocommerce_breadcrumb(); ?>

		<div class="product-main card">
			<div class="product-main__gallery">
				<?php do_action( 'woocommerce_before_single_product_summary' ); ?>
			</div>

			<div class="summary entry-summary product-main__summary">
			<div class="product-main__intro">
			<div>
				<?php if ( $reklamo_category ) : ?>
					<p class="product-eyebrow">
						<a href="<?php echo esc_url( get_term_link( $reklamo_category ) ); ?>">
							<?php
							printf(
								/* translators: %s: product category name */
								esc_html__( 'The %s collection', 'reklamo' ),
								esc_html( $reklamo_category->name )
							);
							?>
						</a>
					</p>
				<?php endif; ?>

				<h1 class="product-title"><?php the_title(); ?></h1>

				<?php if ( $reklamo_lead ) : ?>
					<div class="product-lead"><?php echo wp_kses_post( wpautop( $reklamo_lead ) ); ?></div>
				<?php endif; ?>

				<ul class="product-usps">
					<?php foreach ( $reklamo_usps as $reklamo_usp ) : ?>
						<li>
							<span class="product-usps__icon"><?php echo reklamo_icon( $reklamo_usp[0], 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span><strong><?php echo esc_html( $reklamo_usp[1] ); ?></strong><?php echo esc_html( $reklamo_usp[2] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>

			</div>

				<div class="product-price-badge">
					<span class="product-price-badge__label"><?php esc_html_e( 'Final price', 'reklamo' ); ?></span>
					<span class="product-price-badge__value"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
					<span class="product-price-badge__note"><?php esc_html_e( 'branding included', 'reklamo' ); ?></span>
				</div>
			</div>

				<div class="product-main__lower">
					<div class="product-includes">
						<?php if ( $reklamo_includes ) : ?>
							<h2 class="product-includes__title"><?php esc_html_e( 'What does the package include?', 'reklamo' ); ?></h2>
							<?php echo wp_kses_post( $reklamo_includes ); ?>
						<?php endif; ?>
					</div>

					<div class="product-order card">
						<ul class="product-order__promises">
							<?php foreach ( $reklamo_promises as $reklamo_promise ) : ?>
								<li><?php echo reklamo_icon( 'check', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $reklamo_promise ); ?></span></li>
							<?php endforeach; ?>
						</ul>
						<a class="btn btn--primary product-order__cta" href="<?php echo esc_url( reklamo_request_url( $product ) ); ?>">
							<?php esc_html_e( 'Upload a logo and request a mockup', 'reklamo' ); ?>
						</a>
						<p class="product-order__nopay">
							<?php echo reklamo_icon( 'shield', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
							<?php esc_html_e( 'No payment at this stage', 'reklamo' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<ol class="product-steps card" aria-label="<?php esc_attr_e( 'How the order works', 'reklamo' ); ?>">
			<?php foreach ( $reklamo_steps as $reklamo_step ) : ?>
				<li class="product-steps__item">
					<span class="product-steps__icon"><?php echo reklamo_icon( $reklamo_step[0], 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<div><strong><?php echo esc_html( $reklamo_step[1] ); ?></strong><p><?php echo esc_html( $reklamo_step[2] ); ?></p></div>
				</li>
			<?php endforeach; ?>
		</ol>

		<div class="product-detail">
			<div class="product-detail__tabs card">
				<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
			</div>

			<aside class="product-help card">
				<h2 class="product-help__title"><?php echo reklamo_icon( 'headset', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Need help?', 'reklamo' ); ?></h2>
				<p><?php esc_html_e( 'Our consultant will help you choose the right package for your business.', 'reklamo' ); ?></p>
				<a class="btn btn--outline" href="<?php echo esc_url( reklamo_contact_url() ); ?>">
					<?php echo reklamo_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<?php esc_html_e( 'Get in touch', 'reklamo' ); ?>
				</a>
			</aside>
		</div>
	</div>

	<?php get_template_part( 'template-parts/trust-strip' ); ?>
</div>
<?php
do_action( 'woocommerce_after_single_product' );

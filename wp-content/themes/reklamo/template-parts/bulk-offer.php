<?php
/**
 * The band under the catalogue grids: other quantities get a tailored offer, next to three
 * reasons to ask. The button opens the inquiry dialog (Reklamo_Inquiry); without JavaScript it
 * stays a mailto link, and the address with „Копирай“ sits under it either way.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

$reklamo_reasons = array(
	'tag'   => __( 'Competitive prices', 'reklamo' ),
	'gear'  => __( 'Custom design', 'reklamo' ),
	'truck' => __( 'Fast delivery', 'reklamo' ),
);
?>
<aside class="bulk-offer" aria-labelledby="bulk-offer-title">
	<div class="bulk-offer__lead">
		<span class="bulk-offer__icon"><?php echo reklamo_icon( 'boxes', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<div>
			<h2 class="bulk-offer__title" id="bulk-offer-title"><?php esc_html_e( 'Different quantities?', 'reklamo' ); ?></h2>
			<p class="bulk-offer__sub"><?php esc_html_e( 'Get a tailored offer', 'reklamo' ); ?></p>
		</div>
	</div>
	<div class="bulk-offer__action">
		<a class="btn bulk-offer__cta"<?php echo class_exists( 'Reklamo_Inquiry' ) ? ' data-inquiry-open' : ''; ?> href="<?php echo esc_url( reklamo_offer_mailto(), array( 'mailto', 'http', 'https' ) ); ?>">
			<?php esc_html_e( 'Send an inquiry', 'reklamo' ); ?>
			<?php echo reklamo_icon( 'chevron-r', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		</a>
		<?php $reklamo_offer_mail = reklamo_setting( 'email' ); ?>
		<?php if ( $reklamo_offer_mail ) : ?>
			<p class="bulk-offer__mail">
				<span class="bulk-offer__or"><?php esc_html_e( 'or write to', 'reklamo' ); ?></span> <span data-copy-text><?php echo esc_html( $reklamo_offer_mail ); ?></span>
				<button type="button" class="bulk-offer__copy" data-copy data-copied="<?php esc_attr_e( 'Copied ✓', 'reklamo' ); ?>" hidden><?php esc_html_e( 'Copy', 'reklamo' ); ?></button>
			</p>
		<?php endif; ?>
	</div>
	<ul class="bulk-offer__reasons">
		<?php foreach ( $reklamo_reasons as $reklamo_icon_name => $reklamo_reason ) : ?>
			<li><?php echo reklamo_icon( $reklamo_icon_name, 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $reklamo_reason ); ?></span></li>
		<?php endforeach; ?>
	</ul>
</aside>
<?php reklamo_inquiry_dialog(); ?>

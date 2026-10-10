<?php
/**
 * The band under the catalogue grids: large quantities get a tailored offer through the
 * contacts page, next to three reasons to ask.
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
			<h2 class="bulk-offer__title" id="bulk-offer-title"><?php esc_html_e( 'Large quantities?', 'reklamo' ); ?></h2>
			<p class="bulk-offer__sub"><?php esc_html_e( 'Get a tailored offer', 'reklamo' ); ?></p>
		</div>
	</div>
	<a class="btn bulk-offer__cta" href="<?php echo esc_url( reklamo_contact_url() ); ?>">
		<?php esc_html_e( 'Send an inquiry', 'reklamo' ); ?>
		<?php echo reklamo_icon( 'chevron-r', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
	</a>
	<ul class="bulk-offer__reasons">
		<?php foreach ( $reklamo_reasons as $reklamo_icon_name => $reklamo_reason ) : ?>
			<li><?php echo reklamo_icon( $reklamo_icon_name, 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $reklamo_reason ); ?></span></li>
		<?php endforeach; ?>
	</ul>
</aside>

<?php
/**
 * Four trust points, used under the request form and available as a pattern.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

$reklamo_points = array(
	array( 'shield', __( 'Security', 'reklamo' ), __( 'You pay nothing until you approve the mockup.', 'reklamo' ) ),
	array( 'medal', __( 'Quality', 'reklamo' ), __( 'Proven materials and technologies.', 'reklamo' ) ),
	array( 'user', __( 'Personal service', 'reklamo' ), __( 'A designer and a consultant at every step.', 'reklamo' ) ),
	array( 'clock', __( 'Fast turnaround', 'reklamo' ), __( 'Production and delivery on agreed dates.', 'reklamo' ) ),
);
?>
<div class="container">
<section class="trust-strip" aria-label="<?php esc_attr_e( 'Why us', 'reklamo' ); ?>">
	<?php foreach ( $reklamo_points as $reklamo_p ) : ?>
		<div class="trust-strip__item">
			<span class="trust-strip__icon"><?php echo reklamo_icon( $reklamo_p[0], 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div><strong><?php echo esc_html( $reklamo_p[1] ); ?></strong><p><?php echo esc_html( $reklamo_p[2] ); ?></p></div>
		</div>
	<?php endforeach; ?>
</section>
</div>

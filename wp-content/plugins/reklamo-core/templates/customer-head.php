<?php
/**
 * Order header shared by every standalone customer page (tracking, approval, details):
 * order number, status badge, what was ordered, and the six-step progress line. The
 * customer always sees where the order stands, whichever link they arrived through.
 *
 * Expects the including template's locals: WC_Order $order and array $v (Reklamo_Tracking::view_data()).
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="head">
	<h1 data-order="<?php echo esc_attr( (string) $order->get_id() ); ?>"><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Order %s', 'reklamo-core' ), $order->get_order_number() ) ); ?></h1>
	<span class="badge <?php echo $v['completed'] ? 'done' : ( $v['cancelled'] ? 'off' : '' ); ?>"><?php echo esc_html( $v['status_label'] ); ?></span>
</div>
<p class="meta">
	<?php echo esc_html( implode( ', ', $v['items'] ) ); ?>
	<?php if ( $v['created'] ) : ?>
		· <?php echo esc_html( sprintf( /* translators: %s: date */ __( 'requested on %s', 'reklamo-core' ), $v['created'] ) ); ?>
	<?php endif; ?>
	· <?php echo esc_html( sprintf( /* translators: %s: amount */ __( 'total %s incl. VAT', 'reklamo-core' ), $v['total'] ) ); ?>
</p>

<?php if ( ! $v['cancelled'] ) : ?>
	<ol class="steps" aria-label="<?php esc_attr_e( 'Progress', 'reklamo-core' ); ?>">
		<?php foreach ( $v['step_labels'] as $i => $label ) : ?>
			<?php
			$n     = $i + 1;
			$class = Reklamo_Progress::is_step_done( $n, $v['step'], $v['completed'] ) ? 'done' : ( $n === $v['step'] ? 'now' : '' );
			?>
			<li class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>

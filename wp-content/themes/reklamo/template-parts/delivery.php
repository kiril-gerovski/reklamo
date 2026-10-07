<?php
/**
 * Delivery and deadlines, from the page of that name: one card per <h3>. 'section' is the
 * homepage band with an intro column; 'page' is the card grid on the page itself.
 * Prints nothing while the page has no items.
 *
 * @var array $args { @type string $variant 'section' or 'page'. }
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

$reklamo_items = reklamo_page_items( REKLAMO_DELIVERY_SLUG );
if ( ! $reklamo_items ) {
	return;
}
$reklamo_section = 'section' === ( $args['variant'] ?? 'section' );
$reklamo_icons   = array(
	'clock' => '~визуализац|часа~iu',
	'cube'  => '~производств~iu',
	'truck' => '~доставк|куриер~iu',
	'card'  => '~плащ|изпращ~iu',
);

ob_start();
?>
<ol class="delivery-cards">
	<?php foreach ( $reklamo_items as $reklamo_i => $reklamo_item ) : ?>
		<?php
		$reklamo_icon = 'check';
		foreach ( $reklamo_icons as $reklamo_name => $reklamo_pattern ) {
			if ( preg_match( $reklamo_pattern, $reklamo_item['title'] ) ) {
				$reklamo_icon = $reklamo_name;
				break;
			}
		}
		?>
		<li class="delivery-card">
			<span class="delivery-card__top">
				<span class="delivery-card__icon"><?php echo reklamo_icon( $reklamo_icon, 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="delivery-card__num"><?php echo esc_html( sprintf( '%02d', $reklamo_i + 1 ) ); ?></span>
			</span>
			<h3 class="delivery-card__title"><?php echo esc_html( $reklamo_item['title'] ); ?></h3>
			<div class="delivery-card__text"><?php echo wp_kses_post( $reklamo_item['text'] ); ?></div>
		</li>
	<?php endforeach; ?>
</ol>
<?php
$reklamo_cards = (string) ob_get_clean();

if ( ! $reklamo_section ) {
	echo $reklamo_cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	return;
}
$reklamo_url = (string) get_permalink( get_page_by_path( REKLAMO_DELIVERY_SLUG ) );
?>
<section class="delivery-band" aria-labelledby="delivery-band-title">
	<div class="container delivery-band__inner">
		<div class="delivery-band__intro">
			<p class="eyebrow"><?php esc_html_e( 'From approval to your door', 'reklamo' ); ?></p>
			<h2 id="delivery-band-title" class="delivery-band__title"><?php esc_html_e( 'Delivery and deadlines', 'reklamo' ); ?></h2>
			<p class="delivery-band__text"><?php esc_html_e( 'Clear deadlines at every step, and a courier to any address in Bulgaria.', 'reklamo' ); ?></p>
			<?php if ( $reklamo_url ) : ?>
				<a class="delivery-band__more" href="<?php echo esc_url( $reklamo_url ); ?>"><?php esc_html_e( 'Delivery details', 'reklamo' ); ?> <span aria-hidden="true">→</span></a>
			<?php endif; ?>
		</div>
		<?php echo $reklamo_cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
	</div>
</section>

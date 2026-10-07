<?php
/**
 * The FAQ accordion. 'section' is the dark band at the foot of the homepage, with an intro
 * column; 'page' is the light list on the FAQ page itself. Prints nothing without questions.
 *
 * @var array $args { @type string $variant 'section' or 'page'. }
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

$reklamo_items = reklamo_faq_items();
if ( ! $reklamo_items ) {
	return;
}
$reklamo_section = 'section' === ( $args['variant'] ?? 'section' );
$reklamo_faq_url = (string) get_permalink( get_page_by_path( REKLAMO_FAQ_SLUG ) );

ob_start();
?>
<div class="faq-list<?php echo $reklamo_section ? '' : ' faq-list--light'; ?>">
	<?php foreach ( $reklamo_items as $reklamo_i => $reklamo_item ) : ?>
		<details class="faq-item" name="reklamo-faq"<?php echo 0 === $reklamo_i ? ' open' : ''; ?>>
			<summary>
				<span class="faq-item__num"><?php echo esc_html( sprintf( '%02d', $reklamo_i + 1 ) ); ?></span>
				<span class="faq-item__q"><?php echo esc_html( $reklamo_item['question'] ); ?></span>
				<span class="faq-item__icon" aria-hidden="true"></span>
			</summary>
			<div class="faq-item__a"><?php echo wp_kses_post( $reklamo_item['answer'] ); ?></div>
		</details>
	<?php endforeach; ?>
</div>
<?php
$reklamo_list = (string) ob_get_clean();

if ( ! $reklamo_section ) {
	echo $reklamo_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	return;
}
?>
<section class="faq-band" aria-labelledby="faq-band-title">
	<div class="container faq-band__inner">
		<div class="faq-band__intro">
			<p class="eyebrow"><?php esc_html_e( 'Questions and answers', 'reklamo' ); ?></p>
			<h2 id="faq-band-title" class="faq-band__title"><?php esc_html_e( 'Frequently asked questions', 'reklamo' ); ?></h2>
			<p class="faq-band__text"><?php esc_html_e( 'Everything about your order, from the logo to delivery. Cannot find your answer? Write to us and we will reply quickly.', 'reklamo' ); ?></p>
			<div class="faq-band__actions">
				<a class="btn faq-band__btn" href="<?php echo esc_url( reklamo_contact_url() ); ?>"><?php esc_html_e( 'Get in touch', 'reklamo' ); ?></a>
				<?php if ( $reklamo_faq_url ) : ?>
					<a class="faq-band__more" href="<?php echo esc_url( $reklamo_faq_url ); ?>"><?php esc_html_e( 'All questions', 'reklamo' ); ?> <span aria-hidden="true">→</span></a>
				<?php endif; ?>
			</div>
			<span class="faq-band__mark" aria-hidden="true"><?php echo reklamo_mark( 280 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
		</div>
		<?php echo $reklamo_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
	</div>
</section>

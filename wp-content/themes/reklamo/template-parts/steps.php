<?php
/**
 * The six-step "how the order works" graphic: numbered icons with a one-line caption each.
 * Rendered by the homepage pattern and by the [reklamo_steps] shortcode (the "How it works"
 * page), so both show the same steps from one source. Each step links to its section on the
 * "How it works" page (anchors stapka-1 … stapka-6).
 *
 * @var array $args {
 *   @type string $base    URL prefix for the anchors: '' on the page itself, its permalink elsewhere.
 *   @type bool   $link    Render the steps as links (false when there is no page to link to).
 *   @type bool   $section Wrap in a full-width section with the heading (page use); the pattern brings its own.
 *   @type string $heading Heading tag for that section: 'h1' when it is the page's own heading, else 'h2'.
 * }
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

$reklamo_args  = wp_parse_args(
	$args ?? array(),
	array(
		'base'    => '',
		'link'    => true,
		'section' => false,
		'heading' => 'h2',
	)
);
$reklamo_h     = 'h1' === $reklamo_args['heading'] ? 'h1' : 'h2';
$reklamo_steps = array(
	array( 'cube', __( 'Choose a package', 'reklamo' ), __( 'You see the exact price and quantity.', 'reklamo' ) ),
	array( 'upload', __( 'Upload your logo', 'reklamo' ), __( 'AI, EPS, PDF, SVG or a high-quality PNG.', 'reklamo' ) ),
	array( 'nodes', __( 'Get a mockup', 'reklamo' ), __( 'Our designer prepares a mockup of the products.', 'reklamo' ) ),
	array( 'check', __( 'Approve the design', 'reklamo' ), __( 'You can request changes until you are fully happy.', 'reklamo' ) ),
	array( 'card', __( 'Pay the deposit', 'reklamo' ), __( 'After approval you pay a 50% deposit and we start the order.', 'reklamo' ) ),
	array( 'truck', __( 'Pay the balance and receive', 'reklamo' ), __( 'You pay the rest before dispatch and receive your order.', 'reklamo' ) ),
);
?>
<?php if ( $reklamo_args['section'] ) : ?>
<section class="steps steps--page">
	<<?php echo esc_html( $reklamo_h ); ?> class="section-title"><?php esc_html_e( 'How does the order work?', 'reklamo' ); ?></<?php echo esc_html( $reklamo_h ); ?>>
<?php endif; ?>
<ol class="steps__grid" aria-label="<?php esc_attr_e( 'How the order works', 'reklamo' ); ?>">
	<?php foreach ( $reklamo_steps as $reklamo_i => $reklamo_s ) : ?>
		<?php $reklamo_tag = $reklamo_args['link'] ? 'a' : 'div'; ?>
		<li class="steps__item">
			<<?php echo esc_html( $reklamo_tag ); ?> class="steps__link"<?php echo $reklamo_args['link'] ? ' href="' . esc_url( $reklamo_args['base'] . '#stapka-' . ( $reklamo_i + 1 ) ) . '"' : ''; ?>>
				<span class="steps__num"><?php echo esc_html( sprintf( '%02d', $reklamo_i + 1 ) ); ?></span>
				<span class="steps__icon"><?php echo reklamo_icon( $reklamo_s[0], 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<strong><?php echo esc_html( $reklamo_s[1] ); ?></strong>
				<p><?php echo esc_html( $reklamo_s[2] ); ?></p>
			</<?php echo esc_html( $reklamo_tag ); ?>>
		</li>
	<?php endforeach; ?>
</ol>
<?php if ( $reklamo_args['section'] ) : ?>
</section>
<?php endif; ?>

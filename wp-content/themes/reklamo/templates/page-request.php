<?php
/**
 * Template Name: Request page (upload logo)
 *
 * The request page: a product with its colour and quantity, then a logo (uploaded in the
 * cart) or an inscription, and the print colour. Title and intro come from the page; the form
 * (Reklamo_Request) and layout from here.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header();

$reklamo_product  = class_exists( 'Reklamo_Request' ) ? Reklamo_Request::current_product() : null;
$reklamo_deadline = reklamo_setting( 'mockup_deadline', '24' );
$reklamo_steps    = array(
	array( 'bag', __( 'Choose a product', 'reklamo' ), __( 'From our products and packages', 'reklamo' ) ),
	array( 'pen', __( 'Add a logo or an inscription', 'reklamo' ), __( 'Upload the logo in the cart, or write a text', 'reklamo' ) ),
	/* translators: %s: number of business hours */
	array( 'check', __( 'Approve before printing', 'reklamo' ), sprintf( __( 'You get a mockup to approve within %s h.', 'reklamo' ), $reklamo_deadline ) ),
);
$reklamo_image = $reklamo_product && $reklamo_product->get_image_id() ? (string) wp_get_attachment_image_url( $reklamo_product->get_image_id(), 'woocommerce_single' ) : (string) get_theme_file_uri( 'assets/img/placeholder-product.svg' );

while ( have_posts() ) :
	the_post();
	?>
	<div class="container request-page">
		<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'reklamo' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'reklamo' ); ?></a>
			<span>›</span><span aria-current="page"><?php the_title(); ?></span>
		</nav>

		<div class="request-page__head">
			<div class="request-page__title">
				<h1 class="page-title"><?php the_title(); ?></h1>
				<div class="request-page__intro"><?php the_content(); ?></div>
			</div>
		</div>

		<ol class="steps-strip">
			<?php foreach ( $reklamo_steps as $reklamo_i => $reklamo_step ) : ?>
				<li class="steps-strip__item<?php echo 0 === $reklamo_i ? ' is-active' : ''; ?>">
					<span class="steps-strip__icon"><?php echo reklamo_icon( $reklamo_step[0], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="steps-strip__text">
						<strong><?php echo esc_html( sprintf( '%d. %s', $reklamo_i + 1, $reklamo_step[1] ) ); ?></strong>
						<small><?php echo esc_html( $reklamo_step[2] ); ?></small>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>

		<div class="request-page__grid">
			<section class="request-page__form card">
				<?php echo class_exists( 'Reklamo_Request' ) ? Reklamo_Request::render( array( 'product' => $reklamo_product ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			</section>

			<aside class="request-page__side">
				<div class="card design-preview">
					<h2 class="design-preview__title"><?php esc_html_e( 'Your product', 'reklamo' ); ?></h2>
					<figure class="design-preview__media">
						<img src="<?php echo esc_url( $reklamo_image ); ?>" alt="" data-rk-design-image>
						<figcaption><?php esc_html_e( 'Product photo', 'reklamo' ); ?></figcaption>
					</figure>
					<div class="design-preview__row">
						<span class="design-preview__icon"><?php echo reklamo_icon( 'check', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span><strong><?php esc_html_e( 'Printing after your approval', 'reklamo' ); ?></strong><small><?php esc_html_e( 'You get the final mockup before anything is made.', 'reklamo' ); ?></small></span>
					</div>
					<div class="design-preview__row">
						<span class="design-preview__icon"><?php echo reklamo_icon( 'headset', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span><strong><?php esc_html_e( 'No logo?', 'reklamo' ); ?></strong><small><?php esc_html_e( 'Write an inscription, or contact us and we will help.', 'reklamo' ); ?></small></span>
					</div>
					<button type="button" class="btn btn--outline design-preview__help" data-inquiry-open><?php esc_html_e( 'Contact us for help', 'reklamo' ); ?></button>
				</div>
			</aside>
		</div>
	</div>

	<?php reklamo_inquiry_dialog(); ?>
	<?php get_template_part( 'template-parts/trust-strip' ); ?>
	<?php
endwhile;

get_footer();

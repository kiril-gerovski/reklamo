<?php
/**
 * Template Name: Request page (upload logo)
 *
 * "Качи лого и визуализирай" — the approved design's single request step. Title and
 * intro come from the page; the form and layout come from here.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header();

$reklamo_product  = class_exists( 'Reklamo_Request' ) ? Reklamo_Request::current_product() : null;
$reklamo_deadline = reklamo_setting( 'mockup_deadline', '24' );
$reklamo_deposit  = reklamo_setting( 'deposit_pct', '50' );
$reklamo_shop     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$reklamo_steps    = array(
	array( 'upload', __( 'Upload logo', 'reklamo' ), __( 'Send the file with your logo', 'reklamo' ) ),
	/* translators: %s: number of business hours */
	array( 'monitor', __( 'Review of details', 'reklamo' ), sprintf( __( 'We prepare a mockup within %s h.', 'reklamo' ), $reklamo_deadline ) ),
	array( 'check', __( 'Approval', 'reklamo' ), __( 'Review and approve the mockup', 'reklamo' ) ),
	array( 'bag', __( 'Production', 'reklamo' ), __( 'Production starts after approval', 'reklamo' ) ),
);
$reklamo_next = array(
	array( 'medal', __( 'We receive your logo', 'reklamo' ), __( 'We check the quality of the file.', 'reklamo' ) ),
	/* translators: %s: number of business hours */
	array( 'monitor', __( 'We prepare the mockup', 'reklamo' ), sprintf( __( 'You get the mockup by email within %s business hours.', 'reklamo' ), $reklamo_deadline ) ),
	array( 'check', __( 'You review and approve', 'reklamo' ), __( 'Or request corrections.', 'reklamo' ) ),
	/* translators: %s: deposit percentage */
	array( 'card', sprintf( __( 'You pay a %s%% deposit', 'reklamo' ), $reklamo_deposit ), __( 'Production starts after approval.', 'reklamo' ) ),
);

while ( have_posts() ) :
	the_post();
	?>
	<div class="container request-page">
		<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'reklamo' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'reklamo' ); ?></a>
			<span>›</span><a href="<?php echo esc_url( $reklamo_shop ); ?>"><?php esc_html_e( 'Promo packages', 'reklamo' ); ?></a>
			<?php if ( $reklamo_product ) : ?>
				<span>›</span><a href="<?php echo esc_url( $reklamo_product->get_permalink() ); ?>"><?php echo esc_html( $reklamo_product->get_name() ); ?></a>
			<?php endif; ?>
			<span>›</span><span aria-current="page"><?php the_title(); ?></span>
		</nav>

		<div class="request-page__head">
			<div class="request-page__title">
				<h1 class="page-title"><?php the_title(); ?></h1>
				<div class="request-page__intro"><?php the_content(); ?></div>
			</div>
			<?php if ( $reklamo_product ) : ?>
				<aside class="package-summary">
					<div class="package-summary__media"><?php echo $reklamo_product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC escapes. ?></div>
					<div class="package-summary__body">
						<h2 class="package-summary__name"><?php echo esc_html( $reklamo_product->get_name() ); ?></h2>
						<?php
						// "20 тефтера + 20 химикалки": join the short description's list items.
						preg_match_all( '/<li[^>]*>(.*?)<\/li>/s', $reklamo_product->get_short_description(), $reklamo_li );
						$reklamo_items = array_filter( array_map( 'trim', array_map( 'wp_strip_all_tags', $reklamo_li[1] ) ) );
						$reklamo_text  = $reklamo_items ? implode( ' + ', $reklamo_items ) : wp_strip_all_tags( $reklamo_product->get_short_description() );
						?>
						<div class="package-summary__contents"><?php echo esc_html( $reklamo_text ); ?></div>
						<div class="package-summary__price"><?php echo wp_kses_post( $reklamo_product->get_price_html() ); ?> <small><?php esc_html_e( 'branding included', 'reklamo' ); ?></small></div>
					</div>
				</aside>
			<?php endif; ?>
		</div>

		<ol class="steps-strip">
			<?php foreach ( $reklamo_steps as $reklamo_i => $reklamo_step ) : ?>
				<li class="steps-strip__item<?php echo 0 === $reklamo_i ? ' is-active' : ''; ?>">
					<span class="steps-strip__icon"><?php echo reklamo_icon( $reklamo_step[0], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="steps-strip__text">
						<strong><?php echo esc_html( sprintf( '%02d. %s', $reklamo_i + 1, $reklamo_step[1] ) ); ?></strong>
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
				<div class="card next-steps">
					<h3><?php esc_html_e( 'What happens next?', 'reklamo' ); ?></h3>
					<ul>
						<?php foreach ( $reklamo_next as $reklamo_item ) : ?>
							<li>
								<span class="next-steps__icon"><?php echo reklamo_icon( $reklamo_item[0], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span><strong><?php echo esc_html( $reklamo_item[1] ); ?></strong><small><?php echo esc_html( $reklamo_item[2] ); ?></small></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="card help-card">
					<span class="help-card__icon"><?php echo reklamo_icon( 'headset', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h3><?php esc_html_e( 'Need help?', 'reklamo' ); ?></h3>
						<p><?php esc_html_e( 'Our consultant is available to help you.', 'reklamo' ); ?></p>
					</div>
					<?php $reklamo_contact = get_page_by_path( 'kontakti' ); ?>
					<a class="btn btn--outline" href="<?php echo esc_url( $reklamo_contact ? get_permalink( $reklamo_contact ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Contact us', 'reklamo' ); ?></a>
				</div>
			</aside>
		</div>
	</div>

	<?php get_template_part( 'template-parts/trust-strip' ); ?>
	<?php
endwhile;

get_footer();

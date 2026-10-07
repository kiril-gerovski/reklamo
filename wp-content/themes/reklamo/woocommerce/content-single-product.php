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
$reklamo_short    = reklamo_split_short_description( $product->get_short_description() );
$reklamo_lead     = $reklamo_short['lead'];
$reklamo_includes = $reklamo_short['list'];

$reklamo_items = reklamo_package_items( $reklamo_includes );

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
			<?php
			$reklamo_main  = wp_get_attachment_image_src( $product->get_image_id(), 'full' );
			$reklamo_photo = $reklamo_main && $reklamo_main[1] > $reklamo_main[2]; // a landscape photograph, not a cut-out or artwork
			$reklamo_class = ( $product->is_featured() ? ' has-badge' : '' ) . ( $reklamo_photo ? ' is-photo' : '' );
			?>
			<div class="product-main__gallery<?php echo esc_attr( $reklamo_class ); ?>">
				<?php if ( $product->is_featured() ) : ?>
					<span class="badge product-main__badge"><?php esc_html_e( 'Most popular', 'reklamo' ); ?></span>
				<?php endif; ?>
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
			</div>

				<div class="product-price-badge">
					<span class="product-price-badge__label"><?php esc_html_e( 'Final price', 'reklamo' ); ?></span>
					<?php add_filter( 'woocommerce_price_trim_zeros', '__return_true' ); ?>
					<span class="product-price-badge__value"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
					<?php remove_filter( 'woocommerce_price_trim_zeros', '__return_true' ); ?>
					<span class="product-price-badge__note"><?php esc_html_e( 'branding included', 'reklamo' ); ?></span>
				</div>

				<ul class="product-usps">
					<?php foreach ( $reklamo_usps as $reklamo_usp ) : ?>
						<li>
							<span class="product-usps__icon"><?php echo reklamo_icon( $reklamo_usp[0], 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span><strong><?php echo esc_html( $reklamo_usp[1] ); ?></strong><?php echo esc_html( $reklamo_usp[2] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

				<div class="product-main__lower">
					<div class="product-includes">
						<?php if ( $reklamo_items ) : ?>
							<h2 class="product-includes__title"><?php esc_html_e( 'What does the package include?', 'reklamo' ); ?></h2>
							<ul class="product-includes__list">
								<?php foreach ( $reklamo_items as $reklamo_item ) : ?>
									<li>
										<span class="product-includes__icon"><?php echo reklamo_icon( $reklamo_item['icon'], 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
										<span class="product-includes__plus" aria-hidden="true"><?php echo $reklamo_item['counted'] ? '+' : ''; ?></span>
										<span>
											<strong><?php echo esc_html( $reklamo_item['title'] ); ?></strong>
											<?php if ( $reklamo_item['detail'] ) : ?>
												<small><?php echo esc_html( $reklamo_item['detail'] ); ?></small>
											<?php endif; ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
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
					<span class="product-steps__icon"><?php echo reklamo_icon( $reklamo_step[0], 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<div><strong><?php echo esc_html( $reklamo_step[1] ); ?></strong><p><?php echo esc_html( $reklamo_step[2] ); ?></p></div>
				</li>
			<?php endforeach; ?>
		</ol>

		<div class="product-detail">
			<?php
			$reklamo_section = class_exists( 'Reklamo_Product' ) ? Reklamo_Product::section_image( $product ) : 0;
			$reklamo_bg      = $reklamo_section ? wp_get_attachment_image_url( $reklamo_section, 'large' ) : '';
			?>
			<div class="product-detail__tabs card<?php echo $reklamo_bg ? ' has-image' : ''; ?>"<?php echo $reklamo_bg ? ' style="--section-image: url(' . esc_url( $reklamo_bg ) . ')"' : ''; ?>>
				<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
			</div>

			<aside class="product-help card">
				<?php
				$reklamo_photo = wp_get_attachment_image(
					(int) reklamo_setting( 'consultant_photo' ),
					array( 160, 160 ),
					false,
					array(
						'class' => 'product-help__photo',
						'alt'   => '',
					)
				);
				?>
				<h2 class="product-help__title"><?php echo $reklamo_photo ? '' : reklamo_icon( 'headset', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Need help?', 'reklamo' ); ?></h2>
				<div class="product-help__body">
					<?php echo $reklamo_photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
					<p><?php esc_html_e( 'Our consultant will help you choose the right package for your business.', 'reklamo' ); ?></p>
				</div>
				<a class="btn btn--outline" href="<?php echo esc_url( reklamo_contact_url() ); ?>">
					<?php echo reklamo_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<?php esc_html_e( 'Get in touch', 'reklamo' ); ?>
				</a>
				<?php
				$reklamo_links = array(
					REKLAMO_HOW_IT_WORKS_SLUG => __( 'How ordering works', 'reklamo' ),
					REKLAMO_DELIVERY_SLUG     => __( 'Delivery and deadlines', 'reklamo' ),
					REKLAMO_FAQ_SLUG          => __( 'Frequently asked questions', 'reklamo' ),
				);
				?>
				<ul class="product-help__links">
					<?php foreach ( $reklamo_links as $reklamo_slug => $reklamo_label ) : ?>
						<?php $reklamo_page = get_page_by_path( $reklamo_slug ); ?>
						<?php if ( $reklamo_page instanceof WP_Post && 'publish' === $reklamo_page->post_status ) : ?>
							<li><a href="<?php echo esc_url( (string) get_permalink( $reklamo_page ) ); ?>"><?php echo esc_html( $reklamo_label ); ?> <span aria-hidden="true">→</span></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</aside>
		</div>

		<?php
		$reklamo_how = get_page_by_path( REKLAMO_HOW_IT_WORKS_SLUG );
		get_template_part(
			'template-parts/steps',
			null,
			array(
				'base'    => $reklamo_how ? (string) get_permalink( $reklamo_how ) : '',
				'link'    => (bool) $reklamo_how,
				'section' => true,
				'texts'   => true,
			)
		);
		?>
	</div>

	<?php get_template_part( 'template-parts/trust-strip' ); ?>
</div>
<?php
do_action( 'woocommerce_after_single_product' );

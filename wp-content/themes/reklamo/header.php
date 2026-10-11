<?php
/**
 * Site header — logo mark, primary menu, request CTA.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'reklamo' ); ?></a>
<header class="site-header">
	<div class="container site-header__inner">
		<?php echo reklamo_logo( false, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>

		<nav class="primary-nav" id="primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'reklamo' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'primary-menu',
					'fallback_cb'    => false,
					'depth'          => 2,
					'walker'         => new Reklamo_Nav_Walker(),
				)
			);
			?>
		</nav>

		<div class="site-header__actions">
			<a class="btn btn--outline btn--sm site-header__cta" href="<?php echo esc_url( reklamo_request_url() ); ?>"><?php esc_html_e( 'Request a mockup', 'reklamo' ); ?></a>
			<?php if ( class_exists( 'Reklamo_Cart' ) ) : ?>
				<?php $reklamo_cart_count = Reklamo_Cart::count(); ?>
				<a class="site-header__cart" href="<?php echo esc_url( Reklamo_Cart::url() ); ?>" data-cart-count="<?php echo esc_attr( (string) $reklamo_cart_count ); ?>">
					<?php echo reklamo_icon( 'bag', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Cart', 'reklamo' ); ?></span>
					<?php if ( $reklamo_cart_count ) : ?>
						<span class="site-header__cart-count" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: number of products in the cart */ _n( '%d product', '%d products', $reklamo_cart_count, 'reklamo' ), $reklamo_cart_count ) ); ?>"><?php echo esc_html( (string) $reklamo_cart_count ); ?></span>
					<?php endif; ?>
				</a>
			<?php endif; ?>
			<button class="nav-toggle" type="button" aria-controls="primary-nav" aria-expanded="false" data-nav-toggle>
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'reklamo' ); ?></span>
				<?php echo reklamo_icon( 'menu', 24 ) . reklamo_icon( 'close', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</button>
		</div>
	</div>
</header>
<main id="content" class="site-main">

<?php
/**
 * Site footer — brand + tagline + social, two menus, contacts from settings.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

$reklamo_phone   = reklamo_setting( 'phone' );
$reklamo_email   = reklamo_setting( 'email' );
$reklamo_address = reklamo_setting( 'address' );
$reklamo_social  = array_filter(
	array(
		'facebook'  => reklamo_setting( 'facebook' ),
		'instagram' => reklamo_setting( 'instagram' ),
		'linkedin'  => reklamo_setting( 'linkedin' ),
	)
);
?>
</main>
<footer class="site-footer">
	<div class="container site-footer__grid">
		<div class="site-footer__brand">
			<?php echo reklamo_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			<?php if ( reklamo_setting( 'tagline' ) ) : ?>
				<p class="site-footer__tagline"><?php echo esc_html( reklamo_setting( 'tagline' ) ); ?></p>
			<?php endif; ?>
			<?php if ( $reklamo_social ) : ?>
				<ul class="social">
					<?php foreach ( $reklamo_social as $reklamo_net => $reklamo_url ) : ?>
						<li><a href="<?php echo esc_url( $reklamo_url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $reklamo_net ) ); ?>"><?php echo reklamo_icon( $reklamo_net, 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="site-footer__col">
			<h4><?php esc_html_e( 'Navigation', 'reklamo' ); ?></h4>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer-nav',
					'container'      => false,
					'menu_class'     => 'footer-menu',
					'fallback_cb'    => false,
					'depth'          => 1,
				)
			);
			?>
		</div>

		<div class="site-footer__col">
			<h4><?php esc_html_e( 'Information', 'reklamo' ); ?></h4>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer-info',
					'container'      => false,
					'menu_class'     => 'footer-menu',
					'fallback_cb'    => false,
					'depth'          => 1,
				)
			);
			?>
		</div>

		<div class="site-footer__col">
			<h4><?php esc_html_e( 'Contact', 'reklamo' ); ?></h4>
			<ul class="contact-list">
				<?php if ( $reklamo_phone ) : ?>
					<li><?php echo reklamo_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^+\d]/', '', $reklamo_phone ) ); ?>"><?php echo esc_html( $reklamo_phone ); ?></a></li>
				<?php endif; ?>
				<?php if ( $reklamo_email ) : ?>
					<li><?php echo reklamo_icon( 'mail', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="mailto:<?php echo esc_attr( $reklamo_email ); ?>"><?php echo esc_html( $reklamo_email ); ?></a></li>
				<?php endif; ?>
				<?php if ( $reklamo_address ) : ?>
					<li><?php echo reklamo_icon( 'pin', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $reklamo_address ); ?></span></li>
				<?php endif; ?>
			</ul>
		</div>
	</div>
	<div class="container site-footer__bottom">
		<p>
			<?php
			/* translators: %1$s: year, %2$s: site name */
			printf( esc_html__( '© %1$s %2$s — All rights reserved.', 'reklamo' ), esc_html( wp_date( 'Y' ) ), esc_html( get_bloginfo( 'name' ) ) );
			?>
		</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

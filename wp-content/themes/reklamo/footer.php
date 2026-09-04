<?php
/**
 * Site footer.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;
?>
</main>
<footer class="site-footer">
	<?php
	wp_nav_menu(
		array(
			'theme_location' => 'footer-nav',
			'container'      => 'nav',
			'menu_class'     => 'footer-menu',
			'fallback_cb'    => false,
		)
	);
	wp_nav_menu(
		array(
			'theme_location' => 'footer-info',
			'container'      => 'nav',
			'menu_class'     => 'footer-menu',
			'fallback_cb'    => false,
		)
	);
	?>
	<p>
		<?php
		/* translators: %1$s: year, %2$s: site name */
		printf( esc_html__( '© %1$s %2$s — Всички права запазени.', 'reklamo' ), esc_html( wp_date( 'Y' ) ), esc_html( get_bloginfo( 'name' ) ) );
		?>
	</p>
</footer>
<?php wp_footer(); ?>
</body>
</html>

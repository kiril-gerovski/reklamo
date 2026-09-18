<?php
/**
 * Not found.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header();
$reklamo_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>
<div class="container page-narrow">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Page not found', 'reklamo' ); ?></h1>
	</header>
	<p class="lead"><?php esc_html_e( 'The page you are looking for does not exist or has been moved.', 'reklamo' ); ?></p>
	<div class="page-actions">
		<a class="btn btn--primary" href="<?php echo esc_url( $reklamo_shop ); ?>"><?php esc_html_e( 'Browse the packages', 'reklamo' ); ?></a>
		<a class="btn btn--outline" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the homepage', 'reklamo' ); ?></a>
	</div>
</div>
<?php
get_footer();

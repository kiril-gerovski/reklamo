<?php
/**
 * Fallback template — renders whatever the loop gives us.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php if ( ! is_front_page() ) : ?>
				<h1 class="entry-title"><?php the_title(); ?></h1>
			<?php endif; ?>
			<div class="entry-content"><?php the_content(); ?></div>
		</article>
		<?php
	}
} else {
	?>
	<p><?php esc_html_e( 'Нищо не е намерено.', 'reklamo' ); ?></p>
	<?php
}

get_footer();

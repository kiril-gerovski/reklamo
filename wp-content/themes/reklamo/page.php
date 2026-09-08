<?php
/**
 * Pages. The front page is built from full-width patterns and shows no title; a page that
 * opens with the six-step section ([reklamo_steps]) is full width with that section as its
 * heading; every other page gets a narrow reading column.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) {
	the_post();
	if ( is_front_page() ) {
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'front-page' ); ?>>
			<?php the_content(); ?>
		</article>
		<?php
	} elseif ( has_shortcode( get_the_content(), 'reklamo_steps' ) ) {
		// "How it works": the six-step section is the page's own heading, and the text runs full width.
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'page-wide container' ); ?>>
			<div class="entry-content"><?php the_content(); ?></div>
		</article>
		<?php
	} else {
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'page-narrow container' ); ?>>
			<header class="page-header">
				<h1 class="page-title"><?php the_title(); ?></h1>
			</header>
			<div class="entry-content"><?php the_content(); ?></div>
		</article>
		<?php
	}
}

get_footer();

<?php
/**
 * Search results: titles and excerpts, never full page content.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

get_header();
$reklamo_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>
<div class="container page-narrow">
	<header class="page-header">
		<h1 class="page-title">
			<?php
			/* translators: %s: the search query */
			printf( esc_html__( 'Search results for “%s”', 'reklamo' ), '<span class="search-query">' . esc_html( get_search_query() ) . '</span>' );
			?>
		</h1>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="search-results">
			<?php
			while ( have_posts() ) {
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'search-result' ); ?>>
					<h2 class="search-result__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php if ( has_excerpt() || 'product' !== get_post_type() ) : ?>
						<div class="search-result__excerpt"><?php the_excerpt(); ?></div>
					<?php endif; ?>
				</article>
				<?php
			}
			?>
		</div>
		<?php
		the_posts_pagination(
			array(
				'prev_text' => esc_html__( 'Previous', 'reklamo' ),
				'next_text' => esc_html__( 'Next', 'reklamo' ),
			)
		);
		?>
	<?php else : ?>
		<p class="lead">
			<?php
			/* translators: %s: the search query */
			printf( esc_html__( 'Nothing found for “%s”.', 'reklamo' ), esc_html( get_search_query() ) );
			?>
		</p>
		<p><?php esc_html_e( 'Try a different word, or start from the packages.', 'reklamo' ); ?></p>
		<div class="page-actions">
			<a class="btn btn--primary" href="<?php echo esc_url( $reklamo_shop ); ?>"><?php esc_html_e( 'Browse the packages', 'reklamo' ); ?></a>
			<a class="btn btn--outline" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the homepage', 'reklamo' ); ?></a>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();

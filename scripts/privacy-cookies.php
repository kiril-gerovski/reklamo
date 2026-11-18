<?php
/**
 * One-off for a live site: the Privacy Policy's cookie section becomes [reklamo_cookies], which
 * prints the no-banner text or the Google Ads text to match the settings. Replaces only the exact
 * seeded sentences and leaves the page alone when one has been reworded.
 *   scripts/deploy.sh wp eval-file scripts/privacy-cookies.php            dry run
 *   scripts/deploy.sh wp eval-file scripts/privacy-cookies.php apply      write the page
 *
 * @package Reklamo
 */

$reklamo_apply = in_array( 'apply', (array) ( $args ?? array() ), true );
$reklamo_page  = get_page_by_path( 'politika-za-poveritelnost' );
if ( ! $reklamo_page ) {
	WP_CLI::error( 'no page politika-za-poveritelnost' );
}

$reklamo_swaps = array(
	'§ 6 heading anchor' => array(
		"<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">6. Бисквитки</h2>",
		"<!-- wp:heading {\"anchor\":\"biskvitki\"} -->\n<h2 class=\"wp-block-heading\" id=\"biskvitki\">6. Бисквитки</h2>",
	),
	'§ 6 text'           => array(
		"<!-- wp:paragraph -->\n<p>Сайтът не използва аналитични, рекламни или проследяващи бисквитки и не зарежда съдържание от трети страни. Стриктно необходими технически бисквитки се създават единствено при вход в административния панел. Затова няма банер за бисквитки.</p>\n<!-- /wp:paragraph -->",
		"<!-- wp:shortcode -->\n[reklamo_cookies]\n<!-- /wp:shortcode -->",
	),
	'§ 3 EU sentence'    => array(
		'<p>Данните не се предават извън Европейския съюз.</p>',
		'<p>Данните от заявките и поръчките не се предават извън Европейския съюз. За бисквитките вижте т. 6.</p>',
	),
);

$reklamo_content = str_replace( "\r\n", "\n", $reklamo_page->post_content );
$reklamo_missing = array();
foreach ( $reklamo_swaps as $reklamo_name => $reklamo_pair ) {
	if ( str_contains( $reklamo_content, $reklamo_pair[1] ) ) {
		WP_CLI::log( "  $reklamo_name: already done" );
	} elseif ( str_contains( $reklamo_content, $reklamo_pair[0] ) ) {
		$reklamo_content = str_replace( $reklamo_pair[0], $reklamo_pair[1], $reklamo_content );
		WP_CLI::log( "  $reklamo_name: " . ( $reklamo_apply ? 'replaced' : 'would be replaced' ) );
	} else {
		$reklamo_missing[] = $reklamo_name;
	}
}
if ( $reklamo_missing ) {
	WP_CLI::error( 'the page was edited, not touched; do these by hand: ' . implode( ', ', $reklamo_missing ) );
}
if ( ! $reklamo_apply ) {
	WP_CLI::success( 'dry run, nothing written; add "apply"' );
	return;
}
if ( $reklamo_content !== $reklamo_page->post_content ) {
	wp_update_post(
		array(
			'ID'           => $reklamo_page->ID,
			'post_content' => wp_slash( $reklamo_content ),
		)
	);
}
WP_CLI::success( 'Privacy Policy updated (a revision keeps the previous text)' );

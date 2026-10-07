<?php
/**
 * Content that is the same for every product, kept on its own page and shown again on the
 * homepage: the FAQ, delivery and deadlines, and the step texts of "How it works". Each page is
 * the single source; the owner edits it in the block editor.
 *
 * FAQ and delivery pages: every <h3> starts an item and everything up to the next one is its text.
 * "How it works": every <h2 id="stapka-N"> starts the section for step N.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

const REKLAMO_FAQ_SLUG      = 'chesto-zadavani-vaprosi';
const REKLAMO_DELIVERY_SLUG = 'dostavka-i-srokove';

/** A published page's content with blocks and shortcodes rendered, '' when there is none. */
function reklamo_page_html( string $slug ): string {
	$page = get_page_by_path( $slug );
	if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) {
		return '';
	}
	return do_shortcode( do_blocks( $page->post_content ) );
}

/** @return array<int,array{title:string,text:string}> Item texts are HTML. */
function reklamo_page_items( string $slug ): array {
	$blocks = preg_split( '#<h3[^>]*>#i', reklamo_page_html( $slug ) );
	array_shift( $blocks );
	$items = array();
	foreach ( (array) $blocks as $block ) {
		list( $title, $text ) = array_pad( preg_split( '#</h3>#i', (string) $block, 2 ), 2, '' );
		$title                = trim( wp_strip_all_tags( $title ) );
		$text                 = trim( $text );
		if ( '' !== $title && '' !== wp_strip_all_tags( $text ) ) {
			$items[] = array(
				'title' => $title,
				'text'  => $text,
			);
		}
	}
	return $items;
}

/** @return array<int,array{question:string,answer:string}> */
function reklamo_faq_items(): array {
	return array_map(
		static fn( array $item ): array => array(
			'question' => $item['title'],
			'answer'   => $item['text'],
		),
		reklamo_page_items( REKLAMO_FAQ_SLUG )
	);
}

/** The step sections of "How it works", each an <h2 id="stapka-N"> with its text, in order. */
function reklamo_step_sections(): array {
	// That page holds [reklamo_steps] itself, which asks for these sections again while rendering.
	static $sections = null;
	if ( null !== $sections ) {
		return $sections;
	}
	$sections = array();
	$found    = array();
	foreach ( preg_split( '#(?=<h2\b)#i', reklamo_page_html( REKLAMO_HOW_IT_WORKS_SLUG ) ) as $chunk ) {
		if ( preg_match( '#^<h2\b[^>]*\bid="stapka-\d+"#i', $chunk ) ) {
			$found[] = trim( $chunk );
		}
	}
	$sections = $found;
	return $sections;
}

/** The FAQ and delivery pages show their items in the same design as the homepage. */
add_filter(
	'the_content',
	static function ( string $content ): string {
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$parts = array(
			REKLAMO_FAQ_SLUG      => 'template-parts/faq',
			REKLAMO_DELIVERY_SLUG => 'template-parts/delivery',
		);
		foreach ( $parts as $slug => $part ) {
			if ( is_page( $slug ) ) {
				ob_start();
				get_template_part( $part, null, array( 'variant' => 'page' ) );
				$html = (string) ob_get_clean();
				return '' !== $html ? $html : $content;
			}
		}
		return $content;
	},
	20
);

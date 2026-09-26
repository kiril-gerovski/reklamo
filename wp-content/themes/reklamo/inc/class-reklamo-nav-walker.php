<?php
/**
 * Primary menu walker: gives every item that has children a toggle button, so the submenu
 * opens on hover, on keyboard focus and on touch alike. WordPress already puts
 * `menu-item-has-children` on the <li>, which is what the stylesheet hooks onto.
 *
 * @package Reklamo
 */

defined( 'ABSPATH' ) || exit;

class Reklamo_Nav_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$output .= "\n<ul class=\"sub-menu\">\n";
	}

	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		parent::start_el( $output, $data_object, $depth, $args, $current_object_id );

		if ( ! in_array( 'menu-item-has-children', (array) $data_object->classes, true ) ) {
			return;
		}

		$output .= sprintf(
			'<button class="sub-toggle" type="button" aria-expanded="false"><span class="screen-reader-text">%s</span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg></button>',
			esc_html(
				sprintf(
					/* translators: %s: menu item title */
					__( 'Show submenu of %s', 'reklamo' ),
					$data_object->title
				)
			)
		);
	}
}

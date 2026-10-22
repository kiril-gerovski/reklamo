/**
 * Product editor, "Colours and quantities": a ticked colour shows its photo picker, a ticked
 * quantity its price, and ticking a quantity makes the product variable.
 */
( function () {
	'use strict';
	const panel = document.getElementById( 'reklamo_variants_data' );
	if ( ! panel ) {
		return;
	}
	function sync() {
		panel.querySelectorAll( '[data-rk-colour]' ).forEach( ( box ) => {
			const row = panel.querySelector( '[data-rk-photos="' + box.value + '"]' );
			if ( row ) {
				row.hidden = ! box.checked;
			}
		} );
		panel.querySelectorAll( '[data-rk-size]' ).forEach( ( box ) => {
			const price = panel.querySelector( '[data-rk-size-price="' + box.value + '"]' );
			if ( price ) {
				price.disabled = ! box.checked;
			}
		} );
	}
	panel.addEventListener( 'change', ( e ) => {
		sync();
		const type = window.jQuery && window.jQuery( '#product-type' );
		if ( e.target.matches( '[data-rk-size]' ) && e.target.checked && type && 'variable' !== type.val() ) {
			type.val( 'variable' ).trigger( 'change' );
			// WooCommerce opens the first tab on a type change: stay here.
			window.jQuery( 'a[href="#reklamo_variants_data"]' ).trigger( 'click' );
		}
	} );
	sync();
}() );

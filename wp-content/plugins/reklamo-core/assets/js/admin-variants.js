/**
 * Product editor, "Colours and quantities": a ticked colour shows its photo picker, a ticked
 * quantity its price, promotion and gift, and ticking a quantity makes the product variable.
 * With a quantity ticked the General tab's product-wide promotion and gift give way to these.
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
			panel.querySelectorAll( '[data-rk-size-price="' + box.value + '"], [data-rk-size-field="' + box.value + '"]' ).forEach( ( field ) => {
				field.disabled = ! box.checked;
			} );
		} );
		const tiered = !! panel.querySelector( '[data-rk-size]:checked' );
		document.querySelectorAll( '._reklamo_promo_pct_field, ._reklamo_gift_field' ).forEach( ( row ) => {
			row.style.display = tiered ? 'none' : '';
		} );
		document.querySelectorAll( '[data-rk-tier-note]' ).forEach( ( note ) => {
			note.hidden = ! tiered;
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

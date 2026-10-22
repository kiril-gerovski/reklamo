/**
 * Product page order box for a product sold by the piece: picks the WooCommerce variation that
 * matches the chosen colour, shows its photo and price, steps the quantity from the minimum,
 * and keeps the total in view. Without JavaScript the form still posts the colour and the
 * quantity, and WooCommerce finds the variation itself.
 */
( function () {
	'use strict';
	var form = document.querySelector( '[data-rk-product]' );
	if ( ! form ) { return; }
	var variations = [];
	try { variations = JSON.parse( form.getAttribute( 'data-variations' ) || '[]' ); } catch ( e ) { variations = []; }
	var price = parseFloat( form.getAttribute( 'data-price' ) ) || 0;
	var currency = form.getAttribute( 'data-currency' ) || '';
	var qty = form.querySelector( '[data-rk-qty]' );
	var total = form.querySelector( '[data-rk-total]' );
	var hidden = form.querySelector( '[data-rk-variation]' );

	// As WooCommerce writes prices: its thousands and decimal separators (1 019,99 €).
	var thousands = form.getAttribute( 'data-thousand-sep' ) || '';
	var decimals = form.getAttribute( 'data-decimal-sep' ) || ',';
	function money( value ) {
		var parts = value.toFixed( 2 ).split( '.' );
		return parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, thousands ) + decimals + parts[ 1 ] + ' ' + currency;
	}

	function chosen() {
		var out = {};
		form.querySelectorAll( '.rk-options input:checked' ).forEach( function ( input ) {
			out[ input.name ] = input.value;
			var label = input.closest( '.rk-options' ).querySelector( '[data-rk-chosen]' );
			if ( label ) { label.textContent = input.getAttribute( 'data-label' ) || input.value; }
		} );
		return out;
	}

	function match( picked ) {
		return variations.filter( function ( v ) {
			return Object.keys( v.attrs ).every( function ( key ) {
				return '' === v.attrs[ key ] || v.attrs[ key ] === picked[ key ];
			} );
		} )[ 0 ];
	}

	var shown = null;
	var productGallery = form.getAttribute( 'data-gallery' ) || '';
	// While there is a gift the page opens on its photo; a colour's photos come once a colour is picked.
	var keepFirst = form.hasAttribute( 'data-rk-gift-photo' );

	// Swap the gallery's slides for the colour's own (or back to the product's) and start
	// WooCommerce's gallery script on the new markup.
	function showGallery( html ) {
		html = html || productGallery;
		if ( null === shown ) { shown = productGallery; }
		if ( ! html || html === shown ) { return; }
		var old = document.querySelector( '.woocommerce-product-gallery' );
		if ( ! old ) { return; }
		var fresh = document.createElement( 'div' );
		fresh.className = 'woocommerce-product-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-4 images';
		fresh.setAttribute( 'data-columns', '4' );
		var wrapper = document.createElement( 'div' );
		wrapper.className = 'woocommerce-product-gallery__wrapper';
		wrapper.innerHTML = html;
		fresh.appendChild( wrapper );
		old.parentNode.replaceChild( fresh, old );
		shown = html;
		if ( window.jQuery && window.jQuery.fn.wc_product_gallery && window.wc_single_product_params ) {
			window.jQuery( fresh ).wc_product_gallery( window.wc_single_product_params );
		} else {
			fresh.style.opacity = '1';
		}
	}

	function update() {
		if ( variations.length ) {
			var v = match( chosen() );
			if ( hidden ) { hidden.value = v ? v.id : ''; }
			if ( v ) {
				price = v.price;
				if ( ! keepFirst ) { showGallery( v.gallery ); }
				var badge = document.querySelector( '[data-rk-badge-price]' );
				if ( badge ) { badge.textContent = money( price ).replace( decimals + '00', '' ); }
				// On promotion the old price stands struck through above the new one.
				var was = document.querySelector( '[data-rk-badge-regular]' );
				if ( was ) {
					was.hidden = ! ( v.regular > v.price );
					was.textContent = v.regular > v.price ? money( v.regular ).replace( decimals + '00', '' ) : '';
				}
			}
			// A package size changes the counts in "What does the package include?".
			var size = form.querySelector( '[data-attribute="attribute_pa_kolichestvo"] input:checked' );
			if ( size ) {
				var n = parseInt( size.value.replace( /\D/g, '' ), 10 );
				if ( n ) { document.querySelectorAll( '[data-rk-count]' ).forEach( function ( el ) { el.textContent = String( n ); } ); }
			}
		}
		if ( qty && total ) {
			var n = Math.max( parseInt( qty.min, 10 ) || 1, parseInt( qty.value, 10 ) || 0 );
			total.textContent = money( price * n );
		}
	}

	form.querySelectorAll( '[data-rk-stepper] [data-step]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var min = parseInt( qty.min, 10 ) || 1;
			var next = ( parseInt( qty.value, 10 ) || min ) + parseInt( button.getAttribute( 'data-step' ), 10 );
			qty.value = String( Math.max( min, next ) );
			update();
		} );
	} );
	if ( qty ) {
		qty.addEventListener( 'blur', function () {
			var min = parseInt( qty.min, 10 ) || 1;
			if ( ( parseInt( qty.value, 10 ) || 0 ) < min ) { qty.value = String( min ); update(); }
		} );
	}
	form.addEventListener( 'change', function ( e ) {
		if ( /^attribute_/.test( e.target.name || '' ) && 'attribute_pa_kolichestvo' !== e.target.name ) { keepFirst = false; }
		update();
	} );
	form.addEventListener( 'input', update );
	update();
} )();

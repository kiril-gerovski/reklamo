/**
 * Request page: the chosen product's colours and quantities (its WooCommerce variations), the
 * price and photo of that combination, and the switch between "I have a logo" and an inscription.
 * Without it the form still posts the product, which then goes in its default colour and size.
 */
( function () {
	'use strict';
	var form = document.querySelector( '[data-rk-design]' );
	if ( ! form ) { return; }
	var catalogue = JSON.parse( form.dataset.catalogue || '[]' );
	var select = form.querySelector( '[data-rk-product-select]' );
	var attrsBox = form.querySelector( '[data-rk-attrs]' );
	var variation = form.querySelector( '[data-rk-variation]' );
	var price = form.querySelector( '[data-rk-price]' );
	var image = document.querySelector( '[data-rk-design-image]' );
	var text = form.querySelector( 'textarea[name="rk_text"]' );
	var placeholder = image ? image.getAttribute( 'src' ) : '';
	var allOptions = Array.prototype.slice.call( select.options );

	var product = function () {
		var id = parseInt( select.value, 10 );
		return catalogue.filter( function ( p ) { return p.id === id; } )[ 0 ] || null;
	};

	var showImage = function ( src ) {
		if ( image && src && image.getAttribute( 'src' ) !== src ) {
			image.removeAttribute( 'srcset' );
			image.setAttribute( 'src', src );
		}
	};

	var update = function () {
		var p = product();
		if ( ! p ) {
			variation.value = '';
			price.hidden = true;
			showImage( placeholder );
			return;
		}
		var chosen = {};
		attrsBox.querySelectorAll( 'select' ).forEach( function ( s ) { chosen[ s.name ] = s.value; } );
		var match = p.vars.filter( function ( v ) {
			return Object.keys( v.attrs ).every( function ( k ) { return '' === v.attrs[ k ] || v.attrs[ k ] === chosen[ k ]; } );
		} )[ 0 ];
		variation.value = match ? match.id : '';
		price.textContent = ( match ? match.price : p.price ) + ' ' + ( form.dataset.vat || '' );
		price.hidden = false;
		showImage( match && match.image ? match.image : p.image );
	};

	var build = function () {
		attrsBox.innerHTML = '';
		var p = product();
		if ( p ) {
			p.attrs.forEach( function ( a ) {
				var field = document.createElement( 'div' );
				field.className = 'rq-field';
				var id = 'rk-attr-' + a.name;
				var label = document.createElement( 'label' );
				label.htmlFor = id;
				label.textContent = a.label;
				var s = document.createElement( 'select' );
				s.id = id;
				s.name = a.name;
				a.options.forEach( function ( o ) {
					var opt = document.createElement( 'option' );
					opt.value = o.value;
					opt.textContent = o.label;
					opt.selected = o.value === a.default;
					s.appendChild( opt );
				} );
				s.addEventListener( 'change', update );
				field.appendChild( label );
				field.appendChild( s );
				attrsBox.appendChild( field );
			} );
		}
		update();
	};

	var mode = function () {
		var checked = form.querySelector( 'input[name="rk_mode"]:checked' );
		var m = checked ? checked.value : 'logo';
		form.dataset.mode = m;
		form.querySelectorAll( '[data-rk-when]' ).forEach( function ( el ) { el.hidden = el.getAttribute( 'data-rk-when' ) !== m; } );
		if ( text ) { text.required = 'text' === m; }
		// Packages take a logo only: not in the list at all while an inscription is chosen.
		var keep = select.value;
		select.innerHTML = '';
		allOptions.forEach( function ( o ) {
			if ( 'text' !== m || ! o.hasAttribute( 'data-package' ) ) { select.appendChild( o ); }
		} );
		select.value = keep;
		if ( select.value !== keep ) {
			select.value = '';
			build();
		}
	};

	var printLabel = form.querySelector( '[data-rk-print-chosen]' );
	form.querySelectorAll( 'input[name="rk_colour"]' ).forEach( function ( r ) {
		r.addEventListener( 'change', function () { if ( printLabel ) { printLabel.textContent = r.dataset.label; } } );
	} );
	form.querySelectorAll( 'input[name="rk_mode"]' ).forEach( function ( r ) { r.addEventListener( 'change', mode ); } );
	select.addEventListener( 'change', build );
	mode();
	build();
} )();

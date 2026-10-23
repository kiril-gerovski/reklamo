/**
 * Request page: the chosen product's colours and quantities (its WooCommerce variations) as chips, the
 * price and photo of that combination, and the switch between "I have a logo" and an inscription.
 * The product select is shown as a list with a small photo of each product; the select itself
 * stays underneath and holds the value. Without JavaScript the form still posts the product,
 * which then goes in its default colour and size.
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

	// The picker: a button with the chosen product, and a listbox of every option with its photo.
	var picker = document.createElement( 'div' );
	picker.className = 'rk-picker';
	var button = document.createElement( 'button' );
	button.type = 'button';
	button.className = 'rk-picker__button';
	button.setAttribute( 'aria-haspopup', 'listbox' );
	button.setAttribute( 'aria-expanded', 'false' );
	var list = document.createElement( 'ul' );
	list.className = 'rk-picker__list';
	list.setAttribute( 'role', 'listbox' );
	list.id = select.id + '-list';
	list.tabIndex = -1;
	list.hidden = true;
	button.setAttribute( 'aria-controls', list.id );
	picker.appendChild( button );
	picker.appendChild( list );
	select.parentNode.insertBefore( picker, select.nextSibling );
	select.classList.add( 'rk-picker__native' );
	select.tabIndex = -1;
	select.setAttribute( 'aria-hidden', 'true' );
	var label = form.querySelector( 'label[for="' + select.id + '"]' );
	if ( label ) {
		label.id = label.id || select.id + '-label';
		button.setAttribute( 'aria-labelledby', label.id + ' ' + select.id + '-chosen' );
		list.setAttribute( 'aria-labelledby', label.id );
		label.addEventListener( 'click', function ( e ) { e.preventDefault(); button.focus(); } );
	}

	var info = function ( value ) {
		var id = parseInt( value, 10 );
		return catalogue.filter( function ( p ) { return p.id === id; } )[ 0 ] || null;
	};
	var face = function ( option, chosen ) {
		var p = info( option.value );
		var frag = document.createDocumentFragment();
		var thumb = document.createElement( 'span' );
		thumb.className = 'rk-picker__thumb';
		if ( p && p.thumb ) {
			var img = document.createElement( 'img' );
			img.src = p.thumb;
			img.alt = '';
			img.loading = 'lazy';
			thumb.appendChild( img );
		}
		var text = document.createElement( 'span' );
		text.className = 'rk-picker__text';
		var name = document.createElement( 'span' );
		name.className = 'rk-picker__name';
		name.textContent = option.textContent;
		if ( chosen ) { name.id = select.id + '-chosen'; }
		text.appendChild( name );
		if ( p ) {
			var meta = document.createElement( 'span' );
			meta.className = 'rk-picker__meta';
			var from = ( select.dataset.from || '%s' ).replace( '%s', p.price );
			meta.textContent = option.hasAttribute( 'data-package' ) ? ( select.dataset.packageLabel || '' ) + ' · ' + from : from;
			text.appendChild( meta );
		}
		frag.appendChild( thumb );
		frag.appendChild( text );
		return frag;
	};
	var active = -1;
	var items = function () { return Array.prototype.slice.call( list.children ); };
	var highlight = function ( i ) {
		var all = items();
		if ( ! all.length ) { return; }
		active = Math.max( 0, Math.min( all.length - 1, i ) );
		all.forEach( function ( li, n ) { li.classList.toggle( 'is-active', n === active ); } );
		list.setAttribute( 'aria-activedescendant', all[ active ].id );
		all[ active ].scrollIntoView( { block: 'nearest' } );
	};
	var refresh = function () {
		var current = select.options[ select.selectedIndex ] || select.options[ 0 ];
		button.textContent = '';
		button.appendChild( face( current, true ) );
		button.classList.toggle( 'is-empty', ! select.value );
		var chev = document.createElement( 'span' );
		chev.className = 'rk-picker__chev';
		chev.setAttribute( 'aria-hidden', 'true' );
		button.appendChild( chev );
		list.textContent = '';
		Array.prototype.forEach.call( select.options, function ( o, n ) {
			if ( ! o.value ) { return; }
			var li = document.createElement( 'li' );
			li.id = list.id + '-' + n;
			li.setAttribute( 'role', 'option' );
			li.className = 'rk-picker__option';
			li.dataset.value = o.value;
			li.setAttribute( 'aria-selected', o.value === select.value ? 'true' : 'false' );
			li.appendChild( face( o, false ) );
			list.appendChild( li );
		} );
	};
	var open = function () {
		list.hidden = false;
		button.setAttribute( 'aria-expanded', 'true' );
		var i = items().map( function ( li ) { return li.dataset.value; } ).indexOf( select.value );
		highlight( i < 0 ? 0 : i );
		list.focus();
	};
	var close = function ( refocus ) {
		list.hidden = true;
		button.setAttribute( 'aria-expanded', 'false' );
		if ( refocus ) { button.focus(); }
	};
	var choose = function ( value ) {
		close( true );
		button.classList.remove( 'is-invalid' );
		if ( value !== select.value ) {
			select.value = value;
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
		refresh();
	};
	button.addEventListener( 'click', function () { if ( list.hidden ) { open(); } else { close( true ); } } );
	button.addEventListener( 'keydown', function ( e ) {
		if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) { e.preventDefault(); open(); }
	} );
	list.addEventListener( 'click', function ( e ) {
		var li = e.target.closest( '[role="option"]' );
		if ( li ) { choose( li.dataset.value ); }
	} );
	list.addEventListener( 'keydown', function ( e ) {
		var all = items();
		if ( 'ArrowDown' === e.key ) { e.preventDefault(); highlight( active + 1 ); }
		else if ( 'ArrowUp' === e.key ) { e.preventDefault(); highlight( active - 1 ); }
		else if ( 'Home' === e.key ) { e.preventDefault(); highlight( 0 ); }
		else if ( 'End' === e.key ) { e.preventDefault(); highlight( all.length - 1 ); }
		else if ( 'Enter' === e.key || ' ' === e.key ) { e.preventDefault(); if ( all[ active ] ) { choose( all[ active ].dataset.value ); } }
		else if ( 'Escape' === e.key ) { e.preventDefault(); close( true ); }
		else if ( 'Tab' === e.key ) { close( false ); }
		else if ( 1 === e.key.length ) {
			// Jump to the next product starting with the typed letter.
			var k = e.key.toLowerCase();
			for ( var n = 1; n <= all.length; n++ ) {
				var at = ( active + n ) % all.length;
				if ( all[ at ].textContent.trim().toLowerCase().indexOf( k ) === 0 ) { highlight( at ); break; }
			}
		}
	} );
	document.addEventListener( 'click', function ( e ) { if ( ! list.hidden && ! picker.contains( e.target ) ) { close( false ); } } );
	// The browser cannot point at the hidden select when nothing is chosen: the picker shows it.
	select.addEventListener( 'invalid', function ( e ) {
		e.preventDefault();
		button.classList.add( 'is-invalid' );
		button.focus();
	} );
	select.addEventListener( 'change', refresh );

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
		attrsBox.querySelectorAll( 'input:checked' ).forEach( function ( r ) { chosen[ r.name ] = r.value; } );
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
			// The same chips as on the product page: a colour is a circle, a quantity a pill. Every
			// product has a quantity and few a colour: the quantity comes first, on the left.
			p.attrs.slice().sort( function ( x, y ) {
				return ( 'attribute_pa_kolichestvo' === y.name ) - ( 'attribute_pa_kolichestvo' === x.name );
			} ).forEach( function ( a ) {
				var field = document.createElement( 'fieldset' );
				field.className = 'rk-options';
				field.setAttribute( 'data-attribute', a.name );
				var legend = document.createElement( 'legend' );
				legend.className = 'rk-options__label';
				legend.textContent = a.label + ': ';
				var shown = document.createElement( 'span' );
				shown.setAttribute( 'data-rk-chosen', '' );
				legend.appendChild( shown );
				var row = document.createElement( 'div' );
				row.className = 'rk-options__list';
				// The product's default starts chosen, first in its row.
				var start = a.options.some( function ( o ) { return o.value === a.default; } ) ? a.default : ( a.options[ 0 ] || {} ).value;
				a.options.filter( function ( o ) { return o.value === start; } ).concat( a.options.filter( function ( o ) { return o.value !== start; } ) ).forEach( function ( o ) {
					var chip = document.createElement( 'label' );
					chip.className = 'rk-option' + ( o.hex ? ' rk-option--swatch' : '' );
					chip.title = o.label;
					var r = document.createElement( 'input' );
					r.type = 'radio';
					r.name = a.name;
					r.value = o.value;
					r.checked = o.value === start;
					r.addEventListener( 'change', function () { shown.textContent = o.label; update(); } );
					var face = document.createElement( 'span' );
					if ( o.hex ) {
						face.className = 'rk-option__swatch';
						face.style.setProperty( '--swatch', o.hex );
						var sr = document.createElement( 'span' );
						sr.className = 'screen-reader-text';
						sr.textContent = o.label;
						chip.appendChild( r );
						chip.appendChild( face );
						chip.appendChild( sr );
					} else {
						face.className = 'rk-option__text';
						face.textContent = o.label;
						chip.appendChild( r );
						chip.appendChild( face );
					}
					if ( r.checked ) { shown.textContent = o.label; }
					row.appendChild( chip );
				} );
				field.appendChild( legend );
				field.appendChild( row );
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
		refresh();
	};

	var printLabel = form.querySelector( '[data-rk-print-chosen]' );
	form.querySelectorAll( 'input[name="rk_colour"]' ).forEach( function ( r ) {
		r.addEventListener( 'change', function () { if ( printLabel ) { printLabel.textContent = r.dataset.label; } } );
	} );
	form.querySelectorAll( 'input[name="rk_mode"]' ).forEach( function ( r ) { r.addEventListener( 'change', mode ); } );
	select.addEventListener( 'change', build );
	mode();
	build();
	refresh();
} )();

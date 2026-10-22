/**
 * Cart, step 1: shows live which products have a logo (the note is optional), and keeps
 * "Continue" disabled until every product has one; a package needs one for every item it holds. Step 2: "Send" waits for name, email, phone and both consents.
 * The server checks all of it again on submit.
 */
( function () {
	'use strict';
	var form = document.querySelector( '[data-rk-cart]' );
	if ( ! form ) { return; }

	// Emptying the cart throws away uploads and notes: ask first.
	form.querySelectorAll( '[data-rk-confirm]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function ( e ) {
			if ( ! window.confirm( button.getAttribute( 'data-rk-confirm' ) ) ) { e.preventDefault(); }
		} );
	} );

	var send = form.querySelector( '[data-rk-send]' );
	if ( send ) {
		var sendBlocked = form.querySelector( '[data-rk-send-blocked]' );
		var field = function ( name ) { return form.querySelector( '[name="' + name + '"]' ); };
		var checkSend = function () {
			var name = field( 'rq_name' ), email = field( 'rq_email' ), phone = field( 'rq_phone' );
			var consent = field( 'rq_consent' ), waiver = field( 'rq_waiver' );
			var ok = !! ( name && name.value.trim() && phone && phone.value.trim() && email && email.value.trim() && email.checkValidity() && consent && consent.checked && waiver && waiver.checked );
			send.disabled = ! ok;
			if ( sendBlocked ) { sendBlocked.hidden = ok; }
		};
		form.addEventListener( 'input', checkSend );
		form.addEventListener( 'change', checkSend );
		checkSend();
		return;
	}

	var next = form.querySelector( '[data-rk-continue]' );
	if ( ! next ) { return; }
	var blocked = form.querySelector( '[data-rk-blocked]' );

	function hasFile( set ) {
		if ( set.querySelector( '[data-logo-file]' ) ) { return true; }
		var token = set.querySelector( 'input[type="hidden"][name$="[file_token]"]' );
		return !! ( token && token.value );
	}

	// A product or package item ticked under one logo leaves the other logos' lists.
	function hideTaken() {
		var sets = form.querySelectorAll( '.rk-logo' );
		sets.forEach( function ( set ) {
			var shown = 0;
			set.querySelectorAll( '.rk-logo__item' ).forEach( function ( label ) {
				var box = label.querySelector( 'input' ), taken = false;
				sets.forEach( function ( other ) {
					var twin = other !== set && other.querySelector( 'input[type="checkbox"][value="' + box.value + '"]' );
					taken = taken || !! ( twin && twin.checked );
				} );
				label.hidden = taken && ! box.checked;
				shown += label.hidden ? 0 : 1;
			} );
			set.querySelectorAll( '[data-rk-group]' ).forEach( function ( group ) {
				group.hidden = ! group.querySelector( '.rk-logo__item:not([hidden])' );
			} );
			var none = set.querySelector( '[data-rk-none]' );
			if ( none ) { none.hidden = shown > 0; }
			// A used logo left with nothing to mark steps aside until something frees up.
			var note = set.querySelector( 'textarea' );
			set.hidden = ! shown && ( hasFile( set ) || !! ( note && note.value.trim() ) );
		} );
	}

	function fmt( tpl, a, b ) {
		return ( tpl || '' ).replace( '%1$s', a ).replace( '%2$s', b ).replace( '%s', a );
	}

	function check() {
		hideTaken();
		var ready = true, reasons = [];
		form.querySelectorAll( '.rk-logo' ).forEach( function ( set ) {
			var marked = !! set.querySelector( '.rk-logo__item input:checked' );
			var warn = set.querySelector( '[data-rk-nofile]' );
			var missing = marked && ! hasFile( set );
			set.classList.toggle( 'is-missing-file', missing );
			if ( warn ) { warn.hidden = ! missing; }
		} );
		form.querySelectorAll( '[data-cart-item]' ).forEach( function ( item ) {
			if ( item.hasAttribute( 'data-cart-text' ) ) { return; }
			var product = ( item.querySelector( '.rk-cart__name' ) || item ).textContent.trim(), ok = true;
			var units = JSON.parse( item.getAttribute( 'data-cart-units' ) || '[]' ), short = [], full = [];
			// Each unit: [ checkbox value, package item title or '' for a whole product ].
			units.forEach( function ( unit ) {
				var marks = 0, tpl = '', title = '';
				form.querySelectorAll( '.rk-logo' ).forEach( function ( set ) {
					var box = set.querySelector( 'input[type="checkbox"][value="' + unit[ 0 ] + '"]' );
					if ( box && box.checked ) {
						marks++;
						title = ( set.querySelector( '.rk-logo__title' ) || set ).textContent.trim();
						tpl = hasFile( set ) ? '' : form.dataset.msgNofile;
					}
				} );
				if ( ! marks ) { tpl = form.dataset.msgUnmarked; }
				ok = ok && ! tpl && 1 === marks;
				if ( tpl ) {
					short.push( fmt( tpl, product, title ) );
					full.push( fmt( tpl, unit[ 1 ] ? product + ' — ' + unit[ 1 ] : product, title ) );
				}
			} );
			// The whole package with the same problem is named once.
			var same = short.length === units.length && short.every( function ( r ) { return r === short[ 0 ]; } );
			reasons = reasons.concat( same && short.length ? [ short[ 0 ] ] : full );
			item.classList.toggle( 'is-ready', ok );
			var status = item.querySelector( '[data-cart-status].is-missing' );
			if ( status ) {
				status.dataset.missingText = status.dataset.missingText || status.textContent.trim();
				status.textContent = ok ? status.dataset.readyText : status.dataset.missingText;
			}
			ready = ready && ok;
		} );
		next.disabled = ! ready;
		if ( blocked ) {
			blocked.hidden = ready;
			var list = blocked.querySelector( 'ul' ) || blocked.appendChild( document.createElement( 'ul' ) );
			list.textContent = '';
			reasons.forEach( function ( text ) {
				var li = document.createElement( 'li' );
				li.textContent = text;
				list.appendChild( li );
			} );
		}
	}

	form.addEventListener( 'input', check );
	form.addEventListener( 'change', check );
	check();
} )();

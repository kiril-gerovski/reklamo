/* Order screen: the status dropdown offers only the allowed next steps; "Copy for the invoice". */
( function () {
	'use strict';
	var cfg = window.reklamoOrder || {};
	var select = document.getElementById( 'order_status' );
	if ( select && cfg.allowed ) {
		Array.prototype.slice.call( select.options ).forEach( function ( o ) {
			if ( cfg.allowed.indexOf( o.value ) < 0 ) {
				o.remove();
			}
		} );
	}

	function fallback( text ) {
		var area = document.createElement( 'textarea' );
		area.value = text;
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();
		document.execCommand( 'copy' );
		area.remove();
	}

	document.querySelectorAll( '[data-reklamo-copy]' ).forEach( function ( btn ) {
		var label = btn.textContent;
		btn.addEventListener( 'click', function () {
			var text = btn.getAttribute( 'data-reklamo-copy' );
			var done = function () {
				btn.textContent = '✓ ' + btn.getAttribute( 'data-done' );
				setTimeout( function () {
					btn.textContent = label;
				}, 2000 );
			};
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then( done, function () {
					fallback( text );
					done();
				} );
			} else {
				fallback( text );
				done();
			}
		} );
	} );
}() );

/* Reklamo theme: mobile nav, upload dropzone feedback, note counter. No dependencies. */
( function () {
	'use strict';

	// Mobile navigation.
	var toggle = document.querySelector( '[data-nav-toggle]' );
	var nav = document.getElementById( 'primary-nav' );
	if ( toggle && nav ) {
		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}

	// Submenus: the button drives them on touch and keyboard; CSS handles hover and focus.
	var subToggles = document.querySelectorAll( '.sub-toggle' );
	subToggles.forEach( function ( button ) {
		var sub = button.parentNode.querySelector( '.sub-menu' );
		if ( ! sub ) { return; }
		button.addEventListener( 'click', function () {
			var open = ! sub.classList.contains( 'is-open' );
			subToggles.forEach( function ( other ) {
				if ( other === button ) { return; }
				other.setAttribute( 'aria-expanded', 'false' );
				var s = other.parentNode.querySelector( '.sub-menu' );
				if ( s ) { s.classList.remove( 'is-open' ); }
			} );
			sub.classList.toggle( 'is-open', open );
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) { return; }
		subToggles.forEach( function ( button ) {
			var sub = button.parentNode.querySelector( '.sub-menu' );
			if ( ! sub || ! sub.classList.contains( 'is-open' ) ) { return; }
			sub.classList.remove( 'is-open' );
			button.setAttribute( 'aria-expanded', 'false' );
			button.focus();
		} );
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.primary-menu' ) ) { return; }
		subToggles.forEach( function ( button ) {
			var sub = button.parentNode.querySelector( '.sub-menu' );
			if ( sub ) { sub.classList.remove( 'is-open' ); }
			button.setAttribute( 'aria-expanded', 'false' );
		} );
	} );

	function formatSize( bytes ) {
		if ( bytes >= 1048576 ) { return ( bytes / 1048576 ).toFixed( 1 ) + ' MB'; }
		if ( bytes >= 1024 ) { return Math.round( bytes / 1024 ) + ' KB'; }
		return bytes + ' B';
	}

	// Dropzone: show the chosen file, accept drag & drop.
	document.querySelectorAll( '[data-rq-drop]' ).forEach( function ( drop ) {
		var input = drop.querySelector( 'input[type="file"]' );
		var field = drop.closest( '.rq-field--file' );
		var box = field ? field.querySelector( '[data-rq-file]' ) : null;
		if ( ! input ) { return; }

		function show() {
			var f = input.files && input.files[ 0 ];
			if ( ! box ) { return; }
			if ( ! f ) { box.hidden = true; return; }
			box.hidden = false;
			var ext = ( f.name.split( '.' ).pop() || '' ).toUpperCase().slice( 0, 4 );
			box.querySelector( '[data-rq-file-ext]' ).textContent = ext;
			box.querySelector( '[data-rq-file-name]' ).textContent = f.name;
			box.querySelector( '[data-rq-file-size]' ).textContent = formatSize( f.size );
		}
		input.addEventListener( 'change', show );

		[ 'dragenter', 'dragover' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.add( 'is-dragover' ); } );
		} );
		[ 'dragleave', 'drop' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.remove( 'is-dragover' ); } );
		} );
		drop.addEventListener( 'drop', function ( e ) {
			if ( e.dataTransfer && e.dataTransfer.files.length ) {
				input.files = e.dataTransfer.files;
				show();
				// Assigning .files fires no event; the chunked uploader listens for `change`.
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
	} );

	// Character counter for the designer note.
	document.querySelectorAll( '[data-rq-counter]' ).forEach( function ( ta ) {
		var out = ta.parentNode.querySelector( '[data-rq-count]' );
		if ( ! out ) { return; }
		var update = function () { out.textContent = String( ta.value.length ); };
		ta.addEventListener( 'input', update );
		update();
	} );
} )();

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

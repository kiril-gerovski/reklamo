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
		// The chunked uploader swaps the input for an empty clone after each upload: always look it up.
		var current = function () { return drop.querySelector( 'input[type="file"]' ); };
		var field = drop.closest( '.rq-field--file' );
		var box = field ? field.querySelector( '[data-rq-file]' ) : null;
		if ( ! current() ) { return; }

		function show() {
			var input = current();
			var f = input.files && input.files[ 0 ];
			if ( ! box ) { return; }
			if ( ! f ) { box.hidden = true; return; }
			box.hidden = false;
			var ext = ( f.name.split( '.' ).pop() || '' ).toUpperCase().slice( 0, 4 );
			box.querySelector( '[data-rq-file-ext]' ).textContent = ext;
			box.querySelector( '[data-rq-file-name]' ).textContent = f.name;
			box.querySelector( '[data-rq-file-size]' ).textContent = formatSize( f.size );
		}
		drop.addEventListener( 'change', function ( e ) { if ( e.target === current() && current().files.length ) { show(); } } );

		[ 'dragenter', 'dragover' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.add( 'is-dragover' ); } );
		} );
		[ 'dragleave', 'drop' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.remove( 'is-dragover' ); } );
		} );
		drop.addEventListener( 'drop', function ( e ) {
			if ( e.dataTransfer && e.dataTransfer.files.length ) {
				var input = current();
				input.files = e.dataTransfer.files;
				// Assigning .files fires no event; the chunked uploader listens for `change`.
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
	} );

	// Homepage steps: each step shows its section from "How it works" in place, starting on the
	// first, instead of sending the visitor to that page.
	document.querySelectorAll( '[data-step-switcher]' ).forEach( function ( root ) {
		var steps = [];
		root.querySelectorAll( '.steps__link[href*="#stapka-"]' ).forEach( function ( link ) {
			var heading = document.getElementById( link.getAttribute( 'href' ).split( '#' )[ 1 ] );
			if ( heading && root.contains( heading ) ) { steps.push( { link: link, heading: heading } ); }
		} );
		if ( ! steps.length ) { return; }

		steps.forEach( function ( step ) {
			var panel = document.createElement( 'div' );
			panel.className = 'step-panel';
			panel.id = step.heading.id + '-panel';
			step.heading.parentNode.insertBefore( panel, step.heading );
			var node = step.heading;
			while ( node && ( node === step.heading || node.tagName !== 'H2' ) ) {
				var next = node.nextElementSibling;
				panel.appendChild( node );
				node = next;
			}
			step.panel = panel;
		} );

		var show = function ( index ) {
			steps.forEach( function ( step, i ) {
				var on = i === index;
				step.panel.hidden = ! on;
				step.link.parentNode.classList.toggle( 'is-active', on );
				if ( on ) { step.link.setAttribute( 'aria-current', 'step' ); } else { step.link.removeAttribute( 'aria-current' ); }
			} );
		};
		steps.forEach( function ( step, i ) {
			step.link.setAttribute( 'aria-controls', step.panel.id );
			step.link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				show( i );
				// On a phone the text sits below all six steps; bring it into view.
				if ( step.panel.getBoundingClientRect().top > window.innerHeight - 80 ) {
					step.panel.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				}
			} );
		} );
		root.classList.add( 'is-stepped' );
		show( 0 );
	} );

	// Inquiry dialog behind „Изпрати запитване“; without it (or without <dialog>) the mailto link stays.
	var inquiry = document.querySelector( '[data-inquiry]' );
	if ( inquiry && inquiry.showModal && window.fetch ) {
		var iform = inquiry.querySelector( '[data-inquiry-form]' );
		var ierrors = inquiry.querySelector( '[data-inquiry-errors]' );
		var isend = inquiry.querySelector( '[data-inquiry-send]' );
		var idone = inquiry.querySelector( '[data-inquiry-done]' );
		var sendLabel = isend.textContent;
		var showErrors = function ( list ) {
			ierrors.innerHTML = '';
			list.forEach( function ( text ) { var li = document.createElement( 'li' ); li.textContent = text; ierrors.appendChild( li ); } );
			ierrors.hidden = ! list.length;
		};
		document.querySelectorAll( '[data-inquiry-open], a[href="#zapitvane"]' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				inquiry.showModal();
			} );
		} );
		inquiry.querySelectorAll( '[data-inquiry-close]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { inquiry.close(); } );
		} );
		inquiry.addEventListener( 'click', function ( e ) { if ( e.target === inquiry ) { inquiry.close(); } } );
		iform.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			isend.disabled = true;
			isend.textContent = isend.dataset.busy;
			fetch( iform.getAttribute( 'action' ), { method: 'POST', body: new FormData( iform ), credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res.ok ) {
						showErrors( [] );
						Array.prototype.forEach.call( iform.children, function ( el ) { if ( el !== idone && ! el.matches( '[data-inquiry-close], input[type=hidden]' ) ) { el.hidden = true; } } );
						idone.hidden = false;
					} else {
						showErrors( res.errors || [] );
					}
				} )
				.catch( function () { showErrors( [ iform.dataset.failed || 'Error' ] ); } )
				.then( function () { isend.disabled = false; isend.textContent = sendLabel; } );
		} );
	}

	// "Copy" next to an address: shown only where the browser can copy.
	if ( navigator.clipboard ) {
		document.querySelectorAll( '[data-copy]' ).forEach( function ( button ) {
			var text = button.parentNode.querySelector( '[data-copy-text]' );
			if ( ! text ) { return; }
			var label = button.textContent;
			button.hidden = false;
			button.addEventListener( 'click', function () {
				navigator.clipboard.writeText( text.textContent.trim() ).then( function () {
					button.textContent = button.dataset.copied;
					setTimeout( function () { button.textContent = label; }, 2000 );
				} );
			} );
		} );
	}

	// Character counter for the designer note.
	document.querySelectorAll( '[data-rq-counter]' ).forEach( function ( ta ) {
		var out = ta.parentNode.querySelector( '[data-rq-count]' );
		if ( ! out ) { return; }
		var update = function () { out.textContent = String( ta.value.length ); };
		ta.addEventListener( 'input', update );
		update();
	} );
} )();

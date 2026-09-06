/**
 * Chunked uploader — progressive enhancement of every logo file input.
 *
 * Without JS the form posts the file the old way. With JS: on file choice the file is
 * sliced into chunks and posted to the REST API with a progress bar; on success a hidden
 * reklamo_file_token input is added and the file input is emptied so the final form post
 * stays tiny. Any failure falls back to the plain upload (file input re-enabled).
 */
( function () {
	'use strict';
	var cfg = window.reklamoUpload;
	if ( ! cfg || ! window.fetch || ! window.FormData || ! window.Blob ) { return; }

	function api( path, opts ) {
		opts = opts || {};
		opts.headers = Object.assign( { 'X-WP-Nonce': cfg.nonce }, opts.headers || {} );
		opts.credentials = 'same-origin';
		return fetch( cfg.restUrl + path, opts ).then( async function ( r ) {
			var j = null;
			try { j = await r.json(); } catch ( e ) { /* non-JSON error page */ }
			if ( ! r.ok ) {
				var err = new Error( ( j && j.message ) || ( cfg.i18n.failed + ' (HTTP ' + r.status + ')' ) );
				err.status = r.status;
				throw err;
			}
			return j;
		} );
	}

	async function postChunk( ticket, index, blob ) {
		var attempt = 0, lastErr;
		while ( attempt < 3 ) {
			try {
				var fd = new FormData();
				fd.append( 'ticket', ticket ); fd.append( 'index', String( index ) ); fd.append( 'chunk', blob, 'chunk.bin' );
				return await api( '/upload/chunk', { method: 'POST', body: fd } );
			} catch ( e ) {
				lastErr = e; attempt++;
				await new Promise( function ( res ) { setTimeout( res, 500 * Math.pow( 2, attempt ) ); } );
			}
		}
		throw lastErr;
	}

	function fmt( bytes ) {
		if ( bytes >= 1048576 ) { return ( bytes / 1048576 ).toFixed( 1 ) + ' MB'; }
		if ( bytes >= 1024 ) { return Math.round( bytes / 1024 ) + ' KB'; }
		return bytes + ' B';
	}

	document.querySelectorAll( 'input[type="file"][name="reklamo_logo"]' ).forEach( function ( input ) {
		var form = input.form;
		if ( ! form ) { return; }
		var field = input.closest( '.rq-field--file' ) || input.parentNode;
		var box = field.querySelector( '[data-rq-file]' );
		var submit = form.querySelector( 'button[type="submit"], input[type="submit"]' );

		// Progress + status UI (inside the file box when present, else after the input).
		var ui = document.createElement( 'div' );
		ui.className = 'rq-progress'; ui.hidden = true;
		ui.innerHTML = '<div class="rq-progress__bar"><span></span></div><small class="rq-progress__text"></small>';
		( box || field ).appendChild( ui );
		var bar = ui.querySelector( 'span' ), text = ui.querySelector( '.rq-progress__text' );

		// The server re-emits these after a failed validation so a finished upload is not lost.
		var tokenInput = form.querySelector( 'input[name="reklamo_file_token"]' );
		if ( ! tokenInput ) {
			tokenInput = document.createElement( 'input' );
			tokenInput.type = 'hidden'; tokenInput.name = 'reklamo_file_token'; tokenInput.value = '';
			form.appendChild( tokenInput );
		}
		var nameInput = form.querySelector( 'input[name="reklamo_file_name"]' );
		if ( ! nameInput ) {
			nameInput = document.createElement( 'input' );
			nameInput.type = 'hidden'; nameInput.name = 'reklamo_file_name'; nameInput.value = '';
			form.appendChild( nameInput );
		}

		var uploading = false, currentTicket = null;

		function detach() {
			// The final form post must not carry the file again: replace the input with an empty clone.
			var clone = input.cloneNode( true ); clone.value = '';
			clone.required = false;
			input.parentNode.replaceChild( clone, input );
			input = clone;
			input.addEventListener( 'change', function () { window.location.reload(); } ); // choosing another file: start over cleanly
		}

		function setBusy( on ) {
			uploading = on;
			if ( submit ) { submit.disabled = on; }
		}
		function show( pct, msg, err ) {
			ui.hidden = false;
			bar.style.width = pct + '%';
			text.textContent = msg;
			ui.classList.toggle( 'is-error', !! err );
			ui.classList.toggle( 'is-done', pct >= 100 && ! err );
		}

		input.addEventListener( 'change', async function () {
			var file = input.files && input.files[ 0 ];
			tokenInput.value = ''; nameInput.value = '';
			if ( ! file ) { ui.hidden = true; return; }
			if ( file.size > cfg.maxBytes ) {
				show( 0, cfg.i18n.tooLarge.replace( '%s', fmt( cfg.maxBytes ) ), true );
				input.value = ''; return;
			}
			if ( currentTicket ) { api( '/upload/' + currentTicket, { method: 'DELETE' } ).catch( function () {} ); currentTicket = null; }

			setBusy( true );
			show( 0, cfg.i18n.starting );
			try {
				var init = await api( '/upload/init', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( { filename: file.name, size: file.size } )
				} );
				currentTicket = init.ticket;
				var size = init.chunk_size, total = init.chunks;
				for ( var i = 0; i < total; i++ ) {
					await postChunk( init.ticket, i, file.slice( i * size, Math.min( file.size, ( i + 1 ) * size ) ) );
					var pct = Math.round( ( ( i + 1 ) / total ) * 95 );
					show( pct, cfg.i18n.uploading.replace( '%1$s', fmt( Math.min( file.size, ( i + 1 ) * size ) ) ).replace( '%2$s', fmt( file.size ) ) );
				}
				show( 97, cfg.i18n.checking );
				var done = await api( '/upload/complete', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( { ticket: init.ticket } )
				} );
				currentTicket = null;
				tokenInput.value = done.token; nameInput.value = done.name;
				detach();
				show( 100, cfg.i18n.done.replace( '%s', done.name + ' (' + fmt( done.size ) + ')' ) );
			} catch ( e ) {
				tokenInput.value = ''; nameInput.value = '';
				show( 0, e.message || cfg.i18n.failed, true );
				if ( currentTicket ) { api( '/upload/' + currentTicket, { method: 'DELETE' } ).catch( function () {} ); currentTicket = null; }
				// The server judged the FILE (wrong content, too large): clear it. Anything else —
				// stale nonce (403), rate limit (429), disk (507), 5xx, network — is not the file's
				// fault: keep it in the input and let the plain form post carry it.
				var verdict = e.status && [ 400, 413, 415, 422 ].indexOf( e.status ) !== -1;
				if ( verdict ) {
					input.value = '';
				} else {
					text.textContent += ' — ' + cfg.i18n.fallback;
				}
			} finally {
				setBusy( false );
			}
		} );

		form.addEventListener( 'submit', function ( e ) {
			if ( uploading ) { e.preventDefault(); }
		} );

		// Back on the page after a failed validation with the upload already done: show it as such.
		if ( tokenInput.value ) {
			detach();
			show( 100, cfg.i18n.done.replace( '%s', nameInput.value || '' ) );
		}
	} );
} )();

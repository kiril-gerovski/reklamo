/* Diagnostics probe: post growing random bodies and report what actually arrives. */
( function () {
	'use strict';
	var cfg = window.reklamoDiag || {};
	var btn = document.getElementById( 'reklamo-probe' );
	var out = document.getElementById( 'reklamo-probe-results' );
	var sum = document.getElementById( 'reklamo-probe-summary' );
	if ( ! btn || ! out ) { return; }

	function row( label, text, cls ) {
		var tr = document.createElement( 'tr' );
		tr.innerHTML = '<th style="width:40%">' + label + '</th><td class="' + cls + '">' + text + '</td>';
		out.appendChild( tr );
		return tr.lastChild;
	}

	btn.addEventListener( 'click', async function () {
		btn.disabled = true; out.innerHTML = ''; sum.textContent = '';
		var best = 0;
		for ( var i = 0; i < cfg.sizesMb.length; i++ ) {
			var mb = cfg.sizesMb[ i ];
			var cell = row( mb + ' MB', cfg.i18n.running, '' );
			try {
				var blob = new Blob( [ new Uint8Array( mb * 1024 * 1024 ) ] );
				var fd = new FormData(); fd.append( 'blob', blob, 'probe.bin' );
				var r = await fetch( cfg.probeUrl, { method: 'POST', headers: { 'X-WP-Nonce': cfg.nonce }, body: fd, credentials: 'same-origin' } );
				var j = r.ok ? await r.json() : null;
				var ok = j && j.received === mb * 1024 * 1024;
				cell.textContent = ok ? cfg.i18n.ok : cfg.i18n.fail + ' (HTTP ' + r.status + ( j && j.error ? ', PHP upload error ' + j.error : '' ) + ')';
				cell.style.color = ok ? '#2f7a3b' : '#a3222b';
				if ( ok ) { best = mb; } else { break; }
			} catch ( e ) {
				cell.textContent = cfg.i18n.fail + ' (' + e.message + ')'; cell.style.color = '#a3222b'; break;
			}
		}
		sum.textContent = cfg.i18n.summary.replace( '%s', best ? best + ' MB' : '< 1 MB' );
		btn.disabled = false;
	} );
} )();

/* Keeps the orders news count fresh through the WordPress Heartbeat (about once a minute). */
( function ( $ ) {
	'use strict';
	var title = document.title;

	function show( n ) {
		document.querySelectorAll( '.reklamo-news-count' ).forEach( function ( el ) {
			el.textContent = n;
		} );
		document.querySelectorAll( '.reklamo-news-badge' ).forEach( function ( el ) {
			el.className = el.className.replace( /\bcount-\d+\b/, 'count-' + n );
		} );
		var node = document.getElementById( 'wp-admin-bar-reklamo-news' );
		if ( node ) {
			node.classList.toggle( 'reklamo-news-on', n > 0 );
		}
		document.title = ( n > 0 ? '(' + n + ') ' : '' ) + title;
	}

	$( document ).on( 'heartbeat-send', function ( e, data ) {
		data.reklamo_news = 1;
	} );
	$( document ).on( 'heartbeat-tick', function ( e, data ) {
		if ( undefined !== data.reklamo_news ) {
			show( parseInt( data.reklamo_news, 10 ) || 0 );
		}
	} );
	var first = document.querySelector( '.reklamo-news-count' );
	show( first ? parseInt( first.textContent, 10 ) || 0 : 0 );
}( jQuery ) );

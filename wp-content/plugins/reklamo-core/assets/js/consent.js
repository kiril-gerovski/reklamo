/* Consent banner and Google Ads conversions; nothing reaches Google before „Приемам“. */
( function () {
	var cfg = window.reklamoAds;
	if ( ! cfg || ! cfg.id || ! cfg.label ) {
		return;
	}
	var KEY = 'reklamo-consent';
	var loaded = false;

	function read() {
		try {
			var v = JSON.parse( window.localStorage.getItem( KEY ) || 'null' );
			if ( v && ( 'granted' === v.ads || 'denied' === v.ads ) && Date.now() - v.at < cfg.days * 864e5 ) {
				return v.ads;
			}
		} catch ( e ) {}
		return '';
	}

	function save( choice ) {
		try {
			window.localStorage.setItem( KEY, JSON.stringify( { ads: choice, at: Date.now() } ) );
		} catch ( e ) {}
	}

	function gtag() {
		window.dataLayer.push( arguments );
	}

	function load() {
		if ( loaded ) {
			return;
		}
		loaded = true;
		window.dataLayer = window.dataLayer || [];
		window.gtag = gtag;
		gtag( 'consent', 'default', { ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'denied' } );
		gtag( 'js', new Date() );
		gtag( 'config', cfg.id );
		var s = document.createElement( 'script' );
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent( cfg.id );
		document.head.appendChild( s );
	}

	// Google's own cookies go with a withdrawn consent, on this host and on the bare domain.
	function forget() {
		var host = location.hostname.replace( /^www\./, '' );
		document.cookie.split( ';' ).forEach( function ( c ) {
			var name = c.split( '=' )[ 0 ].trim();
			if ( /^_gcl_|^_ga/.test( name ) ) {
				[ '', '; domain=' + host, '; domain=.' + host ].forEach( function ( d ) {
					document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + d;
				} );
			}
		} );
	}

	function lead( order ) {
		if ( 'granted' !== read() ) {
			return;
		}
		load();
		var params = { send_to: cfg.id + '/' + cfg.label };
		if ( order ) {
			params.transaction_id = String( order );
		}
		gtag( 'event', 'conversion', params );
	}

	if ( 'lead' === cfg.mode ) {
		lead( cfg.order );
		return;
	}

	if ( 'granted' === read() ) {
		load();
	}
	document.addEventListener( 'reklamo:lead', function () {
		lead( '' );
	} );

	var banner = document.querySelector( '[data-consent]' );
	if ( ! banner ) {
		return;
	}
	function show() {
		banner.hidden = false;
	}
	if ( ! read() ) {
		show();
	}
	banner.querySelectorAll( '[data-consent-choice]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var choice = b.getAttribute( 'data-consent-choice' );
			var was = read();
			save( choice );
			banner.hidden = true;
			if ( 'granted' === choice ) {
				load();
			} else if ( loaded || 'granted' === was ) {
				forget();
				location.reload();
			}
		} );
	} );
	document.querySelectorAll( '[data-consent-open]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			show();
			var first = banner.querySelector( 'button' );
			if ( first ) {
				first.focus();
			}
		} );
	} );
}() );

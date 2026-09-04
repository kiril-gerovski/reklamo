/**
 * Registers the "request without payment" method with the block checkout.
 * Plain script, no bundler: reads globals WooCommerce exposes.
 */
( function ( wc, wp ) {
	if ( ! wc || ! wc.wcBlocksRegistry || ! wc.wcSettings || ! wp || ! wp.element ) {
		return;
	}
	var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
	var el = wp.element.createElement;
	var decode = wp.htmlEntities ? wp.htmlEntities.decodeEntities : function ( s ) { return s; };
	var name = 'reklamo_request';

	// Current API is getPaymentMethodData(); fall back to the raw registry for older builds.
	var data = null;
	if ( typeof wc.wcSettings.getPaymentMethodData === 'function' ) {
		data = wc.wcSettings.getPaymentMethodData( name, null );
	}
	if ( ! data ) {
		var all = wc.wcSettings.getSetting( 'paymentMethodData', {} );
		data = all && all[ name ] ? all[ name ] : {};
	}

	var label = decode( data.title || 'Request without payment' );
	var Content = function () {
		return el( 'div', { className: 'reklamo-nopay' }, decode( data.description || '' ) );
	};

	registerPaymentMethod( {
		name: name,
		label: el( 'span', null, label ),
		ariaLabel: label,
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () { return true; },
		supports: { features: data.supports && data.supports.length ? data.supports : [ 'products' ] }
	} );
} )( window.wc, window.wp );

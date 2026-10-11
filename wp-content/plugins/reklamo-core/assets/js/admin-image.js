/*
 * Media Library picker for Reklamo_Admin_Image fields: the hidden input holds the attachment ID,
 * or, for a gallery field (data-multiple), a comma-separated list of IDs. Clicks are handled on
 * the document, so fields WooCommerce loads later (the variations panel) work too.
 */
( function () {
	const frames = new WeakMap();

	function thumb( a ) {
		const img = document.createElement( 'img' );
		img.src = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
		img.width = 64;
		img.height = 64;
		img.alt = '';
		img.style.marginRight = '6px';
		return img;
	}

	function changed( input ) {
		// The variations panel saves only rows it has seen change.
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		if ( window.jQuery ) {
			window.jQuery( input ).closest( '.woocommerce_variation' ).addClass( 'variation-needs-update' );
			window.jQuery( 'button.cancel-variation-changes, button.save-variation-changes' ).prop( 'disabled', false );
		}
	}

	document.addEventListener( 'click', ( e ) => {
		const pick = e.target.closest( '[data-reklamo-image-pick]' );
		const clear = e.target.closest( '[data-reklamo-image-clear]' );
		if ( ! pick && ! clear ) {
			return;
		}
		e.preventDefault();
		const field = ( pick || clear ).closest( '[data-reklamo-image]' );
		const input = field.querySelector( 'input[type="hidden"]' );
		const preview = field.querySelector( '[data-reklamo-image-preview]' );
		const clearButton = field.querySelector( '[data-reklamo-image-clear]' );
		const multiple = field.hasAttribute( 'data-multiple' );

		if ( clear ) {
			input.value = '';
			preview.replaceChildren();
			clearButton.hidden = true;
			changed( input );
			return;
		}

		let frame = frames.get( field );
		if ( ! frame ) {
			frame = wp.media( { library: { type: 'image' }, multiple: multiple ? 'add' : false } );
			frames.set( field, frame );
		}
		frame.off( 'select' ).on( 'select', () => {
			const chosen = frame.state().get( 'selection' ).toJSON();
			input.value = chosen.map( ( a ) => a.id ).join( ',' );
			preview.replaceChildren( ...chosen.map( thumb ) );
			clearButton.hidden = ! chosen.length;
			changed( input );
		} );
		frame.open();
	} );
}() );

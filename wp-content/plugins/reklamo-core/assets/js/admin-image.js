/* Media Library picker for Reklamo_Admin_Image fields: the hidden input holds the attachment ID. */
( function () {
	function setup( field ) {
		if ( field.dataset.ready ) {
			return;
		}
		field.dataset.ready = '1';
		const input = field.querySelector( 'input[type="hidden"]' );
		const preview = field.querySelector( '[data-reklamo-image-preview]' );
		const clear = field.querySelector( '[data-reklamo-image-clear]' );
		let frame;

		field.querySelector( '[data-reklamo-image-pick]' ).addEventListener( 'click', () => {
			frame = frame || wp.media( { library: { type: 'image' }, multiple: false } );
			frame.off( 'select' ).on( 'select', () => {
				const a = frame.state().get( 'selection' ).first().toJSON();
				const img = document.createElement( 'img' );
				img.src = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
				img.width = 96;
				img.height = 96;
				img.alt = '';
				input.value = a.id;
				preview.replaceChildren( img );
				clear.hidden = false;
			} );
			frame.open();
		} );

		clear.addEventListener( 'click', () => {
			input.value = '';
			preview.replaceChildren();
			clear.hidden = true;
		} );
	}

	document.querySelectorAll( '[data-reklamo-image]' ).forEach( setup );
}() );

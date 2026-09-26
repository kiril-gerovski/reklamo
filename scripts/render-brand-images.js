// Builds the two brand images the site cannot take straight from the logo kit:
//   assets/img/share.jpg  1200×630 link-preview card, shown when a page has no picture of its own
//   assets/img/icon.png   512×512 square site icon, which WordPress crops for the browser tab
// JPEG for the card because its soft gradient compresses badly as PNG; PNG for the icon to keep
// the transparent background. Run after changing logo.png or mark.png:
//   node scripts/render-brand-images.js
const { chromium } = require( '../tests/e2e/node_modules/playwright' );
const fs = require( 'fs' );
const path = require( 'path' );

const theme = path.join( __dirname, '../wp-content/themes/reklamo' );
const tagline = 'Промо пакети с вашето лого';
const face = ( weight, subset ) =>
	`@font-face{font-family:Inter;font-style:normal;font-weight:${ weight };src:url('fonts/Inter-${ weight }-${ subset }.woff2') format('woff2')}`;

const html = path.join( theme, 'assets/.brand.html' );
fs.writeFileSync(
	html,
	`<style>
	 ${ [ 400, 500, 600 ].flatMap( ( w ) => [ face( w, 'latin' ), face( w, 'cyrillic' ) ] ).join( '' ) }
	 html,body{margin:0}
	 .card{width:1200px;height:630px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:34px;
	       background:radial-gradient(62% 62% at 50% 46%,#f1e8d8 0%,#faf8f4 100%)}
	 .card img{display:block;width:620px;height:auto}
	 p{margin:0;font:400 27px/1 Inter,system-ui,sans-serif;color:#555}
	 .rule{width:96px;height:3px;border-radius:2px;background:#b8892b}
	 .icon{width:512px;height:512px;display:flex;align-items:center;justify-content:center}
	 .icon img{display:block;height:430px;width:auto}
	</style>
	<div class="card"><img src="img/logo.png"><p>${ tagline }</p><div class="rule"></div></div>
	<div class="icon"><img src="img/mark.png"></div>`
);

( async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage( { viewport: { width: 1200, height: 1200 } } );
	await page.goto( 'file://' + html );
	await page.evaluate( () => document.fonts.ready );
	await page.waitForFunction( () => [ ...document.images ].every( ( i ) => i.complete && i.naturalWidth > 0 ) );

	const share = path.join( theme, 'assets/img/share.jpg' );
	await page.locator( '.card' ).screenshot( { path: share, type: 'jpeg', quality: 90 } );
	const icon = path.join( theme, 'assets/img/icon.png' );
	await page.locator( '.icon' ).screenshot( { path: icon, omitBackground: true } );

	await browser.close();
	fs.unlinkSync( html );
	console.log( `${ path.relative( process.cwd(), share ) } 1200×630, ${ path.relative( process.cwd(), icon ) } 512×512` );
} )();

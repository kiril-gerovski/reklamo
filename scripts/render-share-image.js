// Render themes/reklamo/assets/img/share.svg to share.jpg, the link-preview image used when a
// page has no picture of its own. PNG because Facebook and Viber handle WebP previews poorly.
// Run after editing the SVG:  node scripts/render-share-image.js
const { chromium } = require( '../tests/e2e/node_modules/playwright' );
const fs = require( 'fs' );
const path = require( 'path' );

const theme = path.join( __dirname, '../wp-content/themes/reklamo' );
const svg = fs.readFileSync( path.join( theme, 'assets/img/share.svg' ), 'utf8' );
const face = ( weight, subset ) =>
	`@font-face{font-family:Inter;font-style:normal;font-weight:${ weight };src:url('file://${ theme }/assets/fonts/Inter-${ weight }-${ subset }.woff2') format('woff2')}`;

( async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage( { viewport: { width: 1200, height: 630 } } );
	await page.setContent(
		`<style>html,body{margin:0;padding:0}svg{display:block}
		${ [ 400, 500, 600 ].flatMap( ( w ) => [ face( w, 'latin' ), face( w, 'cyrillic' ) ] ).join( '' ) }</style>${ svg }`
	);
	await page.evaluate( () => document.fonts.ready );
	const out = path.join( theme, 'assets/img/share.jpg' );
	await page.screenshot( { path: out, type: 'jpeg', quality: 90, clip: { x: 0, y: 0, width: 1200, height: 630 } } );
	await browser.close();
	console.log( `share.svg → ${ path.relative( process.cwd(), out ) } at 1200×630` );
} )();

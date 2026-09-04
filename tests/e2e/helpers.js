// @ts-check
const path = require( 'path' );

const MAILPIT = process.env.MAILPIT_URL || 'http://localhost:8025';
const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS || 'reklamo-dev';
const FIXTURES = path.resolve( __dirname, '..', 'fixtures' );

async function mailpitCount() {
	const r = await fetch( `${ MAILPIT }/api/v1/messages?limit=1` );
	return ( await r.json() ).total;
}

/** Newest message whose subject contains `needle`; returns { subject, to, text, html }. */
async function mailpitFind( needle ) {
	const list = await ( await fetch( `${ MAILPIT }/api/v1/messages?limit=20` ) ).json();
	const m = list.messages.find( ( x ) => x.Subject.includes( needle ) );
	if ( ! m ) {
		throw new Error( `no email with subject containing "${ needle }"` );
	}
	const full = await ( await fetch( `${ MAILPIT }/api/v1/message/${ m.ID }` ) ).json();
	return { subject: full.Subject, to: full.To[ 0 ].Address, text: full.Text, html: full.HTML };
}

async function adminLogin( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', ADMIN_USER );
	await page.fill( '#user_pass', ADMIN_PASS );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

module.exports = { MAILPIT, FIXTURES, mailpitCount, mailpitFind, adminLogin };

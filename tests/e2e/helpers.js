// @ts-check
const path = require( 'path' );
const { expect } = require( '@playwright/test' );

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
	// Already logged in? wp-admin loads instead of redirecting to wp-login.
	const probe = await page.goto( '/wp-admin/', { waitUntil: 'commit' } );
	if ( probe && ! page.url().includes( 'wp-login.php' ) ) {
		return;
	}
	for ( let attempt = 1; attempt <= 3; attempt++ ) {
		// Wait for `load`: the login page's own scripts (password toggle) re-wire the fields.
		await page.goto( '/wp-login.php', { waitUntil: 'load' } );
		// wp-login.php focuses AND selects #user_login 200 ms after load (wp_attempt_focus); a fill
		// racing that timer lands the password in the username box. Let it fire first.
		await page.waitForTimeout( 500 );
		await page.fill( '#user_login', ADMIN_USER );
		await page.fill( '#user_pass', ADMIN_PASS );
		await expect( page.locator( '#user_login' ) ).toHaveValue( ADMIN_USER );
		await page.click( '#wp-submit' );
		try {
			// First admin loads after a fresh install are slow (WooCommerce onboarding jobs); don't wait for `load`.
			await page.waitForURL( /wp-admin/, { waitUntil: 'commit', timeout: 60_000 } );
			return;
		} catch ( e ) {
			if ( attempt === 3 ) {
				throw e;
			}
		}
	}
}

module.exports = { MAILPIT, FIXTURES, mailpitCount, mailpitFind, adminLogin };

// @ts-check
// Visits counted without cookies: a visitor's arrival shows under its source, robots and the logged-in
// owner do not count.
const { test, expect } = require( '@playwright/test' );
const { adminLogin } = require( './helpers' );

const BROWSER_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

async function visit( page, path, referer ) {
	const hit = page.waitForResponse( ( r ) => r.url().includes( '/reklamo/v1/hit' ) );
	await page.goto( path, { referer } );
	return hit;
}

test( 'a visit is counted under its source; robots and the logged-in owner are not', async ( { browser, page } ) => {
	const run = Date.now();
	const counted = `e2e-${ run }.example`;
	const robot = `robot-${ run }.example`;
	const owner = `owner-${ run }.example`;

	const person = await browser.newContext( { userAgent: BROWSER_UA, baseURL: test.info().project.use.baseURL } );
	const visitor = await person.newPage();
	expect( ( await visit( visitor, '/promo-paketi/', `http://${ counted }/` ) ).status() ).toBe( 204 );
	// A move inside the site is a page view, not a new visit.
	await visit( visitor, '/kontakti/', `${ test.info().project.use.baseURL }/promo-paketi/` );
	expect( await person.cookies() ).toEqual( [] );
	await person.close();

	// The default headless browser announces itself as HeadlessChrome.
	await visit( page, '/', `http://${ robot }/` );

	await adminLogin( page );
	await page.goto( '/', { referer: `http://${ owner }/` } );
	await expect( page.locator( 'script', { hasText: 'reklamo/v1/hit' } ) ).toHaveCount( 0 );

	await page.goto( '/wp-admin/admin.php?page=reklamo-stats&period=7' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Посещения' );
	const sources = page.locator( '[data-rk-sources] tbody tr' );
	await expect( sources.filter( { hasText: counted } ).locator( 'td' ).nth( 1 ) ).toHaveText( '1' );
	await expect( sources.filter( { hasText: robot } ) ).toHaveCount( 0 );
	await expect( sources.filter( { hasText: owner } ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-rk-pages]' ) ).toContainText( 'Промо пакети' );
	await expect( page.locator( '[data-rk-pages]' ) ).toContainText( 'Контакти' );
} );

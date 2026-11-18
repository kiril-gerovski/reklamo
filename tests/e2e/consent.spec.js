// @ts-check
// Google Ads behind the cookie banner: nothing from Google before „Приемам“, a declined or withdrawn
// choice loads nothing, and a sent inquiry or request counts as a conversion without the order
// page's address reaching Google. Google's servers are never contacted: gtag.js is a stub here.
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const { test, expect } = require( '@playwright/test' );

const ID = 'AW-1234567890';
const LABEL = 'E2e-Label_1';

function wp( ...args ) {
	return execFileSync( path.resolve( __dirname, '../../scripts/wp' ), args, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ).trim();
}

/** A browser that records every request to Google and answers gtag.js with an empty script. */
async function visitor( browser, consent ) {
	const ctx = await browser.newContext( { baseURL: test.info().project.use.baseURL } );
	const google = [];
	await ctx.route( /google|doubleclick/, ( route ) => {
		google.push( route.request().url() );
		return route.request().url().includes( 'googletagmanager.com/gtag/js' )
			? route.fulfill( { contentType: 'text/javascript', body: '' } )
			: route.abort();
	} );
	if ( consent ) {
		await ctx.addInitScript( ( c ) => window.localStorage.setItem( 'reklamo-consent', JSON.stringify( { ads: c, at: Date.now() } ) ), consent );
	}
	return { ctx, page: await ctx.newPage(), google };
}

const dataLayer = ( page ) => page.evaluate( () => ( window.dataLayer || [] ).map( ( a ) => Array.from( a ) ) );
const conversions = async ( page ) => ( await dataLayer( page ) ).filter( ( a ) => 'event' === a[ 0 ] && 'conversion' === a[ 1 ] ).map( ( a ) => a[ 2 ] );

test.describe( 'with Google Ads set up', () => {
	test.beforeAll( () => {
		wp( 'option', 'update', 'reklamo_gads_id', ID );
		wp( 'option', 'update', 'reklamo_gads_label', LABEL );
	} );
	test.afterAll( () => {
		wp( 'option', 'delete', 'reklamo_gads_id' );
		wp( 'option', 'delete', 'reklamo_gads_label' );
	} );

	test( 'the banner asks first; „Отказвам“ loads nothing and is remembered', async ( { browser } ) => {
		const { ctx, page, google } = await visitor( browser );
		await page.goto( '/' );
		const banner = page.locator( '[data-consent]' );
		await expect( banner ).toBeVisible();
		await expect( banner ).toContainText( 'Използваме бисквитки на Google за реклама.' );
		await expect( banner.getByRole( 'link', { name: 'Политика за поверителност' } ) ).toHaveAttribute( 'href', /politika-za-poveritelnost\/#biskvitki$/ );
		await banner.getByRole( 'button', { name: 'Отказвам' } ).click();
		await expect( banner ).toBeHidden();
		await page.goto( '/promo-paketi/' );
		await page.waitForLoadState( 'networkidle' );
		await expect( banner ).toBeHidden();
		expect( google, 'nothing may reach Google without consent' ).toEqual( [] );
		expect( await ctx.cookies() ).toEqual( [] );
		await ctx.close();
	} );

	test( '„Приемам“ loads the tag; „Бисквитки“ in the footer withdraws it', async ( { browser } ) => {
		const { ctx, page, google } = await visitor( browser );
		await page.goto( '/' );
		await page.waitForLoadState( 'networkidle' );
		expect( google ).toEqual( [] );
		await page.locator( '[data-consent]' ).getByRole( 'button', { name: 'Приемам' } ).click();
		await expect.poll( () => google.some( ( u ) => u.includes( `gtag/js?id=${ ID }` ) ) ).toBe( true );
		expect( await dataLayer( page ) ).toContainEqual( [ 'config', ID ] );

		await page.reload();
		await expect( page.locator( '[data-consent]' ) ).toBeHidden();
		await page.locator( '.site-footer [data-consent-open]' ).click();
		await expect( page.locator( '[data-consent]' ) ).toBeVisible();
		await Promise.all( [ page.waitForEvent( 'load' ), page.locator( '[data-consent]' ).getByRole( 'button', { name: 'Отказвам' } ).click() ] );
		google.length = 0;
		await page.goto( '/kontakti/' );
		await page.waitForLoadState( 'networkidle' );
		expect( google ).toEqual( [] );
		await ctx.close();
	} );

	test( 'the Privacy Policy describes Google Ads while it is on', async ( { page } ) => {
		await page.goto( '/politika-za-poveritelnost/' );
		const section = page.locator( '#biskvitki ~ p' );
		await expect( section.first() ).toContainText( 'локално хранилище' );
		await expect( page.locator( '.entry-content, main' ).first() ).toContainText( 'Google Ireland Limited' );
		await expect( page.getByRole( 'button', { name: 'Промяна на избора за бисквитки' } ) ).toBeVisible();
	} );

	test( 'a sent inquiry counts once consent is given, never without it', async ( { browser } ) => {
		for ( const consent of [ 'denied', 'granted' ] ) {
			const { ctx, page, google } = await visitor( browser, consent );
			await page.goto( '/promo-paketi/' );
			await page.locator( '.bulk-offer__cta' ).click();
			const dialog = page.locator( '[data-inquiry]' );
			await dialog.getByLabel( 'Име / фирма' ).fill( `Е2Е Реклама ${ consent }` );
			await dialog.getByLabel( 'Имейл' ).fill( 'ads@example.com' );
			await dialog.getByLabel( 'Телефон' ).fill( '0888 123 456' );
			await dialog.getByLabel( 'Продукт или пакет' ).fill( 'Бизнес комплект Magnet' );
			await dialog.getByLabel( 'Количество' ).fill( '100 бр.' );
			await dialog.locator( 'input[name="inq_consent"]' ).check();
			await dialog.getByRole( 'button', { name: 'Изпрати запитването' } ).click();
			await expect( dialog.locator( '[data-inquiry-done]' ) ).toBeVisible();
			if ( 'granted' === consent ) {
				expect( await conversions( page ) ).toEqual( [ { send_to: `${ ID }/${ LABEL }` } ] );
			} else {
				expect( await conversions( page ) ).toEqual( [] );
				expect( google ).toEqual( [] );
			}
			await ctx.close();
		}
	} );

	test( 'a sent request counts from a frame at a plain address; the order page loads nothing from Google', async ( { browser } ) => {
		const made = JSON.parse( wp( 'eval', `
			$o = wc_create_order();
			$o->set_billing_email( 'ads-order@example.com' );
			$o->save();
			echo wp_json_encode( array( 'id' => $o->get_id(), 'number' => $o->get_order_number(), 'url' => Reklamo_Tracking::url( $o ) ) );
		` ) );
		try {
			const { ctx, page, google } = await visitor( browser, 'granted' );
			const frames = [];
			page.on( 'request', ( r ) => r.url().includes( 'reklamo_lead=' ) && frames.push( r ) );
			await page.goto( `${ made.url }&new=1` );
			await page.waitForLoadState( 'networkidle' );
			expect( frames.map( ( r ) => new URL( r.url() ).search ) ).toEqual( [ `?reklamo_lead=${ made.number }` ] );
			expect( await frames[ 0 ].headerValue( 'referer' ), 'the order page address stays with the customer' ).toBeNull();
			expect( google.every( ( u ) => ! u.includes( 'moyata-porachka' ) && ! u.includes( 'k=' ) ) ).toBe( true );
			const frame = page.frames().find( ( f ) => f.url().includes( 'reklamo_lead=' ) );
			expect( await frame.evaluate( () => window.dataLayer.map( ( a ) => Array.from( a ) ).filter( ( a ) => 'conversion' === a[ 1 ] ).map( ( a ) => a[ 2 ] ) ) )
				.toEqual( [ { send_to: `${ ID }/${ LABEL }`, transaction_id: String( made.number ) } ] );
			expect( await page.evaluate( () => typeof window.gtag ) ).toBe( 'undefined' );

			// Without „new“ (a later visit) there is no frame.
			frames.length = 0;
			await page.goto( made.url );
			await page.waitForLoadState( 'networkidle' );
			expect( frames ).toEqual( [] );
			await ctx.close();
		} finally {
			wp( 'eval', `wc_get_order( ${ made.id } )->delete( true );` );
		}
	} );
} );

test( 'without both codes there is no banner, no tag and no lead page', async ( { page } ) => {
	wp( 'option', 'update', 'reklamo_gads_id', ID );
	try {
		await page.goto( '/' );
		await expect( page.locator( '[data-consent]' ) ).toHaveCount( 0 );
		await expect( page.locator( 'script[src*="consent.js"]' ) ).toHaveCount( 0 );
		expect( ( await page.goto( '/?reklamo_lead=1' ) ).status() ).toBe( 404 );
		await page.goto( '/politika-za-poveritelnost/' );
		await expect( page.locator( '#biskvitki ~ p' ).first() ).toContainText( 'Затова няма банер за бисквитки' );
	} finally {
		wp( 'option', 'delete', 'reklamo_gads_id' );
	}
} );

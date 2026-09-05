// @ts-check
// The walking skeleton, end to end: product → logo upload → no-payment checkout →
// admin sends mockup → customer approves via one-time link → replay is refused.
const { test, expect, request } = require( '@playwright/test' );
const { FIXTURES, mailpitFind, adminLogin } = require( './helpers' );

test.describe.configure( { mode: 'serial' } );

let orderId = '';
let approvalUrl = '';

test( 'customer places a request with a logo and no payment', async ( { page } ) => {
	// Product page leads to the request page — no add-to-cart, no checkout.
	await page.goto( '/product/red-business-pack/' );
	await page.getByRole( 'link', { name: /Избери този пакет/ } ).click();
	await page.waitForURL( /\/kachi-logo\/\?paket=red-business-pack/ );

	await expect( page.locator( 'h1' ) ).toHaveText( 'Качи лого и визуализирай' );
	await expect( page.locator( '.package-summary__name' ) ).toHaveText( 'Red Business Pack' );
	await expect( page.locator( '.rq-nopay' ) ).toContainText( 'Не се извършва плащане' );

	await page.setInputFiles( 'input[name="reklamo_logo"]', `${ FIXTURES }/logo.ai` );
	await expect( page.locator( '[data-rq-file]' ) ).toBeVisible();
	await expect( page.locator( '[data-rq-file-name]' ) ).toHaveText( 'logo.ai' );
	await page.fill( 'textarea[name="reklamo_note"]', 'E2E: златно лого, центрирано.' );
	await page.fill( 'input[name="rq_name"]', 'Е2Е Тест' );
	await page.fill( 'input[name="rq_email"]', 'e2e@example.com' );
	await page.check( 'input[name="rq_consent"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализация/ } ).click();

	await page.waitForURL( /order-received\/(\d+)/ );
	orderId = page.url().match( /order-received\/(\d+)/ )[ 1 ];
	await expect( page.locator( 'body' ) ).toContainText( 'Заявката Ви е получена' );

	const mail = await mailpitFind( `Получихме Вашата заявка ${ orderId }` );
	expect( mail.to ).toBe( 'e2e@example.com' );
	expect( mail.text ).toContain( 'не се извършва плащане' );
} );

test( 'request form validation: missing consent and file are refused, values are kept', async ( { page } ) => {
	await page.goto( '/kachi-logo/?paket=red-business-pack' );
	await page.fill( 'input[name="rq_name"]', 'Без файл' );
	await page.fill( 'input[name="rq_email"]', 'nofile@example.com' );
	// Bypass browser-side "required" to exercise the server-side validation.
	await page.evaluate( () => document.querySelector( 'form.rq-form' ).setAttribute( 'novalidate', '' ) );
	await page.evaluate( () => document.querySelectorAll( 'form.rq-form [required]' ).forEach( ( el ) => el.removeAttribute( 'required' ) ) );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализация/ } ).click();
	await page.waitForURL( /rq=/ );
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'Общите условия' );
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'файл с Вашето лого' );
	await expect( page.locator( 'input[name="rq_name"]' ) ).toHaveValue( 'Без файл' );
} );

test( 'homepage quick-start form creates a request too', async ( { page } ) => {
	await page.goto( '/' );
	const form = page.locator( '.rq-form--compact' );
	await form.locator( 'select[name="product_id"]' ).selectOption( { index: 2 } );
	await form.locator( 'input[name="reklamo_logo"]' ).setInputFiles( `${ FIXTURES }/logo.png` );
	await form.locator( 'input[name="rq_name"]' ).fill( 'Бърз Старт' );
	await form.locator( 'input[name="rq_email"]' ).fill( 'quick@example.com' );
	await form.locator( 'input[name="rq_consent"]' ).check();
	await form.getByRole( 'button', { name: /Изпрати за визуализация/ } ).click();
	await page.waitForURL( /order-received\/(\d+)/ );
	await expect( page.locator( 'body' ) ).toContainText( 'Заявката Ви е получена' );
} );

test( 'admin sees the logo and sends a mockup', async ( { page } ) => {
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );

	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-received' );
	await expect( page.locator( '#reklamo_mockup' ) ).toContainText( 'logo.ai' );
	// The note is line-item meta, shown with the order items — not in the mockup box.
	await expect( page.locator( '#woocommerce-order-items' ) ).toContainText( 'E2E: златно лого' );

	await page.setInputFiles( 'input[name="reklamo_mockup"]', `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	await expect( page.locator( '.notice-success' ) ).toContainText( 'Визуализация №1 е изпратена' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-mockup-sent' );

	const mail = await mailpitFind( `Визуализацията за поръчка ${ orderId }` );
	const m = mail.text.match( /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ );
	expect( m, 'approval link in email' ).toBeTruthy();
	approvalUrl = m[ 0 ];
} );

test( 'approval link: GET is idempotent, POST approves once, replay is refused', async ( { page } ) => {
	// Email scanners prefetch links: two anonymous GETs must change nothing.
	const anon = await request.newContext();
	for ( let i = 0; i < 2; i++ ) {
		const r = await anon.get( approvalUrl );
		expect( r.status() ).toBe( 200 );
		expect( await r.text() ).toContain( 'Визуализацията Ви е готова' );
		expect( r.headers()[ 'x-robots-tag' ] ).toContain( 'noindex' );
		expect( r.headers()[ 'referrer-policy' ] ).toBe( 'no-referrer' );
	}
	// Wrong secret: 404, never a hint.
	const bad = await anon.get( approvalUrl.replace( /k=.*/, 'k=' + 'A'.repeat( 43 ) ) );
	expect( bad.status() ).toBe( 404 );

	// The customer approves in a browser (logged out).
	await page.context().clearCookies();
	await page.goto( approvalUrl );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Визуализацията Ви е готова' );
	await expect( page.locator( '.preview img' ) ).toBeVisible();
	await page.click( 'button[name="decision"][value="approve"]' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Одобрено — благодарим!' );

	// Replay: the same link now only reports the outcome, and cannot approve again.
	await page.goto( approvalUrl );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Тази визуализация вече е обработена' );
	await expect( page.locator( 'button[name="decision"]' ) ).toHaveCount( 0 );
} );

test( 'order ended in the approved status', async ( { page } ) => {
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-approved' );
	await expect( page.locator( '#reklamo_mockup' ) ).toContainText( '— одобрена' );
} );

// @ts-check
// The walking skeleton, end to end: product → logo upload → no-payment checkout →
// admin sends mockup → customer approves via one-time link → replay is refused.
const { test, expect, request } = require( '@playwright/test' );
const { FIXTURES, mailpitFind, adminLogin } = require( './helpers' );

test.describe.configure( { mode: 'serial' } );

let orderId = '';
let approvalUrl = '';

test( 'customer places a request with a logo and no payment', async ( { page } ) => {
	await page.goto( '/product/red-business-pack/' );
	await expect( page.locator( '.reklamo-nopay-hint' ) ).toContainText( 'Не се извършва плащане' );

	await page.setInputFiles( '#reklamo_logo', `${ FIXTURES }/logo.ai` );
	await page.fill( '#reklamo_note', 'E2E: златно лого, центрирано.' );
	await page.click( 'button[name="add-to-cart"]' );

	// Straight to the block checkout with our notice, our gateway, the design's button label.
	await page.waitForURL( /\/porachka\// );
	await expect( page.locator( '.reklamo-nopay-notice' ) ).toBeVisible();
	await expect( page.getByRole( 'radio', { name: 'Заявка без плащане' } ) ).toBeChecked();

	await page.getByRole( 'textbox', { name: 'Имейл адрес' } ).fill( 'e2e@example.com' );
	await page.getByRole( 'textbox', { name: 'Име', exact: true } ).fill( 'Е2Е' );
	await page.getByRole( 'textbox', { name: 'Фамилия' } ).fill( 'Тест' );
	await page.getByRole( 'textbox', { name: 'Адрес', exact: true } ).fill( 'ул. Тестова 1' );
	await page.getByRole( 'textbox', { name: 'Град' } ).fill( 'София' );
	await page.getByRole( 'textbox', { name: 'Пощенски код' } ).fill( '1000' );
	await page.getByRole( 'button', { name: 'Изпрати и заяви визуализация' } ).click();

	await page.waitForURL( /order-received\/(\d+)/ );
	orderId = page.url().match( /order-received\/(\d+)/ )[ 1 ];
	await expect( page.locator( 'body' ) ).toContainText( 'Заявката Ви е получена' );

	const mail = await mailpitFind( `Получихме Вашата заявка ${ orderId }` );
	expect( mail.to ).toBe( 'e2e@example.com' );
	expect( mail.text ).toContain( 'не се извършва плащане' );
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

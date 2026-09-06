// @ts-check
// The walking skeleton, end to end: product → logo upload → no-payment checkout →
// admin sends mockup → customer approves via one-time link → replay is refused.
const { test, expect, request } = require( '@playwright/test' );
const { FIXTURES, mailpitFind, adminLogin } = require( './helpers' );

test.describe.configure( { mode: 'serial' } );

let orderId = '';
let trackUrl = '';
let approvalUrl = '';
let detailsUrl = '';
let bigOrderId = '';
let svgOrderId = '';

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
	// Chunked uploader finished: token present, file input emptied so the form post stays small.
	await expect( page.locator( '.rq-progress.is-done' ) ).toContainText( 'Качено:' );
	await expect( page.locator( 'input[name="reklamo_file_token"]' ) ).toHaveValue( /^[a-f0-9]{32}$/ );
	await page.fill( 'textarea[name="reklamo_note"]', 'E2E: златно лого, центрирано.' );
	await page.fill( 'input[name="rq_name"]', 'Е2Е Тест' );
	await page.fill( 'input[name="rq_email"]', 'e2e@example.com' );
	await page.check( 'input[name="rq_consent"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализация/ } ).click();

	// The confirmation page is the customer's own order page (passwordless, keyed by the link).
	await page.waitForURL( /porachka\/\?s=/ );
	orderId = await page.locator( 'h1[data-order]' ).getAttribute( 'data-order' );
	trackUrl = page.url().replace( /&new=1$/, '' );
	await expect( page.locator( 'body' ) ).toContainText( 'Заявката Ви е получена' );
	await expect( page.locator( '.steps li.now' ) ).toHaveText( 'Заявка приета' );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Заявка приета' );

	const mail = await mailpitFind( `Получихме Вашата заявка ${ orderId }` );
	expect( mail.to ).toBe( 'e2e@example.com' );
	expect( mail.text ).toContain( 'не се извършва плащане' );
	expect( mail.text, 'tracking link in the confirmation email' ).toContain( trackUrl );
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

test( 'a mistyped email domain is refused, values are kept', async ( { page } ) => {
	await page.goto( '/kachi-logo/?paket=red-business-pack' );
	await page.setInputFiles( 'input[name="reklamo_logo"]', `${ FIXTURES }/logo.png` );
	await expect( page.locator( '.rq-progress.is-done' ) ).toContainText( 'logo.png' );
	await page.fill( 'input[name="rq_name"]', 'Грешен Домейн' );
	await page.fill( 'input[name="rq_email"]', 'nobody@abv.bh' );
	await page.check( 'input[name="rq_consent"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализация/ } ).click();
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'abv.bh' );
	await expect( page.locator( 'input[name="rq_name"]' ) ).toHaveValue( 'Грешен Домейн' );
	// The finished upload survived the round-trip: no second upload needed.
	await expect( page.locator( 'input[name="reklamo_file_token"]' ) ).toHaveValue( /^[a-f0-9]{32}$/ );
	await expect( page.locator( '.rq-progress.is-done' ) ).toContainText( 'logo.png' );
	await page.fill( 'input[name="rq_email"]', 'fixed@example.com' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализация/ } ).click();
	await page.waitForURL( /porachka\/\?s=/ );
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
	await page.waitForURL( /porachka\/\?s=/ );
	await expect( page.locator( 'body' ) ).toContainText( 'Заявката Ви е получена' );
} );

test( 'chunked upload: a 150 MB PSD gets through a 64 MB PHP limit', async ( { page } ) => {
	test.setTimeout( 180_000 );
	await page.goto( '/kachi-logo/?paket=office-starter-pack' );
	await page.setInputFiles( 'input[name="reklamo_logo"]', `${ FIXTURES }/big.psd` );
	await expect( page.locator( '.rq-progress' ) ).toBeVisible();
	await expect( page.locator( '.rq-progress.is-done' ) ).toContainText( 'big.psd', { timeout: 150_000 } );
	await expect( page.locator( '.rq-progress.is-done' ) ).toContainText( '150.0 MB' );
	await page.fill( 'input[name="rq_name"]', 'Голям Файл' );
	await page.fill( 'input[name="rq_email"]', 'big@example.com' );
	await page.check( 'input[name="rq_consent"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализация/ } ).click();
	await page.waitForURL( /porachka\/\?s=/ );
	bigOrderId = await page.locator( 'h1[data-order]' ).getAttribute( 'data-order' );
} );

test( 'wrong content is refused: an .exe renamed to .ai', async ( { page } ) => {
	await page.goto( '/kachi-logo/?paket=red-business-pack' );
	await page.setInputFiles( 'input[name="reklamo_logo"]', `${ FIXTURES }/fake.ai` );
	await expect( page.locator( '.rq-progress.is-error' ) ).toContainText( 'не изглежда като валиден AI файл', { timeout: 60_000 } );
	await expect( page.locator( 'input[name="reklamo_file_token"]' ) ).toHaveValue( '' );
} );

test( 'SVG with a script is stored sanitised', async ( { page } ) => {
	await page.goto( '/kachi-logo/?paket=red-business-pack' );
	await page.setInputFiles( 'input[name="reklamo_logo"]', `${ FIXTURES }/xss.svg` );
	await expect( page.locator( '.rq-progress.is-done' ) ).toContainText( 'xss.svg' );
	await page.fill( 'input[name="rq_name"]', 'СВГ Тест' );
	await page.fill( 'input[name="rq_email"]', 'svg@example.com' );
	await page.check( 'input[name="rq_consent"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализация/ } ).click();
	await page.waitForURL( /porachka\/\?s=/ );
	const id = await page.locator( 'h1[data-order]' ).getAttribute( 'data-order' );
	svgOrderId = id;

	// The admin download must be a forced attachment whose bytes carry no script.
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ id }` );
	const href = await page.locator( '#reklamo_mockup a[href*="reklamo_download"]' ).first().getAttribute( 'href' );
	const r = await page.request.get( href );
	expect( r.status() ).toBe( 200 );
	expect( r.headers()[ 'content-disposition' ] ).toContain( 'attachment' );
	expect( r.headers()[ 'content-type' ] ).toContain( 'application/octet-stream' );
	const body = await r.text();
	expect( body ).not.toContain( '<script' );
	expect( body ).toContain( '<rect' );
} );

test( 'admin sees the big upload with its real size', async ( { page } ) => {
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ bigOrderId }` );
	await expect( page.locator( '#reklamo_mockup' ) ).toContainText( 'big.psd (PSD, 150 MB)' );
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

test( 'order page: shows the pending mockup, re-sends the email, never acts, guards its files', async ( { page } ) => {
	const anon = await request.newContext();
	expect( trackUrl ).toContain( '/moyata-porachka/' );
	// The seeded checkout page keeps its own slug — the tracking rule must not shadow it.
	expect( ( await anon.get( '/porachka/' ) ).status() ).toBe( 200 );
	// Wrong secret: 404 with no hint. Approval-style headers on the real page.
	const bad = await anon.get( trackUrl.replace( /k=[^&]+/, 'k=' + 'A'.repeat( 43 ) ) );
	expect( bad.status() ).toBe( 404 );
	const ok = await anon.get( trackUrl );
	expect( ok.status() ).toBe( 200 );
	expect( ok.headers()[ 'referrer-policy' ] ).toBe( 'no-referrer' );
	expect( ok.headers()[ 'x-robots-tag' ] ).toContain( 'noindex' );

	await page.context().clearCookies();
	await page.goto( trackUrl );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Изпратена визуализация' );
	await expect( page.locator( '.steps li.now' ) ).toHaveText( 'Визуализация' );
	await expect( page.locator( '.next h2' ) ).toContainText( 'Визуализация №1 очаква Вашето решение' );
	// The mockup is visible here, but nothing on this page can approve it.
	await expect( page.locator( '.rev img' ) ).toBeVisible();
	await expect( page.locator( 'button[name="decision"]' ) ).toHaveCount( 0 );
	const img = await page.locator( '.rev img' ).getAttribute( 'src' );
	const r = await anon.get( img );
	expect( r.status() ).toBe( 200 );
	expect( r.headers()[ 'content-type' ] ).toContain( 'image/png' );
	// A file id from another order is refused.
	const foreign = await anon.get( img.replace( /view=\d+/, 'view=1' ) );
	expect( foreign.status() ).toBe( 404 );

	// "Send it again" re-issues the approval link; a second click within minutes is throttled.
	await page.getByRole( 'button', { name: 'Изпрати отново' } ).click();
	await expect( page.locator( '.notice.ok' ) ).toContainText( 'Изпратено' );
	const again = await mailpitFind( `Визуализацията за поръчка ${ orderId }` );
	const m = again.text.match( /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ );
	expect( m[ 0 ] ).not.toBe( approvalUrl );
	approvalUrl = m[ 0 ]; // the old link is superseded — the fresh one is what the customer now holds.
	await page.getByRole( 'button', { name: 'Изпрати отново' } ).click();
	await expect( page.locator( '.notice.err' ) ).toContainText( 'преди няколко минути' );
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

	// The customer asks for changes first (logged out).
	await page.context().clearCookies();
	await page.goto( approvalUrl );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Визуализацията Ви е готова' );
	await expect( page.locator( '.preview img' ) ).toBeVisible();
	await page.click( 'details summary' );
	await page.fill( 'textarea[name="message"]', 'E2E: логото по-голямо, моля.' );
	await page.click( 'button[name="decision"][value="changes"]' );
	await expect( page.locator( 'h1' ) ).toContainText( 'получихме' );
	const changes = await mailpitFind( `Заявени корекции по поръчка ${ orderId }` );
	expect( changes.text ).toContain( 'по-голямо' );

	// The order page shows the rejected revision with the comment; the shop sends mockup #2.
	await page.goto( trackUrl );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Заявени корекции' );
	await expect( page.locator( '.rev' ).first() ).toContainText( 'по-голямо' );
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-changes' );
	await page.setInputFiles( 'input[name="reklamo_mockup"]', `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	await expect( page.locator( '.notice-success' ) ).toContainText( 'Визуализация №2 е изпратена' );
	const second = ( await mailpitFind( `Визуализацията за поръчка ${ orderId }` ) ).text.match( /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ );
	// The link for #1 is used up and reports so; it cannot approve #2.
	await page.context().clearCookies();
	await page.goto( approvalUrl );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Тази визуализация вече е обработена' );
	approvalUrl = second[ 0 ];

	// Now approve #2.
	await page.goto( approvalUrl );
	await expect( page.locator( '.meta' ) ).toContainText( '№2' );
	await page.click( 'button[name="decision"][value="approve"]' );
	// Approval lands on the details step (its own signed link) with the deposit amount and bank details.
	await page.waitForURL( /odobrenie\/\?s=/ );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Одобрено — още една стъпка' );
	await expect( page.locator( '.amount' ) ).toContainText( 'Дължим аванс' );
	await expect( page.locator( '.bank-details' ) ).toContainText( 'IBAN' );
	detailsUrl = page.url();

	// Replay: the same link now only reports the outcome, and cannot approve again.
	await page.goto( approvalUrl );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Тази визуализация вече е обработена' );
	await expect( page.locator( 'button[name="decision"]' ) ).toHaveCount( 0 );
} );

test( 'approval tells the shop; a mockup re-sent after approval voids the deposit and the details link', async ( { page } ) => {
	const admin = await mailpitFind( `одобрена за поръчка ${ orderId }` );
	expect( admin.text ).toContain( 'аванс' );

	await adminLogin( page );
	const url = `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`;
	await page.goto( url );
	await expect( page.locator( 'button[name="reklamo_action"][value="recalc_deposit"]' ) ).toHaveCount( 1 );
	await page.setInputFiles( 'input[name="reklamo_mockup"]', `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-mockup-sent' );
	await expect( page.locator( 'button[name="reklamo_action"][value="recalc_deposit"]' ) ).toHaveCount( 0 );
	// The old details link (with bank details) is dead now.
	await page.context().clearCookies();
	await page.goto( detailsUrl );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Тази връзка е изтекла' );
	// Approve #3 to get back on track, with a fresh details link.
	const third = ( await mailpitFind( `Визуализацията за поръчка ${ orderId }` ) ).text.match( /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ );
	await page.goto( third[ 0 ] );
	await page.click( 'button[name="decision"][value="approve"]' );
	await page.waitForURL( /odobrenie\/\?s=/ );
	detailsUrl = page.url();
} );

test( 'a cancelled order tells the customer and its links stop acting', async ( { page } ) => {
	// Use the SVG test order (rq-received): send a mockup, then cancel, then try the link.
	await adminLogin( page );
	const url = `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ svgOrderId }`;
	await page.goto( url );
	await page.setInputFiles( 'input[name="reklamo_mockup"]', `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	const link = ( await mailpitFind( `Визуализацията за поръчка ${ svgOrderId }` ) ).text.match( /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ )[ 0 ];
	await page.selectOption( '#order_status', 'wc-cancelled' );
	await page.click( 'button.save_order' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-cancelled' );
	const mail = await mailpitFind( `Поръчка ${ svgOrderId } е отказана` );
	expect( mail.to ).toBe( 'svg@example.com' );

	await page.context().clearCookies();
	await page.goto( link );
	await expect( page.locator( 'button[name="decision"]' ) ).toHaveCount( 0 );
	await expect( page.locator( 'h1' ) ).toContainText( 'вече не очаква' );
	const track = mail.text.match( /https?:\/\/\S+moyata-porachka\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ )[ 0 ];
	await page.goto( track );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Отказана' );
} );

test( 'deposit email arrived; customer fills invoice and delivery details', async ( { page } ) => {
	const mail = await mailpitFind( `Одобрено — аванс и данни за поръчка ${ orderId }` );
	expect( mail.to ).toBe( 'e2e@example.com' );
	expect( mail.text ).toContain( 'IBAN' );
	expect( mail.text ).toContain( `Поръчка ${ orderId }` );

	await page.context().clearCookies();
	await page.goto( detailsUrl );
	await page.check( 'input[name="d_customer_type"][value="company"]' );
	await page.fill( 'input[name="d_company"]', 'Ноубъл ЕООД' );
	await page.fill( 'input[name="d_eik"]', '123456789' );
	await page.fill( 'input[name="d_vat"]', 'BG123456789' );
	await page.fill( 'input[name="d_mol"]', 'Иван Иванов' );
	await page.fill( 'input[name="d_phone"]', '+359 88 000 0000' );
	await page.fill( 'input[name="d_address_1"]', 'ул. Тестова 1' );
	await page.fill( 'input[name="d_city"]', 'София' );
	await page.fill( 'input[name="d_postcode"]', '1000' );
	await page.click( 'form button[type="submit"]' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Благодарим — данните са запазени' );

	// Bad ЕИК is refused, good values are kept.
	await page.fill( 'input[name="d_eik"]', '12' );
	await page.click( 'form button[type="submit"]' );
	await expect( page.locator( '.notice.err' ) ).toContainText( 'ЕИК' );
	await expect( page.locator( 'input[name="d_company"]' ) ).toHaveValue( 'Ноубъл ЕООД' );

	const admin = await mailpitFind( `Данни за фактура по поръчка ${ orderId }` );
	expect( admin.text ).toContain( '123456789' );
} );

test( 'shop moves the order: deposit → production → final payment → completed', async ( { page } ) => {
	await adminLogin( page );
	const url = `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`;
	await page.goto( url );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-approved' );
	await expect( page.locator( '#reklamo_mockup' ) ).toContainText( 'Ноубъл ЕООД' );

	// The status dropdown refuses an illegal jump.
	await page.selectOption( '#order_status', 'wc-completed' );
	await page.click( 'button.save_order' );
	await expect( page.locator( '.notice-warning' ) ).toContainText( 'не е валидна следваща стъпка' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-approved' );

	await page.getByRole( 'button', { name: /Аванс получен/ } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-deposit-paid' );
	const dep = await mailpitFind( `Получен аванс по поръчка ${ orderId }` );
	expect( dep.to ).toBe( 'e2e@example.com' );

	await page.getByRole( 'button', { name: 'Стартирай производство' } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-production' );
	const prod = await mailpitFind( `Поръчка ${ orderId } е в производство` );
	expect( prod.to ).toBe( 'e2e@example.com' );

	await page.getByRole( 'button', { name: /поискай доплащане/ } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-final-due' );
	const fin = await mailpitFind( `Поръчка ${ orderId } е готова — доплащане` );
	expect( fin.text ).toContain( 'IBAN' );

	await page.getByRole( 'button', { name: /Доплащане получено/ } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-completed' );
} );

test( 'order page after completion: full history, all steps done, nothing left to do', async ( { page } ) => {
	await page.context().clearCookies();
	await page.goto( trackUrl );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Приключена' );
	await expect( page.locator( '.steps li.done' ) ).toHaveCount( 6 );
	await expect( page.locator( '.next h2' ) ).toContainText( 'Завършена на' );
	await expect( page.locator( '.rev' ) ).toHaveCount( 3 );
	await expect( page.locator( '.rev' ).first() ).toContainText( 'Одобрена на' );
	await expect( page.locator( '.rev' ).last() ).toContainText( 'по-голямо' );
	await expect( page.locator( '.kv' ).first() ).toContainText( 'получен' );
	await expect( page.locator( 'body' ) ).toContainText( 'Ноубъл ЕООД' );
	await expect( page.locator( 'form.inline-form' ) ).toHaveCount( 0 );
	// The completed-order email from WooCommerce carries the link too.
	const done = await mailpitFind( 'Вашата поръчка от' );
	expect( done.text ).toContain( 'porachka/?s=' );
} );

test( 'diagnostics: storage protected, probe finds the real single-request ceiling', async ( { page } ) => {
	test.setTimeout( 180_000 );
	await adminLogin( page );
	await page.goto( '/wp-admin/admin.php?page=reklamo-diagnostics' );
	await expect( page.locator( '.notice-success' ) ).toContainText( 'Извън уеб директорията' );
	await expect( page.locator( '.wrap' ) ).toContainText( 'upload_max_filesize' );
	await page.click( '#reklamo-probe' );
	// Local PHP is capped at 64M on purpose: 2 MB (a chunk) must pass, 128 MB must not.
	await expect( page.locator( '#reklamo-probe-results' ) ).toContainText( '2 MB', { timeout: 120_000 } );
	await expect( page.locator( '#reklamo-probe-summary' ) ).toContainText( 'Най-голяма приета единична заявка', { timeout: 120_000 } );
	const rows = await page.locator( '#reklamo-probe-results tr' ).allTextContents();
	expect( rows.find( ( r ) => r.startsWith( '2 MB' ) ) ).toContain( 'приета' );
	expect( rows.some( ( r ) => r.includes( 'отхвърлена' ) ) ).toBe( true );
} );

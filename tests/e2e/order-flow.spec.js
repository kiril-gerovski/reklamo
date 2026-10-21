// @ts-check
// The walking skeleton, end to end, for one product: request page → logo upload → cart →
// no-payment request → admin sends mockup → customer approves via one-time link → replay
// is refused. Several products with their own mockups: cart.spec.js.
const { test, expect, request } = require( '@playwright/test' );
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );
const { FIXTURES, mailpitFind, adminLogin } = require( './helpers' );

test.describe.configure( { mode: 'serial' } );

let orderId = '';
let trackUrl = '';
let approvalUrl = '';
let detailsUrl = '';
let bigOrderId = '';
let svgOrderId = '';

/** Cart step 2: contact details and consents, then send. Lands on the new order's page. */
async function sendCart( page, name, email, phone = '0888 123 456' ) {
	if ( await page.locator( '[data-rk-continue]' ).count() ) {
		await page.locator( '[data-rk-continue]' ).click();
		await expect( page.locator( '.rk-cart' ) ).toHaveAttribute( 'data-step', 'details' );
	}
	await page.fill( 'input[name="rq_name"]', name );
	await page.fill( 'input[name="rq_email"]', email );
	await page.fill( 'input[name="rq_phone"]', phone );
	await page.check( 'input[name="rq_consent"]' );
	await page.check( 'input[name="rq_waiver"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализации/ } ).click();
	await page.waitForURL( /porachka\/\?s=/ );
	return page.locator( 'h1[data-order]' ).getAttribute( 'data-order' );
}

/** Cart step 1: the first logo (marked for every product by default) gets its file and its note. */
async function logoInCart( page, fixture, note = 'E2E: лого в центъра.' ) {
	const logo = page.locator( '.rk-logo' ).first();
	await logo.locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/${ fixture }` );
	await expect( logo.locator( '.rq-progress.is-done' ) ).toContainText( fixture, { timeout: 150_000 } );
	await logo.locator( 'textarea' ).fill( note );
}

/** The request page for one product puts it in the cart; the logo is uploaded there. */
async function addFromRequestPage( page, paket, fixture = '', note = undefined ) {
	await page.goto( `/kachi-logo/?paket=${ paket }&logo=1` );
	await page.getByRole( 'button', { name: /Добави в количката/ } ).click();
	await page.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
	await page.waitForURL( /\/koshnitsa\/$/ );
	if ( fixture ) {
		await logoInCart( page, fixture, note );
	}
}

test( 'customer places a request with a logo and no payment', async ( { page } ) => {
	// The request page for one product (the header button leads here) adds it to the cart.
	// The inscription is the default, but a package takes a logo only; ?logo=1 opens the logo tab.
	await page.goto( '/kachi-logo/' );
	await expect( page.locator( 'input[name="rk_mode"][value="text"]' ) ).toBeChecked();
	await page.goto( '/kachi-logo/?paket=biznes-komplekt-magnet' );
	await expect( page.locator( 'input[name="rk_mode"][value="logo"]' ) ).toBeChecked();
	await page.goto( '/kachi-logo/?paket=biznes-komplekt-magnet&logo=1' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Създайте продукт с вашето лого или надпис' );
	await expect( page.locator( '[data-rk-product-select] option:checked' ) ).toHaveText( 'Бизнес комплект Magnet' );
	await expect( page.locator( '.rq-nopay:visible' ) ).toContainText( 'не се извършва плащане' );
	await page.getByRole( 'button', { name: /Добави в количката/ } ).click();
	await page.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
	await page.waitForURL( /\/koshnitsa\/$/ );

	await expect( page.locator( '.rk-cart__item' ) ).toHaveCount( 1 );
	await expect( page.locator( '[data-rk-continue]' ) ).toBeDisabled();
	const logo = page.locator( '.rk-logo' ).first();
	await logo.locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/logo.ai` );
	await expect( logo.locator( '[data-rq-file-name]' ) ).toHaveText( 'logo.ai' );
	// Chunked uploader finished: token present, file input emptied so the form post stays small.
	await expect( logo.locator( '.rq-progress.is-done' ) ).toContainText( 'Качено:' );
	await expect( logo.locator( 'input[type="hidden"][name$="[file_token]"]' ) ).toHaveValue( /^[a-f0-9]{32}$/ );
	// The logo alone is enough; the note is optional.
	await expect( page.locator( '[data-rk-continue]' ) ).toBeEnabled();
	await logo.locator( 'textarea' ).fill( 'E2E: златно лого, центрирано.' );

	// The confirmation page is the customer's own order page (passwordless, keyed by the link).
	orderId = await sendCart( page, 'Е2Е Тест', 'e2e@example.com' );
	trackUrl = page.url().replace( /&new=1$/, '' );
	await expect( page.locator( 'body' ) ).toContainText( 'Заявката Ви е получена' );
	await expect( page.locator( 'section.product' ) ).toContainText( 'logo.ai' );
	// The request is in (done); the mockup is the step in progress.
	await expect( page.locator( '.steps li.done' ) ).toHaveText( [ 'Заявка приета' ] );
	await expect( page.locator( '.steps li.now' ) ).toHaveText( 'Визуализация' );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Заявка приета' );

	const mail = await mailpitFind( `Получихме Вашата заявка ${ orderId }` );
	expect( mail.to ).toBe( 'e2e@example.com' );
	expect( mail.text ).toContain( 'не се извършва плащане' );
	expect( mail.text, 'tracking link in the confirmation email' ).toContain( trackUrl );
} );

test( 'validation: no step 2 without a logo, a note is optional; then consents and phone are checked, values kept', async ( { page } ) => {
	await addFromRequestPage( page, 'biznes-komplekt-magnet' );
	// The disabled button is a convenience; the server refuses the same on its own.
	await page.locator( '[data-rk-continue]' ).evaluate( ( b ) => { b.disabled = false; } );
	await page.locator( '[data-rk-continue]' ).click();
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'Бизнес комплект Magnet - Червен, 50 бр.: логото му още не е качено' );
	await expect( page.locator( '.rk-cart' ) ).toHaveAttribute( 'data-step', 'logos' );

	// A logo without a note is enough: the button turns on by itself.
	await logoInCart( page, 'logo.png', '' );
	await expect( page.locator( '.rk-logo' ).first().locator( 'textarea' ) ).not.toHaveAttribute( 'required', /.*/ );
	await expect( page.locator( '[data-rk-continue]' ) ).toBeEnabled();
	await page.locator( '[data-rk-continue]' ).click();
	await expect( page.locator( '.rk-cart' ) ).toHaveAttribute( 'data-step', 'details' );

	await page.fill( 'input[name="rq_name"]', 'Без съгласие' );
	await page.fill( 'input[name="rq_email"]', 'noconsent@example.com' );
	await page.fill( 'input[name="rq_phone"]', '12' );
	// Without the consents the button stays off; the server refuses the same on its own.
	await expect( page.locator( '[data-rk-send]' ) ).toBeDisabled();
	// Leaving the field fires its own `change` (which re-runs the check); do that before forcing the button.
	await page.locator( 'input[name="rq_phone"]' ).blur();
	await page.locator( '[data-rk-send]' ).evaluate( ( b ) => { b.disabled = false; } );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализации/ } ).click();
	await page.waitForURL( /rq=/ );
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'Общите условия' );
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'правото на отказ' );
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'телефонен номер' );
	await expect( page.locator( 'input[name="rq_phone"]' ) ).toHaveValue( '12' );
	await expect( page.locator( 'input[name="rq_name"]' ) ).toHaveValue( 'Без съгласие' );
	await expect( page.locator( '.rk-cart__item' ) ).toHaveCount( 1 );
} );

test( 'a mistyped email domain is refused, values and the logo are kept', async ( { page } ) => {
	await addFromRequestPage( page, 'biznes-komplekt-magnet', 'logo.png' );
	await page.locator( '[data-rk-continue]' ).click();
	await page.fill( 'input[name="rq_name"]', 'Грешен Домейн' );
	await page.fill( 'input[name="rq_email"]', 'nobody@abv.bh' );
	await page.fill( 'input[name="rq_phone"]', '+359 2 987 65 43' );
	await page.check( 'input[name="rq_consent"]' );
	await page.check( 'input[name="rq_waiver"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализации/ } ).click();
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'abv.bh' );
	await expect( page.locator( 'input[name="rq_name"]' ) ).toHaveValue( 'Грешен Домейн' );
	await expect( page.locator( '.rk-cart__item [data-cart-logo]' ) ).toContainText( 'logo.png' );
	await sendCart( page, 'Грешен Домейн', 'fixed@example.com', '+359 2 987 65 43' );
} );

test( 'chunked upload: a 150 MB PSD gets through a 64 MB PHP limit', async ( { page } ) => {
	test.setTimeout( 180_000 );
	await addFromRequestPage( page, 'komplekt-cork' );
	const logo = page.locator( '.rk-logo' ).first();
	await logo.locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/big.psd` );
	await expect( logo.locator( '.rq-progress' ) ).toBeVisible();
	await expect( logo.locator( '.rq-progress.is-done' ) ).toContainText( 'big.psd', { timeout: 150_000 } );
	await expect( logo.locator( '.rq-progress.is-done' ) ).toContainText( '150.0 MB' );
	await logo.locator( 'textarea' ).fill( 'Голям файл' );
	bigOrderId = await sendCart( page, 'Голям Файл', 'big@example.com' );
} );

test( 'wrong content is refused: an .exe renamed to .ai', async ( { page } ) => {
	await addFromRequestPage( page, 'biznes-komplekt-magnet' );
	const logo = page.locator( '.rk-logo' ).first();
	await logo.locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/fake.ai` );
	await expect( logo.locator( '.rq-progress.is-error' ) ).toContainText( 'не изглежда като валиден AI файл', { timeout: 60_000 } );
	await expect( logo.locator( 'input[type="hidden"][name$="[file_token]"]' ) ).toHaveValue( '' );
} );

test( 'SVG with a script is stored sanitised', async ( { page } ) => {
	await addFromRequestPage( page, 'biznes-komplekt-magnet', 'xss.svg' );
	const id = await sendCart( page, 'СВГ Тест', 'svg@example.com' );
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
	await expect( page.locator( '#reklamo_mockup' ) ).toContainText( 'Съгласие с Общите условия и Политиката за поверителност' );
	await expect( page.locator( '#reklamo_mockup' ) ).toContainText( 'Приет отказ от правото на отказ' );
	// The note is line-item meta, shown with the order items — not in the mockup box.
	await expect( page.locator( '#woocommerce-order-items' ) ).toContainText( 'E2E: златно лого' );

	await page.setInputFiles( 'input[name^="reklamo_mockup["]', `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	await expect( page.locator( '.notice-success' ) ).toContainText( 'Бизнес комплект Magnet - Червен, 50 бр. (визуализация №1)' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-mockup-sent' );

	const mail = await mailpitFind( `Визуализацията за поръчка ${ orderId }` );
	const m = mail.text.match( /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ );
	expect( m, 'approval link in email' ).toBeTruthy();
	approvalUrl = m[ 0 ];
} );

test( 'order page: shows the pending mockup with its decision buttons, guards its files', async ( { page } ) => {
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
	await expect( page.locator( '.steps li.now' ) ).toHaveText( 'Одобрение' );
	await expect( page.locator( '.next h2' ) ).toContainText( 'Визуализация №1 очаква Вашето решение' );
	await expect( page.locator( '.product .preview img' ) ).toBeVisible();
	await expect( page.locator( 'button[name="act"][value="approve"]' ) ).toHaveCount( 1 );
	// GET changed nothing: the order is still awaiting the decision.
	const img = await page.locator( '.product .preview img' ).getAttribute( 'src' );
	const r = await anon.get( img );
	expect( r.status() ).toBe( 200 );
	expect( r.headers()[ 'content-type' ] ).toContain( 'image/png' );
	// A file id from another order is refused.
	const foreign = await anon.get( img.replace( /view=\d+/, 'view=1' ) );
	expect( foreign.status() ).toBe( 404 );
	// A POST without the nonce is refused and changes nothing.
	const forged = await anon.post( trackUrl.split( '?' )[ 0 ], { form: { s: trackUrl.match( /s=([^&]+)/ )[ 1 ], k: trackUrl.match( /k=([^&]+)/ )[ 1 ], act: 'approve' } } );
	expect( forged.url() ).toContain( 'msg=nonce' );
	await page.reload();
	await expect( page.locator( 'button[name="act"][value="approve"]' ) ).toHaveCount( 1 );
	// "Changes" without a message is refused with a hint.
	await page.click( '.product details summary' );
	await page.click( 'button[name="act"][value="changes"]' );
	await expect( page.locator( '.notice.err' ) ).toContainText( 'опишете' );
} );

test( 'approval link: GET is idempotent, POST approves once, replay is refused', async ( { page } ) => {
	// Email scanners prefetch links: two anonymous GETs must change nothing.
	const anon = await request.newContext();
	for ( let i = 0; i < 2; i++ ) {
		const r = await anon.get( approvalUrl );
		expect( r.status() ).toBe( 200 );
		expect( await r.text() ).toContain( 'очаква Вашето решение' );
		expect( r.headers()[ 'x-robots-tag' ] ).toContain( 'noindex' );
		expect( r.headers()[ 'referrer-policy' ] ).toBe( 'no-referrer' );
	}
	// Wrong secret: 404, never a hint.
	const bad = await anon.get( approvalUrl.replace( /k=.*/, 'k=' + 'A'.repeat( 43 ) ) );
	expect( bad.status() ).toBe( 404 );

	// The customer asks for changes first (logged out).
	await page.context().clearCookies();
	await page.goto( approvalUrl );
	// The emailed link shows the same order header and progress as the order page.
	await expect( page.locator( 'h1[data-order]' ) ).toHaveAttribute( 'data-order', orderId );
	await expect( page.locator( '.steps li.now' ) ).toHaveText( 'Одобрение' );
	await expect( page.locator( '.next h2' ) ).toContainText( 'Визуализация №1 очаква Вашето решение' );
	await expect( page.locator( '.preview img' ) ).toBeVisible();
	await page.click( 'details summary' );
	await page.fill( 'textarea[name="message"]', 'E2E: логото по-голямо, моля.' );
	await page.click( 'button[name="decision"][value="changes"]' );
	await expect( page.locator( '.next h2' ) ).toContainText( 'получихме' );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Заявени корекции' );
	const changes = await mailpitFind( `Заявени корекции по поръчка ${ orderId }` );
	expect( changes.text ).toContain( 'по-голямо' );

	// The order page shows the rejected revision with the comment; the shop sends mockup #2.
	await page.goto( trackUrl );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Заявени корекции' );
	await expect( page.locator( '.rev' ).first() ).toContainText( 'по-голямо' );
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-changes' );
	await page.setInputFiles( 'input[name^="reklamo_mockup["]', `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	await expect( page.locator( '.notice-success' ) ).toContainText( 'Бизнес комплект Magnet - Червен, 50 бр. (визуализация №2)' );
	const second = ( await mailpitFind( `Визуализацията за поръчка ${ orderId }` ) ).text.match( /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ );
	// The link for #1 is used up and reports so; it cannot approve #2.
	await page.context().clearCookies();
	await page.goto( approvalUrl );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Тази визуализация вече е обработена' );
	approvalUrl = second[ 0 ];

	// Now approve #2.
	await page.goto( approvalUrl );
	await expect( page.locator( '.next h2' ) ).toContainText( '№2' );
	await page.click( 'button[name="decision"][value="approve"]' );
	// Approval lands on the deposit step (its own signed link): approval done, deposit in progress, bank details.
	await page.waitForURL( /odobrenie\/\?s=/ );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Плащане' );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Одобрена — чака плащане' );
	await expect( page.locator( '.steps li.now' ) ).toHaveText( 'Плащане' );
	await expect( page.locator( '.steps li.done' ) ).toHaveCount( 3 );
	await expect( page.locator( '.amount' ) ).toContainText( 'Дължима сума' );
	await expect( page.locator( '.bank-details' ) ).toContainText( 'IBAN' );
	detailsUrl = page.url();

	// Replay: the same link now only reports the outcome, and cannot approve again.
	await page.goto( approvalUrl );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Тази визуализация вече е обработена' );
	await expect( page.locator( 'button[name="decision"]' ) ).toHaveCount( 0 );
} );

test( 'approval tells the shop; a mockup re-sent after approval voids the deposit and the details link', async ( { page } ) => {
	const admin = await mailpitFind( `одобрена за поръчка ${ orderId }` );
	expect( admin.text ).toContain( 'плащане' );

	await adminLogin( page );
	const url = `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`;
	await page.goto( url );
	await expect( page.locator( 'button[name="reklamo_action"][value="recalc_deposit"]' ) ).toHaveCount( 1 );
	await page.setInputFiles( 'input[name^="reklamo_mockup["]', `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-mockup-sent' );
	await expect( page.locator( 'button[name="reklamo_action"][value="recalc_deposit"]' ) ).toHaveCount( 0 );
	// The old details link (with bank details) is dead now.
	await page.context().clearCookies();
	await page.goto( detailsUrl );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Тази връзка е изтекла' );
	await expect( page.locator( '.track a' ) ).toHaveAttribute( 'href', trackUrl );
	// Approve #3 from the order page this time; the emailed link is consumed by that.
	const third = ( await mailpitFind( `Визуализацията за поръчка ${ orderId }` ) ).text.match( /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ )[ 0 ];
	await page.goto( trackUrl );
	await expect( page.locator( '.next h2' ) ).toContainText( '№3' );
	await page.click( 'button[name="act"][value="approve"]' );
	await page.waitForURL( /odobrenie\/\?s=/ );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Плащане' );
	await expect( page.locator( '.steps li.now' ) ).toHaveText( 'Плащане' );
	detailsUrl = page.url();
	await page.goto( third );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Тази визуализация вече е обработена' );
	// Back on the order page: approved, with a button to the details form.
	await page.goto( trackUrl );
	await expect( page.locator( 'button[name="act"][value="details"]' ) ).toHaveCount( 1 );
} );

test( 'a cancelled order tells the customer and its links stop acting', async ( { page } ) => {
	// Use the SVG test order (rq-received): send a mockup, then cancel, then try the link.
	await adminLogin( page );
	const url = `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ svgOrderId }`;
	await page.goto( url );
	await page.setInputFiles( 'input[name^="reklamo_mockup["]', `${ FIXTURES }/mockup.png` );
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
	await expect( page.locator( '.next h2' ) ).toContainText( 'вече не очаква' );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Отказана' );
	const track = mail.text.match( /https?:\/\/\S+moyata-porachka\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ )[ 0 ];
	await page.goto( track );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Отказана' );
} );

test( 'deposit email arrived; customer fills invoice and delivery details', async ( { page } ) => {
	const mail = await mailpitFind( `Одобрено — плащане и данни за поръчка ${ orderId }` );
	expect( mail.to ).toBe( 'e2e@example.com' );
	expect( mail.text ).toContain( 'IBAN' );
	expect( mail.text ).toContain( `Поръчка ${ orderId }` );

	await page.context().clearCookies();
	await page.goto( detailsUrl );
	// Private person: company fields disappear, a name field appears (prefilled from the request).
	await page.locator( '.seg label', { hasText: 'Частно лице' } ).click();
	await expect( page.locator( 'input[name="d_eik"]' ) ).toBeHidden();
	await expect( page.locator( 'input[name="d_mol"]' ) ).toBeHidden();
	await expect( page.locator( 'input[name="d_name"]' ) ).toBeVisible();
	await expect( page.locator( 'input[name="d_name"]' ) ).toHaveValue( 'Е2Е Тест' );
	await page.locator( '.seg label', { hasText: 'Фирма' } ).click();
	await expect( page.locator( 'input[name="d_name"]' ) ).toBeHidden();
	await expect( page.locator( 'input[name="d_eik"]' ) ).toBeVisible();
	await page.fill( 'input[name="d_company"]', 'Ноубъл ЕООД' );
	await page.fill( 'input[name="d_eik"]', '123456789' );
	await page.fill( 'input[name="d_vat"]', 'BG123456789' );
	await page.fill( 'input[name="d_mol"]', 'Иван Иванов' );
	await page.fill( 'input[name="d_phone"]', '+359 88 000 0000' );
	await page.fill( 'input[name="d_address_1"]', 'ул. Тестова 1' );
	await page.fill( 'input[name="d_city"]', 'София' );
	await page.fill( 'input[name="d_postcode"]', '1000' );
	await page.click( 'form button[type="submit"]' );
	await expect( page.locator( '.notice.ok' ) ).toContainText( 'Получихме данните Ви' );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Плащане' );

	// Bad ЕИК is refused, good values are kept.
	await page.fill( 'input[name="d_eik"]', '12' );
	await page.click( 'form button[type="submit"]' );
	await expect( page.locator( '.notice.err' ) ).toContainText( 'ЕИК' );
	await expect( page.locator( 'input[name="d_company"]' ) ).toHaveValue( 'Ноубъл ЕООД' );

	const admin = await mailpitFind( `Данни за фактура по поръчка ${ orderId }` );
	expect( admin.text ).toContain( '123456789' );

	// Delivery to a courier's office: no postcode, a courier is required (BoxNow only for lockers),
	// and a company gives its own address for the invoice.
	await page.fill( 'input[name="d_eik"]', '123456789' );
	await page.locator( '.seg--delivery label', { hasText: 'До офис на куриер' } ).click();
	await expect( page.locator( 'input[name="d_postcode"]' ) ).toBeHidden();
	await expect( page.locator( '[data-label-address]' ) ).toHaveText( 'Офис на куриера (име или адрес)' );
	await expect( page.locator( 'select[name="d_courier"] option[value="boxnow"]' ) ).toBeDisabled();
	await expect( page.locator( 'input[name="d_billing_address"]' ) ).toBeVisible();
	await page.selectOption( 'select[name="d_courier"]', 'econt' );
	await page.fill( 'input[name="d_address_1"]', 'офис Младост 1' );
	await page.fill( 'input[name="d_billing_address"]', 'гр. София, ул. Фирмена 5' );
	await page.click( 'form button[type="submit"]' );
	await expect( page.locator( '.notice.ok' ) ).toContainText( 'Получихме данните Ви' );
	await expect( page.locator( 'input[name="d_delivery_type"][value="office"]' ) ).toBeChecked();
	await expect( page.locator( 'select[name="d_courier"]' ) ).toHaveValue( 'econt' );
} );

test( 'the shop changes the delivery from the order screen, e.g. after a call', async ( { page } ) => {
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	await expect( page.locator( 'select[name="reklamo_d_delivery_type"]' ) ).toHaveValue( 'office' );
	await expect( page.locator( 'select[name="reklamo_d_courier"]' ) ).toHaveValue( 'econt' );
	await expect( page.locator( 'input[name="reklamo_d_address_1"]' ) ).toHaveValue( 'офис Младост 1' );
	await page.selectOption( 'select[name="reklamo_d_delivery_type"]', 'locker' );
	await page.selectOption( 'select[name="reklamo_d_courier"]', 'speedy' );
	await page.fill( 'input[name="reklamo_d_address_1"]', 'автомат Бизнес парк' );
	await page.click( 'button.save_order' );
	await expect( page.locator( 'select[name="reklamo_d_delivery_type"]' ) ).toHaveValue( 'locker' );
	await expect( page.locator( 'select[name="reklamo_d_courier"]' ) ).toHaveValue( 'speedy' );
	await expect( page.locator( 'input[name="reklamo_d_address_1"]' ) ).toHaveValue( 'автомат Бизнес парк' );
	await expect( page.locator( '#woocommerce-order-notes' ) ).toContainText( 'Данните за фактура и доставка са редактирани от магазина' );
} );

test( 'the shop\'s emails go to every WordPress administrator, too', async ( { page } ) => {
	await adminLogin( page );
	const removeHelper = async () => {
		await page.goto( '/wp-admin/users.php?s=e2e-second-admin' );
		const row = page.locator( 'tr', { hasText: 'e2e-second-admin' } );
		if ( await row.count() ) {
			await row.locator( '.delete a' ).evaluate( ( a ) => a.click() );
			await page.locator( '#submit' ).click();
		}
		await expect( page.locator( 'tr', { hasText: 'e2e-second-admin' } ) ).toHaveCount( 0 );
	};
	await removeHelper(); // left over from an interrupted run
	// A second administrator, as when someone helps the shop.
	await page.goto( '/wp-admin/user-new.php' );
	await page.fill( '#user_login', 'e2e-second-admin' );
	await page.fill( '#email', 'second-admin@example.com' );
	await page.selectOption( '#role', 'administrator' );
	await page.locator( '#send_user_notification' ).uncheck();
	await page.locator( '#createusersub' ).click();
	await expect( page.locator( '#message, .notice-success, .updated' ).first() ).toBeVisible();

	// WooCommerce's own "resend new order notification" goes to the configured address and to both administrators.
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	await page.selectOption( 'select[name="wc_order_action"]', 'send_order_details_admin' );
	// The click submits the order form; wait for that navigation, or its late redirect aborts the next goto.
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'load' } ), page.locator( 'button.wc-reload' ).evaluate( ( b ) => b.click() ) ] );
	// Not there yet is not a failure: ask Mailpit again until the resent email arrives.
	const recipients = async () => {
		try {
			return ( await mailpitFind( `нова поръчка #${ orderId }` ) ).all.join( ' ' );
		} catch {
			return '';
		}
	};
	await expect.poll( recipients, { timeout: 15_000 } ).toContain( 'second-admin@example.com' );
	const mail = await mailpitFind( `нова поръчка #${ orderId }` );
	expect( mail.all ).toContain( 'dev@reklamo.local' );

	await removeHelper();
} );

test( 'shop moves the order: payment → production → dispatched and completed', async ( { page } ) => {
	await adminLogin( page );
	const url = `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`;
	await page.goto( url );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-approved' );
	await expect( page.locator( '#reklamo_mockup' ) ).toContainText( 'Ноубъл ЕООД' );

	// The status dropdown refuses an illegal jump.
	await page.selectOption( '#order_status', 'wc-completed' );
	await page.click( 'button.save_order' );
	await expect( page.locator( '.reklamo-notice' ) ).toContainText( 'не е валидна следваща стъпка' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-approved' );

	await page.getByRole( 'button', { name: /Плащането е получено/ } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-deposit-paid' );
	const dep = await mailpitFind( `Получено плащане по поръчка ${ orderId }` );
	expect( dep.to ).toBe( 'e2e@example.com' );

	await page.getByRole( 'button', { name: 'Стартирай производство' } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-production' );
	const prod = await mailpitFind( `Поръчка ${ orderId } е в производство` );
	expect( prod.to ).toBe( 'e2e@example.com' );

	// Paid in full before production: nothing is left to collect, so there is no final-payment step.
	await expect( page.getByRole( 'button', { name: /поискай доплащане/ } ) ).toHaveCount( 0 );
	// The dispatch box: courier from the customer's choice, a bad waybill is refused, then waybill and invoice.
	await expect( page.locator( '#reklamo-courier' ) ).toHaveValue( 'speedy' );
	await page.fill( '#reklamo-waybill', '600/123' );
	await page.getByRole( 'button', { name: 'Изпратена — приключи поръчката' } ).click();
	await expect( page.locator( '.notice-error' ) ).toContainText( 'товарителницата' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-production' );
	await page.fill( '#reklamo-waybill', '600 123 456 78' );
	await page.setInputFiles( '#reklamo-invoice', `${ FIXTURES }/logo.pdf` );
	await page.getByRole( 'button', { name: 'Изпратена — приключи поръчката' } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-completed' );
	await expect( page.locator( '#woocommerce-order-notes' ) ).toContainText( 'товарителница 600 123 456 78 (Спиди); прикачена фактура logo.pdf' );
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
	await expect( page.locator( '.kv:not(.shipment)' ).first() ).toContainText( 'получен' );
	await expect( page.locator( 'body' ) ).toContainText( 'Ноубъл ЕООД' );
	await expect( page.locator( '.kv' ).last() ).toContainText( 'До автомат — Спиди: автомат Бизнес парк, София' );
	await expect( page.locator( 'form.inline-form' ) ).toHaveCount( 0 );
	// The completed-order email from WooCommerce carries the link too.
	const done = await mailpitFind( 'Вашата поръчка от' );
	expect( done.text ).toContain( 'porachka/?s=' );
	// …and the waybill with its tracking link, and the invoice as an attachment with a readable name.
	expect( done.text ).toContain( 'Поръчката Ви е изпратена — Спиди, товарителница № 600 123 456 78.' );
	expect( done.text ).toContain( 'https://www.speedy.bg/bg/track-shipment?shipmentNumber=60012345678' );
	expect( done.attachments ).toEqual( [ `Фактура-${ orderId }.pdf` ] );
	// The order page shows both; the invoice downloads through the page's own guarded link.
	const ship = page.locator( '.kv.shipment' );
	await expect( ship ).toContainText( 'Спиди 600 123 456 78' );
	await expect( ship.getByRole( 'link', { name: 'Проследете пратката' } ) ).toHaveAttribute( 'href', /shipmentNumber=60012345678/ );
	const invoice = await page.request.get( await ship.getByRole( 'link', { name: `Фактура-${ orderId }.pdf` } ).getAttribute( 'href' ) );
	expect( invoice.status() ).toBe( 200 );
	expect( invoice.headers()[ 'content-disposition' ] ).toMatch( /^attachment; filename="logo\.pdf"/ );
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

test( 'storefront sets no cookies: home, product, request and order pages', async ( { browser } ) => {
	const ctx = await browser.newContext();
	const p = await ctx.newPage();
	for ( const url of [ '/', '/product/biznes-komplekt-magnet/', '/kachi-logo/?paket=biznes-komplekt-magnet', trackUrl, '/promo-paketi/' ] ) {
		await p.goto( url );
		await p.waitForLoadState( 'networkidle' );
	}
	const cookies = await ctx.cookies();
	expect( cookies.map( ( c ) => c.name ), 'no cookie may be set without consent' ).toEqual( [] );
	await ctx.close();
} );

/** WP-CLI on the local stack, for what a browser cannot do (time passing). */
function wp( ...args ) {
	return execFileSync( path.resolve( __dirname, '../../scripts/wp' ), args, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ).trim();
}

test( 'an unpaid order is reminded at 48 h and cancelled at 72 h; a late payment restores it', async ( { page } ) => {
	const received = await mailpitFind( `Получихме Вашата заявка ${ bigOrderId }` );
	expect( received.text ).toContain( 'имате 48 часа за плащане' );

	await adminLogin( page );
	const url = `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ bigOrderId }`;
	await page.goto( url );
	await page.setInputFiles( 'input[name^="reklamo_mockup["]', `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-mockup-sent' );
	const link = ( await mailpitFind( `Визуализацията за поръчка ${ bigOrderId }` ) ).text.match( /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ )[ 0 ];
	await page.context().clearCookies();
	await page.goto( link );
	await page.click( 'button[name="decision"][value="approve"]' );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Плащане' );

	const request = await mailpitFind( `Одобрено — плащане и данни за поръчка ${ bigOrderId }` );
	expect( request.text ).toMatch( /Моля, платете до \d\d\.\d\d\.\d{4}, \d\d:\d\d \(в рамките на 48 часа\)/ );
	expect( request.text ).toContain( 'поръчката се анулира автоматично' );
	expect( request.text ).toContain( 'изчерпа' );

	// Reminder at 48 h and cancellation at 72 h after the approval, in calendar hours.
	const plan = JSON.parse( wp( 'eval', `
		$o = wc_get_order( ${ bigOrderId } );
		$at = strtotime( $o->get_meta( '_reklamo_approved_at' ) . ' UTC' );
		$out = array();
		foreach ( as_get_scheduled_actions( array( 'group' => 'reklamo', 'status' => 'pending', 'per_page' => 100 ) ) as $a ) {
			if ( (int) ( $a->get_args()[0] ?? 0 ) === ${ bigOrderId } ) {
				$out[ $a->get_hook() ] = $a->get_schedule()->get_date()->getTimestamp() - $at;
			}
		}
		echo wp_json_encode( $out );` ) );
	expect( plan ).toEqual( { reklamo_remind_deposit: 48 * 3600, reklamo_payment_expire: 72 * 3600 } );

	// The order page quotes the same deadline.
	await page.goto( ( request.text.match( /https?:\/\/\S+moyata-porachka\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ ) || [ link ] )[ 0 ] );
	await expect( page.locator( '.deadline' ) ).toContainText( 'Моля, платете до' );

	// Hour 48: the reminder. Hour 72: the order cancels itself and both sides hear of it.
	wp( 'eval', `do_action( 'reklamo_remind_deposit', ${ bigOrderId } );` );
	const reminder = await mailpitFind( `Напомняне: Одобрено — плащане и данни за поръчка ${ bigOrderId }` );
	expect( reminder.text ).toContain( `Срокът за плащане по поръчка ${ bigOrderId } изтече на` );
	expect( reminder.text ).toContain( 'все още не сме получили превода Ви' );
	wp( 'eval', `do_action( 'reklamo_payment_expire', ${ bigOrderId } );` );
	const cancelled = await mailpitFind( `Поръчка ${ bigOrderId } е отказана` );
	expect( cancelled.to ).toBe( 'big@example.com' );
	expect( cancelled.text ).toContain( 'анулирана автоматично, защото плащането не пристигна' );
	const shop = await mailpitFind( `Поръчка #${ bigOrderId } беше отказана` );
	expect( shop.all ).not.toContain( 'big@example.com' );
	const track = cancelled.text.match( /https?:\/\/\S+moyata-porachka\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/ );
	if ( track ) {
		await page.goto( track[ 0 ] );
		await expect( page.locator( '.next' ) ).toContainText( 'анулирана автоматично' );
	}

	// The money arrives late after all: the shop restores the order.
	await adminLogin( page );
	await page.goto( url );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-cancelled' );
	await page.getByRole( 'button', { name: /Плащането пристигна късно/ } ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-deposit-paid' );
	expect( ( await mailpitFind( `Получено плащане по поръчка ${ bigOrderId }` ) ).to ).toBe( 'big@example.com' );
	expect( wp( 'eval', `echo count( as_get_scheduled_actions( array( 'hook' => 'reklamo_payment_expire', 'args' => array( ${ bigOrderId } ), 'status' => 'pending' ) ) );` ) ).toBe( '0' );
} );

test( 'deleting an order removes its files, links and notes', async ( { page } ) => {
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ svgOrderId }` );
	const download = await page.locator( '#reklamo_mockup a[href*="reklamo_download"]' ).first().getAttribute( 'href' );
	const track = await page.locator( '#reklamo_mockup a[href*="moyata-porachka"]' ).first().getAttribute( 'href' );
	expect( ( await page.request.get( download ) ).status() ).toBe( 200 );

	// Trash from the list, then delete permanently from the trash view: the real dashboard path
	// (the orders list has no per-row links; both are bulk actions on the checkbox).
	const bulk = async ( status, action ) => {
		await page.goto( `/wp-admin/admin.php?page=wc-orders${ status ? `&status=${ status }` : '' }&s=${ svgOrderId }` );
		await page.check( `input[name="id[]"][value="${ svgOrderId }"]` );
		await page.selectOption( '#bulk-action-selector-top', action );
		await page.click( '#doaction' );
		await page.waitForLoadState( 'networkidle' );
	};
	await bulk( '', 'trash' );
	await bulk( 'trash', 'delete' );
	await expect( page.locator( `input[name="id[]"][value="${ svgOrderId }"]` ) ).toHaveCount( 0 );

	// The download URL is nonce-guarded: only the logged-in context can tell "gone" (404) from "no nonce" (400).
	expect( ( await page.request.get( download ) ).status(), 'file row and file gone' ).toBe( 404 );
	const anon = await request.newContext();
	expect( ( await anon.get( track ) ).status(), 'tracking token gone' ).toBe( 404 );
	await anon.dispose();
} );

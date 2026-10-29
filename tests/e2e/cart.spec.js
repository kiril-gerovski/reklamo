// @ts-check
// Several products in one request: they go into the cart with a plain button, the logos are
// uploaded there (one per product, or one for all), each product gets its own mockup and its
// own approval, and the deposit is asked for only once every product is approved.
const { test, expect } = require( '@playwright/test' );
const { FIXTURES, MAILPIT, mailpitFind, adminLogin } = require( './helpers' );

test.describe.configure( { mode: 'serial' } );

const LINK = /https?:\/\/\S+odobrenie\/\?s=[A-Za-z0-9]+&k=[A-Za-z0-9_-]+/g;
let orderId = '';
let trackUrl = '';
let links = [];
// The cart lives in the session cookie: the customer's steps share one browser context.
let shopper;

test.beforeAll( async ( { browser } ) => {
	shopper = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
} );
test.afterAll( async () => {
	await shopper.context().close();
} );

/** Into the cart with the product page's button. */
async function addToCart( page, slug ) {
	await page.goto( `/product/${ slug }/` );
	await page.locator( 'form.rq-form--product' ).getByRole( 'button', { name: 'Добави в количката' } ).click();
	await page.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
	await page.waitForURL( /\/koshnitsa\/$/ );
}

/** A logo set in cart step 1: file, note, and exactly the products named (a package: all its items). */
async function fillLogo( logo, fixture, note, products ) {
	if ( fixture ) {
		await logo.locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/${ fixture }` );
		await expect( logo.locator( '.rq-progress.is-done' ) ).toContainText( fixture );
	}
	await logo.locator( 'textarea' ).fill( note );
	for ( const box of await logo.locator( '.rk-logo__item:visible' ).all() ) {
		const label = await box.evaluate( ( el ) => ( el.closest( '[data-rk-group]' )?.querySelector( '.rk-logo__group-name' )?.textContent || '' ) + ' ' + el.textContent );
		const wanted = products.some( ( p ) => label.includes( p ) );
		await box.locator( 'input' ).setChecked( wanted );
	}
}

/** The checkbox of a package item under a logo. */
function itemBox( logo, product, item ) {
	return logo.locator( '[data-rk-group]', { hasText: product } ).locator( '.rk-logo__item', { hasText: item } ).locator( 'input' );
}

async function subjectCount( needle ) {
	const list = await ( await fetch( `${ MAILPIT }/api/v1/messages?limit=50` ) ).json();
	return list.messages.filter( ( m ) => m.Subject.includes( needle ) ).length;
}

test( 'products go into the cart with a plain button; the checkout is not used', async () => {
	const page = shopper;
	await page.goto( '/koshnitsa/' );
	await expect( page.locator( '.rk-cart__empty' ) ).toBeVisible();

	await addToCart( page, 'biznes-komplekt-magnet' );
	await expect( page.locator( '.site-header__cart-count' ) ).toHaveText( '1' );
	await addToCart( page, 'komplekt-cork' );
	await addToCart( page, 'metalna-himikalka-stone-white' );
	await expect( page.locator( '.site-header__cart-count' ) ).toHaveText( '3' );
	await expect( page.locator( '.rk-cart__item' ) ).toHaveCount( 3 );
	// Nothing to continue with yet: no logo, no note.
	await expect( page.locator( '[data-rk-continue]' ) ).toBeDisabled();
	await expect( page.locator( '[data-rk-blocked]' ) ).toBeVisible();

	// WooCommerce's checkout would ask for payment details: it leads back to the cart.
	await page.goto( '/porachka/' );
	await expect( page ).toHaveURL( /\/koshnitsa\/$/ );
} );

test( 'logos with notes are marked for products item by item; a marked item leaves the next logo\'s list', async () => {
	const page = shopper;
	await page.goto( '/koshnitsa/' );
	const items = page.locator( '.rk-cart__item' );
	await items.nth( 2 ).getByRole( 'button', { name: 'Премахни' } ).click();
	await expect( items ).toHaveCount( 2 );
	await expect( page.locator( '[data-rk-package-hint]' ) ).toBeVisible();

	// Logo 1 starts marked for everything; give it to the first package only. A package lists its items.
	const first = page.locator( '.rk-logo' ).first();
	await expect( first.locator( '[data-rk-group]', { hasText: 'Бизнес комплект Magnet' } ).locator( '.rk-logo__item' ) ).toHaveText( [ /50 бр\. Тефтери/, /50 бр\. Метални химикалки/ ] );
	await fillLogo( first, 'logo.pdf', 'A: златно лого на корицата.', [ 'Бизнес комплект Magnet' ] );
	await expect( items.nth( 0 ) ).toHaveClass( /is-ready/ );
	await expect( page.locator( '[data-rk-continue]' ) ).toBeDisabled();
	// The page says exactly what is missing, item by item.
	await expect( page.locator( '[data-rk-blocked] li' ) ).toHaveText( [ 'Комплект Cork – чаша, тефтер и химикалка - 50 бр.: не е маркиран при нито едно лого.' ] );
	await page.getByRole( 'button', { name: /Добави още едно лого/ } ).click();
	const pending = page.locator( '.rk-logo' ).nth( 1 );
	await pending.locator( 'textarea' ).fill( 'Без файл засега' );
	await itemBox( pending, 'Комплект Cork', 'Чаши' ).check();
	await expect( pending.locator( '[data-rk-nofile]' ) ).toBeVisible();
	// Part of a package is named item by item.
	await expect( page.locator( '[data-rk-blocked] li' ) ).toHaveText( [
		'Комплект Cork – чаша, тефтер и химикалка - 50 бр. - 50 бр. Чаши: файлът на „Лого 2“ още не е качен.',
		'Комплект Cork – чаша, тефтер и химикалка - 50 бр. - 50 бр. Тефтери: не е маркиран при нито едно лого.',
		'Комплект Cork – чаша, тефтер и химикалка - 50 бр. - 50 бр. Химикалки: не е маркиран при нито едно лого.',
	] );
	await pending.getByRole( 'button', { name: 'Премахни това лого' } ).click();
	await page.getByRole( 'button', { name: /Добави още едно лого/ } ).click();
	await expect( page.locator( '.rk-logo' ) ).toHaveCount( 2 );
	await expect( first.locator( 'textarea' ) ).toHaveValue( 'A: златно лого на корицата.' );

	// Logo 2 lists only what is still without a logo: the first package has left its list.
	const second = page.locator( '.rk-logo' ).nth( 1 );
	await expect( second.locator( '[data-rk-group]:visible' ) ).toHaveCount( 1 );
	await expect( second.locator( '[data-rk-group]:visible' ) ).toContainText( 'Комплект Cork' );
	await fillLogo( second, 'logo.png', 'B: златно лого на чашата и химикалката.', [ 'Комплект Cork' ] );
	// Unticking an item under logo 1 brings it back to logo 2's list.
	await itemBox( first, 'Бизнес комплект Magnet', 'Тефтери' ).uncheck();
	await expect( second.locator( '[data-rk-group]', { hasText: 'Бизнес комплект Magnet' } ).locator( '.rk-logo__item:visible' ) ).toHaveText( [ /Тефтери/ ] );
	await expect( page.locator( '[data-rk-continue]' ) ).toBeDisabled();
	await itemBox( first, 'Бизнес комплект Magnet', 'Тефтери' ).check();
	await expect( second.locator( '[data-rk-group]', { hasText: 'Бизнес комплект Magnet' } ) ).toBeHidden();
	// The server refuses an item under two logos on its own, whatever the page shows.
	await itemBox( second, 'Бизнес комплект Magnet', 'Тефтери' ).evaluate( ( el ) => { el.checked = true; } );
	await page.locator( '[data-rk-continue]' ).evaluate( ( b ) => { b.disabled = false; } );
	await page.locator( '[data-rk-continue]' ).click();
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'Бизнес комплект Magnet - Червен, 50 бр. - 50 бр. Тефтери: маркиран е при повече от едно лого' );
	await itemBox( page.locator( '.rk-logo' ).nth( 1 ), 'Бизнес комплект Magnet', 'Тефтери' ).evaluate( ( el ) => { el.checked = false; el.dispatchEvent( new Event( 'change', { bubbles: true } ) ); } );

	// One package, two logos: the pens of the Magnet set take logo 2.
	await itemBox( first, 'Бизнес комплект Magnet', 'Метални химикалки' ).uncheck();
	await itemBox( second, 'Бизнес комплект Magnet', 'Метални химикалки' ).check();
	await expect( items.nth( 0 ) ).toHaveClass( /is-ready/ );
	await expect( page.locator( '[data-rk-continue]' ) ).toBeEnabled();
	await page.locator( '[data-rk-continue]' ).click();
	await expect( page.locator( '.rk-cart' ) ).toHaveAttribute( 'data-step', 'details' );
	await expect( items.nth( 0 ) ).toContainText( 'Логота:' );
	await expect( items.nth( 0 ).locator( '[data-cart-logo]' ) ).toHaveText( 'logo.pdf - 50 бр. Тефтери; logo.png - 50 бр. Метални химикалки' );
	await expect( items.nth( 1 ).locator( '[data-cart-logo]' ) ).toContainText( 'logo.png' );
	await expect( items.nth( 1 ).locator( '[data-cart-note]' ) ).toContainText( 'химикалката' );
} );

test( 'sending the cart creates one order with a line, a logo and a note per product', async () => {
	const page = shopper;
	await page.goto( '/koshnitsa/' );
	// "Send" waits for name, email and phone.
	const send = page.locator( '[data-rk-send]' );
	await expect( send ).toBeDisabled();
	await expect( page.locator( '[data-rk-send-blocked]' ) ).toBeVisible();
	await page.fill( 'input[name="rq_name"]', 'Количка Тест' );
	await page.fill( 'input[name="rq_email"]', 'cart@' );
	await page.fill( 'input[name="rq_phone"]', '12' );
	await expect( send ).toBeDisabled();
	await page.fill( 'input[name="rq_email"]', 'cart@example.com' );
	// The terms and the withdrawal notice must be accepted too.
	await expect( send ).toBeDisabled();
	await page.check( 'input[name="rq_consent"]' );
	await expect( send ).toBeDisabled();
	await page.check( 'input[name="rq_waiver"]' );
	await expect( send ).toBeEnabled();
	await expect( page.locator( '[data-rk-send-blocked]' ) ).toBeHidden();
	// Contact errors keep the cart and the values.
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализации/ } ).click();
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'телефонен номер' );
	await expect( page.locator( 'input[name="rq_name"]' ) ).toHaveValue( 'Количка Тест' );
	await expect( page.locator( '.rk-cart__item' ) ).toHaveCount( 2 );

	await page.fill( 'input[name="rq_phone"]', '0888 123 456' );
	await page.check( 'input[name="rq_consent"]' );
	await page.check( 'input[name="rq_waiver"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализации/ } ).click();
	await page.waitForURL( /porachka\/\?s=/ );
	orderId = await page.locator( 'h1[data-order]' ).getAttribute( 'data-order' );
	trackUrl = page.url().replace( /&new=1$/, '' );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Заявка приета' );
	const products = page.locator( 'section.product' );
	await expect( products ).toHaveCount( 2 );
	await expect( products.nth( 0 ) ).toContainText( 'Бизнес комплект Magnet' );
	await expect( products.nth( 0 ) ).toContainText( 'logo.pdf' );
	await expect( products.nth( 0 ) ).toContainText( 'logo.png' );
	await expect( products.nth( 0 ) ).toContainText( 'logo.pdf - 50 бр. Тефтери: A: златно лого на корицата.' );
	await expect( products.nth( 0 ) ).toContainText( 'logo.png - 50 бр. Метални химикалки: B: златно лого' );
	await expect( products.nth( 1 ) ).toContainText( 'Комплект Cork' );
	await expect( products.nth( 1 ) ).toContainText( 'химикалката' );

	// The cart is empty again.
	await page.goto( '/koshnitsa/' );
	await expect( page.locator( '.rk-cart__empty' ) ).toBeVisible();
	await expect( page.locator( '.site-header__cart-count' ) ).toHaveCount( 0 );
} );

test( 'the designer sends both mockups at once: one email, a link per product', async ( { page } ) => {
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	const blocks = page.locator( '#reklamo_mockup .reklamo-item' );
	await expect( blocks ).toHaveCount( 2 );
	await expect( blocks.nth( 0 ) ).toContainText( 'Логота на клиента' );
	await expect( blocks.nth( 0 ) ).toContainText( 'logo.pdf' );
	await expect( blocks.nth( 0 ) ).toContainText( 'logo.png' );
	await expect( blocks.nth( 1 ) ).toContainText( 'logo.png' );
	await expect( blocks.nth( 1 ) ).toContainText( 'химикалката' );

	await blocks.nth( 0 ).locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/mockup.png` );
	await blocks.nth( 1 ).locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	await expect( page.locator( '.notice-success' ) ).toContainText( 'Бизнес комплект Magnet - Червен, 50 бр. (визуализация №1), Комплект Cork – чаша, тефтер и химикалка - 50 бр. (визуализация №1)' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-mockup-sent' );

	const mail = await mailpitFind( `Визуализацията за поръчка ${ orderId }` );
	links = [ ...new Set( mail.text.match( LINK ) ) ];
	expect( links ).toHaveLength( 2 );
	expect( mail.text ).toContain( 'Бизнес комплект Magnet - Червен, 50 бр. (визуализация №1)' );
	expect( mail.text ).toContain( 'Комплект Cork – чаша, тефтер и химикалка - 50 бр. (визуализация №1)' );
} );

test( 'each product is decided on its own; no deposit while one is open', async ( { page } ) => {
	await page.context().clearCookies();
	await page.goto( links[ 0 ] );
	await expect( page.locator( '.product-name' ) ).toHaveText( 'Бизнес комплект Magnet - Червен, 50 бр.' );
	await page.click( 'button[name="decision"][value="approve"]' );
	await expect( page.locator( '.next h2' ) ).toContainText( 'Одобрено' );
	await expect( page.locator( '.next' ) ).toContainText( 'Одобрени: 1 от 2' );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Изпратена визуализация' );
	expect( await subjectCount( `плащане и данни за поръчка ${ orderId }` ) ).toBe( 0 );

	// The second product from the order page: changes.
	await page.goto( trackUrl );
	await expect( page.locator( '.next h2' ) ).toContainText( '1 визуализация очаква Вашето решение' );
	const red = page.locator( 'section.product' ).nth( 0 );
	const office = page.locator( 'section.product' ).nth( 1 );
	await expect( red.locator( '.pill' ) ).toHaveText( 'Одобрена' );
	await expect( red.locator( 'button[name="act"]' ) ).toHaveCount( 0 );
	await office.locator( 'details summary' ).click();
	await office.locator( 'textarea[name="message"]' ).fill( 'B: логото по-малко, моля.' );
	await office.locator( 'button[name="act"][value="changes"]' ).click();
	await expect( page.locator( '.notice.ok' ) ).toContainText( 'получихме' );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Заявени корекции' );
	await expect( page.locator( 'section.product' ).nth( 1 ) ).toContainText( 'по-малко' );

	const changes = await mailpitFind( `Заявени корекции по поръчка ${ orderId }` );
	expect( changes.text ).toContain( 'Комплект Cork' );
	expect( changes.text ).toContain( 'по-малко' );
	// The second emailed link was consumed by the decision on the order page.
	await page.goto( links[ 1 ] );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Тази визуализация вече е обработена' );
} );

test( 'the revised mockup is approved and only then the deposit is asked for', async ( { page } ) => {
	await adminLogin( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-changes' );
	const office = page.locator( '#reklamo_mockup .reklamo-item' ).nth( 1 );
	await expect( office ).toContainText( 'по-малко' );
	await office.locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/mockup.png` );
	await page.getByRole( 'button', { name: 'Изпрати на клиента' } ).click();
	await expect( page.locator( '.notice-success' ) ).toContainText( 'Комплект Cork – чаша, тефтер и химикалка - 50 бр. (визуализация №2)' );
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-rq-mockup-sent' );

	const mail = await mailpitFind( `Визуализацията за поръчка ${ orderId }` );
	const second = [ ...new Set( mail.text.match( LINK ) ) ];
	expect( second ).toHaveLength( 1 );

	await page.context().clearCookies();
	await page.goto( second[ 0 ] );
	await expect( page.locator( '.next h2' ) ).toContainText( '№2' );
	await expect( page.locator( '.product-name' ) ).toHaveText( 'Комплект Cork – чаша, тефтер и химикалка - 50 бр.' );
	await page.click( 'button[name="decision"][value="approve"]' );
	await page.waitForURL( /odobrenie\/\?s=/ );
	await expect( page.locator( '.next h2' ) ).toHaveText( 'Плащане' );
	await expect( page.locator( '.badge' ) ).toHaveText( 'Одобрена - чака плащане' );
	expect( await subjectCount( `плащане и данни за поръчка ${ orderId }` ) ).toBe( 1 );
} );

test( 'one logo and note for several products: one file, on every line', async ( { browser } ) => {
	const page = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
	await addToCart( page, 'metalna-himikalka-classic' );
	await addToCart( page, 'chasha-s-kapak-travel-black' );
	await addToCart( page, 'komplekt-stripe' );
	// The first logo starts marked for every product: one upload and one note cover all three.
	await fillLogo( page.locator( '.rk-logo' ).first(), 'logo.eps', 'Едно лого за всичко.', [ 'Метална химикалка Classic', 'Чаша с капак Travel Black', 'Комплект Stripe' ] );
	await expect( page.locator( '.rk-cart__item.is-ready' ) ).toHaveCount( 3 );
	await page.locator( '[data-rk-continue]' ).click();
	await page.fill( 'input[name="rq_name"]', 'Общо Лого' );
	await page.fill( 'input[name="rq_email"]', 'same@example.com' );
	await page.fill( 'input[name="rq_phone"]', '0888 123 456' );
	await page.check( 'input[name="rq_consent"]' );
	await page.check( 'input[name="rq_waiver"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализации/ } ).click();
	await page.waitForURL( /porachka\/\?s=/ );
	const products = page.locator( 'section.product' );
	await expect( products ).toHaveCount( 3 );
	for ( let i = 0; i < 3; i++ ) {
		await expect( products.nth( i ) ).toContainText( 'logo.eps' );
		await expect( products.nth( i ) ).toContainText( 'Едно лого за всичко.' );
	}
	await page.context().close();
} );

test( 'the cart can be emptied after a confirmation, and shopping continues from it', async ( { browser } ) => {
	const page = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
	await addToCart( page, 'zapalka-satin-black' );
	await addToCart( page, 'komplekt-stripe' );
	await fillLogo( page.locator( '.rk-logo' ).first(), 'logo.png', 'Ще бъде изтрито.', [ 'Запалка Satin Black', 'Комплект Stripe' ] );

	await expect( page.getByRole( 'link', { name: 'Продължи с пазаруването' } ) ).toHaveAttribute( 'href', /\/promo-paketi\/$/ );

	// Cancelling the dialog keeps everything.
	page.once( 'dialog', ( d ) => d.dismiss() );
	await page.getByRole( 'button', { name: 'Изчисти количката' } ).click();
	await expect( page.locator( '.rk-cart__item' ) ).toHaveCount( 2 );

	page.once( 'dialog', ( d ) => d.accept() );
	await page.getByRole( 'button', { name: 'Изчисти количката' } ).click();
	await expect( page.locator( '.rk-cart__empty' ) ).toBeVisible();
	await expect( page.locator( '.woocommerce-message' ) ).toContainText( 'Количката е изчистена' );
	await expect( page.locator( '.site-header__cart-count' ) ).toHaveCount( 0 );

	// A new product starts clean: no logo or note left over from before.
	await addToCart( page, 'zapalka-satin-black' );
	await expect( page.locator( '.rk-logo' ) ).toHaveCount( 1 );
	await expect( page.locator( '.rk-logo textarea' ) ).toHaveValue( '' );
	await page.context().close();
} );

test( 'adding to the cart keeps the customer on the page, with a dialog: the cart or more shopping', async ( { browser } ) => {
	const page = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
	await page.goto( '/product/zapalka-satin-black/' );
	await page.locator( 'form.rq-form--product' ).getByRole( 'button', { name: 'Добави в количката' } ).click();
	await expect( page ).toHaveURL( /\/product\/zapalka-satin-black\/$/ );
	const dialog = page.locator( '[data-rk-added]' );
	await expect( dialog ).toBeVisible();
	await expect( dialog ).toContainText( '„Запалка Satin Black“ е добавен в количката' );
	await expect( page.locator( '.site-header__cart-count' ) ).toHaveText( '1' );

	// "Continue shopping" closes it and stays; it does not come back on the next page.
	await dialog.getByRole( 'button', { name: 'Продължи с пазаруването' } ).click();
	await expect( dialog ).toBeHidden();
	await page.reload();
	await expect( page.locator( '[data-rk-added]' ) ).toHaveCount( 0 );

	// From another page the second product; this time to the cart, which shows no stale notice.
	await page.goto( '/kachi-logo/?paket=komplekt-stripe&logo=1' );
	await page.getByRole( 'button', { name: /Добави в количката/ } ).click();
	await expect( page.locator( '[data-rk-added]' ) ).toContainText( 'Комплект Stripe' );
	await page.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
	await page.waitForURL( /\/koshnitsa\/$/ );
	await expect( page.locator( '.rk-cart__item' ) ).toHaveCount( 2 );
	await expect( page.locator( '.woocommerce-message' ) ).toHaveCount( 0 );
	await page.context().close();
} );

test( 'a product sold by the piece: colour, quantity from the minimum, editable in the cart, on the order line', async ( { browser } ) => {
	const page = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
	await page.goto( '/product/klasicheski-tefter/' );
	await expect( page.locator( '.product-price-badge' ) ).toContainText( 'Цена за брой' );
	await expect( page.locator( '.product-price-badge' ) ).toContainText( '3,20' );
	const form = page.locator( 'form.rq-form--product' );
	await expect( form.locator( '.rk-option input' ) ).toHaveCount( 4 );
	await expect( form.locator( 'input[value="cherven"]' ) ).toBeChecked();
	await expect( form.locator( '[data-rk-chosen]' ) ).toHaveText( 'Червен' );

	// The quantity starts at the minimum and the stepper cannot go below it.
	const qty = form.locator( '[data-rk-qty]' );
	await expect( qty ).toHaveValue( '50' );
	await form.locator( '[data-step="-1"]' ).click();
	await expect( qty ).toHaveValue( '50' );
	await form.locator( '[data-step="1"]' ).click();
	await expect( qty ).toHaveValue( '51' );
	await form.locator( '.rk-option', { has: page.locator( 'input[value="chern"]' ) } ).click();
	await expect( form.locator( '[data-rk-chosen]' ) ).toHaveText( 'Черен' );
	await expect( form.locator( '[data-rk-variation]' ) ).toHaveValue( /^\d+$/ );
	await expect( form.locator( '[data-rk-total]' ) ).toContainText( '163,20' );

	// Below the minimum the server refuses on its own.
	await qty.evaluate( ( el ) => { el.min = '1'; el.value = '10'; } );
	await form.getByRole( 'button', { name: 'Добави в количката' } ).click();
	await expect( page.locator( '[data-rk-added]' ) ).toHaveCount( 0 );
	await expect( page.locator( '.site-header__cart-count' ) ).toHaveCount( 0 );
	await expect( page.locator( '.woocommerce-error' ) ).toContainText( 'минималното количество е 50 броя' );

	await page.locator( 'form.rq-form--product .rk-option', { has: page.locator( 'input[value="chern"]' ) } ).click();
	await page.locator( 'form.rq-form--product [data-rk-qty]' ).fill( '51' );
	await page.locator( 'form.rq-form--product' ).getByRole( 'button', { name: 'Добави в количката' } ).click();
	await page.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
	await page.waitForURL( /\/koshnitsa\/$/ );
	const item = page.locator( '.rk-cart__item' );
	await expect( item.locator( '.rk-cart__name' ) ).toContainText( 'Класически тефтер - Черен' );
	await expect( item.locator( '[data-cart-qty]' ) ).toHaveValue( '51' );
	await expect( item.locator( '.rk-cart__price' ) ).toContainText( '163,20' );

	// In the cart: below the minimum is refused and put back; above it is saved.
	await item.locator( '[data-cart-qty]' ).fill( '40' );
	await page.getByRole( 'button', { name: 'Запази промените' } ).click();
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'минималното количество е 50 броя' );
	await expect( item.locator( '[data-cart-qty]' ) ).toHaveValue( '50' );
	await item.locator( '[data-cart-qty]' ).fill( '60' );
	await page.getByRole( 'button', { name: 'Запази промените' } ).click();
	await expect( item.locator( '.rk-cart__price' ) ).toContainText( '192,00' );
	await expect( page.locator( '.rk-cart__total' ) ).toContainText( '192,00' );

	const logo = page.locator( '.rk-logo' ).first();
	await logo.locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/logo.png` );
	await expect( logo.locator( '.rq-progress.is-done' ) ).toContainText( 'logo.png' );
	await logo.locator( 'textarea' ).fill( 'Сребърно лого.' );
	await page.locator( '[data-rk-continue]' ).click();
	await page.fill( 'input[name="rq_name"]', 'На Брой' );
	await page.fill( 'input[name="rq_email"]', 'piece@example.com' );
	await page.fill( 'input[name="rq_phone"]', '0888 123 456' );
	await page.check( 'input[name="rq_consent"]' );
	await page.check( 'input[name="rq_waiver"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализации/ } ).click();
	await page.waitForURL( /porachka\/\?s=/ );
	await expect( page.locator( 'section.product h4' ) ).toHaveText( 'Класически тефтер - Черен × 60' );
	await expect( page.locator( '.meta' ) ).toContainText( '192,00' );
	await page.context().close();
} );

test( 'choosing a colour swaps the whole gallery for that colour\'s photos, and back', async ( { browser } ) => {
	const page = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
	await page.goto( '/product/biznes-komplekt-magnet/' );
	const slides = () => page.locator( '.woocommerce-product-gallery__image img' ).evaluateAll( ( imgs ) => imgs.map( ( i ) => i.getAttribute( 'src' ) ) );
	const red = await slides();
	expect( red.length ).toBeGreaterThan( 1 );

	await page.locator( '.rk-option', { has: page.locator( 'input[value="sin"]' ) } ).click();
	await expect.poll( async () => ( await slides() ).every( ( s ) => s.includes( '-blue' ) ) ).toBe( true );
	await expect( page.locator( '.flex-control-thumbs li' ) ).toHaveCount( red.length );

	await page.locator( '.rk-option', { has: page.locator( 'input[value="byal"]' ) } ).click();
	await expect.poll( async () => ( await slides() ).every( ( s ) => s.includes( '-white' ) ) ).toBe( true );

	await page.locator( '.rk-option', { has: page.locator( 'input[value="cherven"]' ) } ).click();
	await expect.poll( slides ).toEqual( red );
	await page.context().close();
} );

test( 'package sizes: the owner sets one price per size; choosing a size changes only the price and the counts', async ( { page, browser } ) => {
	await adminLogin( page );
	const id = ( await ( await page.request.get( '/wp-json/wc/store/v1/products?sku=PKG-STRIPE' ) ).json() )[ 0 ].id;
	const edit = `/wp-admin/post.php?post=${ id }&action=edit`;
	// WooCommerce's admin overlay sits over the button for a pointer click; press it in the page.
	const save = () => Promise.all( [ page.waitForURL( /message=/, { waitUntil: 'commit' } ), page.locator( '#publish' ).evaluate( ( b ) => b.click() ) ] );
	const tab = () => page.locator( 'a[href="#reklamo_variants_data"]' ).click();
	await page.goto( edit );
	await tab();
	const field = page.locator( 'input[name="reklamo_size_price[150-br]"]' );
	// The client's price for 150 pcs; restored even if a check below fails.
	const real = '1199,99';
	await expect( field ).toHaveValue( real );
	await expect( page.locator( 'input[name="reklamo_size_price[50-br]"]' ) ).toHaveValue( '409,99' );
	await field.fill( '1250' );
	await save();
	await tab();
	await expect( field ).toHaveValue( '1250' );
	try {
		await checkShop();
	} finally {
		await page.goto( edit );
		await tab();
		await field.fill( real );
		await save();
	}
	await tab();
	await expect( field ).toHaveValue( real );

	async function checkShop() {

		const shop = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
		await shop.goto( '/product/komplekt-stripe/' );
		const sizes = shop.locator( '[data-attribute="attribute_pa_kolichestvo"] .rk-option' );
		await expect( sizes ).toHaveText( [ '50 бр.', '100 бр.', '150 бр.', '200 бр.' ] );
		const slides = () => shop.locator( '.woocommerce-product-gallery__image img' ).evaluateAll( ( imgs ) => imgs.map( ( i ) => i.getAttribute( 'src' ) ) );
		const before = await slides();
		await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /409,99\s€/ );
		await sizes.filter( { hasText: '150 бр.' } ).click();
		await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /1\s250\s€/ );
		await expect( shop.locator( '[data-rk-count]' ).first() ).toHaveText( '150' );
		expect( await slides() ).toEqual( before );
		await shop.context().close();
	}
} );

test( 'colours and quantities: the owner ticks colours with one set of photos each and quantities with one price each; the variations follow', async ( { page, browser } ) => {
	await adminLogin( page );
	// Photos already in the Media Library: Magnet's.
	const images = ( await ( await page.request.get( '/wp-json/wc/store/v1/products?sku=PKG-MAGNET' ) ).json() )[ 0 ].images.map( ( i ) => i.id );
	const save = () => Promise.all( [ page.waitForURL( /message=/, { waitUntil: 'commit' } ), page.locator( '#publish' ).evaluate( ( b ) => b.click() ) ] );
	const tab = () => page.locator( 'a[href="#reklamo_variants_data"]' ).click();
	const panel = page.locator( '#reklamo_variants_data' );
	const colour = ( slug ) => panel.locator( `input[data-rk-colour][value="${ slug }"]` );
	const photos = ( slug ) => panel.locator( `input[name="reklamo_colour_photos[${ slug }]"]` );

	await page.goto( '/wp-admin/post-new.php?post_type=product' );
	await page.fill( '#title', 'Тест Цветове' );
	await tab();
	// Every colour of the palette is offered as a circle; a colour's photos open when it is ticked.
	await expect( panel.locator( '.rk-variants__colour' ) ).toHaveCount( 16 );
	await expect( panel.locator( '[data-rk-photos="sin"]' ) ).toBeHidden();
	await colour( 'cherven' ).check();
	await colour( 'sin' ).check();
	await expect( panel.locator( '[data-rk-photos="sin"]' ) ).toBeVisible();
	await photos( 'cherven' ).evaluate( ( el, v ) => { el.value = v; }, `${ images[ 0 ] },${ images[ 1 ] }` );
	await photos( 'sin' ).evaluate( ( el, v ) => { el.value = v; }, String( images[ 2 ] ) );
	await expect( panel.locator( '[data-rk-size-price="50-br"]' ) ).toBeDisabled();
	await panel.locator( 'input[data-rk-size][value="50-br"]' ).check();
	// Ticking a quantity makes the product variable.
	await expect( page.locator( '#product-type' ) ).toHaveValue( 'variable' );
	await panel.locator( '[data-rk-size-price="50-br"]' ).fill( '100' );
	await panel.locator( 'input[data-rk-size][value="100-br"]' ).check();
	await panel.locator( '[data-rk-size-price="100-br"]' ).fill( '180,50' );
	await save();
	const id = new URL( page.url() ).searchParams.get( 'post' );
	const edit = `/wp-admin/post.php?post=${ id }&action=edit`;
	try {
		await tab();
		await expect( colour( 'cherven' ) ).toBeChecked();
		await expect( colour( 'sin' ) ).toBeChecked();
		await expect( panel.locator( '[data-rk-size-price="100-br"]' ) ).toHaveValue( '180,50' );
		const product = await ( await page.request.get( `/wp-json/wc/store/v1/products/${ id }` ) ).json();
		expect( product.variations ).toHaveLength( 4 );
		// Every quantity of a colour carries that colour's photos.
		const vars = await page.evaluate( async ( pid ) => ( await ( await fetch( `/wp-json/wc/store/v1/products?type=variation&parent=${ pid }&per_page=20` ) ).json() ).map( ( v ) => [ v.variation, v.images.map( ( i ) => i.id ) ] ), id );
		for ( const [ name, ids ] of vars ) {
			expect( ids[ 0 ] ).toBe( name.includes( 'Син' ) ? images[ 2 ] : images[ 0 ] );
		}

		const shop = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
		await shop.goto( product.permalink );
		await expect( shop.locator( '[data-attribute="attribute_pa_cvyat"] .rk-option__swatch' ) ).toHaveCount( 2 );
		await expect( shop.locator( '[data-attribute="attribute_pa_kolichestvo"] .rk-option' ) ).toHaveText( [ '50 бр.', '100 бр.' ] );
		await shop.locator( '.rk-option', { has: shop.locator( 'input[value="100-br"]' ) } ).click();
		await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /180,50\s€/ );
		await shop.context().close();

		// Unticking a colour removes its variations.
		await page.goto( edit );
		await tab();
		await colour( 'sin' ).uncheck();
		await save();
		const after = await ( await page.request.get( `/wp-json/wc/store/v1/products/${ id }` ) ).json();
		expect( after.variations ).toHaveLength( 2 );
	} finally {
		await page.goto( edit );
		await Promise.all( [ page.waitForURL( /trashed=1/, { waitUntil: 'commit' } ), page.locator( '#delete-action a' ).evaluate( ( a ) => a.click() ) ] );
	}
} );

test( 'a product card shows its colours as circles', async ( { page } ) => {
	await page.goto( '/promo-paketi/' );
	const card = page.locator( 'li.product', { hasText: 'Бизнес комплект Magnet' } );
	await expect( card.locator( '.package-card__colours li' ) ).not.toHaveCount( 0 );
	await expect( card.locator( '.package-card__colours li' ).first() ).toHaveAttribute( 'style', /--swatch: #/ );
	await expect( page.locator( 'li.product', { hasText: 'Комплект Stripe' } ).locator( '.package-card__colours' ) ).toHaveCount( 0 );
} );

test( 'a promotion and a gift set in the product: labels, struck price, sale price and gift in the cart', async ( { page, browser } ) => {
	await adminLogin( page );
	const id = ( await ( await page.request.get( '/wp-json/wc/store/v1/products?sku=PKG-STRIPE' ) ).json() )[ 0 ].id;
	const edit = `/wp-admin/post.php?post=${ id }&action=edit`;
	const save = () => Promise.all( [ page.waitForURL( /message=/, { waitUntil: 'commit' } ), page.locator( '#publish' ).evaluate( ( b ) => b.click() ) ] );
	const clear = async () => {
		await page.goto( edit );
		await page.fill( '#_reklamo_promo_pct', '' );
		await page.fill( '#_reklamo_gift', '' );
		await save();
	};
	// The photo with the gift: any image in the Media Library, here Cork's.
	const giftPhoto = ( await ( await page.request.get( '/wp-json/wc/store/v1/products?sku=PKG-CORK' ) ).json() )[ 0 ].images[ 0 ];
	const stripe = ( await ( await page.request.get( '/wp-json/wc/store/v1/products?sku=PKG-STRIPE' ) ).json() )[ 0 ].images[ 0 ];
	const file = ( src ) => src.replace( /^.*\/|(-\d+x\d+)?\.\w+$/g, '' );
	await page.goto( edit );
	await page.fill( '#_reklamo_promo_pct', '15' );
	await page.fill( '#_reklamo_gift', 'Запалка Satin Black' );
	await page.locator( 'input[name="_reklamo_gift_image"]' ).evaluate( ( el, v ) => { el.value = v; }, String( giftPhoto.id ) );
	await save();
	await expect( page.locator( '#_reklamo_promo_pct' ) ).toHaveValue( '15' );
	// Whatever happens below, the product must not stay on promotion for the other tests.
	try {
		await checkPromotion();
	} finally {
		await clear();
	}
	// No gift, no gift photo: the product's own photo is back, and the photo is not kept for later.
	await expect( page.locator( 'input[name="_reklamo_gift_image"]' ) ).toHaveValue( '' );
	await page.goto( '/promo-paketi/' );
	const after = page.locator( 'li.product', { hasText: 'Комплект Stripe' } );
	await expect( after.locator( '.package-card__media img' ) ).toHaveAttribute( 'src', new RegExp( file( stripe.src ) ) );
	await expect( after.locator( '.badge' ) ).toHaveCount( 0 );
	await expect( after.locator( '.package-card__amount' ) ).toContainText( '409,99' );

	async function checkPromotion() {
		const shop = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
		await shop.goto( '/promo-paketi/' );
		const card = shop.locator( 'li.product', { hasText: 'Комплект Stripe' } );
		await expect( card.locator( '.badge' ) ).toHaveText( [ 'Промоция', '+ Подарък' ] );
		await expect( card.locator( '.package-card__was' ) ).toContainText( '409,99' );
		await expect( card.locator( '.package-card__amount' ) ).toContainText( '347,99' ); // 409,99 − 15 % → ,99
		await expect( card.locator( '.package-card__gift' ) ).toContainText( 'Подарък: Запалка Satin Black' );
		// While there is a gift its photo is the main one, on the card and on the page.
		await expect( card.locator( '.package-card__media img' ) ).toHaveAttribute( 'src', new RegExp( file( giftPhoto.src ) ) );

		await shop.goto( '/product/komplekt-stripe/' );
		const slides = shop.locator( '.woocommerce-product-gallery__image img' );
		await expect( slides.first() ).toHaveAttribute( 'src', new RegExp( file( giftPhoto.src ) ) );
		// The product's own main photo is still there, second.
		await expect( slides.nth( 1 ) ).toHaveAttribute( 'src', new RegExp( file( stripe.src ) ) );
		await expect( shop.locator( '[data-rk-badge-regular]' ) ).toHaveText( /409,99/ );
		await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /347,99/ );
		await shop.locator( '.rk-option', { has: shop.locator( 'input[value="150-br"]' ) } ).click();
		await expect( shop.locator( '[data-rk-badge-regular]' ) ).toHaveText( /1\s199,99/ );
		await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /1\s019,99/ );
		await expect( shop.locator( '.product-gift' ) ).toContainText( 'Запалка Satin Black' );
		await shop.locator( 'form.rq-form--product' ).getByRole( 'button', { name: 'Добави в количката' } ).click();
		await shop.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
		await shop.waitForURL( /\/koshnitsa\/$/ );
		await expect( shop.locator( '.rk-cart__price' ) ).toHaveText( /1\s019,99/ );
		await expect( shop.locator( '.rk-cart__gift' ) ).toContainText( 'Запалка Satin Black' );
		await shop.context().close();
	}
} );

test( 'a package priced per colour: no shared price fields, and the size and the colour each set the price', async ( { page, browser } ) => {
	await adminLogin( page );
	const id = ( await ( await page.request.get( '/wp-json/wc/store/v1/products?sku=PKG-MAGNET' ) ).json() )[ 0 ].id;
	await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
	// One price per size would overwrite the colours' own prices on save, so the editor offers none.
	await expect( page.locator( 'input[name^="reklamo_size_price"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '#reklamo_variants_data' ) ).toContainText( 'различни цени' );
	// Saving a product set up before the tab changes nothing: its colours' photos and prices stay.
	const count = ( await ( await page.request.get( `/wp-json/wc/store/v1/products/${ id }` ) ).json() ).variations.length;
	await Promise.all( [ page.waitForURL( /message=/, { waitUntil: 'commit' } ), page.locator( '#publish' ).evaluate( ( b ) => b.click() ) ] );
	expect( ( await ( await page.request.get( `/wp-json/wc/store/v1/products/${ id }` ) ).json() ).variations ).toHaveLength( count );

	const shop = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
	await shop.goto( '/product/biznes-komplekt-magnet/' );
	const sizes = shop.locator( '[data-attribute="attribute_pa_kolichestvo"] .rk-option' );
	await expect( sizes ).toHaveText( [ '50 бр.', '100 бр.', '150 бр.', '200 бр.' ] );
	const slides = () => shop.locator( '.woocommerce-product-gallery__image img' ).evaluateAll( ( imgs ) => imgs.map( ( i ) => i.getAttribute( 'src' ) ) );
	const red = await slides();
	await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /539,99\s€/ );

	await sizes.filter( { hasText: '150 бр.' } ).click();
	await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /1\s589,99\s€/ );
	await expect( shop.locator( '[data-rk-count]' ).first() ).toHaveText( '150' );
	expect( await slides() ).toEqual( red );

	// Blue is its own price, with its own photos.
	await shop.locator( '.rk-option', { has: shop.locator( 'input[value="sin"]' ) } ).click();
	await expect.poll( async () => ( await slides() ).every( ( s ) => s.includes( '-blue' ) ) ).toBe( true );
	await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /999,99\s€/ );

	await shop.locator( 'form.rq-form--product' ).getByRole( 'button', { name: 'Добави в количката' } ).click();
	await shop.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
	await shop.waitForURL( /\/koshnitsa\/$/ );
	await expect( shop.locator( '.rk-cart__name' ) ).toContainText( 'Син' );
	await expect( shop.locator( '.rk-cart__name' ) ).toContainText( '150 бр.' );
	await expect( shop.locator( '.rk-cart__price' ) ).toContainText( '999,99' );
	await shop.context().close();
} );

test( 'an inscription instead of a logo, and a print colour for a logo: request page → cart → order', async ( { browser } ) => {
	const ctx = await browser.newContext( { locale: 'bg-BG' } );
	const page = await ctx.newPage();
	// The request page: product, its colour and quantity from WooCommerce, the inscription and the print colour.
	// A package opens with the logo: it takes no inscription, and the list then hides the packages.
	await page.goto( '/kachi-logo/?paket=biznes-komplekt-magnet' );
	await expect( page.locator( 'input[name="rk_mode"][value="logo"]' ) ).toBeChecked();
	await page.locator( '.rk-mode__option', { hasText: 'Искам надпис' } ).click();
	await expect( page.locator( '[data-rk-product-select]' ) ).toHaveValue( '' );
	await expect( page.locator( '[data-rk-product-select] option[data-package]' ) ).toHaveCount( 0 );
	await page.locator( '.rk-mode__option', { hasText: 'Имам лого' } ).click();
	await expect( page.locator( '[data-rk-product-select] option[data-package]' ) ).not.toHaveCount( 0 );

	// The product list shows a photo and a price for each product; choosing one sets the form's product.
	await page.goto( '/kachi-logo/' );
	const picker = page.locator( '.rk-picker' );
	await expect( picker.locator( '.rk-picker__button' ) ).toContainText( 'Изберете продукт' );
	await picker.locator( '.rk-picker__button' ).click();
	const choices = picker.locator( '[role="option"]' );
	await expect( choices ).toHaveCount( await page.locator( '[data-rk-product-select] option[value]:not([value=""])' ).count() );
	await expect( choices.first().locator( '.rk-picker__thumb img' ) ).toHaveAttribute( 'src', /\.(webp|png|jpe?g)$/ );
	await expect( choices.first() ).toContainText( 'от ' );
	const pick = choices.filter( { hasText: 'Бутилка Matt' } );
	await pick.click();
	await expect( picker.locator( '[role="listbox"]' ) ).toBeHidden();
	await expect( page.locator( '[data-rk-product-select] option:checked' ) ).toHaveText( 'Бутилка Matt' );
	await expect( picker.locator( '.rk-picker__button' ) ).toContainText( 'Бутилка Matt' );
	// The keyboard works as in a select.
	await picker.locator( '.rk-picker__button' ).press( 'ArrowDown' );
	await expect( picker.locator( '[role="listbox"]' ) ).toBeFocused();
	await page.keyboard.press( 'Escape' );
	await expect( picker.locator( '.rk-picker__button' ) ).toBeFocused();

	await page.goto( '/kachi-logo/?paket=butilka-matt' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Създайте продукт с вашето лого или надпис' );
	await expect( page.locator( '[data-rk-product-select]' ) ).toHaveValue( /\d+/ );
	const before = await page.locator( '[data-rk-design-image]' ).getAttribute( 'src' );
	await expect( page.locator( 'input[name="rk_mode"][value="text"]' ) ).toBeChecked();
	const form = page.locator( '[data-rk-design]' );
	// Another colour than the one shown: the photo follows.
	// Colour and quantity are chips, as on the product page: a colour circle and a quantity pill.
	await expect( form.locator( '[data-attribute="attribute_pa_cvyat"] .rk-option__swatch' ) ).not.toHaveCount( 0 );
	await form.locator( '[data-attribute="attribute_pa_cvyat"] .rk-option:has(input:not(:checked))' ).first().click();
	await form.locator( '[data-attribute="attribute_pa_kolichestvo"] .rk-option' ).nth( 1 ).click();
	await expect( form.locator( '[data-attribute="attribute_pa_kolichestvo"] [data-rk-chosen]' ) ).toHaveText( await form.locator( '[data-attribute="attribute_pa_kolichestvo"] .rk-option' ).nth( 1 ).textContent() );
	await expect( page.locator( '[data-rk-design-image]' ) ).not.toHaveAttribute( 'src', before );
	await expect( page.locator( '[data-rk-price]' ) ).toContainText( '€' );
	// No text, no cart: the button waits for it, and the server says so on its own.
	const add = page.getByRole( 'button', { name: /Добави в количката/ } );
	await expect( add ).toBeDisabled();
	await expect( page.locator( '[data-rk-add-blocked]' ) ).toContainText( 'напишете надписа' );
	await page.locator( 'textarea[name="rk_text"]' ).evaluate( ( t ) => { t.required = false; } );
	await add.evaluate( ( b ) => { b.disabled = false; } );
	await add.click();
	await expect( page.locator( '[data-rk-design] .rq-errors' ) ).toContainText( 'напишете надписа' );
	await expect( page.locator( 'input[name="rk_mode"][value="text"]' ) ).toBeChecked();
	await page.fill( 'textarea[name="rk_text"]', 'Екип с характер' );
	await page.locator( '.rk-print .rk-option', { hasText: '' } ).nth( 3 ).click();
	await expect( page.locator( '[data-rk-print-chosen]' ) ).toHaveText( 'Злато' );
	await page.fill( 'input[name="rk_note"]', 'По-малък надпис' );
	await page.getByRole( 'button', { name: /Добави в количката/ } ).click();
	await page.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
	await page.waitForURL( /\/koshnitsa\/$/ );

	// A second product with a logo, from its own page.
	await addToCart( page, 'komplekt-cork' );
	const bottle = page.locator( '.rk-cart__item', { hasText: 'Бутилка Matt' } );
	await expect( bottle ).toContainText( 'Надпис: Екип с характер' );
	await expect( bottle ).toContainText( 'Цвят на печата: Злато' );
	await expect( bottle ).toContainText( 'Надпис вместо лого' );
	await expect( bottle.locator( 'select[name^="colour"]' ) ).toHaveCount( 0 );
	// Only the logo product waits for a logo, and gets its own print colour.
	const logo = page.locator( '.rk-logo' ).first();
	await expect( logo.locator( '[data-rk-group]' ) ).toHaveCount( 1 );
	await expect( logo.locator( '.rk-logo__item' ) ).toHaveCount( 3 );
	await page.locator( '.rk-cart__item', { hasText: 'Cork' } ).locator( 'select[name^="colour"]' ).selectOption( { label: 'Сребро' } );
	await fillLogo( logo, 'logo.pdf', '', [ 'Cork' ] );
	await page.locator( '[data-rk-continue]' ).click();
	await expect( page.locator( '.rk-cart' ) ).toHaveAttribute( 'data-step', 'details' );
	await expect( page.locator( '.rk-cart__item', { hasText: 'Cork' } ) ).toContainText( 'Цвят на печата: Сребро' );

	await page.fill( 'input[name="rq_name"]', 'Надпис Тест' );
	await page.fill( 'input[name="rq_email"]', 'nadpis@example.com' );
	await page.fill( 'input[name="rq_phone"]', '0888 123 456' );
	await page.check( 'input[name="rq_consent"]' );
	await page.check( 'input[name="rq_waiver"]' );
	await page.getByRole( 'button', { name: /Изпрати и заяви визуализации/ } ).click();
	await page.waitForURL( /porachka\/\?s=/ );
	const id = await page.locator( 'h1[data-order]' ).getAttribute( 'data-order' );
	await ctx.close();

	// The shop sees the text and both colours, per product.
	await adminLogin( shopper );
	await shopper.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ id }` );
	const box = shopper.locator( '#reklamo_mockup' );
	await expect( box ).toContainText( 'Екип с характер' );
	await expect( box ).toContainText( 'Цвят на печата: Злато' );
	await expect( box ).toContainText( 'Цвят на печата: Сребро' );
	await expect( box ).toContainText( 'По-малък надпис' );
	await expect( shopper.locator( '#woocommerce-order-items' ) ).toContainText( 'Надпис:' );
} );

test( 'a logo uploaded on the request page arrives in the cart with its product, ready', async ( { browser } ) => {
	const ctx = await browser.newContext( { locale: 'bg-BG' } );
	const page = await ctx.newPage();
	await page.goto( '/kachi-logo/?paket=komplekt-cork&logo=1' );
	const form = page.locator( '[data-rk-design]' );
	await expect( form.locator( 'input[name="rk_mode"][value="logo"]' ) ).toBeChecked();
	await form.locator( 'input[type="file"][name="rk_logo_file"]' ).setInputFiles( `${ FIXTURES }/logo.png` );
	await expect( form.locator( '.rq-progress.is-done' ) ).toContainText( 'logo.png' );
	await form.getByRole( 'button', { name: /Добави в количката/ } ).click();
	await page.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
	await page.waitForURL( /\/koshnitsa\/$/ );
	const cork = page.locator( '.rk-cart__item', { hasText: 'Cork' } );
	await expect( cork ).toHaveClass( /is-ready/ );
	await expect( cork ).toContainText( 'logo.png' );
	await expect( page.locator( '[data-rk-continue]' ) ).toBeEnabled();
	// A product added later is offered under "Add another logo"; the first keeps its file.
	await addToCart( page, 'biznes-komplekt-magnet' );
	await expect( page.locator( '.rk-cart__item', { hasText: 'Magnet' } ) ).not.toHaveClass( /is-ready/ );
	await expect( page.locator( '.rk-cart__item', { hasText: 'Cork' } ) ).toHaveClass( /is-ready/ );
	await page.getByRole( 'button', { name: /Добави още едно лого/ } ).click();
	await expect( page.locator( '.rk-logo' ).nth( 1 ).locator( '[data-rk-group]:visible' ) ).toHaveCount( 1 );
	await expect( page.locator( '.rk-logo' ).nth( 1 ).locator( '[data-rk-group]:visible' ) ).toContainText( 'Magnet' );
	// A logo with a file but nothing left to mark steps aside, and comes back when something frees up.
	const spare = page.locator( '.rk-logo' ).nth( 1 );
	await spare.locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/logo.pdf` );
	await expect( spare.locator( '.rq-progress.is-done' ) ).toContainText( 'logo.pdf' );
	await itemBox( spare, 'Magnet', 'Тефтери' ).uncheck();
	await itemBox( spare, 'Magnet', 'Метални химикалки' ).uncheck();
	await itemBox( page.locator( '.rk-logo' ).first(), 'Magnet', 'Тефтери' ).check();
	await itemBox( page.locator( '.rk-logo' ).first(), 'Magnet', 'Метални химикалки' ).check();
	await expect( spare ).toBeHidden();
	await expect( page.locator( '[data-rk-continue]' ) ).toBeEnabled();
	await itemBox( page.locator( '.rk-logo' ).first(), 'Magnet', 'Метални химикалки' ).uncheck();
	await expect( spare ).toBeVisible();
	await expect( page.locator( '.rk-logo' ).first().locator( '.rk-logo__tag' ).first() ).toHaveText( 'Пакет' );
	await ctx.close();
} );

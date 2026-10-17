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

/** A logo set in cart step 1: file, note, and exactly the products named. */
async function fillLogo( logo, fixture, note, products ) {
	if ( fixture ) {
		await logo.locator( 'input[type="file"]' ).setInputFiles( `${ FIXTURES }/${ fixture }` );
		await expect( logo.locator( '.rq-progress.is-done' ) ).toContainText( fixture );
	}
	await logo.locator( 'textarea' ).fill( note );
	for ( const box of await logo.locator( '.rk-logo__item:visible' ).all() ) {
		const label = ( await box.textContent() ) || '';
		const wanted = products.some( ( p ) => label.includes( p ) );
		await box.locator( 'input' ).setChecked( wanted );
	}
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

test( 'logos with notes are marked for products; a marked product leaves the next logo\'s list', async () => {
	const page = shopper;
	await page.goto( '/koshnitsa/' );
	const items = page.locator( '.rk-cart__item' );
	await items.nth( 2 ).getByRole( 'button', { name: 'Премахни' } ).click();
	await expect( items ).toHaveCount( 2 );

	// Logo 1 starts marked for everything; give it to the first product only.
	await fillLogo( page.locator( '.rk-logo' ).first(), 'logo.pdf', 'A: златно лого на корицата.', [ 'Бизнес комплект Magnet' ] );
	await expect( items.nth( 0 ) ).toHaveClass( /is-ready/ );
	await expect( page.locator( '[data-rk-continue]' ) ).toBeDisabled();
	// The page says exactly what is missing, product by product.
	await expect( page.locator( '[data-rk-blocked] li' ) ).toHaveText( [ 'Комплект Cork – чаша, тефтер и химикалка - 50 бр.: не е маркиран при нито едно лого.' ] );
	await page.getByRole( 'button', { name: /Добави още едно лого/ } ).click();
	const pending = page.locator( '.rk-logo' ).nth( 1 );
	await pending.locator( 'textarea' ).fill( 'Без файл засега' );
	await pending.locator( '.rk-logo__item', { hasText: 'Комплект Cork' } ).locator( 'input' ).check();
	await expect( pending.locator( '[data-rk-nofile]' ) ).toBeVisible();
	await expect( page.locator( '[data-rk-blocked] li' ) ).toHaveText( [ 'Комплект Cork – чаша, тефтер и химикалка - 50 бр.: файлът на „Лого 2“ още не е качен.' ] );
	await pending.getByRole( 'button', { name: 'Премахни това лого' } ).click();
	await page.getByRole( 'button', { name: /Добави още едно лого/ } ).click();
	await expect( page.locator( '.rk-logo' ) ).toHaveCount( 2 );
	await expect( page.locator( '.rk-logo' ).first().locator( 'textarea' ) ).toHaveValue( 'A: златно лого на корицата.' );

	// Logo 2 lists only what is still without a logo: the first product has left its list.
	const second = page.locator( '.rk-logo' ).nth( 1 );
	await expect( second.locator( '.rk-logo__item:visible' ) ).toHaveText( [ /Комплект Cork/ ] );
	await fillLogo( second, 'logo.png', 'B: златно лого на чашата и химикалката.', [ 'Комплект Cork' ] );
	// Unticking a product under logo 1 brings it back to logo 2's list.
	await page.locator( '.rk-logo' ).first().locator( '.rk-logo__item', { hasText: 'Бизнес комплект Magnet' } ).locator( 'input' ).uncheck();
	await expect( second.locator( '.rk-logo__item:visible' ) ).toHaveCount( 2 );
	await expect( page.locator( '[data-rk-continue]' ) ).toBeDisabled();
	await page.locator( '.rk-logo' ).first().locator( '.rk-logo__item', { hasText: 'Бизнес комплект Magnet' } ).locator( 'input' ).check();
	await expect( second.locator( '.rk-logo__item:visible' ) ).toHaveCount( 1 );
	// The server refuses a product under two logos on its own, whatever the page shows.
	await second.locator( '.rk-logo__item', { hasText: 'Бизнес комплект Magnet' } ).locator( 'input' ).evaluate( ( el ) => { el.checked = true; } );
	await page.locator( '[data-rk-continue]' ).evaluate( ( b ) => { b.disabled = false; } );
	await page.locator( '[data-rk-continue]' ).click();
	await expect( page.locator( '.rq-errors' ) ).toContainText( 'Бизнес комплект Magnet - Червен, 50 бр.: маркиран е при повече от едно лого' );
	await page.locator( '.rk-logo' ).nth( 1 ).locator( '.rk-logo__item', { hasText: 'Бизнес комплект Magnet' } ).locator( 'input' ).evaluate( ( el ) => { el.checked = false; el.dispatchEvent( new Event( 'change', { bubbles: true } ) ); } );

	await expect( page.locator( '[data-rk-continue]' ) ).toBeEnabled();
	await page.locator( '[data-rk-continue]' ).click();
	await expect( page.locator( '.rk-cart' ) ).toHaveAttribute( 'data-step', 'details' );
	await expect( items.nth( 0 ).locator( '[data-cart-logo]' ) ).toContainText( 'logo.pdf' );
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
	await expect( blocks.nth( 0 ) ).toContainText( 'logo.pdf' );
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
	await expect( page.locator( '.badge' ) ).toHaveText( 'Одобрена — чака плащане' );
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
	await page.goto( '/kachi-logo/?paket=komplekt-stripe' );
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
	await page.goto( edit );
	const field = page.locator( 'input[name="reklamo_tier_price[150-br]"]' );
	// The client's price for 150 pcs; restored even if a check below fails.
	const real = '1199,99';
	await expect( field ).toHaveValue( real );
	await expect( page.locator( 'input[name="reklamo_tier_price[50-br]"]' ) ).toHaveValue( '409,99' );
	await field.fill( '1250' );
	await save();
	await expect( field ).toHaveValue( '1250' );
	try {
		await checkShop();
	} finally {
		await page.goto( edit );
		await field.fill( real );
		await save();
	}
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
	await page.goto( edit );
	await page.fill( '#_reklamo_promo_pct', '15' );
	await page.fill( '#_reklamo_gift', 'Черна чаша с Вашето лого' );
	await save();
	await expect( page.locator( '#_reklamo_promo_pct' ) ).toHaveValue( '15' );
	// Whatever happens below, the product must not stay on promotion for the other tests.
	try {
		await checkPromotion();
	} finally {
		await clear();
	}
	await page.goto( '/promo-paketi/' );
	const after = page.locator( 'li.product', { hasText: 'Комплект Stripe' } );
	await expect( after.locator( '.badge' ) ).toHaveCount( 0 );
	await expect( after.locator( '.package-card__amount' ) ).toContainText( '409,99' );

	async function checkPromotion() {
		const shop = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
		await shop.goto( '/promo-paketi/' );
		const card = shop.locator( 'li.product', { hasText: 'Комплект Stripe' } );
		await expect( card.locator( '.badge' ) ).toHaveText( [ 'Промоция', '+ Подарък' ] );
		await expect( card.locator( '.package-card__was' ) ).toContainText( '409,99' );
		await expect( card.locator( '.package-card__amount' ) ).toContainText( '347,99' ); // 409,99 − 15 % → ,99
		await expect( card.locator( '.package-card__gift' ) ).toContainText( 'Подарък: Черна чаша с Вашето лого' );

		await shop.goto( '/product/komplekt-stripe/' );
		await expect( shop.locator( '[data-rk-badge-regular]' ) ).toHaveText( /409,99/ );
		await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /347,99/ );
		await shop.locator( '.rk-option', { has: shop.locator( 'input[value="150-br"]' ) } ).click();
		await expect( shop.locator( '[data-rk-badge-regular]' ) ).toHaveText( /1\s199,99/ );
		await expect( shop.locator( '[data-rk-badge-price]' ) ).toHaveText( /1\s019,99/ );
		await expect( shop.locator( '.product-gift' ) ).toContainText( 'Черна чаша с Вашето лого' );
		await shop.locator( 'form.rq-form--product' ).getByRole( 'button', { name: 'Добави в количката' } ).click();
		await shop.locator( '[data-rk-added]' ).getByRole( 'link', { name: 'Към количката' } ).click();
		await shop.waitForURL( /\/koshnitsa\/$/ );
		await expect( shop.locator( '.rk-cart__price' ) ).toHaveText( /1\s019,99/ );
		await expect( shop.locator( '.rk-cart__gift' ) ).toContainText( 'Черна чаша с Вашето лого' );
		await shop.context().close();
	}
} );

test( 'a package priced per colour: no shared price fields, and the size and the colour each set the price', async ( { page, browser } ) => {
	await adminLogin( page );
	const id = ( await ( await page.request.get( '/wp-json/wc/store/v1/products?sku=PKG-MAGNET' ) ).json() )[ 0 ].id;
	await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
	// One price per size would overwrite the colours' own prices on save, so the editor offers none.
	await expect( page.locator( 'input[name^="reklamo_tier_price"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '#general_product_data' ) ).toContainText( 'различни цени' );

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

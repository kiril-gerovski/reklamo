// @ts-check
// The business calendars: one product per quantity tier, seeded from the client's artwork.
const { test, expect } = require( '@playwright/test' );

const TIERS = [
	{ n: 1, qty: 20, price: '56,40' },
	{ n: 2, qty: 50, price: '136,80' },
	{ n: 3, qty: 100, price: '205,20' },
	{ n: 4, qty: 200, price: '391,20' },
	{ n: 5, qty: 300, price: '529,20' },
	{ n: 6, qty: 500, price: '846' }, // whole euros drop the ",00"
];

test( 'the Календари category lists every tier at its own price', async ( { page } ) => {
	await page.goto( '/product-category/kalendari/' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Календари' );

	const cards = page.locator( 'ul.products li.product' );
	await expect( cards ).toHaveCount( TIERS.length );

	for ( const tier of TIERS ) {
		const card = cards.filter( { hasText: `START ${ tier.n }` } );
		await expect( card ).toContainText( `${ tier.qty } броя календари` );
		await expect( card ).toContainText( tier.price );
		// The client's artwork, not the WooCommerce placeholder.
		await expect( card.locator( 'img' ) ).toHaveAttribute( 'src', /calendar-start-/ );
	}
} );

test( 'a calendar has a latin URL, the client copy and a route into the request form', async ( { page } ) => {
	await page.goto( '/product/biznes-kalendar-start-1/' );

	await expect( page.locator( 'h1' ) ).toHaveText( 'Бизнес календар START 1' );
	await expect( page.locator( '.summary' ) ).toContainText( '56,40' );
	await expect( page.locator( '.summary' ) ).toContainText( '20 броя календари с индивидуален дизайн' );

	// Dimensions supplied by the client; the materials live in the Specifications tab.
	const description = page.locator( '#tab-description, .woocommerce-Tabs-panel--description' ).first();
	await expect( description ).toContainText( 'Размер на подложката 320 x 240 мм' );
	await expect( description ).toContainText( 'Размер на главата 320 x 240 мм' );

	await page.getByRole( 'link', { name: /Качи лого и заяви визуализация/i } ).click();
	await page.waitForURL( /\/kachi-logo\/\?paket=biznes-kalendar-start-1/ );
	await expect( page.locator( '.package-summary__name' ) ).toHaveText( 'Бизнес календар START 1' );
} );

test( 'a card opens the product page, and the product page opens the request form', async ( { page } ) => {
	await page.goto( '/promo-paketi/' );

	// The card no longer jumps straight to the form — it shows the product first.
	const card = page.locator( 'li.product' ).first();
	await expect( card.locator( '.btn' ) ).toHaveText( /Виж повече/i );
	await card.locator( '.btn' ).click();
	await page.waitForURL( /\/product\/[^/]+\/$/ );

	const slug = new URL( page.url() ).pathname.split( '/' ).filter( Boolean ).pop();
	await expect( page.locator( '.product-main__summary' ) ).toBeVisible();
	await expect( page.locator( '.product-price-badge' ) ).toContainText( '€' );
	await expect( page.locator( '.product-steps__item' ) ).toHaveCount( 4 );
	await expect( page.locator( '.product-help' ) ).toContainText( 'Нуждаете се от помощ' );

	await page.locator( '.product-order__cta' ).click();
	await page.waitForURL( new RegExp( `/kachi-logo/\\?paket=${ slug }` ) );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Качи лого и визуализирай' );
} );

test( 'the tabs are the design\'s, with specifications separate from the description', async ( { page } ) => {
	await page.goto( '/product/biznes-kalendar-start-1/' );

	const tabs = page.locator( '.woocommerce-tabs ul.tabs li' );
	await expect( tabs ).toHaveText( [ 'Описание', 'Спецификации' ] );

	// The design keeps dimensions and materials apart; they used to be one blob.
	await expect( page.locator( '.woocommerce-Tabs-panel--description' ) ).toContainText( 'Размер на подложката' );
	await expect( page.locator( '.woocommerce-Tabs-panel--description' ) ).not.toContainText( 'офсетова хартия' );

	await tabs.filter( { hasText: 'Спецификации' } ).click();
	const specs = page.locator( '#tab-specifications' );
	await expect( specs ).toBeVisible();
	await expect( specs ).toContainText( '70 г/м2 офсетова хартия' );
	await expect( specs ).toContainText( '295 г/м2 едностранно хромов картон Зенит' );
} );

test( 'a package with no specifications has no Specifications tab', async ( { page } ) => {
	await page.goto( '/product/office-starter-pack/' );
	const tabs = page.locator( '.woocommerce-tabs ul.tabs li' );
	await expect( tabs.filter( { hasText: 'Спецификации' } ) ).toHaveCount( 0 );
} );

test( 'Продукти sorts through its dropdown', async ( { page } ) => {
	await page.goto( '/produkti/?orderby=price-desc' );
	await expect( page.locator( 'select.orderby' ) ).toHaveValue( 'price-desc' );
	await expect( page.locator( 'li.product h3' ).first() ).toHaveText( /START 6/i );
} );

test( 'the package page follows the redesign: badge, lead, contents with details, whole-euro price', async ( { page } ) => {
	await page.goto( '/product/red-business-pack/' );
	await expect( page.locator( '.product-main__badge' ) ).toHaveText( 'Най-популярен' );
	await expect( page.locator( '.product-lead' ) ).toContainText( 'Готов фирмен пакет за вашия бизнес' );

	const items = page.locator( '.product-includes__list li' );
	await expect( items.locator( 'strong' ) ).toHaveText( [ '20 бр. Премиум тефтера', '20 бр. Метални химикалки', 'Опаковка' ] );
	await expect( items.first().locator( 'small' ) ).toHaveText( 'A5, твърда корица, 96 листа' );
	await expect( items.first().locator( 'svg' ) ).toHaveClass( /icon-notebook/ );
	await expect( items.last().locator( 'svg' ) ).toHaveClass( /icon-gift/ );

	await expect( page.locator( '.product-price-badge__value .woocommerce-Price-amount' ) ).toHaveText( /^100\s€$/ );
	await expect( page.locator( '.woocommerce-tabs ul.tabs li' ).first() ).toHaveText( 'Описание' );
	await expect( page.locator( '.woocommerce-Tabs-panel--description' ) ).toContainText( 'силно и професионално впечатление' );
} );

test( 'the package page has the design\'s tabs and the section photo behind them', async ( { page } ) => {
	await page.goto( '/product/red-business-pack/' );
	const tabs = page.locator( '.woocommerce-tabs ul.tabs li' );
	await expect( tabs ).toHaveText( [ 'Описание', 'Спецификации' ] );
	await expect( page.locator( '.product-detail__tabs' ) ).toHaveClass( /has-image/ );

	await tabs.filter( { hasText: 'Спецификации' } ).click();
	await expect( page.locator( '#tab-specifications' ) ).toContainText( '96 листа' );

	// What is the same for every product lives on the homepage and its own pages; the help card points there.
	const links = page.locator( '.product-help__links a' );
	await expect( links ).toHaveCount( 3 );
	await expect( links.nth( 0 ) ).toHaveAttribute( 'href', /kak-raboti/ );
	await expect( links.nth( 1 ) ).toHaveAttribute( 'href', /dostavka-i-srokove/ );
	await expect( links.nth( 2 ) ).toHaveAttribute( 'href', /chesto-zadavani-vaprosi/ );
} );

test( 'the homepage ends with the FAQ band: one answer open at a time, numbers from the settings', async ( { page } ) => {
	await page.goto( '/' );
	const band = page.locator( '.faq-band' );
	await expect( band.locator( 'h2' ) ).toHaveText( 'Често задавани въпроси' );
	const items = band.locator( 'details.faq-item' );
	await expect( items ).toHaveCount( 7 );
	await expect( items.first() ).toHaveAttribute( 'open', '' );

	await items.nth( 4 ).locator( 'summary' ).click();
	await expect( items.nth( 4 ) ).toHaveAttribute( 'open', '' );
	await expect( items.first() ).not.toHaveAttribute( 'open', '' );
	await expect( items.nth( 4 ) ).toContainText( /аванс \d+%/ );
	await expect( band ).not.toContainText( 'reklamo_value' );
} );

test( 'the FAQ page shows its questions as the same accordion', async ( { page } ) => {
	await page.goto( '/chesto-zadavani-vaprosi/' );
	await expect( page.locator( '.faq-list--light details.faq-item' ) ).toHaveCount( 7 );
	await expect( page.locator( '.entry-content > h3' ) ).toHaveCount( 0 );
} );

test( 'a product without a section photo keeps a plain tab section', async ( { page } ) => {
	await page.goto( '/product/biznes-kalendar-start-1/' );
	await expect( page.locator( '.product-detail__tabs' ) ).not.toHaveClass( /has-image/ );
} );

test( 'the FAQ page publishes FAQPage schema with the settings filled in', async ( { page } ) => {
	await page.goto( '/chesto-zadavani-vaprosi/' );
	const graph = ( await page.locator( 'script[type="application/ld+json"]' ).allTextContents() ).join( '\n' );
	expect( graph ).toContain( 'FAQPage' );
	expect( graph ).toContain( 'Как се плаща?' );
	expect( graph ).not.toContain( 'reklamo_value' );
} );

test( 'the homepage steps start on step one and each step shows its own text in place', async ( { page } ) => {
	await page.goto( '/' );
	const root = page.locator( '[data-step-switcher]' );
	const panels = root.locator( '.step-panel' );
	await expect( panels ).toHaveCount( 6 );
	await expect( panels.nth( 0 ) ).toBeVisible();
	await expect( panels.nth( 0 ) ).toContainText( 'Избираш пакет' );
	await expect( panels.nth( 1 ) ).toBeHidden();
	await expect( root.locator( '.steps__item' ).first() ).toHaveClass( /is-active/ );

	await root.locator( '.steps__link' ).nth( 4 ).click();
	await expect( panels.nth( 4 ) ).toBeVisible();
	await expect( panels.nth( 4 ) ).toContainText( 'Плащаш аванс' );
	await expect( panels.nth( 0 ) ).toBeHidden();
	await expect( page ).toHaveURL( /\/$/ );
} );

test( 'delivery and deadlines: a band on the homepage and cards on its own page', async ( { page } ) => {
	await page.goto( '/' );
	const band = page.locator( '.delivery-band' );
	await expect( band.locator( 'h2' ) ).toHaveText( 'Доставка и срокове' );
	await expect( band.locator( '.delivery-card' ) ).toHaveCount( 4 );
	await expect( band ).toContainText( /аванса от \d+%/ );
	await expect( band ).not.toContainText( 'reklamo_value' );

	await page.goto( '/dostavka-i-srokove/' );
	await expect( page.locator( '.entry-content .delivery-card' ) ).toHaveCount( 4 );
	await expect( page.locator( '.entry-content .delivery-card' ).nth( 2 ).locator( 'svg' ) ).toHaveClass( /icon-truck/ );
} );

test( 'every product page ends with the same "how ordering works" step switcher', async ( { page } ) => {
	for ( const url of [ '/product/red-business-pack/', '/product/biznes-kalendar-start-1/' ] ) {
		await page.goto( url );
		const root = page.locator( '.product-single [data-step-switcher]' );
		await expect( root.locator( '.steps__item' ) ).toHaveCount( 6 );
		await expect( root.locator( '.step-panel:not([hidden])' ) ).toHaveCount( 1 );
		await root.locator( '.steps__link' ).nth( 3 ).click();
		await expect( root.locator( '.step-panel:not([hidden])' ) ).toContainText( 'Одобряваш визията' );
		await expect( page ).toHaveURL( new RegExp( url + '$' ) );
	}
} );

test( 'Вдъхновение is off the site: no link anywhere and its page is gone', async ( { page } ) => {
	await page.goto( '/' );
	await expect( page.locator( 'a[href*="vdahnovenie"]' ) ).toHaveCount( 0 );
	const res = await page.goto( '/vdahnovenie/' );
	expect( res.status() ).toBe( 404 );
} );

test( 'Промо пакети filters by category through its chips and shows the price seal', async ( { page } ) => {
	await page.goto( '/promo-paketi/' );
	await expect( page.locator( '.shop-hero h1' ) ).toHaveText( /Промо пакети/i );
	const chips = page.locator( '.shop-chip' );
	await expect( chips.first() ).toHaveText( /Всички пакети/i );
	await expect( chips.first() ).toHaveClass( /is-active/ );
	// Packages only: the calendars live under Продукти, and so does their chip.
	await expect( page.locator( 'li.product' ).filter( { hasText: 'START 1' } ) ).toHaveCount( 0 );
	await expect( chips.filter( { hasText: 'Календари' } ) ).toHaveCount( 0 );

	const all = await page.locator( 'li.product' ).count();
	await chips.filter( { hasText: 'Химикалки' } ).click();
	await expect( page ).toHaveURL( /kategoria=himikalki/ );
	await expect( page.locator( '.shop-hero h1' ) ).toHaveText( /Промо пакети/i );
	await expect( chips.filter( { hasText: 'Химикалки' } ) ).toHaveClass( /is-active/ );
	const pens = page.locator( 'li.product' );
	expect( await pens.count() ).toBeLessThan( all );
	await expect( pens.filter( { hasText: 'Green Pen Pack' } ) ).toHaveCount( 1 );
	await expect( pens.filter( { hasText: 'Event Pack' } ) ).toHaveCount( 0 );

	const seal = pens.filter( { hasText: 'Green Pen Pack' } ).locator( '.package-card__seal' );
	await expect( seal ).toContainText( '50 бр.' );
	await expect( seal ).toContainText( /81\s€/ );
} );

// @ts-check
// The business calendars: one product per quantity tier, seeded from the client's artwork.
const { test, expect } = require( '@playwright/test' );

const TIERS = [
	{ n: 1, qty: 20, price: '56,40' },
	{ n: 2, qty: 50, price: '136,80' },
	{ n: 3, qty: 100, price: '205,20' },
	{ n: 4, qty: 200, price: '391,20' },
	{ n: 5, qty: 300, price: '529,20' },
	{ n: 6, qty: 500, price: '846,00' },
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
	await expect( card.locator( '.btn' ) ).toHaveText( /Виж пакета/i );
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
	await expect( tabs ).toHaveText( [ 'Описание', 'Спецификации', 'Брандиране', 'Доставка и срокове' ] );

	// The design keeps dimensions and materials apart; they used to be one blob.
	await expect( page.locator( '.woocommerce-Tabs-panel--description' ) ).toContainText( 'Размер на подложката' );
	await expect( page.locator( '.woocommerce-Tabs-panel--description' ) ).not.toContainText( 'офсетова хартия' );

	await tabs.filter( { hasText: 'Спецификации' } ).click();
	const specs = page.locator( '#tab-specifications' );
	await expect( specs ).toBeVisible();
	await expect( specs ).toContainText( '70 г/м2 офсетова хартия' );
	await expect( specs ).toContainText( '295 г/м2 едностранно хромов картон Зенит' );

	// "Често задавани въпроси" still holds nothing but its own title, so it must not appear.
	await expect( tabs.filter( { hasText: 'Често задавани' } ) ).toHaveCount( 0 );
} );

test( 'a package with no specifications has no Specifications tab', async ( { page } ) => {
	await page.goto( '/product/red-business-pack/' );
	const tabs = page.locator( '.woocommerce-tabs ul.tabs li' );
	await expect( tabs.filter( { hasText: 'Спецификации' } ) ).toHaveCount( 0 );
} );

test( 'the Календари tile on the products page shows a real picture', async ( { page } ) => {
	await page.goto( '/produkti/' );

	const tile = page.locator( 'li.product-category' ).filter( { hasText: 'Календари' } );
	await expect( tile ).toHaveCount( 1 );
	await expect( tile.locator( 'img' ) ).toHaveAttribute( 'src', /calendar-start-/ );
} );

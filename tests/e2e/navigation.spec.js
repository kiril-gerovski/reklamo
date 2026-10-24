// @ts-check
// The header the client's redesign asked for: the brand lockup as an image, and a ПРОДУКТИ
// item that drops down the product categories on hover, on keyboard and on touch.
const { test, expect, devices } = require( '@playwright/test' );
const { adminLogin } = require( './helpers' );

const CATEGORIES = [
	'Календари',
	'Тефтери',
	'Химикалки',
	'Бутилки и чаши',
	'Запалки',
	'Чанти',
	'Текстил',
	'Технологии',
	'Офис артикули',
];
// The menu shows a category once it holds a single product; the seeded Текстил, Технологии and
// Офис артикули hold none yet (Технологии only the Trio package).
const SHOWN = CATEGORIES.filter( ( c ) => ! [ 'Текстил', 'Технологии', 'Офис артикули' ].includes( c ) );

test( 'header and footer show the brand lockup, not drawn text', async ( { page } ) => {
	await page.goto( '/' );

	const logo = page.locator( '.site-header .brand__logo' );
	await expect( logo ).toHaveAttribute( 'src', /logo-slogan\.png$/ );
	await expect( logo ).toHaveAttribute( 'alt', /\S/ );
	// The lockup must actually decode, not just be referenced.
	expect( await logo.evaluate( ( img ) => img.naturalWidth ) ).toBeGreaterThan( 0 );
	await expect( page.locator( '.site-footer .brand__logo' ) ).toHaveAttribute( 'src', /\/logo\.png$/ );

	// The browser tab icon is seeded, so link previews and tabs are never bare.
	await expect( page.locator( 'link[rel="icon"]' ).first() ).toHaveAttribute( 'href', /\S/ );
} );

test( 'ПРОДУКТИ drops down the categories that hold products on hover', async ( { page } ) => {
	await page.goto( '/' );

	const parent = page.locator( '.primary-menu > li.menu-item-has-children' );
	await expect( parent ).toHaveCount( 1 );
	await expect( parent.locator( '> a' ) ).toHaveText( 'Продукти' );

	const sub = parent.locator( '.sub-menu' );
	await expect( sub ).toBeHidden();

	await parent.locator( '> a' ).hover();
	await expect( sub ).toBeVisible();
	await expect( sub.locator( 'a' ) ).toHaveText( SHOWN );

	// Every entry leads somewhere real.
	const href = await sub.locator( 'a' ).first().getAttribute( 'href' );
	expect( ( await page.request.get( href ) ).status() ).toBe( 200 );
} );

test( 'the panel survives the trip from the menu item down to the last category', async ( { page } ) => {
	await page.goto( '/' );

	const parent = page.locator( '.primary-menu > li.menu-item-has-children' );
	const link = parent.locator( '> a' );
	const sub = parent.locator( '.sub-menu' );

	await link.hover();
	await expect( sub ).toBeVisible();

	const a = await link.boundingBox();
	const s = await sub.boundingBox();
	// The gap between the item and the panel is the part that used to close it.
	await page.mouse.move( a.x + a.width / 2, a.y + a.height );
	await page.mouse.move( a.x + a.width / 2, s.y - 4 );
	await expect( sub, 'stays open while crossing the gap' ).toBeVisible();

	await page.mouse.move( s.x + 60, s.y + s.height - 20 );
	await expect( sub, 'stays open down at the last category' ).toBeVisible();
	await expect( sub.locator( 'a' ).last() ).toBeVisible();
} );

test( 'the dropdown arrow sits on the same line as the menu item', async ( { page } ) => {
	await page.goto( '/' );

	const link = page.locator( '.primary-menu > li.menu-item-has-children > a' );
	const toggle = page.locator( '.sub-toggle' );
	const a = await link.boundingBox();
	const t = await toggle.boundingBox();

	const linkCentre = a.y + a.height / 2;
	const arrowCentre = t.y + t.height / 2;
	expect( Math.abs( arrowCentre - linkCentre ), 'arrow is vertically centred on the label' ).toBeLessThanOrEqual( 1 );
	// And it follows the label instead of floating off to the right of the item.
	expect( t.x - ( a.x + a.width ) ).toBeLessThanOrEqual( 8 );
} );

test( 'the submenu toggle is operable by keyboard and closes on Escape', async ( { page } ) => {
	await page.goto( '/' );

	const parent = page.locator( '.primary-menu > li.menu-item-has-children' );
	const toggle = parent.locator( '.sub-toggle' );
	const sub = parent.locator( '.sub-menu' );

	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
	await toggle.click();
	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( sub ).toBeVisible();

	await page.keyboard.press( 'Escape' );
	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );

	// The button carries a label for screen readers, since its glyph is decorative.
	await expect( toggle.locator( '.screen-reader-text' ) ).toHaveText( /Покажи подменюто/ );
} );

test( 'on a phone the submenu is an accordion inside the burger menu', async ( { browser } ) => {
	const context = await browser.newContext( { ...devices[ 'Pixel 5' ], locale: 'bg-BG' } );
	const page = await context.newPage();
	await page.goto( '/' );

	const sub = page.locator( '.primary-menu > li.menu-item-has-children .sub-menu' );
	await expect( sub ).toBeHidden();

	await page.click( '[data-nav-toggle]' );
	await page.click( '.sub-toggle' );
	await expect( sub ).toBeVisible();
	await expect( sub.locator( 'a' ) ).toHaveCount( SHOWN.length );

	await context.close();
} );

test( 'the Продукти page lists every single product with "all products" and the category chips; packages stay on Промо пакети', async ( { page } ) => {
	await page.goto( '/produkti/' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Продукти' );

	const chips = page.locator( '.shop-chip' );
	await expect( chips.first() ).toHaveText( /Всички продукти/i );
	await expect( chips.first() ).toHaveClass( /is-active/ );
	// Only categories that hold products get a chip, in the ПРОДУКТИ menu's order; never Uncategorized or Пакети.
	const names = ( await chips.allTextContents() ).map( ( t ) => t.trim() ).slice( 1 );
	expect( names.length ).toBeGreaterThan( 0 );
	expect( names.every( ( n ) => CATEGORIES.includes( n ) ) ).toBe( true );
	expect( names ).toEqual( CATEGORIES.filter( ( c ) => names.includes( c ) ) );

	// Single products only: the packages are on Промо пакети.
	const cards = page.locator( 'li.product' );
	await expect( cards.filter( { hasText: 'Календар Mono' } ) ).toHaveCount( 1 );
	await expect( cards.filter( { hasText: 'Бизнес комплект Magnet' } ) ).toHaveCount( 0 );
	await expect( page.locator( 'li.product.product_cat-paketi' ) ).toHaveCount( 0 );

	// By default the products follow the menu, category by category; a chosen sorting still applies.
	const slugs = { kalendari: 'Календари', tefteri: 'Тефтери', himikalki: 'Химикалки', 'butilki-i-chashi': 'Бутилки и чаши', zapalki: 'Запалки', chanti: 'Чанти' };
	const places = await cards.evaluateAll( ( lis, map ) => lis.map( ( li ) => Object.keys( map ).find( ( k ) => li.classList.contains( `product_cat-${ k }` ) ) ), slugs );
	const at = places.map( ( k ) => CATEGORIES.indexOf( slugs[ k ] ) );
	expect( at.every( ( v, i ) => i === 0 || v >= at[ i - 1 ] ) ).toBe( true );
	expect( new Set( at ).size ).toBeGreaterThan( 1 );

	await chips.filter( { hasText: 'Календари' } ).click();
	await expect( page ).toHaveURL( /\/product-category\/kalendari\/$/ );
	await expect( page.locator( '.shop-chip.is-active' ) ).toHaveText( /Календари/i );
	await expect( page.locator( '.shop-chip' ).first() ).toHaveText( /Всички продукти/i );
} );

test( 'the За нас page carries the company copy', async ( { page } ) => {
	await page.goto( '/za-nas/' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'За нас' );
	await expect( page.locator( '.entry-content p.lead' ).first() ).toContainText( 'реклама, която работи' );
	await expect( page.locator( '.entry-content' ) ).toContainText( 'промо пакети' );
	await expect( page.locator( '.site-footer' ) ).toContainText( 'За нас' );
} );

test( 'between phone and desktop the header folds into the burger instead of widening the page', async ( { page } ) => {
	for ( const width of [ 960, 1100, 1200, 1279 ] ) {
		await page.setViewportSize( { width, height: 800 } );
		await page.goto( '/' );
		await expect( page.locator( '.nav-toggle' ) ).toBeVisible();
		expect( await page.evaluate( () => document.documentElement.scrollWidth ) ).toBeLessThanOrEqual( width );
	}
} );

test( 'on a phone the company details stack, each value on its own full-width line', async ( { page } ) => {
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( '/obshti-usloviya/' );
	const table = page.locator( '.company-details' ).first();
	const box = await table.boundingBox();
	for ( const dd of await table.locator( 'dd' ).all() ) {
		// Wider than half the card: the values no longer share a row with their labels.
		expect( ( await dd.boundingBox() ).width ).toBeGreaterThan( box.width / 2 );
	}
} );

test( 'Корпоративни подаръци sits right after Продукти in the header and after Промо пакети in the footer', async ( { page } ) => {
	await page.setViewportSize( { width: 1366, height: 800 } );
	await page.goto( '/' );
	const top = page.locator( '.primary-menu > li > a' );
	await expect( top ).toHaveText( [ /Начало/i, /Промо пакети/i, /Продукти/i, /Корпоративни подаръци/i, /Как работи/i, /За бизнеса/i, /Контакти/i ] );
	await expect( page.locator( '.site-footer a', { hasText: 'Корпоративни подаръци' } ) ).toHaveCount( 1 );
	// Seven items still fit the header from laptop to wide screens, without a sideways scroll.
	for ( const width of [ 1280, 1440, 1920 ] ) {
		await page.setViewportSize( { width, height: 800 } );
		const over = await page.evaluate( () => [ document.documentElement.scrollWidth - innerWidth, document.querySelector( '.site-header__inner' ).scrollWidth - document.querySelector( '.site-header__inner' ).clientWidth ] );
		expect( over, `at ${ width } px` ).toEqual( [ 0, 0 ] );
		const heights = await top.evaluateAll( ( links ) => links.map( ( a ) => a.getBoundingClientRect().height ) );
		expect( new Set( heights ).size, `every item on one line at ${ width } px` ).toBe( 1 );
	}
} );

test( 'the address opens Google Maps, in the footer and on Контакти', async ( { page } ) => {
	await page.goto( '/kontakti/' );
	for ( const link of [ page.locator( '.site-footer a.maps-link' ), page.locator( 'main a.maps-link' ) ] ) {
		await expect( link ).toHaveCount( 1 );
		const text = ( await link.innerText() ).trim();
		expect( text ).not.toBe( '' );
		const href = new URL( await link.getAttribute( 'href' ) );
		expect( href.origin + href.pathname ).toBe( 'https://www.google.com/maps/search/' );
		expect( href.searchParams.get( 'query' ) ).toBe( text );
		await expect( link ).toHaveAttribute( 'target', '_blank' );
	}
} );

test( 'on a phone „Добави свой надпис“ is one tap away: the round button and the top of the menu', async ( { page } ) => {
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( '/' );
	// The bar's own button gives way to the round one at the bottom.
	await expect( page.locator( '.site-header__cta' ) ).toBeHidden();
	await expect( page.locator( '.mobile-cta' ) ).toHaveAttribute( 'href', /\/kachi-logo\/$/ );
	await expect( page.locator( '.mobile-cta__label span' ) ).toHaveText( [ 'Добави', 'свой', 'надпис' ] );
	await expect( page.locator( '.mobile-cta svg' ) ).toHaveCount( 0 );
	expect( await page.evaluate( () => document.documentElement.scrollWidth - innerWidth ) ).toBe( 0 );
	await page.locator( '[data-nav-toggle]' ).click();
	await expect( page.locator( '.primary-nav__cta' ) ).toBeVisible();
	await expect( page.locator( '.primary-nav__cta' ) ).toHaveText( 'Добави свой надпис' );
	// On a desktop the bar shows the text and the menu has no second button.
	await page.setViewportSize( { width: 1440, height: 900 } );
	await expect( page.locator( '.site-header__cta-text' ) ).toBeVisible();
	await expect( page.locator( '.primary-nav__cta' ) ).toBeHidden();
} );

test( 'on a phone a round button at the bottom left leads to „Добави свой надпис“, except on that page and in the cart', async ( { page } ) => {
	await page.setViewportSize( { width: 390, height: 844 } );
	for ( const url of [ '/', '/promo-paketi/', '/product/biznes-komplekt-magnet/' ] ) {
		await page.goto( url );
		await expect( page.locator( '.mobile-cta' ), url ).toBeVisible();
		await expect( page.locator( '.mobile-cta' ) ).toHaveAccessibleName( 'Добави свой надпис' );
	}
	await page.locator( '[data-nav-toggle]' ).click();
	await expect( page.locator( '.mobile-cta' ) ).toBeHidden();
	for ( const url of [ '/kachi-logo/', '/koshnitsa/' ] ) {
		await page.goto( url );
		await expect( page.locator( '.mobile-cta' ), url ).toHaveCount( 0 );
	}
	await page.setViewportSize( { width: 1024, height: 800 } );
	await page.goto( '/' );
	await expect( page.locator( '.mobile-cta' ) ).toBeHidden();
} );

test( 'an empty category joins the menu by itself once a product is filed in it, and leaves again', async ( { page, browser } ) => {
	await adminLogin( page );
	const id = ( await ( await page.request.get( '/wp-json/wc/store/v1/products?sku=TEF-002' ) ).json() )[ 0 ].id;
	const save = () => Promise.all( [ page.waitForURL( /message=/, { waitUntil: 'commit' } ), page.locator( '#publish' ).evaluate( ( b ) => b.click() ) ] );
	const file = async ( on ) => {
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
		await page.locator( '#product_catchecklist label', { hasText: 'Текстил' } ).locator( 'input' ).setChecked( on );
		await save();
	};
	const menu = async () => {
		const shop = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
		await shop.goto( '/' );
		const names = ( await shop.locator( '.primary-menu > li.menu-item-has-children .sub-menu a' ).allTextContents() ).map( ( t ) => t.trim() );
		await shop.context().close();
		return names;
	};
	expect( await menu() ).not.toContain( 'Текстил' );
	await file( true );
	try {
		expect( await menu() ).toContain( 'Текстил' );
	} finally {
		await file( false );
	}
	expect( await menu() ).not.toContain( 'Текстил' );
} );

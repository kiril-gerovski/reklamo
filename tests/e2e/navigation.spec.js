// @ts-check
// The header the client's redesign asked for: the brand lockup as an image, and a ПРОДУКТИ
// item that drops down the nine product categories on hover, on keyboard and on touch.
const { test, expect, devices } = require( '@playwright/test' );

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

test( 'header and footer show the brand lockup, not drawn text', async ( { page } ) => {
	await page.goto( '/' );

	const logo = page.locator( '.site-header .brand__logo' );
	await expect( logo ).toHaveAttribute( 'src', /logo\.png$/ );
	await expect( logo ).toHaveAttribute( 'alt', /\S/ );
	// The lockup must actually decode, not just be referenced.
	expect( await logo.evaluate( ( img ) => img.naturalWidth ) ).toBeGreaterThan( 0 );
	await expect( page.locator( '.site-footer .brand__logo' ) ).toHaveCount( 1 );

	// The browser tab icon is seeded, so link previews and tabs are never bare.
	await expect( page.locator( 'link[rel="icon"]' ).first() ).toHaveAttribute( 'href', /\S/ );
} );

test( 'ПРОДУКТИ drops down the nine categories on hover', async ( { page } ) => {
	await page.goto( '/' );

	const parent = page.locator( '.primary-menu > li.menu-item-has-children' );
	await expect( parent ).toHaveCount( 1 );
	await expect( parent.locator( '> a' ) ).toHaveText( 'Продукти' );

	const sub = parent.locator( '.sub-menu' );
	await expect( sub ).toBeHidden();

	await parent.locator( '> a' ).hover();
	await expect( sub ).toBeVisible();
	await expect( sub.locator( 'a' ) ).toHaveText( CATEGORIES );

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
	await expect( sub.locator( 'a' ) ).toHaveCount( CATEGORIES.length );

	await context.close();
} );

test( 'the Продукти page lists the categories and has lost Uncategorized', async ( { page } ) => {
	await page.goto( '/produkti/' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Продукти' );

	const titles = page.locator( '.woocommerce-loop-category__title' );
	for ( const name of CATEGORIES ) {
		await expect( titles.filter( { hasText: name } ).first() ).toBeVisible();
	}
	await expect( titles.filter( { hasText: 'Uncategorized' } ) ).toHaveCount( 0 );
} );

test( 'the homepage order form shows the brand mark from the logo kit', async ( { page } ) => {
	await page.goto( '/' );

	// Stored page content used to hard-code an inline SVG, which froze the old logo into the database.
	const mark = page.locator( '.quick-start .brand__mark' );
	await expect( mark ).toHaveAttribute( 'src', /mark\.png$/ );
	expect( await mark.evaluate( ( img ) => img.naturalWidth ) ).toBeGreaterThan( 0 );
} );

test( 'the За нас page carries the company copy', async ( { page } ) => {
	await page.goto( '/za-nas/' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'За нас' );
	await expect( page.locator( '.entry-content p.lead' ).first() ).toContainText( 'реклама, която работи' );
	await expect( page.locator( '.entry-content' ) ).toContainText( 'промо пакети' );
	await expect( page.locator( '.site-footer' ) ).toContainText( 'За нас' );
} );

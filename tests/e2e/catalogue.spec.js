// @ts-check
// The catalogue from the client's photos: three calendars, the single products and the packages, each
// sold in sizes with a price per size.
const { test, expect } = require( '@playwright/test' );
const { adminLogin } = require( './helpers' );

const TIERS = [
	{ qty: 50, price: '137,99' },
	{ qty: 100, price: '205,99' },
	{ qty: 200, price: '391,99' },
	{ qty: 300, price: '530,99' },
	{ qty: 500, price: '845,99' },
];

test( 'the Календари category lists the three calendars, each from its smallest size', async ( { page } ) => {
	await page.goto( '/product-category/kalendari/' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'Календари' );

	const cards = page.locator( 'ul.products li.product' );
	await expect( cards.locator( 'h3' ) ).toHaveText( [ /Календар Mono/i, /Трисекционен календар Trio/i, /настолен календар Photo/i ] );
	await expect( cards.first() ).toContainText( TIERS[ 0 ].price );
	// The client's photos, not the WooCommerce placeholder.
	await expect( cards.first().locator( 'img' ) ).toHaveAttribute( 'src', /kalendar-mono-main/ );
} );

test( 'each calendar size has its own price and count under one photo', async ( { page } ) => {
	await page.goto( '/product/kalendar-mono/' );
	const form = page.locator( 'form.rq-form--product' );
	await expect( form.locator( '[data-attribute="attribute_pa_kolichestvo"] input' ) ).toHaveCount( TIERS.length );
	for ( const tier of TIERS ) {
		await form.locator( '.rk-option', { has: page.locator( `input[value="${ tier.qty }-br"]` ) } ).click();
		await expect( page.locator( '.product-price-badge' ) ).toContainText( tier.price );
		await expect( page.locator( '.summary' ) ).toContainText( `${ tier.qty } броя календари` );
		await expect( page.locator( '.woocommerce-product-gallery img' ).first() ).toHaveAttribute( 'src', /kalendar-mono-main/ );
	}
} );

// The redirects from the earlier samples exist only on a site that had them, so they are not tested here.
test( 'Промо пакети holds the four packages from the client\'s photos', async ( { page } ) => {
	await page.goto( '/promo-paketi/' );
	await expect( page.locator( 'li.product h3' ) ).toHaveText( [ /Бизнес комплект Magnet/i, /Комплект Stripe/i, /Комплект Cork/i, /Комплект Trio/i ] );
} );

test( 'a calendar has a latin URL, the client copy and its own cart form', async ( { page } ) => {
	await page.goto( '/product/kalendar-mono/' );

	await expect( page.locator( 'h1' ) ).toHaveText( 'Календар Mono' );
	await expect( page.locator( '.summary' ) ).toContainText( '137,99' );
	await expect( page.locator( '.summary' ) ).toContainText( '50 броя календари с индивидуален дизайн' );

	// Dimensions supplied by the client; the materials live in the Specifications tab.
	const description = page.locator( '#tab-description, .woocommerce-Tabs-panel--description' ).first();
	await expect( description ).toContainText( 'Размер на подложката 320 x 240 мм' );
	await expect( description ).toContainText( 'Размер на главата 320 x 240 мм' );

	const form = page.locator( '.product-order form.rq-form--product' );
	await expect( form.locator( 'input[name="add-to-cart"]' ) ).toHaveValue( /^\d+$/ );
	await expect( form.getByRole( 'button', { name: 'Добави в количката' } ) ).toBeVisible();
} );

test( 'a card opens the product page, whose order card adds it to the cart', async ( { page } ) => {
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
	// The note that a mockup is a guide, on every product and package page.
	await expect( page.locator( '.product-disclaimer' ) ).toContainText( 'Показаната визуализация е ориентировъчна' );
	await expect( page.locator( '.product-disclaimer' ) ).toContainText( 'финална визуализация за одобрение' );

	const form = page.locator( '.product-order form.rq-form--product' );
	await expect( form ).toHaveAttribute( 'action', new RegExp( `/product/${ slug }/$` ) );
	await expect( form.getByRole( 'button', { name: 'Добави в количката' } ) ).toBeVisible();
	await expect( form ).toContainText( 'Логото качвате в количката' );
} );

test( 'the tabs are the design\'s, with specifications separate from the description', async ( { page } ) => {
	await page.goto( '/product/kalendar-mono/' );

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

test( 'packages and products have both tabs, Описание and Спецификации', async ( { page } ) => {
	for ( const slug of [ 'komplekt-cork', 'tefter-vintage-black', 'zapalka-satin-black' ] ) {
		await page.goto( `/product/${ slug }/` );
		await expect( page.locator( '.woocommerce-tabs ul.tabs li' ) ).toHaveText( [ 'Описание', 'Спецификации' ] );
		await page.locator( '.woocommerce-tabs ul.tabs li', { hasText: 'Спецификации' } ).click();
		await expect( page.locator( '#tab-specifications' ) ).toContainText( 'Брандиране' );
	}
} );

test( 'Продукти sorts through its dropdown', async ( { page } ) => {
	await page.goto( '/produkti/?orderby=price-desc' );
	await expect( page.locator( 'select.orderby' ) ).toHaveValue( 'price-desc' );
	await expect( page.locator( 'li.product h3' ).first() ).toHaveText( /Бутилка Bamboo Black|Комплект Cork/i ); // both top out at 2429,99 €
} );

test( 'the package page follows the redesign: badge, lead, contents with details, whole-euro price', async ( { page } ) => {
	await page.goto( '/product/biznes-komplekt-magnet/' );
	await expect( page.locator( '.product-main__badge' ) ).toHaveText( 'Най-популярен' );
	await expect( page.locator( '.product-lead' ) ).toContainText( 'Тефтер и метална химикалка в един цвят' );

	const items = page.locator( '.product-includes__list li' );
	await expect( items.locator( 'strong' ) ).toHaveText( [ '50 бр. Тефтери', '50 бр. Метални химикалки' ] );
	await expect( items.first().locator( 'small' ) ).toHaveText( 'Твърда корица, с Вашето лого' );
	await expect( items.first().locator( 'svg' ) ).toHaveClass( /icon-notebook/ );
	await expect( items.last().locator( 'svg' ) ).toHaveClass( /icon-ballpen/ );

	await expect( page.locator( '.product-price-badge__value .woocommerce-Price-amount' ) ).toHaveText( /^539,99\s€$/ );
	await expect( page.locator( '.woocommerce-tabs ul.tabs li' ).first() ).toHaveText( 'Описание' );
	await expect( page.locator( '.woocommerce-Tabs-panel--description' ) ).toContainText( 'Изчистен и практичен подарък' );
} );

test( 'a product page has the design\'s tabs and the section photo behind them', async ( { page } ) => {
	await page.goto( '/product/kalendar-mono/' );
	const tabs = page.locator( '.woocommerce-tabs ul.tabs li' );
	await expect( tabs ).toHaveText( [ 'Описание', 'Спецификации' ] );
	await expect( page.locator( '.product-detail__tabs' ) ).toHaveClass( /has-image/ );

	await tabs.filter( { hasText: 'Спецификации' } ).click();
	await expect( page.locator( '#tab-specifications' ) ).toContainText( '70 г/м2 офсетова хартия' );

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
	await expect( items ).toHaveCount( 8 );
	await expect( items.first() ).toHaveAttribute( 'open', '' );

	await items.nth( 4 ).locator( 'summary' ).click();
	await expect( items.nth( 4 ) ).toHaveAttribute( 'open', '' );
	await expect( items.first() ).not.toHaveAttribute( 'open', '' );
	await expect( items.nth( 4 ) ).toContainText( /\d+–\d+ работни дни/ );
	await expect( band ).not.toContainText( 'reklamo_value' );
} );

test( 'the FAQ page shows its questions as the same accordion', async ( { page } ) => {
	await page.goto( '/chesto-zadavani-vaprosi/' );
	await expect( page.locator( '.faq-list--light details.faq-item' ) ).toHaveCount( 8 );
	await expect( page.locator( '.entry-content > h3' ) ).toHaveCount( 0 );
} );

test( 'a product without a section photo keeps a plain tab section', async ( { page } ) => {
	// The by-the-piece sample has none; scripts/e2e.sh publishes it for the run.
	await page.goto( '/product/klasicheski-tefter/' );
	await expect( page.locator( '.product-detail__tabs' ) ).not.toHaveClass( /has-image/ );
} );

test( 'the FAQ page publishes FAQPage schema with the settings filled in', async ( { page } ) => {
	await page.goto( '/chesto-zadavani-vaprosi/' );
	const graph = ( await page.locator( 'script[type="application/ld+json"]' ).allTextContents() ).join( '\n' );
	expect( graph ).toContain( 'FAQPage' );
	expect( graph ).toContain( 'Колко корекции мога да поискам?' );
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
	await expect( panels.nth( 4 ) ).toContainText( 'Плащаш поръчката' );
	await expect( panels.nth( 0 ) ).toBeHidden();
	await expect( page ).toHaveURL( /\/$/ );
} );

test( 'delivery and deadlines: a band on the homepage and cards on its own page', async ( { page } ) => {
	await page.goto( '/' );
	const band = page.locator( '.delivery-band' );
	await expect( band.locator( 'h2' ) ).toHaveText( 'Доставка и срокове' );
	await expect( band.locator( '.delivery-card' ) ).toHaveCount( 4 );
	await expect( band ).toContainText( /над \d+ €/ );
	await expect( band ).not.toContainText( 'reklamo_value' );

	await page.goto( '/dostavka-i-srokove/' );
	await expect( page.locator( '.entry-content .delivery-card' ) ).toHaveCount( 4 );
	await expect( page.locator( '.entry-content .delivery-card' ).nth( 2 ).locator( 'svg' ) ).toHaveClass( /icon-truck/ );
} );

test( 'every product page ends with the same "how ordering works" step switcher', async ( { page } ) => {
	for ( const url of [ '/product/biznes-komplekt-magnet/', '/product/kalendar-mono/' ] ) {
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
	// "Подходящи за всякакъв бизнес" carries a briefcase, not the delivery truck.
	await expect( page.locator( '.shop-hero__usps li' ).last().locator( 'svg' ) ).toHaveClass( /icon-briefcase/ );
	const chips = page.locator( '.shop-chip' );
	await expect( chips.first() ).toHaveText( /Всички пакети/i );
	await expect( chips.first() ).toHaveClass( /is-active/ );
	// Packages only: the calendars live under Продукти, and so does their chip.
	await expect( page.locator( 'li.product' ).filter( { hasText: 'Календар Mono' } ) ).toHaveCount( 0 );
	await expect( chips.filter( { hasText: 'Календари' } ) ).toHaveCount( 0 );

	const all = await page.locator( 'li.product' ).count();
	await chips.filter( { hasText: 'Бутилки и чаши' } ).click();
	await expect( page ).toHaveURL( /kategoria=butilki-i-chashi/ );
	await expect( page.locator( '.shop-hero h1' ) ).toHaveText( /Промо пакети/i );
	await expect( chips.filter( { hasText: 'Бутилки и чаши' } ) ).toHaveClass( /is-active/ );
	const mugs = page.locator( 'li.product' );
	expect( await mugs.count() ).toBeLessThan( all );
	await expect( mugs.filter( { hasText: 'Комплект Stripe' } ) ).toHaveCount( 1 );
	await expect( mugs.filter( { hasText: 'Бизнес комплект Magnet' } ) ).toHaveCount( 0 );
	// Packages only: a single cup is a product, not a package.
	await expect( mugs.filter( { hasText: 'Чаша с капак Travel Black' } ) ).toHaveCount( 0 );

	const seal = mugs.filter( { hasText: 'Комплект Stripe' } ).locator( '.package-card__seal' );
	await expect( seal ).toContainText( '50 бр.' );
	await expect( seal ).toContainText( /409,99\s€/ );
} );

test( 'on a phone the category chips fit the screen and the cards sit two to a row', async ( { page } ) => {
	await page.setViewportSize( { width: 390, height: 844 } );
	for ( const url of [ '/promo-paketi/', '/produkti/' ] ) {
		await page.goto( url );
		const chips = page.locator( '.shop-chip' );
		for ( const box of await chips.evaluateAll( ( els ) => els.map( ( e ) => e.getBoundingClientRect().right ) ) ) {
			expect( box ).toBeLessThanOrEqual( 390 );
		}
		await expect( chips.first().locator( 'svg' ) ).toBeVisible();
		// Every card's price fits inside its round seal, the longest ones included.
		const seals = await page.locator( '.package-card__seal' ).evaluateAll( ( els ) => els.map( ( e ) => [ e.getBoundingClientRect().width, e.querySelector( '.woocommerce-Price-amount' ).getBoundingClientRect().width ] ) );
		for ( const [ circle, price ] of seals ) {
			expect( price ).toBeLessThan( circle * 0.9 );
		}
		// The chips span the page like the sort box under them, however few there are.
		const widths = await page.evaluate( () => [ '.shop-chips', '.shop-tools .woocommerce-ordering select' ].map( ( q ) => Math.round( document.querySelector( q ).getBoundingClientRect().width ) ) );
		expect( widths[ 0 ] ).toBe( widths[ 1 ] );
		const tops = await page.locator( 'ul.products li.product' ).evaluateAll( ( els ) => els.slice( 0, 2 ).map( ( e ) => Math.round( e.getBoundingClientRect().top ) ) );
		expect( tops[ 0 ] ).toBe( tops[ 1 ] );
		expect( await page.evaluate( () => document.documentElement.scrollWidth ) ).toBeLessThanOrEqual( 390 );
	}
} );

test( 'the homepage packages, Промо пакети, Продукти and the category pages end with the large-quantity band', async ( { page } ) => {
	for ( const url of [ '/', '/promo-paketi/', '/produkti/', '/product-category/kalendari/' ] ) {
		await page.goto( url );
		const band = page.locator( '.bulk-offer' );
		await expect( band.locator( 'h2' ) ).toHaveText( /Различни количества\?/i );
		await expect( band.locator( '.bulk-offer__reasons li' ) ).toHaveText( [ /Конкурентни цени/i, /Индивидуален дизайн/i, /Бърза доставка/i ] );
		// A new email to the shop, subject and outline filled in.
		const mail = new URL( await band.locator( '.bulk-offer__cta' ).getAttribute( 'href' ) );
		expect( mail.protocol + mail.pathname ).toBe( 'mailto:office@reklamo.bg' );
		expect( mail.searchParams.get( 'subject' ) ).toBe( 'Запитване за индивидуална оферта' );
		expect( mail.searchParams.get( 'body' ) ).toContain( 'Количество:' );
	}
	await page.goto( '/' );
	await expect( page.locator( '.packages .bulk-offer' ) ).toHaveCount( 1 );
} );

test( 'the homepage hero shows the Magnet package: its photo, the badge and its price from the shop, linking to it', async ( { page } ) => {
	await page.goto( '/' );
	const badge = page.locator( '.hero__badge' );
	await expect( badge.locator( '.eyebrow' ) ).toHaveText( 'Metal Business Pack' );
	await expect( badge ).toContainText( '50 тефтера' );
	await expect( badge.locator( 'strong' ) ).toHaveText( /^[\d\s]+(,\d\d)?\s€$/ );
	await expect( page.locator( '.hero__photo img' ) ).toHaveAttribute( 'src', /biznes-komplekt-magnet-red/ );
	await expect( page.locator( '.hero__photo' ) ).toHaveAttribute( 'href', /\/product\/biznes-komplekt-magnet\/$/ );
	// The same price the package card shows.
	const card = page.locator( '.packages li.product', { hasText: 'Бизнес комплект Magnet' } ).locator( '.package-card__amount' );
	expect( ( await badge.locator( 'strong' ).innerText() ).replace( /\s/g, '' ) ).toBe( ( await card.innerText() ).replace( /\s/g, '' ) );
} );

test( 'the two hero buttons are the same size, side by side on a laptop and stacked on a phone', async ( { page } ) => {
	const boxes = async () => page.locator( '.hero .wp-block-button__link' ).evaluateAll( ( links ) => links.map( ( a ) => {
		const r = a.getBoundingClientRect();
		return { w: Math.round( r.width ), h: Math.round( r.height ), x: Math.round( r.x ), y: Math.round( r.y ) };
	} ) );
	await page.goto( '/' );
	let [ filled, outline ] = await boxes();
	expect( [ outline.w, outline.h, outline.y ] ).toEqual( [ filled.w, filled.h, filled.y ] );
	await page.setViewportSize( { width: 390, height: 844 } );
	[ filled, outline ] = await boxes();
	expect( [ outline.w, outline.h, outline.x ] ).toEqual( [ filled.w, filled.h, filled.x ] );
	expect( outline.y ).toBeGreaterThan( filled.y );
} );

test( 'Бутилка Matt is one product in two colours, each with its own photos, at the same price', async ( { page } ) => {
	await page.goto( '/product/butilka-matt/' );
	const form = page.locator( 'form.rq-form--product' );
	const slides = () => page.locator( '.woocommerce-product-gallery__image img' ).evaluateAll( ( imgs ) => imgs.map( ( i ) => i.getAttribute( 'src' ) ) );
	await expect( form.locator( '[data-attribute="attribute_pa_cvyat"] input' ) ).toHaveCount( 2 );
	await expect( page.locator( '[data-rk-badge-price]' ) ).toHaveText( /519,99\s€/ );
	expect( ( await slides() ).every( ( s ) => s.includes( '-blue' ) ) ).toBe( true );

	await form.locator( '.rk-option', { has: page.locator( 'input[value="byal"]' ) } ).click();
	await expect.poll( async () => ( await slides() ).every( ( s ) => s.includes( '-white' ) ) ).toBe( true );
	await expect( page.locator( '[data-rk-badge-price]' ) ).toHaveText( /519,99\s€/ );
	await form.locator( '.rk-option', { has: page.locator( 'input[value="150-br"]' ) } ).click();
	await expect( page.locator( '[data-rk-badge-price]' ) ).toHaveText( /1\s549,99\s€/ );
} );

test( 'the second batch is in its categories: lighters, pens and Trio under Технологии', async ( { page } ) => {
	for ( const [ cat, names ] of [ [ 'zapalki', [ 'Запалка Chrome', 'Запалка Spark' ] ], [ 'himikalki', [ 'Метална химикалка Slim', 'Метална химикалка Soft' ] ], [ 'tehnologii', [ 'Комплект Trio' ] ] ] ) {
		await page.goto( `/product-category/${ cat }/` );
		for ( const name of names ) {
			await expect( page.locator( 'li.product', { hasText: name } ) ).toHaveCount( 1 );
		}
	}
} );

test( 'Корпоративни подаръци lists what is ticked into it, like Промо пакети, and invites an offer while empty', async ( { page, browser } ) => {
	const shop = await ( await browser.newContext( { locale: 'bg-BG' } ) ).newPage();
	await shop.goto( '/korporativni-podaratsi/' );
	await expect( shop.locator( '.shop-hero h1' ) ).toHaveText( /Корпоративни подаръци/i );
	await expect( shop.locator( '.shop-chip' ).first() ).toHaveText( /Всички подаръци/i );
	await expect( shop.locator( '.shop-empty' ) ).toContainText( 'Подбираме корпоративните подаръци' );
	await expect( shop.locator( '.shop-empty a' ) ).toHaveAttribute( 'href', /kontakti/ );

	// The owner ticks the category on a product; untick it whatever happens, for the other tests.
	await adminLogin( page );
	const id = ( await ( await page.request.get( '/wp-json/wc/store/v1/products?sku=TEF-002' ) ).json() )[ 0 ].id;
	const save = () => Promise.all( [ page.waitForURL( /message=/, { waitUntil: 'commit' } ), page.locator( '#publish' ).evaluate( ( b ) => b.click() ) ] );
	const tick = async ( on ) => {
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
		await page.locator( '#product_catchecklist label', { hasText: 'Корпоративни подаръци' } ).locator( 'input' ).setChecked( on );
		await save();
	};
	await tick( true );
	try {
		await shop.goto( '/korporativni-podaratsi/' );
		await expect( shop.locator( 'li.product h3' ) ).toHaveText( [ /Тефтер Vintage Black/i ] );
		await expect( shop.locator( '.shop-empty' ) ).toHaveCount( 0 );
		// Its own category becomes a chip that narrows the page in place; the collection itself never is one.
		const chip = shop.locator( '.shop-chip', { hasText: 'Тефтери' } );
		await expect( chip ).toHaveAttribute( 'href', /korporativni-podaratsi\/\?kategoria=tefteri/ );
		await expect( shop.locator( '.shop-chip', { hasText: 'Корпоративни подаръци' } ) ).toHaveCount( 0 );
		await chip.click();
		await expect( shop.locator( 'li.product h3' ) ).toHaveText( [ /Тефтер Vintage Black/i ] );
		await shop.goto( '/produkti/' );
		await expect( shop.locator( '.shop-chip', { hasText: 'Корпоративни подаръци' } ) ).toHaveCount( 0 );
	} finally {
		await tick( false );
	}
	await shop.goto( '/korporativni-podaratsi/' );
	await expect( shop.locator( '.shop-empty' ) ).toBeVisible();
	await shop.context().close();
} );

<?php
/**
 * The catalogue from the client's photos (design/v3/web product, 2026-10-12), run by seed.sh
 * through `wp eval-file`. Once (reklamo_seeded_catalogue_v3): afterwards names, prices, texts
 * and photos belong to the owner in Products. Two steps run every time: specifications and the
 * section photo are filled while a product has none (an earlier seeded section shot is replaced).
 *
 * Every product and package is variable on "Количество" with one price per size. The prices are
 * SAMPLES until the owner sets the real ones (owner's decision: visible with sample prices).
 * Photos come from the theme's assets/img/catalogue/, re-encoded from the client's PNGs: the
 * "ВАШЕТО ЛОГО" shot is the product image, the rest the gallery, the wide shot the photo behind
 * the description. The earlier sample packages and the six calendar tiers go to draft; each old
 * address redirects to the closest new product through WordPress's own `_wp_old_slug`.
 *
 * @package Reklamo
 */

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Bulgarian site content from here on.
$catalogue = array(
	array(
		'sku'      => 'PKG-MAGNET',
		'name'     => 'Бизнес комплект Magnet',
		'slug'     => 'biznes-komplekt-magnet',
		'cats'     => array( 'paketi', 'tefteri', 'himikalki' ),
		'order'    => 1,
		'featured' => true,
		'prices'   => array(
			'cherven' => array( 50 => '539.99', 100 => '1079.99', 150 => '1589.99', 200 => '2109.99' ),
			'byal'    => array( 50 => '539.99', 100 => '1079.99', 150 => '1589.99', 200 => '2115.99' ),
			'sin'     => array( 50 => '349.99', 100 => '689.99', 150 => '999.99', 200 => '1329.99' ),
		),
		'short'    => '<p>Тефтер и метална химикалка в един цвят — червен, бял или син — с Вашето лого на двете.</p><ul><li><strong>50 бр. Тефтери</strong><br>Твърда корица, с Вашето лого</li><li><strong>50 бр. Метални химикалки</strong><br>В цвета на тефтера, с Вашето лого</li></ul>',
		'desc'     => '<p>Бизнес комплект Magnet събира тефтер и химикалка в един цвят, с Вашето лого на двете. Изчистен и практичен подарък, който се използва всеки ден и държи бранда Ви пред очите на екипа, клиентите и партньорите.</p><p>Подходящ за конференции, обучения, нови служители и корпоративни подаръци.</p>',
		'specs'  => '<ul><li><strong>Тефтер:</strong> твърда корица, лентичка за отбелязване</li><li><strong>Химикалка:</strong> метална, в цвета на тефтера</li><li><strong>Цветове:</strong> червен, бял или син</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'colours'  => array(
			'cherven' => array( 'red', 'червен' ),
			'byal'    => array( 'white', 'бял' ),
			'sin'     => array( 'blue', 'син' ),
		),
	),
	array(
		'sku'    => 'PKG-STRIPE',
		'name'   => 'Комплект Stripe – тефтер, чаша и химикалка',
		'slug'   => 'komplekt-stripe',
		'cats'   => array( 'paketi', 'tefteri', 'butilki-i-chashi', 'himikalki' ),
		'order'  => 2,
		'prices' => array( 50 => '409.99', 100 => '819.99', 150 => '1199.99', 200 => '1599.99' ),
		'short'  => '<p>Изцяло черен комплект: тефтер с релефна корица, чаша и химикалка, всяко с Вашето лого.</p><ul><li><strong>50 бр. Тефтери</strong><br>Релефна корица, с Вашето лого</li><li><strong>50 бр. Чаши</strong><br>Черни, с Вашето лого</li><li><strong>50 бр. Химикалки</strong><br>Черни, с Вашето лого</li></ul>',
		'desc'   => '<p>Комплект Stripe е в изцяло черно — тефтер с релефна корица, чаша и химикалка, с Вашето лого в бяло. Стилен и сдържан избор за офиса и за подарък на клиенти и партньори.</p>',
		'specs'  => '<ul><li><strong>Тефтер:</strong> черен, с релефна корица на райета и лентичка за отбелязване</li><li><strong>Чаша:</strong> черна, с бяло лого</li><li><strong>Химикалка:</strong> черна, с хромирани детайли</li><li><strong>Цвят:</strong> черен</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'komplekt-stripe/main', 'Комплект Stripe — черен тефтер, чаша и химикалка с лого' ),
			'gallery' => array(
				array( 'komplekt-stripe/notebook', 'Черен тефтер с релефна корица и лого' ),
				array( 'komplekt-stripe/mug', 'Черна чаша с лого' ),
				array( 'komplekt-stripe/pen', 'Черна химикалка с лого' ),
			),
			'section' => array( 'komplekt-stripe/section-centred', 'Комплект Stripe — тефтер, чаша и химикалка' ),
		),
	),
	array(
		'sku'    => 'PKG-CORK',
		'name'   => 'Комплект Cork – чаша, тефтер и химикалка',
		'slug'   => 'komplekt-cork',
		'cats'   => array( 'paketi', 'tefteri', 'butilki-i-chashi', 'himikalki' ),
		'order'  => 3,
		'prices' => array( 50 => '619.99', 100 => '1239.99', 150 => '1819.99', 200 => '2429.99' ),
		'short'  => '<p>Жълто и черно, което се забелязва: чаша с коркова основа, тефтер с ластик и химикалка в тон.</p><ul><li><strong>50 бр. Чаши</strong><br>Жълти, с коркова основа и Вашето лого</li><li><strong>50 бр. Тефтери</strong><br>Черни, с жълт ластик и Вашето лого</li><li><strong>50 бр. Химикалки</strong><br>Жълти, с Вашето лого</li></ul>',
		'desc'   => '<p>Комплект Cork съчетава ярко жълто и черно — чаша с коркова основа, тефтер с ластик и химикалка в тон, с Вашето лого. Весел и запомнящ се подарък за екип, събитие или клиенти.</p>',
		'specs'  => '<ul><li><strong>Чаша:</strong> жълта, с коркова основа</li><li><strong>Тефтер:</strong> черен, с жълт ластик за затваряне и жълта лентичка</li><li><strong>Химикалка:</strong> жълта, метална, с хромирани детайли</li><li><strong>Цвят:</strong> жълто и черно</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'komplekt-cork/main', 'Комплект Cork — жълта чаша, черен тефтер и жълта химикалка с лого' ),
			'gallery' => array(
				array( 'komplekt-cork/notebook', 'Черен тефтер с жълт ластик и лого' ),
				array( 'komplekt-cork/mug', 'Жълта чаша с коркова основа и лого' ),
				array( 'komplekt-cork/pen', 'Жълта химикалка с лого' ),
			),
			'section' => array( 'komplekt-cork/section-centred', 'Комплект Cork — чаша, тефтер и химикалка' ),
		),
	),
	array(
		'sku'    => 'CAL',
		'name'   => 'Календар Mono',
		'slug'   => 'kalendar-mono',
		'cats'   => array( 'kalendari' ),
		'order'  => 10,
		'prices' => array( 50 => '137.99', 100 => '205.99', 200 => '391.99', 300 => '530.99', 500 => '845.99' ),
		'short'  => '<p>Едносекционен стенен календар с голяма глава за Вашия дизайн и лого.</p><ul><li>50 броя календари с индивидуален дизайн</li></ul>',
		'desc'   => '<p>Размер на подложката 320 x 240 мм. Размер на главата 320 x 240 мм.</p>',
		'specs'  => '<p>Всички тела за календари се изработват от 70 г/м2 офсетова хартия. С перфорация и шапка и прозорче за отбелязване на дата. Съдържат информация за:</p><ul><li>номер на седмица,</li><li>фази на луната,</li><li>начало на астрологичната зодия,</li><li>официални и православни празници в България.</li></ul><p>Подложката е от 295 г/м2 едностранно хромов картон Зенит, бигована за сгъване.</p>',
		'photos' => array(
			'main'    => array( 'kalendar-mono/main', 'Календар Mono с Вашия дизайн' ),
			'section' => array( 'kalendar-mono/section-centred', 'Стенен календар Mono с Вашето лого' ),
		),
	),
	array(
		'sku'    => 'CAL-TRIO',
		'name'   => 'Трисекционен календар Trio',
		'slug'   => 'trisektsionen-kalendar-trio',
		'cats'   => array( 'kalendari' ),
		'order'  => 11,
		'prices' => array( 50 => '151.99', 100 => '286.99', 200 => '538.99', 300 => '786.99', 500 => '1274.99' ),
		'short'  => '<p>Стенен календар с три месеца наведнъж и голяма глава за Вашия дизайн и лого.</p><ul><li>50 броя календари с индивидуален дизайн</li></ul>',
		'desc'   => '<p>Трисекционният календар показва предходния, текущия и следващия месец, с подвижно прозорче за датата. Главата е изцяло за Вашия дизайн — лого, слоган и контакти стоят на стената на клиента цяла година.</p>',
		'specs'  => '<p>Всички тела за календари се изработват от 70 г/м2 офсетова хартия. С перфорация и шапка и прозорче за отбелязване на дата. Съдържат информация за:</p><ul><li>номер на седмица,</li><li>фази на луната,</li><li>начало на астрологичната зодия,</li><li>официални и православни празници в България.</li></ul><ul><li><strong>Вид:</strong> стенен трисекционен календар, три месеца наведнъж, с подвижно прозорче за датата.</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 200, 300 или 500 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'kalendar-trio/main', 'Трисекционен календар Trio с Вашия дизайн' ),
			'section' => array( 'kalendar-trio/section-centred', 'Трисекционен календар с Вашето лого' ),
		),
	),
	array(
		'sku'    => 'CAL-PHOTO',
		'name'   => 'Персонализиран настолен календар Photo',
		'slug'   => 'nastolen-kalendar-photo',
		'cats'   => array( 'kalendari' ),
		'order'  => 12,
		'prices' => array( 10 => '71.99', 20 => '119.99' ),
		'short'  => '<p>Настолен календар-пирамидка с Вашите снимки за всеки месец.</p><ul><li>10 броя календари с Вашите снимки и лого</li></ul>',
		'desc'   => '<p>Календар-пирамидка за бюрото, със снимка по Ваш избор за всеки месец и Вашето лого. Личен подарък, който остава пред очите на клиента всеки ден.</p>',
		'specs'  => '<ul><li><strong>Вид:</strong> настолен календар-пирамидка със спирала</li><li><strong>Снимки:</strong> по една Ваша снимка за всеки месец</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 10 или 20 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'kalendar-photo/main', 'Настолен календар-пирамидка със снимки на клиента' ),
			'section' => array( 'kalendar-photo/section-centred', 'Настолен календар-пирамидка с Вашите снимки' ),
		),
	),
	array(
		'sku'    => 'TEF-002',
		'name'   => 'Тефтер Vintage Black',
		'slug'   => 'tefter-vintage-black',
		'cats'   => array( 'tefteri' ),
		'order'  => 20,
		'prices' => array( 50 => '222.99', 100 => '444.99', 150 => '666.99', 200 => '899.99' ),
		'short'  => '<p>Черен тефтер с ластик и лентичка за отбелязване — класика, която стои добре с всяко лого.</p><ul><li>50 бр. тефтери с Вашето лого</li></ul>',
		'desc'   => '<p>Тефтер Vintage Black е с мека на допир черна корица, ластик за затваряне и лентичка за отбелязване. Вашето лого стои изчистено на корицата — за срещи, обучения и подарък.</p>',
		'specs'  => '<ul><li><strong>Корица:</strong> черна, мека на допир</li><li><strong>Детайли:</strong> ластик за затваряне и лентичка за отбелязване</li><li><strong>Цвят:</strong> черен</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'tefter-vintage-black/main', 'Тефтер Vintage Black с Вашето лого' ),
			'gallery' => array(
				array( 'tefter-vintage-black/logo', 'Черен тефтер с бяло лого' ),
				array( 'tefter-vintage-black/plain', 'Черен тефтер без лого' ),
			),
			'section' => array( 'tefter-vintage-black/section-centred', 'Черен тефтер с лого' ),
		),
	),
	array(
		'sku'    => 'TEF-004',
		'name'   => 'Тефтер Contrast Red',
		'slug'   => 'tefter-contrast-red',
		'cats'   => array( 'tefteri' ),
		'order'  => 21,
		'prices' => array( 50 => '329.99', 100 => '649.99', 150 => '969.99', 200 => '1289.99' ),
		'short'  => '<p>Червен тефтер с черна лента и държач за химикалка — ярък и запомнящ се.</p><ul><li>50 бр. тефтери с Вашето лого</li></ul>',
		'desc'   => '<p>Тефтер Contrast Red съчетава наситено червено с черна лента и държач за химикалка. Вашето лого изпъква на корицата — подарък, който се забелязва на всяко бюро.</p>',
		'specs'  => '<ul><li><strong>Корица:</strong> червена</li><li><strong>Детайли:</strong> черна лента с държач за химикалка и лентичка за отбелязване</li><li><strong>Цвят:</strong> червен с черно</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'tefter-contrast-red/main', 'Тефтер Contrast Red с Вашето лого' ),
			'gallery' => array(
				array( 'tefter-contrast-red/logo', 'Червен тефтер с бяло лого' ),
				array( 'tefter-contrast-red/plain', 'Червен тефтер без лого' ),
			),
			'section' => array( 'tefter-contrast-red/section-centred', 'Червен тефтер с лого' ),
		),
	),
	array(
		'sku'    => 'CHA-001',
		'name'   => 'Памучна торба Basic Black',
		'slug'   => 'pamuchna-torba-basic-black',
		'cats'   => array( 'chanti' ),
		'order'  => 30,
		'prices' => array( 50 => '183.99', 100 => '366.99', 150 => '549.99', 200 => '729.99' ),
		'short'  => '<p>Черна памучна торба с дълги дръжки — практична за всеки ден и за събития.</p><ul><li>50 бр. торби с Вашето лого</li></ul>',
		'desc'   => '<p>Памучната торба Basic Black се носи на рамо и побира всичко за деня. С Вашето лого тя е реклама, която обикаля града — за събития, конференции и магазини.</p>',
		'specs'  => '<ul><li><strong>Материал:</strong> памук</li><li><strong>Дръжки:</strong> дълги, за носене на рамо</li><li><strong>Цвят:</strong> черен</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'pamuchna-torba-basic-black/main', 'Черна памучна торба с Вашето лого' ),
			'gallery' => array(
				array( 'pamuchna-torba-basic-black/logo', 'Черна памучна торба с бяло лого' ),
				array( 'pamuchna-torba-basic-black/plain', 'Черна памучна торба без лого' ),
			),
			'section' => array( 'pamuchna-torba-basic-black/section-centred', 'Черна памучна торба с лого' ),
		),
	),
	array(
		'sku'    => 'CHA-003',
		'name'   => 'Памучна торба Classic Red',
		'slug'   => 'pamuchna-torba-classic-red',
		'cats'   => array( 'chanti' ),
		'order'  => 31,
		'prices' => array( 50 => '215.99', 100 => '431.99', 150 => '647.99', 200 => '863.99' ),
		'short'  => '<p>Червена памучна торба с дълги дръжки — ярка и удобна.</p><ul><li>50 бр. торби с Вашето лого</li></ul>',
		'desc'   => '<p>Памучната торба Classic Red е в наситено червено, с дълги дръжки за носене на рамо. Вашето лого в бяло изпъква отдалеч — за събития, промоции и подарък.</p>',
		'specs'  => '<ul><li><strong>Материал:</strong> памук</li><li><strong>Дръжки:</strong> дълги, за носене на рамо</li><li><strong>Цвят:</strong> червен</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'pamuchna-torba-classic-red/main', 'Червена памучна торба с Вашето лого' ),
			'gallery' => array(
				array( 'pamuchna-torba-classic-red/logo', 'Червена памучна торба с бяло лого' ),
				array( 'pamuchna-torba-classic-red/plain', 'Червена памучна торба без лого' ),
			),
			'section' => array( 'pamuchna-torba-classic-red/section-centred', 'Червена памучна торба с лого' ),
		),
	),
	array(
		'sku'    => 'BUT-002',
		'name'   => 'Чаша с капак Travel Black',
		'slug'   => 'chasha-s-kapak-travel-black',
		'cats'   => array( 'butilki-i-chashi' ),
		'order'  => 40,
		'prices' => array( 50 => '319.99', 100 => '639.99', 150 => '959.99', 200 => '1269.99' ),
		'short'  => '<p>Черна чаша за път с прозрачен капак — за кафето в офиса и в колата.</p><ul><li>50 бр. чаши с Вашето лого</li></ul>',
		'desc'   => '<p>Чашата Travel Black е с матово черно покритие и капак с отвор за пиене. Вашето лого е с нея всеки ден — на бюрото, в колата и на път.</p>',
		'specs'  => '<ul><li><strong>Корпус:</strong> черен, матово покритие</li><li><strong>Капак:</strong> прозрачен, с отвор за пиене и капаче</li><li><strong>Цвят:</strong> черен</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'chasha-travel-black/main', 'Черна чаша с капак и Вашето лого' ),
			'gallery' => array(
				array( 'chasha-travel-black/logo', 'Черна чаша с капак и бяло лого' ),
				array( 'chasha-travel-black/plain', 'Черна чаша с капак без лого' ),
			),
			'section' => array( 'chasha-travel-black/section-centred', 'Черна чаша с капак и лого' ),
		),
	),
	array(
		'sku'    => 'BUT-004',
		'name'   => 'Бутилка Bamboo Black',
		'slug'   => 'butilka-bamboo-black',
		'cats'   => array( 'butilki-i-chashi' ),
		'order'  => 41,
		'prices' => array( 50 => '609.99', 100 => '1219.99', 150 => '1829.99', 200 => '2429.99' ),
		'short'  => '<p>Матова черна бутилка с бамбукова капачка — елегантна и за многократна употреба.</p><ul><li>50 бр. бутилки с Вашето лого</li></ul>',
		'desc'   => '<p>Бутилката Bamboo Black съчетава матово черно с топлината на бамбука. Вашето лого стои изчистено на корпуса — подарък, който се носи навсякъде.</p>',
		'specs'  => '<ul><li><strong>Корпус:</strong> черен, матово покритие</li><li><strong>Капачка:</strong> бамбукова</li><li><strong>Употреба:</strong> многократна</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'butilka-bamboo-black/main', 'Черна бутилка с бамбукова капачка и Вашето лого' ),
			'gallery' => array(
				array( 'butilka-bamboo-black/logo', 'Черна бутилка със златно лого' ),
				array( 'butilka-bamboo-black/plain', 'Черна бутилка с бамбукова капачка без лого' ),
			),
			'section' => array( 'butilka-bamboo-black/section-centred', 'Черна бутилка с бамбукова капачка и лого' ),
		),
	),
	array(
		'sku'    => 'HIM-001-BK',
		'name'   => 'Метална химикалка Classic',
		'slug'   => 'metalna-himikalka-classic',
		'cats'   => array( 'himikalki' ),
		'order'  => 50,
		'prices' => array( 50 => '45.99', 100 => '99.99', 150 => '139.99', 200 => '189.99' ),
		'short'  => '<p>Черна метална химикалка с хромирани детайли — класика за всеки офис.</p><ul><li>50 бр. химикалки с Вашето лого</li></ul>',
		'desc'   => '<p>Металната химикалка Classic е в лъскаво черно с хромирани пръстени и връх. Вашето лого стои по дължината на корпуса — за рецепции, конференции и подаръчни комплекти.</p>',
		'specs'  => '<ul><li><strong>Корпус:</strong> метален, лъскаво черен</li><li><strong>Детайли:</strong> хромирани пръстени, клипс и връх</li><li><strong>Механизъм:</strong> с натискане</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'himikalka-classic/main', 'Черна метална химикалка с Вашето лого' ),
			'gallery' => array(
				array( 'himikalka-classic/logo', 'Черна метална химикалка с бяло лого' ),
				array( 'himikalka-classic/plain', 'Черна метална химикалка без лого' ),
			),
			'section' => array( 'himikalka-classic/section-centred', 'Черна метална химикалка с лого' ),
		),
	),
	array(
		'sku'    => 'HIM-004',
		'name'   => 'Метална химикалка Stone White',
		'slug'   => 'metalna-himikalka-stone-white',
		'cats'   => array( 'himikalki' ),
		'order'  => 51,
		'prices' => array( 50 => '43.99', 100 => '87.99', 150 => '131.99', 200 => '175.99' ),
		'short'  => '<p>Бяла метална химикалка с графитени детайли — изчистена и премиум.</p><ul><li>50 бр. химикалки с Вашето лого</li></ul>',
		'desc'   => '<p>Металната химикалка Stone White е в матово бяло с графитени детайли. Вашето лого изпъква ясно на светлия корпус — практичен и стилен избор за офиса и събития.</p>',
		'specs'  => '<ul><li><strong>Корпус:</strong> метален, матово бял</li><li><strong>Детайли:</strong> графитен клипс и връх</li><li><strong>Механизъм:</strong> с натискане</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'himikalka-stone-white/main', 'Бяла метална химикалка с Вашето лого' ),
			'gallery' => array(
				array( 'himikalka-stone-white/logo', 'Бяла метална химикалка с черно лого' ),
				array( 'himikalka-stone-white/plain', 'Бяла метална химикалка без лого' ),
			),
			'section' => array( 'himikalka-stone-white/section-centred', 'Бяла метална химикалка с лого' ),
		),
	),
	array(
		'sku'    => 'ZAP-001-BK',
		'name'   => 'Запалка Satin Black',
		'slug'   => 'zapalka-satin-black',
		'cats'   => array( 'zapalki' ),
		'order'  => 60,
		'prices' => array( 50 => '46.99', 100 => '93.99', 150 => '139.99', 200 => '189.99' ),
		'short'  => '<p>Черна запалка с матово покритие — малък подарък, който се използва всеки ден.</p><ul><li>50 бр. запалки с Вашето лого</li></ul>',
		'desc'   => '<p>Запалката Satin Black е с матово черно покритие и метален капак. Вашето лого стои по дължината на корпуса — евтина и ефективна реклама, която минава от ръка на ръка.</p>',
		'specs'  => '<ul><li><strong>Корпус:</strong> черен, матово покритие</li><li><strong>Капак:</strong> метален</li><li><strong>Цвят:</strong> черен</li><li><strong>Брандиране:</strong> Вашето лого, с печат или гравиране според материала. Точното разположение виждате на визуализацията преди производството.</li><li><strong>Количества:</strong> 50, 100, 150 или 200 бр.</li></ul>',
		'photos' => array(
			'main'    => array( 'zapalka-satin-black/main', 'Черна запалка с Вашето лого' ),
			'gallery' => array(
				array( 'zapalka-satin-black/logo', 'Черна запалка с бяло лого' ),
				array( 'zapalka-satin-black/plain', 'Черна запалка без лого' ),
			),
			'section' => array( 'zapalka-satin-black/section-centred', 'Черна запалка с лого' ),
		),
	),
);

// A product's prices by size: one table, or one per colour (Magnet's colours are priced apart).
$price_table = static function ( array $item, string $colour = '' ): array {
	$prices = $item['prices'];
	return is_array( reset( $prices ) ) ? ( $prices[ $colour ] ?? reset( $prices ) ) : $prices;
};

// One attachment per photo: found again by its source path, so a re-run never duplicates it.
$import = static function ( array $photo, int $parent ): int {
	list( $rel, $alt ) = $photo;
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_reklamo_source', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $rel, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}
	$src = get_theme_file_path( 'assets/img/catalogue/' . $rel . '.webp' );
	if ( ! file_exists( $src ) ) {
		echo "  ! $rel: image missing\n";
		return 0;
	}
	$tmp = wp_tempnam( basename( $src ) );
	copy( $src, $tmp );
	$id = media_handle_sideload(
		array(
			'name'     => str_replace( '/', '-', $rel ) . '.webp',
			'tmp_name' => $tmp,
		),
		$parent,
		$alt
	);
	if ( is_wp_error( $id ) ) {
		echo '  ! ' . $rel . ': ' . $id->get_error_message() . "\n";
		return 0;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, '_reklamo_source', $rel );
	return (int) $id;
};

$term_ids = static function ( string $taxonomy, array $slugs ): array {
	$ids = array();
	foreach ( $slugs as $slug ) {
		$t = get_term_by( 'slug', $slug, $taxonomy );
		if ( $t ) {
			$ids[] = (int) $t->term_id;
		}
	}
	return $ids;
};

$attribute = static function ( string $taxonomy, array $options, int $position ): WC_Product_Attribute {
	$a = new WC_Product_Attribute();
	$a->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
	$a->set_name( $taxonomy );
	$a->set_options( $options );
	$a->set_position( $position );
	$a->set_visible( true );
	$a->set_variation( true );
	return $a;
};

$set_photos = static function ( WC_Product $product, array $photos ) use ( $import ): void {
	$id = $product->get_id();
	if ( isset( $photos['main'] ) ) {
		$product->set_image_id( $import( $photos['main'], $id ) );
	}
	$product->set_gallery_image_ids( array_filter( array_map( static fn( $p ) => $import( $p, $id ), $photos['gallery'] ?? array() ) ) );
	if ( isset( $photos['section'] ) ) {
		$product->update_meta_data( '_reklamo_section_image', $import( $photos['section'], $id ) );
	}
};

// Specifications, on every run but only while a product has none: the owner's text always wins.
foreach ( $catalogue as $item ) {
	$id = wc_get_product_id_by_sku( $item['sku'] );
	if ( $id && isset( $item['specs'] ) && '' === (string) get_post_meta( $id, '_reklamo_specs', true ) ) {
		update_post_meta( $id, '_reklamo_specs', $item['specs'] );
		echo "  {$item['name']}: specifications filled\n";
	}
}

// The photo behind the tabs: the client's wide shots had the product squeezed against the right edge,
// so they were recomposed with it centred (section-centred). A product with none, or still showing
// the earlier seeded shot, gets the new one; a photo the owner chose is never touched.
foreach ( $catalogue as $item ) {
	$id  = wc_get_product_id_by_sku( $item['sku'] );
	$new = isset( $item['colours'] ) ? array( $item['slug'] . '/' . reset( $item['colours'] )[0] . '-section-centred', $item['name'] ) : ( $item['photos']['section'] ?? null );
	if ( ! $id || ! $new ) {
		continue;
	}
	$current = (int) get_post_meta( $id, '_reklamo_section_image', true );
	if ( ! $current || get_post_meta( $current, '_reklamo_source', true ) === str_replace( '-centred', '', $new[0] ) ) {
		$att = $import( $new, $id );
		if ( $att ) {
			update_post_meta( $id, '_reklamo_section_image', $att );
			echo "  {$item['name']}: section photo set\n";
		}
	}
}

// The client's real prices (2026-10-15): other sizes than the samples (50 / 100 / 150 / 200, the
// calendars 50 to 500, Photo 10 and 20) and Magnet priced per colour. Once, on a site built with the
// sample prices: each product gets exactly these sizes (a missing one added with the colour's photos,
// a dropped one deleted), these prices, and its texts' smallest quantity. Later edits are the owner's.
if ( ! get_option( 'reklamo_seeded_prices_v2' ) ) {
	foreach ( $catalogue as $item ) {
		$p = wc_get_product( wc_get_product_id_by_sku( $item['sku'] ) );
		if ( ! $p || ! $p->is_type( 'variable' ) ) {
			continue;
		}
		$sizes = array_keys( $price_table( $item ) );
		$attrs = $p->get_attributes();
		$attrs['pa_kolichestvo'] = $attribute( 'pa_kolichestvo', $term_ids( 'pa_kolichestvo', array_map( static fn( $q ) => $q . '-br', $sizes ) ), isset( $attrs['pa_cvyat'] ) ? 1 : 0 );
		$p->set_attributes( $attrs );
		$p->set_default_attributes( array_merge( $p->get_default_attributes(), array( 'pa_kolichestvo' => $sizes[0] . '-br' ) ) );
		$min  = (string) $sizes[0];
		$p->set_short_description( (string) preg_replace( '~(<li>(?:<strong>)?)20 (бр\.|броя)~u', '${1}' . $min . ' $2', $p->get_short_description() ) );
		$specs = (string) $p->get_meta( '_reklamo_specs' );
		if ( isset( $item['specs'] ) && preg_match( '~<li><strong>Количества:</strong>.*?</li>~u', $item['specs'], $line ) ) {
			$p->update_meta_data( '_reklamo_specs', str_replace( '<li><strong>Количества:</strong> 20, 50, 100 или 200 бр.</li>', $line[0], $specs ) );
		}
		$p->save();

		$have = array();
		foreach ( $p->get_children() as $child ) {
			$v      = wc_get_product( $child );
			$a      = $v ? $v->get_attributes() : array();
			$colour = (string) ( $a['pa_cvyat'] ?? '' );
			$qty    = (int) ( $a['pa_kolichestvo'] ?? 0 );
			if ( ! $v || ! in_array( $qty, $sizes, true ) ) {
				$v && $v->delete( true );
				continue;
			}
			$v->set_regular_price( $price_table( $item, $colour )[ $qty ] );
			$v->save();
			$have[ $colour ][ $qty ] = $v;
		}
		foreach ( $have as $colour => $by_size ) {
			$model = reset( $by_size );
			foreach ( $price_table( $item, (string) $colour ) as $qty => $price ) {
				if ( isset( $by_size[ $qty ] ) ) {
					continue;
				}
				$v = new WC_Product_Variation();
				$v->set_parent_id( $p->get_id() );
				$v->set_attributes( array_filter( array( 'pa_cvyat' => (string) $colour, 'pa_kolichestvo' => $qty . '-br' ) ) );
				$v->set_regular_price( $price );
				$v->set_manage_stock( false );
				$v->set_image_id( $model->get_image_id( 'edit' ) );
				$gallery = $model->get_meta( '_reklamo_variation_gallery' );
				if ( $gallery ) {
					$v->update_meta_data( '_reklamo_variation_gallery', $gallery );
				}
				$v->save();
			}
		}
		WC_Product_Variable::sync( $p->get_id() );
		echo "  {$item['name']}: prices and sizes " . implode( ' / ', $sizes ) . "\n";
	}
	update_option( 'reklamo_seeded_prices_v2', 1 );
}

if ( get_option( 'reklamo_seeded_catalogue_v3' ) ) {
	return;
}

// Create a product that is still missing, with a variation per colour and size.
foreach ( $catalogue as $item ) {
	if ( wc_get_product_id_by_sku( $item['sku'] ) ) {
		continue;
	}
	$p = new WC_Product_Variable();
	$p->set_name( $item['name'] );
	$p->set_slug( $item['slug'] );
	$p->set_sku( $item['sku'] );
	$p->set_status( 'publish' );
	$p->set_short_description( $item['short'] );
	$p->set_description( $item['desc'] );
	$p->set_category_ids( $term_ids( 'product_cat', $item['cats'] ) );
	$p->set_menu_order( $item['order'] );
	$p->set_featured( ! empty( $item['featured'] ) );
	if ( isset( $item['specs'] ) ) {
		$p->update_meta_data( '_reklamo_specs', $item['specs'] );
	}
	$size_slugs = array_map( static fn( $q ) => $q . '-br', array_keys( $price_table( $item ) ) );
	$attrs      = array();
	$defaults   = array( 'pa_kolichestvo' => $size_slugs[0] );
	if ( ! empty( $item['colours'] ) ) {
		$attrs['pa_cvyat']    = $attribute( 'pa_cvyat', $term_ids( 'pa_cvyat', array_keys( $item['colours'] ) ), 0 );
		$defaults['pa_cvyat'] = (string) array_key_first( $item['colours'] );
	}
	$attrs['pa_kolichestvo'] = $attribute( 'pa_kolichestvo', $term_ids( 'pa_kolichestvo', $size_slugs ), count( $attrs ) );
	$p->set_attributes( $attrs );
	$p->set_default_attributes( $defaults );
	$p->save();

	$folder = $item['slug'];
	if ( ! empty( $item['colours'] ) ) {
		// The first colour's photos are the product's own; every colour's are its variations'.
		$first = reset( $item['colours'] );
		$set_photos(
			$p,
			array(
				'main'    => array( "$folder/{$first[0]}", "{$item['name']} — {$first[1]}" ),
				'gallery' => array(
					array( "$folder/{$first[0]}-notebook", "Тефтер — {$first[1]}, с Вашето лого" ),
					array( "$folder/{$first[0]}-pen", "Метална химикалка — {$first[1]}, с Вашето лого" ),
				),
				'section' => array( "$folder/{$first[0]}-section-centred", "{$item['name']} — {$first[1]}" ),
			)
		);
		$p->save();
	} else {
		$set_photos( $p, $item['photos'] );
		$p->save();
	}

	foreach ( $item['colours'] ?? array( '' => null ) as $colour => $c ) {
		foreach ( $price_table( $item, (string) $colour ) as $qty => $price ) {
			$v = new WC_Product_Variation();
			$v->set_parent_id( $p->get_id() );
			$v->set_attributes( array_filter( array( 'pa_cvyat' => $colour, 'pa_kolichestvo' => $qty . '-br' ) ) );
			$v->set_regular_price( $price );
			$v->set_manage_stock( false );
			if ( $c ) {
				$v->set_image_id( $import( array( "$folder/{$c[0]}", "{$item['name']} — {$c[1]}" ), $p->get_id() ) );
				$v->update_meta_data(
					'_reklamo_variation_gallery',
					implode(
						',',
						array_filter(
							array(
								$import( array( "$folder/{$c[0]}-notebook", "Тефтер — {$c[1]}, с Вашето лого" ), $p->get_id() ),
								$import( array( "$folder/{$c[0]}-pen", "Метална химикалка — {$c[1]}, с Вашето лого" ), $p->get_id() ),
							)
						)
					)
				);
			}
			$v->save();
		}
	}
	WC_Product_Variable::sync( $p->get_id() );
	echo "  {$item['name']}: created (sample prices)\n";
}

// Where the six tiers were already merged into one calendar, that product becomes "Календар Mono"
// (the create step above skipped it, its SKU being taken): the new photos, one for every size,
// since the new artwork no longer prints the price. Its six sizes and prices stay.
$mono_id = wc_get_product_id_by_sku( 'CAL' );
$mono    = $mono_id ? wc_get_product( $mono_id ) : null;
if ( $mono && 'Бизнес календар START' === $mono->get_name() ) {
	$mono->set_name( 'Календар Mono' );
	$mono->set_slug( 'kalendar-mono' );
	$mono->set_short_description( str_replace( 'Готов фирмен пакет за вашия бизнес. Практичен, стилен и запомнящ се.', 'Едносекционен стенен календар с голяма глава за Вашия дизайн и лого.', $mono->get_short_description() ) );
	$mono->set_menu_order( 10 );
	$set_photos(
		$mono,
		array(
			'main'    => array( 'kalendar-mono/main', 'Календар Mono с Вашия дизайн' ),
			'section' => array( 'kalendar-mono/section-centred', 'Стенен календар Mono с Вашето лого' ),
		)
	);
	$mono->save();
	foreach ( $mono->get_children() as $child ) {
		$v = wc_get_product( $child );
		$v->set_image_id( 0 );
		$v->save();
	}
	echo "  Бизнес календар START: now Календар Mono, new photos\n";
}

// Earlier samples → the new product their address now leads to.
$retire = array(
	'RBP'     => 'PKG-MAGNET',
	'PRP'     => 'PKG-STRIPE',
	'OSP'     => 'PKG-CORK',
	'EVP'     => 'CHA-001',
	'TCP'     => 'BUT-002',
	'GPP'     => 'HIM-001-BK',
	'WPP'     => 'HIM-004',
	'CAL-20'  => 'CAL',
	'CAL-50'  => 'CAL',
	'CAL-100' => 'CAL',
	'CAL-200' => 'CAL',
	'CAL-300' => 'CAL',
	'CAL-500' => 'CAL',
);
$retired = 0;
foreach ( $retire as $old_sku => $new_sku ) {
	$old = wc_get_product( wc_get_product_id_by_sku( $old_sku ) );
	$new = wc_get_product_id_by_sku( $new_sku );
	if ( ! $old ) {
		continue;
	}
	if ( 'publish' === $old->get_status() ) {
		$old->set_status( 'draft' );
		$old->save();
		++$retired;
	}
	if ( $new && ! in_array( $old->get_slug(), get_post_meta( $new, '_wp_old_slug' ), true ) ) {
		add_post_meta( $new, '_wp_old_slug', $old->get_slug() );
	}
}
if ( $mono_id && ! in_array( 'biznes-kalendar-start', get_post_meta( $mono_id, '_wp_old_slug' ), true ) ) {
	add_post_meta( $mono_id, '_wp_old_slug', 'biznes-kalendar-start' );
}
if ( $retired ) {
	echo "  $retired earlier samples: draft, their addresses redirect\n";
}

// A product category's picture: its first product's photo, while it has none (or still shows the
// old calendar artwork with its printed price).
foreach ( $catalogue as $item ) {
	$photo = (int) get_post_thumbnail_id( (int) wc_get_product_id_by_sku( $item['sku'] ) );
	foreach ( $item['cats'] as $slug ) {
		$cat = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $cat || 'paketi' === $slug || ! $photo ) {
			continue;
		}
		$current = (int) get_term_meta( $cat->term_id, 'thumbnail_id', true );
		if ( ! $current || str_contains( (string) get_attached_file( $current ), 'calendar-start' ) ) {
			update_term_meta( $cat->term_id, 'thumbnail_id', $photo );
		}
	}
}
update_option( 'reklamo_seeded_catalogue_v3', 1 );

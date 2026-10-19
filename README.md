# Reklamo.bg

Online shop for promotional packages branded with the customer's logo — **Variant A** from
`docs/Reklamo-4-varianta-za-izgrazhdane.docx`: WordPress + WooCommerce, bank transfer only,
custom-built theme. WooCommerce is the only third-party plugin; everything else is our own code
plus configuration from the dashboard.

Full plan: [`docs/PLAN.md`](docs/PLAN.md). Design mockups: [`design/`](design/). Going live: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md). Personal data and legal compliance: [`docs/GDPR.md`](docs/GDPR.md).

Convention: code, comments and docs are in English. Site content (pages, products, menus,
emails) is Bulgarian — that is data, seeded by `scripts/seed.sh`.

## What is in the repo

Only our code. WordPress core and WooCommerce are installed by `scripts/setup.sh` and are
gitignored, so the owner can still update them from the dashboard.

```
wp-content/themes/reklamo/        theme — presentation only
wp-content/plugins/reklamo-core/  plugin — all business logic (statuses, uploads, emails)
scripts/seed.sh                   THE site configuration, as WP-CLI commands
scripts/host/                     runs ON the hosting account: install.sh / update.sh / check.sh / bootstrap.sh
scripts/deploy.sh                 drives the hosting account over SSH from your workstation
scripts/backup.sh                 snapshots of the live site on your workstation: pull / list / load / restore
scripts/worktree/                 per-branch isolated stacks (wt-new / wt-build / wt-push / wt-merge / wt-drop)
versions.env                      WordPress + WooCommerce version pins, used locally and on the server
docker-compose.yml                local environment (Apache, MariaDB, Mailpit)
```

## Local environment

Requirements: Docker + Compose v2. No PHP on the host.

```bash
cp .env.example .env      # optional — setup.sh does it for you
scripts/setup.sh          # zero → working Bulgarian store
```

The stack listens on `127.0.0.1` only. From your own machine, open a tunnel:

```bash
ssh -p 22022 -L 8080:127.0.0.1:8080 -L 8025:127.0.0.1:8025 dev@178.104.78.114
```

| What | Where |
|---|---|
| Storefront | http://localhost:8080 |
| Admin | http://localhost:8080/wp-admin — credentials in `.env` |
| Mailpit (every outgoing email) | http://localhost:8025 |

## Everyday commands

```bash
scripts/wp <command>          # WP-CLI, e.g. scripts/wp plugin list
scripts/seed.sh               # re-apply configuration (idempotent)
scripts/reset.sh              # destroy everything and rebuild from zero — THE MASTER TEST
scripts/lint.sh               # PHPCS + WordPress Coding Standards
scripts/test.sh               # PHPUnit (pure-PHP units, e.g. the approval token)
scripts/e2e.sh                # Playwright: full order → mockup → approval flow, plus header/navigation, against the running stack
scripts/make-fixtures.sh 150  # test files with real magic bytes (AI/PSD/CDR/EPS/SVG)
docker compose logs -f wp
```

## Deploying to SuperHosting

```bash
export DEPLOY_USER=<cpanel-user> DEPLOY_HOST=reklamo.bg   # or put them in .env.deploy
cp .env.server.example .env.server    # optional: fill it in and no prompt is asked
scripts/deploy.sh install             # fresh account → live site
scripts/deploy.sh update [tag]        # ship a version: checkout, WP/WC pins, flush, health check
scripts/deploy.sh update --seed       # refused on the live site: the seed is locked there (below)
scripts/deploy.sh check --mail-test you@example.com
scripts/deploy.sh wp plugin list      # any WP-CLI command on the server
```

**Copies of the live site** (`scripts/backup.sh`, from your workstation):

```bash
scripts/backup.sh pull [--daily]      # snapshot: database, media, customer files, site files → ~/reklamo-backups/<date>/
scripts/backup.sh list                # the snapshots you have (the newest 30 are kept)
scripts/backup.sh load [snapshot]     # put one into the LOCAL stack (default: latest), live URLs → localhost
scripts/backup.sh restore <snapshot>  # put one back on the LIVE site; asks you to type reklamo.bg first
```

A snapshot is about 64 MB. The site-files archive (`site.tar.gz`: `public_html` without uploads,
`wp-config.php` and `error_log`) is kept for disaster recovery only; `load` and `restore` never apply
it. Secrets (`wp-config.php`, the server `.env`) are not copied. On the workstation a crontab line
runs `scripts/backup.sh pull --daily` every hour at :17, which takes one snapshot a day whenever the
machine is on (log: `~/reklamo-backups/pull.log`):

```
17 * * * * /home/<you>/projects/reklamo/scripts/backup.sh pull --daily >> $HOME/reklamo-backups/pull.log 2>&1
```

`load` saves the local database to `~/reklamo-backups/local/` first, replaces `wp/wp-content/uploads`
and `private/`, and locks the seed on the copy. Log in with your live account; emails go to
Mailpit. `scripts/e2e.sh` refuses a live copy because the tests edit products;
`scripts/reset.sh` brings the test data back. `REKLAMO_BACKUP_DIR` and `REKLAMO_BACKUP_KEEP`
override the folder and the count. The snapshots hold customer data: keep them on an encrypted disk.

**The seed is locked on the live site.** The option `reklamo_seed_locked` makes `scripts/seed.sh`
change nothing and `update --seed` stop before it touches the site, because the client's products,
prices and settings live there now. A configuration change after that is a one-off WP-CLI command
(`scripts/deploy.sh wp …`), written to create only what is missing. A fresh install has no lock.

The server runs the same `scripts/seed.sh` and `scripts/wp` natively (`WP_PATH` set in its
`.env`). WordPress and WooCommerce versions come from `versions.env`; bump it, test with
`scripts/reset.sh` + `scripts/e2e.sh`, then `scripts/deploy.sh update`. Everything else, including
the clicks in cPanel that no script can do: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Working on a branch in an isolated stack

```bash
scripts/worktree/wt-new.sh feature/x      # worktree + second stack on :8081 (Mailpit :9081)
scripts/worktree/wt-build.sh feature/x    # restart + cache flush; --seed / --lint / --reset
scripts/worktree/wt-push.sh feature/x -m "msg"
scripts/worktree/wt-merge.sh feature/x    # leaves the merge STAGED — you commit it
scripts/worktree/wt-drop.sh feature/x --yes
```

The same commands exist as `/wt-new`, `/wt-build`, `/wt-push`, `/wt-merge`, `/wt-drop` in
Claude Code. Details: [`scripts/worktree/README.md`](scripts/worktree/README.md).

## How the site is put together

- **Homepage** = five block patterns from `wp-content/themes/reklamo/patterns/` (hero, packages, steps, trust, quick-start), copied into the Начало page by `seed.sh` on first run. The owner edits texts/images in the block editor (`templateLock: contentOnly`).
- **Request page** (`/kachi-logo/?paket=<slug>`, the header's "Заяви визуализация") = theme template `templates/page-request.php` + the plugin's `Reklamo_Request::render()`: the package and "Добави в количката". The homepage quick-start form is the same form with a package picker. Every route ends in the cart, where the logos, the notes (both required for every product), the contact details (name, email, phone — required; saved as the order's billing phone and pre-filled on the later details step) and the consents are given and the request is sent.
- **Корпоративни подаръци** (`page-korporativni-podaratsi.php`, seeded page, category and menu items: the header right after ПРОДУКТИ, the footer after Промо пакети): a listing like Промо пакети of the products in the category `korporativni-podaratsi` — the owner ticks it on a product. Same header band, chips of the categories those products are in (narrowing the page in place, `?kategoria=`) and sorting (`reklamo_catalogue_sort()`, shared with Продукти); while it is empty a card invites an offer. Like Пакети it is a collection, never a chip.
- **Promotion and gift** (product editor, General tab): a percent off for the whole product (rounded down to ,99, optional last day; written as WooCommerce's sale price on every variation) and a gift text. Cards and pages show "Промоция" / "+ Подарък" instead of "Най-популярен", the old price struck through, and the gift; the gift is also written on the order line.
- **Products sold by the piece** (`Reklamo_Product`): a product with a **Minimum quantity** above 1 (Products → Product data → Основни) is sold by the piece — the price is per piece, the product page shows "Цена за брой", a quantity stepper starting at the minimum and the live total, and the cards say "от 50 бр. / 3,20 € за брой". Colours are WooCommerce's own variations on the global attribute **Цвят** (`pa_cvyat`, Products → Attributes); each colour has a **Swatch colour** (term meta `reklamo_hex`) drawn as the circle, and choosing a colour swaps the whole product gallery for that colour's photos — the variation image plus its **Gallery for this colour** (Products → Variations, `_reklamo_variation_gallery`, picked with the shared Media Library picker); a colour without photos shows the product's own (`assets/js/product.js`, which re-renders WooCommerce's gallery markup and restarts its `wc_product_gallery` script). The minimum is enforced on add-to-cart (`woocommerce_add_to_cart_validation`) and in the cart, where the quantity can be changed. Packages and calendar tiers stay fixed sets, but the promo packages come in colours too: red, black and white variations at the same price, chosen with the same swatches and no quantity (the seed converted the sample packages once, `reklamo_seeded_pkg_colours`). They also come in **sizes**: the global attribute **Количество** (`pa_kolichestvo`: 20 / 50 / 100 / 200 бр.) is a second variation attribute, and the product editor's **Prices by quantity** (General tab) sets one price per size for every colour at once — written into the matching variations on save (`Reklamo_Product::save_tiers()`); a size without a price is not offered. On the product page a size changes only the price (the badge follows) and the counts in "Какво включва пакетът?"; the colour alone changes the photos. A variation may carry its own photo; Magnet's colours do, while every size of a product shares the product's photos. The request page and the homepage form have no colour picker and add a package in its default colour (`Reklamo_Cart::require_package()` fills in the default attributes). The seed creates the attribute, five colours (red, black, white, green, blue) and the sample **Класически тефтер** (NB-001, 3,20 € per piece, minimum 50) once; the homepage quick form lists only products without colours. The shop sells fixed quantities only for now, so the seed turns that sample into a draft once (`reklamo_seeded_nb_draft`; publishing it again is the owner's call), and `scripts/e2e.sh` publishes it for the test run and returns it to draft afterwards.
- **Cart** (`Reklamo_Cart`, the seeded Кошница page): the one place a request is sent from, for one product or several. Products and packages go in with a plain "Добави в количката" (product page, request page, homepage form); a product can go in more than once. After adding, the customer stays on the same page and a dialog (`<dialog>`, `Reklamo_Cart::added_dialog()`) offers "Към количката" or "Продължи с пазаруването"; WooCommerce's own "added to your cart" notice is dropped. The header shows a bag icon with the count. The cart page (rendered by the plugin whatever blocks the page holds) has two steps. Both steps offer "Продължи с пазаруването" (to the shop) and "Изчисти количката" (after a confirmation it removes every product, logo and note). **1. Logos and notes**: the customer adds one or more logos, each with a required note to the designer and a tick per cart product it is for (the first logo starts ticked for every product, the common case); "Добави още едно лого" adds another, whose list holds only the products not yet marked under another logo (unticking a product elsewhere brings it back). Each product shows live whether it has a logo and a note, and "Продължи към данните" stays disabled until every product has both under exactly one logo (`assets/js/cart.js`); the server checks the same on continue and again on send. **2. Details and sending**: the contact fields and consents, then "Изпрати и заяви визуализации" (disabled until name, email and phone are filled in and both consents are ticked; the server still checks all of it, plus the phone format and the email domain) creates ONE order with a line per product carrying its logo and note (a logo used for several products is one file, claimed to the first of them). Nothing is paid; WooCommerce's checkout page redirects to the cart. WooCommerce has no built-in per-line file upload (only paid add-ons, which the one-plugin rule excludes), so the logos live in the WooCommerce session (`reklamo_logos`, `reklamo_cart_step`). An uploaded logo waits for up to two days, then Reklamo_Cleanup removes the unsent upload and the cart asks for it again.
- **Company details** (phone, email, address, hours, social, payment %, mockup deadline, free corrections and the fee for each further one, production days, the free-delivery threshold; under Legal & privacy the registered name, ЕИК, ДДС №, seat, МОЛ and the privacy email) live in WooCommerce → Settings → Reklamo and feed the footer, request page, emails, the `Organization` structured data and the page texts through `[reklamo_value key="…"]`. An empty social link is not shown. The address, in the footer and wherever `[reklamo_value key="address"]` prints it (Контакти), is a link to Google Maps (`Reklamo_Settings::address_link()`, the `api=1` search URL, new tab), which on a phone opens the maps app ready to navigate; changing the address in the settings moves the link with it.
- **Search engines and link previews** (`themes/reklamo/inc/seo.php`): meta description, Open Graph and Twitter tags on every page, and an `Organization` / `WebSite` / `BreadcrumbList` / `FAQPage` JSON-LD graph. WooCommerce prints its own `Product` block; the theme only fixes its run-together description. The FAQ page turns each `<h3>` into a question and the text under it into the answer, so schema appears as soon as the page has real content. Cart, checkout, account and the users sitemap are dropped from `wp-sitemap.xml`. The home page title uses the WordPress **Site Tagline**, the descriptions use the longer tagline from Settings → Reklamo. Pages with no picture of their own share the brand card `assets/img/share.jpg` — the logo at 1200×630, the ratio the networks crop to. See **Brand assets** below for how it is built.
- **Brand assets** (`themes/reklamo/assets/img/`): `logo.png` (horizontal lockup, gold), `logo-light.png` (same in white, for dark backgrounds) and `mark.png` (the ring-R alone) come from the client's logo kit, `design/logo-kit-and-redesign.zip` → `REKLAMO_ALL.pdf`, whose pages are vector artwork with no text layer. The header uses `logo-slogan.png` instead: the same lockup with the slogan "Рекламни продукти с характер", rasterised from `design/v2/REKLAMO_SLOG.ai` (852×162, shown at 54px, 40px on phones; `reklamo_logo( false, true )`). `reklamo_logo()` and `reklamo_mark()` in `inc/icons.php` print them. Two images are derived from those files by `node scripts/render-brand-images.js`: `share.jpg`, the 1200×630 link-preview card, and `icon.png`, the 512×512 square the seed imports as the **Site Icon**. Both are committed, so nothing is rendered at deploy time; re-run the script after changing the logo. Page *content* never hard-codes the mark — it goes through `[reklamo_mark size="76"]`, because stored block markup would otherwise freeze today's logo into the database.
- **Products menu**: ПРОДУКТИ in the header opens the nine product categories (Календари, Тефтери, Химикалки, Бутилки и чаши, Запалки, Чанти, Текстил, Технологии, Офис артикули), seeded as WooCommerce terms and menu items. `Reklamo_Nav_Walker` adds the toggle button: hover and keyboard focus open the panel through CSS, the button covers touch and screen readers, Escape and an outside click close it. The item points at the **Продукти** page (`page-produkti.php`): every product, packages and single products alike, under the catalogue band, with "Всички продукти" and a chip per category that holds products; a chip opens that category's page. Sorting works as on Промо пакети (WooCommerce's own dropdown, mapped onto the `[products]` shortcode). The page's own content is not shown.
- **Shared sections** (`themes/reklamo/inc/page-sections.php`): content that is the same for every product is kept on its own page and shown again on the homepage, below the page content, so editing the page updates both.
  - **Steps** on the homepage ("Как става поръчката?", the `[reklamo_steps]` shortcode) and at the foot of every product page: step 1's text from "Как работи" shows under the six steps, and clicking a step swaps in that step's section in place (`<h2 id="stapka-N">` headings; `theme.js` switches them). On "Как работи" itself the steps stay anchors.
  - **Доставка и срокове** (`template-parts/delivery.php`): each `<h3>` on that page plus the text under it becomes a numbered card with an icon picked from its wording; light band on the homepage, the same cards on the page.
- **FAQ** (`themes/reklamo/inc/page-sections.php`, `template-parts/faq.php`): the Често задавани въпроси page is the one source — each `<h3>` is a question, the text under it the answer, numbers pulled from the settings by `[reklamo_value]`. It renders as a numbered accordion (one answer open at a time, the first open) in a dark band closing the homepage, as the same accordion in light on its own page, and as FAQPage schema. Adding or editing a question in that page updates all three.
- **404 and search** have their own templates (`404.php`, `search.php`): a heading, one sentence and buttons to the packages and the homepage; search lists titles with excerpts and paginates. Every other unknown request falls back to `index.php`.
- **Промо пакети** (the shop page, `woocommerce/archive-product.php`) lists **packages only** (the Пакети category, `REKLAMO_PACKAGES_CAT`): a title band with three advantages, then "Всички пакети" and a chip per category some package is in, with WooCommerce's sorting, above a four-column grid. A chip narrows the packages in place through `?kategoria=<slug>` (`pre_get_posts`, no JavaScript needed, survives a change of sorting). The product category pages (`/product-category/<slug>/`) use the same band (`template-parts/shop-hero.php`) in the Продукти flavour: "Всички продукти" first and their own chip lit. A package shows under a chip once it is ticked in that category (the seed placed the sample packages once, `reklamo_seeded_pkg_cats`). Each chip carries an icon picked from the category's name (`reklamo_item_icon()`, the same matcher as the package contents list). On a phone the chips become a two-column grid of tiles (the "all" chip, and an odd last one, span the row) instead of a strip running off the screen, and every product grid shows two cards a row with a compact body: the name, a two-line contents text and a full-width button. Under the grid, Промо пакети, Продукти and every category page end with the large-quantity band (`template-parts/bulk-offer.php`; on the homepage a `render_block_core/group` filter appends it to the section with the `packages` class, so the seeded content stays untouched): "Големи количества? Получете индивидуална оферта", a button to the Контакти page (`reklamo_contact_url()`) and three reasons with icons.
- **Product cards** open the product page. A gold seal on the photo carries the pieces and the final price: the pieces are the number the first contents item starts with ("50 бр. …"), and the seal shows only the price when there is none. Under the name, the contents titles run on one line. "Featured" products show the "Най-популярен" badge.
- **Product page** (`themes/reklamo/woocommerce/content-single-product.php`) follows the client's design: gallery, category eyebrow, the final price in a circle (whole euros drop the ",00"), "Какво включва пакетът?", a card listing what is included with the single call to action, the four ordering steps, the tabs and the help card. **Main photo**: a landscape photograph (wider than tall) fills the gallery column down to the height of the summary, with rounded corners, a soft shadow and a slow zoom on hover; square cut-outs and portrait artwork such as the calendars keep their own shape. The theme asks WooCommerce for 1200px product images and 600px card images (`add_theme_support( 'woocommerce', … )`), twice the displayed size. **Gallery**: with more than one photo (Products → Product gallery) the thumbnails stand in a column on the left and the slide gets prev/next arrows; a Featured product carries the "Най-популярен" badge on it. **Lead and contents** come from the product's **short description**: a paragraph, then a list. Each list item is a bold title, optionally followed by a line break (Shift+Enter; once saved the editor stores it as a plain newline, which counts the same) and a detail line, e.g. **20 бр. Премиум тефтера** / A5, твърда корица, 96 листа. The icon is picked from the title's wording (тефтер, химикалка, чаша, бутилка, торба, календар, опаковка/кутия; anything else gets a box), and a title that starts with a number gets the gold "+". **Tab section photo**: Products → Product data → Основни → "Section background image" puts a photo behind the tabs, fading in from the right (a banner under the text on phones); without one the section stays plain. **Mockup note**: under the main block every product and package page says that the mockup is a guide — colours, size and logo position may differ slightly with the material, the printing and the screen — and that a final mockup comes for approval before production (`.product-disclaimer`, a translated theme string). **Help card**: the consultant's photo from Settings → Reklamo → Company & contact ("Consultant photo"); without one it shows the headset icon. The order card adds the product to the cart with its logo and note (see **Cart**). The step strip reads the mockup deadline and deposit percentage from Settings → Reklamo, so changing a setting changes the page. **Tabs** use WooCommerce's own `woocommerce_product_tabs` and hold only what belongs to the product: Описание (its description) and Спецификации (the **Specifications** box in Products → Product data → Основни, `Reklamo_Product`, meta `_reklamo_specs`; the tab disappears while that box is empty). How ordering works, delivery and the FAQ are the same for every product, so they live on the homepage and their own pages, and the help card links to all three.
- **Catalogue** (`scripts/seed-catalogue.php`, from the client's photos in `design/v3/web product`): four packages in Пакети — Бизнес комплект Magnet (red, white, blue; the "Най-популярен" one), Комплект Stripe, Комплект Cork and Комплект Trio (pen and charging cable in a gift box), also listed under the categories of what they contain — and seventeen products: Календар Mono, Trio and Photo; two notebooks, two cotton bags, a travel cup, two bottles (Bamboo Black; Matt in navy or white, one product with colours), four metal pens and three lighters, each in its category. Two batches, each created once (`reklamo_seeded_catalogue_v3`, `_v4`); a product with colours lists each colour's gallery photos in its `colour_gallery`. Everything is variable on Количество with the client's prices (2026-10-15): 50 / 100 / 150 / 200 бр.; Mono and Trio 50 / 100 / 200 / 300 / 500; Photo 10 / 20. Magnet is priced per colour (blue cheaper), so its editor shows no "Prices by quantity" fields, which would set one price for every colour, and points to the variations instead; a product whose colours cost the same keeps the fields. The texts are drafts for the owner. Created once (`reklamo_seeded_catalogue_v3`); afterwards everything belongs to the dashboard. Bulgarian names would become percent-encoded URLs, so every product carries an explicit latin slug.
- **Uploads**: logos are uploaded in 2 MB chunks to our REST API (progress bar, retries), checked by real file signature (`.ai` is a PDF, CorelDRAW X4+ is a ZIP…), SVG sanitised, stored outside the web root, never served inline. Abandoned/unclaimed files and old orders' files are cleaned hourly (retention in Settings → Reklamo → Files). WooCommerce → **Reklamo diagnostics** shows the host's real limits and probes them.
- **Customer order page**: every customer email carries a passwordless link (`/moyata-porachka/?s=…&k=…`) to a page with progress, payments and a card per product (its logo, note, current mockup and history). The customer approves or requests changes to each product's pending mockup right there (the emailed one-time link works too, and is consumed by either route). No accounts. The link expires together with the order's files under the retention setting.
- **Emails the shop receives** go to the address set for each email (office@reklamo.bg) and to every WordPress administrator, read at sending time (`Reklamo_Emails::add_administrators()`): new order, changes requested, mockup approved (deposit expected), invoice details submitted, an "order waiting" alert when a customer ignores every reminder (days under Settings → Reklamo → Process), and WooCommerce's "Cancelled order" when an unpaid order cancels itself. The customer additionally gets deposit-received and order-cancelled emails.
- **Data protection**: the request form records consent (time, text version from Settings → Reklamo → Legal & privacy, hashed IP) and the withdrawal-right waiver on the order. Tools → Export / Erase Personal Data cover files, links, consent and change requests; erasure anonymises closed orders through WooCommerce's own routine and reports open ones as retained. The hourly cleanup deletes files after the file period and anonymises closed orders after the anonymisation period, both settings. Deleting an order from the dashboard removes its files, links, notes and reminders. The storefront sets no cookies, so there is no banner. Trader identification (the registered name, ЕИК, ДДС №, seat) prints in the footer and through `[reklamo_company]`; `[reklamo_value key="retention_months"]` puts a setting into page text. Plan and rationale: [`docs/GDPR.md`](docs/GDPR.md).
- **Order flow** (WooCommerce → Orders → order → "Mockup & approval" box): send mockups → customer approves each product via its one-time link → customer fills invoice/delivery details (delivery to an address, a courier's office or a parcel locker — Спиди, Еконт, BoxNow for lockers only, Pigeon; a company delivered elsewhere gives its own address for the invoice; the shop can change all of it in the order's box) → *Deposit received* → *Start production* → *Request final payment* → *Complete*. The order is paid in full before production (`reklamo_deposit_pct` = 100, the Bulgarian texts call it „плащане“, not „аванс“), so there is no balance: from production the box offers *Dispatched — complete the order*, and the final-payment step and the balance rows only appear if the owner lowers the percentage. Each product block shows its corrections against the free ones (a correction is every mockup after the first) and warns when it is over; the customer sees the rule and the count on the approval and order pages. The fee is never charged automatically: corrections of the shop's own mistakes do not count, and only the shop knows which those were. **Every product runs its own mockup loop** (`Reklamo_Items`, state on the order line item): the box shows a block per product with its logo, note, numbered mockups and the customer's words under each, plus a file field; files chosen for several products go out together in ONE email with a link per product. The order status follows the products: any open change request → *Changes requested* (the shop gets an email per request, naming the product); otherwise any mockup out → *Mockup sent*; all approved → *Approved*, and only then the deposit is set and requested. Sending a new mockup for a product invalidates that product's previous approval link, so an outdated revision cannot be approved by mistake; a new mockup after the order was approved voids the approval and the deposit as before. Approval reminders run per product, counted from the day its mockup went out. **The payment has a deadline** (`Reklamo_Payment_Deadline`, Settings → Reklamo → Process): calendar hours from the moment every mockup is approved (`_reklamo_approved_at`), weekends included. The payment request, the order page and the shop's box quote the deadline, plus the rule that a product which runs out means a refund or a new delivery date; the request-received email states the rule in advance. At 48 h (`reklamo_payment_hours`) the customer gets a reminder; at 72 h (`reklamo_cancel_hours`) an order still *Approved* is cancelled automatically (`_reklamo_payment_expired_at`), the customer gets the cancellation email with the reason and every administrator WooCommerce's "Cancelled order" email. If the money arrives late, the cancelled order's box offers *Late payment received — restore the order*, which moves it to *Deposit received*. Both values default in code, so no seed is needed; 0 switches a step off, and with the cancellation off the payment falls back to the reminder days. The status dropdown refuses jumps outside this path. Emails at every step are editable under WooCommerce → Settings → Emails; bank details under Settings → Reklamo → Bank details; reminder days under Settings → Reklamo → Process.

## The configuration rule

The database is not in git — **`scripts/seed.sh` is**. The same script runs on the hosting account at install; since the client started adding products the live site is locked against it (`reklamo_seed_locked`, see Deploying) — see `docs/DEPLOYMENT.md`. Every setting you click in the dashboard
must become a line in `seed.sh`, otherwise it does not exist. `scripts/reset.sh` proves that
nothing lives only in the local database.

Two kinds of lines: `opt` enforces configuration (currency, tax, statuses, pages, menus) on every
run; `opt_default` seeds owner-editable content (company details, bank data, texts) once and leaves
the dashboard value alone afterwards. When a seeded sample has to give way to the owner's real value, `opt_replace key new old…`
changes the option only while it is empty or still exactly an earlier sample, and `refresh_if_seeded slug new old…` does the
same for a page's text (compared without whitespace). Either way an owner's own edit is never touched — "once" meaning while the option is missing **or empty**,
since WordPress and WooCommerce pre-create some of them blank. Site visibility is the exception to
both: the seed only publishes the storefront during a fresh install (`REKLAMO_INSTALL=1`), so
`update --seed` can never take a deliberately hidden site live. Sample products are created when their SKU is missing and
never updated. A plain `update` does not seed at all, so the owner's dashboard changes to `opt`
settings survive releases; pass `--seed` when the seed itself changed and you want it applied.

The catalogue — packages, products, their prices and photos — is `scripts/seed-catalogue.php`,
run once by the seed (`reklamo_seeded_catalogue_v3`). The photos are the client's
(`design/v3/web product`), re-encoded to WebP in `wp-content/themes/reklamo/assets/img/catalogue/<product>/`:
`main` (with "ВАШЕТО ЛОГО") is the product image, `logo` and `plain` the gallery, `section` the wide shot behind
the description; Magnet has one set per colour. Each photo is imported once and found again by its source path
(attachment meta `_reklamo_source`), so a re-run never duplicates it. Specifications are the one part looked at on every seed run:
filled while a product has none.

The PHP limits in `config/php/uploads.ini` are deliberately low (64M) to mimic shared hosting.
Do not raise them to "make a test pass".

## Translations

WooCommerce's own Bulgarian pack has gaps; customer-facing ones are filled from `wp-content/plugins/reklamo-core/languages/woocommerce-overrides-bg.php` (applied only when WooCommerce has no translation). Add a line there when a new English string shows up on the storefront.

Source strings in the theme and plugin are English, wrapped in `__( '…', 'reklamo' )`. Bulgarian
lives in `wp-content/themes/reklamo/languages/bg_BG.po and wp-content/plugins/reklamo-core/languages/reklamo-core-bg_BG.po (the plugin uses its own text domain `reklamo-core`; plugin files are named {domain}-{locale})`. After editing a `.po`:

```bash
scripts/wp i18n make-mo wp-content/themes/reklamo/languages
```

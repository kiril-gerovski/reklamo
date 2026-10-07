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
scripts/deploy.sh update --seed       # same, plus re-apply scripts/seed.sh (only when the seed changed)
scripts/deploy.sh check --mail-test you@example.com
scripts/deploy.sh wp plugin list      # any WP-CLI command on the server
```

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
- **Request page** (`/kachi-logo/?paket=<slug>`) = theme template `templates/page-request.php` + the plugin's `Reklamo_Request::render()`. The form asks for logo, note to the designer, name, email and phone (required; saved as the order's billing phone and pre-filled on the later details step). Submitting creates the WooCommerce order directly — there is no cart/checkout step (the block checkout stays installed as an unlinked fallback).
- **Company details** (phone, email, address, social, deposit %, mockup deadline) live in WooCommerce → Settings → Reklamo and feed the footer, request page, emails and the `Organization` structured data.
- **Search engines and link previews** (`themes/reklamo/inc/seo.php`): meta description, Open Graph and Twitter tags on every page, and an `Organization` / `WebSite` / `BreadcrumbList` / `FAQPage` JSON-LD graph. WooCommerce prints its own `Product` block; the theme only fixes its run-together description. The FAQ page turns each `<h3>` into a question and the text under it into the answer, so schema appears as soon as the page has real content. Cart, checkout, account and the users sitemap are dropped from `wp-sitemap.xml`. The home page title uses the WordPress **Site Tagline**, the descriptions use the longer tagline from Settings → Reklamo. Pages with no picture of their own share the brand card `assets/img/share.jpg` — the logo at 1200×630, the ratio the networks crop to. See **Brand assets** below for how it is built.
- **Brand assets** (`themes/reklamo/assets/img/`): `logo.png` (horizontal lockup, gold), `logo-light.png` (same in white, for dark backgrounds) and `mark.png` (the ring-R alone) come from the client's logo kit, `design/logo-kit-and-redesign.zip` → `REKLAMO_ALL.pdf`, whose pages are vector artwork with no text layer. The header uses `logo-slogan.png` instead: the same lockup with the slogan "Рекламни продукти с характер", rasterised from `design/v2/REKLAMO_SLOG.ai` (852×162, shown at 54px, 40px on phones; `reklamo_logo( false, true )`). `reklamo_logo()` and `reklamo_mark()` in `inc/icons.php` print them. Two images are derived from those files by `node scripts/render-brand-images.js`: `share.jpg`, the 1200×630 link-preview card, and `icon.png`, the 512×512 square the seed imports as the **Site Icon**. Both are committed, so nothing is rendered at deploy time; re-run the script after changing the logo. Page *content* never hard-codes the mark — it goes through `[reklamo_mark size="76"]`, because stored block markup would otherwise freeze today's logo into the database.
- **Products menu**: ПРОДУКТИ in the header opens the nine product categories (Календари, Тефтери, Химикалки, Бутилки и чаши, Запалки, Чанти, Текстил, Технологии, Офис артикули), seeded as WooCommerce terms and menu items. `Reklamo_Nav_Walker` adds the toggle button: hover and keyboard focus open the panel through CSS, the button covers touch and screen readers, Escape and an outside click close it. The item points at the **Продукти** page (`page-produkti.php`): every product, packages and single products alike, under the catalogue band, with "Всички продукти" and a chip per category that holds products; a chip opens that category's page. Sorting works as on Промо пакети (WooCommerce's own dropdown, mapped onto the `[products]` shortcode). The page's own content is not shown.
- **Shared sections** (`themes/reklamo/inc/page-sections.php`): content that is the same for every product is kept on its own page and shown again on the homepage, below the page content, so editing the page updates both.
  - **Steps** on the homepage ("Как става поръчката?", the `[reklamo_steps]` shortcode) and at the foot of every product page: step 1's text from "Как работи" shows under the six steps, and clicking a step swaps in that step's section in place (`<h2 id="stapka-N">` headings; `theme.js` switches them). On "Как работи" itself the steps stay anchors.
  - **Доставка и срокове** (`template-parts/delivery.php`): each `<h3>` on that page plus the text under it becomes a numbered card with an icon picked from its wording; light band on the homepage, the same cards on the page.
- **FAQ** (`themes/reklamo/inc/page-sections.php`, `template-parts/faq.php`): the Често задавани въпроси page is the one source — each `<h3>` is a question, the text under it the answer, numbers pulled from the settings by `[reklamo_value]`. It renders as a numbered accordion (one answer open at a time, the first open) in a dark band closing the homepage, as the same accordion in light on its own page, and as FAQPage schema. Adding or editing a question in that page updates all three.
- **404 and search** have their own templates (`404.php`, `search.php`): a heading, one sentence and buttons to the packages and the homepage; search lists titles with excerpts and paginates. Every other unknown request falls back to `index.php`.
- **Промо пакети** (the shop page, `woocommerce/archive-product.php`) lists **packages only** (the Пакети category, `REKLAMO_PACKAGES_CAT`): a title band with three advantages, then "Всички пакети" and a chip per category some package is in, with WooCommerce's sorting, above a four-column grid. A chip narrows the packages in place through `?kategoria=<slug>` (`pre_get_posts`, no JavaScript needed, survives a change of sorting). The product category pages (`/product-category/<slug>/`) use the same band (`template-parts/shop-hero.php`) in the Продукти flavour: "Всички продукти" first and their own chip lit. A package shows under a chip once it is ticked in that category (the seed placed the sample packages once, `reklamo_seeded_pkg_cats`).
- **Product cards** open the product page. A gold seal on the photo carries the pieces and the final price: the pieces are the number the first contents item starts with ("50 бр. …"), and the seal shows only the price when there is none. Under the name, the contents titles run on one line. "Featured" products show the "Най-популярен" badge.
- **Product page** (`themes/reklamo/woocommerce/content-single-product.php`) follows the client's design: gallery, category eyebrow, the final price in a circle (whole euros drop the ",00"), "Какво включва пакетът?", a card listing what is included with the single call to action, the four ordering steps, the tabs and the help card. **Main photo**: a landscape photograph (wider than tall) fills the gallery column down to the height of the summary, with rounded corners, a soft shadow and a slow zoom on hover; square cut-outs and portrait artwork such as the calendars keep their own shape. The theme asks WooCommerce for 1200px product images and 600px card images (`add_theme_support( 'woocommerce', … )`), twice the displayed size. **Gallery**: with more than one photo (Products → Product gallery) the thumbnails stand in a column on the left and the slide gets prev/next arrows; a Featured product carries the "Най-популярен" badge on it. **Lead and contents** come from the product's **short description**: a paragraph, then a list. Each list item is a bold title, optionally followed by a line break (Shift+Enter; once saved the editor stores it as a plain newline, which counts the same) and a detail line, e.g. **20 бр. Премиум тефтера** / A5, твърда корица, 96 листа. The icon is picked from the title's wording (тефтер, химикалка, чаша, бутилка, торба, календар, опаковка/кутия; anything else gets a box), and a title that starts with a number gets the gold "+". **Tab section photo**: Products → Product data → Основни → "Section background image" puts a photo behind the tabs, fading in from the right (a banner under the text on phones); without one the section stays plain. **Help card**: the consultant's photo from Settings → Reklamo → Company & contact ("Consultant photo"); without one it shows the headset icon. There is no add-to-cart anywhere: the CTA goes to `/kachi-logo/?paket=<slug>`. The step strip reads the mockup deadline and deposit percentage from Settings → Reklamo, so changing a setting changes the page. **Tabs** use WooCommerce's own `woocommerce_product_tabs` and hold only what belongs to the product: Описание (its description) and Спецификации (the **Specifications** box in Products → Product data → Основни, `Reklamo_Product`, meta `_reklamo_specs`; the tab disappears while that box is empty). How ordering works, delivery and the FAQ are the same for every product, so they live on the homepage and their own pages, and the help card links to all three.
- **Catalogue**: seven promo packages in Пакети (the original four, then Thermo Cup Pack, Green Pen Pack and White Pen Pack with the client's offer artwork and draft texts; the homepage shows the first four by menu order), and six Бизнес календар START 1–6 in Календари — one product per quantity tier (20/50/100/200/300/500 броя), priced and photographed from the client's artwork, with the supplied paper and print specifications as the description. Products are created only when their SKU is missing, so names, prices and texts belong to the dashboard afterwards. Bulgarian names would become percent-encoded URLs, so `ensure_product` takes an explicit latin slug.
- **Uploads**: logos are uploaded in 2 MB chunks to our REST API (progress bar, retries), checked by real file signature (`.ai` is a PDF, CorelDRAW X4+ is a ZIP…), SVG sanitised, stored outside the web root, never served inline. Abandoned/unclaimed files and old orders' files are cleaned hourly (retention in Settings → Reklamo → Files). WooCommerce → **Reklamo diagnostics** shows the host's real limits and probes them.
- **Customer order page**: every customer email carries a passwordless link (`/moyata-porachka/?s=…&k=…`) to a page with progress, mockup history and payments. The customer approves or requests changes to the pending mockup right there (the emailed one-time link works too, and is consumed by either route). No accounts. The link expires together with the order's files under the retention setting.
- **Emails the shop receives**: new order, changes requested, mockup approved (deposit expected), invoice details submitted, and an "order waiting" alert when a customer ignores every reminder (days under Settings → Reklamo → Process). The customer additionally gets deposit-received and order-cancelled emails.
- **Data protection**: the request form records consent (time, text version from Settings → Reklamo → Legal & privacy, hashed IP) and the withdrawal-right waiver on the order. Tools → Export / Erase Personal Data cover files, links, consent and change requests; erasure anonymises closed orders through WooCommerce's own routine and reports open ones as retained. The hourly cleanup deletes files after the file period and anonymises closed orders after the anonymisation period, both settings. Deleting an order from the dashboard removes its files, links, notes and reminders. The storefront sets no cookies, so there is no banner. Trader identification (ЕИК, ДДС №, seat) prints in the footer and through `[reklamo_company]`; `[reklamo_value key="retention_months"]` puts a setting into page text. Plan and rationale: [`docs/GDPR.md`](docs/GDPR.md).
- **Order flow** (WooCommerce → Orders → order → "Mockup & approval" box): send mockup → customer approves via one-time link → customer fills invoice/delivery details → *Deposit received* → *Start production* → *Request final payment* → *Complete*. Corrections can go round as often as needed: every mockup is numbered, and the box lists each one with the customer's own words underneath, the open request in full contrast and the answered ones greyed. Sending a new mockup invalidates the previous approval link, so an outdated revision cannot be approved by mistake. The status dropdown refuses jumps outside this path. Emails at every step are editable under WooCommerce → Settings → Emails; bank details under Settings → Reklamo → Bank details; reminder days under Settings → Reklamo → Process.

## The configuration rule

The database is not in git — **`scripts/seed.sh` is**. The same script runs on the hosting account at install and whenever `scripts/deploy.sh update --seed` is used — see `docs/DEPLOYMENT.md`. Every setting you click in the dashboard
must become a line in `seed.sh`, otherwise it does not exist. `scripts/reset.sh` proves that
nothing lives only in the local database.

Two kinds of lines: `opt` enforces configuration (currency, tax, statuses, pages, menus) on every
run; `opt_default` seeds owner-editable content (company details, bank data, texts) once and leaves
the dashboard value alone afterwards — "once" meaning while the option is missing **or empty**,
since WordPress and WooCommerce pre-create some of them blank. Site visibility is the exception to
both: the seed only publishes the storefront during a fresh install (`REKLAMO_INSTALL=1`), so
`update --seed` can never take a deliberately hidden site live. Sample products are created when their SKU is missing and
never updated. A plain `update` does not seed at all, so the owner's dashboard changes to `opt`
settings survive releases; pass `--seed` when the seed itself changed and you want it applied.

Each package gets its own photo from `wp-content/themes/reklamo/assets/img/packages/`, imported
only when the product has no image, so an owner's upload is never replaced. Those four files were
cut out of the design mockup by `scripts/crop-previews.php` and are stand-ins at catalogue size:
replace them with real photography in Products → edit → Product image.

The PHP limits in `config/php/uploads.ini` are deliberately low (64M) to mimic shared hosting.
Do not raise them to "make a test pass".

## Translations

WooCommerce's own Bulgarian pack has gaps; customer-facing ones are filled from `wp-content/plugins/reklamo-core/languages/woocommerce-overrides-bg.php` (applied only when WooCommerce has no translation). Add a line there when a new English string shows up on the storefront.

Source strings in the theme and plugin are English, wrapped in `__( '…', 'reklamo' )`. Bulgarian
lives in `wp-content/themes/reklamo/languages/bg_BG.po and wp-content/plugins/reklamo-core/languages/reklamo-core-bg_BG.po (the plugin uses its own text domain `reklamo-core`; plugin files are named {domain}-{locale})`. After editing a `.po`:

```bash
scripts/wp i18n make-mo wp-content/themes/reklamo/languages
```

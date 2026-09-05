# Reklamo.bg — Variant A: WordPress + WooCommerce

## Context

`Reklamo-4-varianta-za-izgrazhdane.docx` offers four ways to build the same shop. All four deliver an identical customer journey — pick package → send logo → mockup → approval → bank transfer → delivery — and differ only in *where the shop lives* and *how the mockup is produced*.

| Variant | Stack | One-off | Yearly | 3 years |
|---|---|---|---|---|
| **A (А)** | WordPress + WooCommerce, designer mockup | — | **€78** | €234 |
| B (Б) | A + Lumise instant preview | €64 | €78 | €298 |
| C (В) | Shopify Basic, designer mockup | — | €320 | €960 |
| D (Г) | Shopify Basic + Qstomizer Advanced | — | €585 | €1 755 |

**We build Variant A (Вариант А)** — cheapest, and the only family (A/B) that accepts unlimited-size professional files and lets us control the checkout page. Shopify caps uploads at 20 MB and forbids checkout customisation, which is exactly why the document rules it out: the "плащане не се извършва на този етап" message can't be placed where it matters.

Variant B is **A plus one paid plugin, added later with nothing rebuilt**. Everything here is the foundation B sits on, so that upgrade path stays open at no cost.

### Conventions

- Code, comments, docs and translatable source strings are **English**. Bulgarian UI text lives in `languages/bg_BG.po` per theme/plugin.
- Site *content* (pages, products, menus, emails the customer reads) is Bulgarian — it is data, seeded by `scripts/seed.sh`.

### Constraints

- **WooCommerce is the only third-party plugin.** Everything else = our code + dashboard config.
- The non-technical owner edits **all** texts, prices, images and packages without a developer.
- Entirely Bulgarian (`bg_BG`).
- **Bank transfer only.** No card processor.
- Uploads accept AI, EPS, PDF, PSD, CDR, SVG, PNG, JPG at **any size** — the headline advantage over Shopify.

### Decisions taken

| Question | Choice |
|---|---|
| Design assets | PDF / image mockups — **not yet on this VM** (see Prerequisites) |
| Theme | Classic theme + core block editor for page content |
| Checkout | **Block Checkout** (composes fine with a classic theme) |
| Uploads | Chunked JS upload to a custom REST endpoint |
| Repo | Track theme + plugin only; core & WooCommerce installed by WP-CLI, gitignored |
| Sequencing | Walking skeleton first, then harden |

**Classic theme + block checkout is deliberate, not a contradiction.** `theme.json`, block patterns and `templateLock` all work in classic themes, and the Cart/Checkout blocks are just blocks on a normal Page. What a classic theme gives up is the Site Editor for header/footer — those stay PHP, edited via menus and the settings page. The reason to accept the block checkout anyway: the **Additional Checkout Fields API is block-checkout-only**, and it is the only upgrade-safe way to collect фирма / ЕИК / ДДС № / МОЛ for Bulgarian фактури.

---

## Verified environment facts

Checked on this VM, not assumed:

- Ubuntu 24.04, **Docker 29.7.2 + Compose v5.5.0**, Node 24. **No PHP on the host** — everything in containers.
- **Ports 8000, 8001, 8002, 3306, 6379, 9000, 11000, 13000 are taken** by a running ERP stack. `8080`, `8081`, `8025`, `3307` are free.
- SSH on **22 and 22022**; public IP `178.104.78.114`.
- **RAM is tight**: 7.6 GB total, ~1.9 GB free with the ERP stack up. WP + MariaDB + Mailpit needs ~600 MB — it fits, but stop the ERP worktrees if it drags.
- `mariadb:11.8` already cached. All four image tags confirmed to resolve: `wordpress:7.1-php8.3-apache`, `wordpress:cli-2-php8.3`, `axllent/mailpit:v1.31`.

Current upstream (both newer than my training data — checked live):

- **WordPress 7.1** "Mary Lou" (19 Aug 2026); WP 7.0 shipped the admin redesign in May 2026.
- **WooCommerce 11.1.0**, requires WP ≥ 7.0, PHP ≥ 7.4.

Three external facts that shape the build:

- **The classic checkout shortcode still exists in WC 11 but is maintenance-only** — security and bug fixes, no new features. Reinforces the block-checkout decision.
- **Bulgaria joined the euro 1 Jan 2026, and mandatory BGN/EUR dual price display expired 8 Aug 2026** — four weeks ago. The store is **single-currency EUR**, matching the document's € pricing. No dual-display work.
- ⚠️ **Bulgarian translation is incomplete and nobody has costed it.** Core `bg_BG` lags at **7.0.4** behind 7.1. The WooCommerce `bg_BG` pack exists for 11.1.0 (refreshed 2026‑09‑03) but has **4,704 untranslated strings**, and WordPress silently falls back to English for each. Since "изцяло на български език" is a headline promise, this needs a real **translation audit**: crawl every customer-facing page and email, list the English leaks, ship our own `bg_BG.mo` from the plugin to override. Code and config, so it respects the no-plugins rule — but it is real work.

---

### Phase 0 decisions (settled)

| Question | Choice |
|---|---|
| Packages | **Real names + prices from `design/preview.webp`**: Red Business Pack 100 €, Office Starter Pack 119 €, Event Pack 149 €, Premium Pack 169 €. Prices are a field on each product — the owner edits them in Продукти → edit. No photos yet (owner uploads). |
| VAT (ДДС) | **Tax handling off for now** (`woocommerce_calc_taxes = no`). Prices are plain numbers. ⚠️ Must be revisited *before* real products are entered — flipping incl./excl. later means touching every product. Listed under Open questions. |
| Git | **You create the remote first; I work in the clone.** |

## Prerequisites (blocking, on you)

1. ~~Create the git remote~~ — done: `git@github.com:kiril-gerovski/reklamo.git`, cloned into `/home/dev/projects/reklamo`.
2. ~~Put the design mockups in `design/`~~ — done: `preview.webp` (homepage) and `preview_2.webp` (upload page; `preview_3.webp` is a byte-identical duplicate). ⚠️ The upload mockup says "макс. 20MB", contradicting the document's headline no-limit advantage — that text is intentionally not reproduced.
3. **Confirm hosting** (SuperHosting vs Jump.bg) and get an account early. Deployment steps for SuperHosting: `docs/DEPLOYMENT.md`. Phase 2 ends with a diagnostics probe that must run *on the real host* — see the upload risk below.

---

## The two hard problems

### 1. Uploads — measured, not guessed

I generated files with genuine magic bytes on this VM and sniffed them as PHP's `finfo` will:

| File | Sniffed MIME | Consequence |
|---|---|---|
| `.psd` | `image/vnd.adobe.photoshop` | not whitelisted by WP |
| **`.ai`** | **`application/pdf`** | **extension/MIME mismatch → WordPress rejects it** |
| `.eps` | `application/postscript` | not whitelisted |
| `.cdr` | `application/vnd.corel-draw` | not whitelisted; CorelDRAW X4+ is ZIP-based and sniffs as `application/zip` |
| `.svg` | `image/svg+xml` | executes embedded `<script>` — stored XSS |

**Illustrator files are PDFs internally.** Since WP 4.7.1 `wp_check_filetype_and_ext()` rejects extension/MIME mismatches, so a `.ai` file is refused *even after* whitelisting `.ai`. Fixing it needs the `wp_check_filetype_and_ext` filter, scoped to our own request only — never weaken WordPress globally. This breaks first, and it breaks on the format professional customers use most.

**The real risk is the host, not PHP.** `upload_max_filesize`/`post_max_size` are raisable on SuperHosting via cPanel PHP Manager. The killers are invisible from wp-admin: Apache `LimitRequestBody` in the vhost, **mod_security `SecRequestBodyNoFilesLimit` (~128 KB default)**, CloudLinux LVE limits, `max_input_time` (~60 s — a 250 MB upload on a 10 Mbps uplink takes 100–200 s and dies *before PHP runs*), `open_basedir`, disk quota, and inode limits.

Hence: **2 MB `multipart/form-data` chunks** to a custom REST route (never base64 in a JSON body — that trips mod_security), sequential `fetch()` with retry and a progress bar, reassembly via `stream_copy_to_stream()`. And a **Диагностика** admin screen that prints the real limits and actively probes 1/4/16/64 MB POSTs to detect the true `LimitRequestBody`. If the host fails the probe, the answer is a €5/month VPS, not more code.

**Files never enter the media library.** Stored as `{32hex}.bin` in a salted private directory (ideally above web root), registered in a custom `reklamo_files` table, served only through an authenticated `admin-post.php` endpoint with `Content-Disposition: attachment` and a `realpath()` traversal guard. That single decision kills the SVG XSS vector structurally (an SVG never rendered as `image/svg+xml` on our origin cannot execute against it), keeps brand assets out of Google, and avoids widening `upload_mimes` site-wide.

### 2. Approval links — `wp_create_nonce()` is the trap

Nonces are **wrong** for emailed links to logged-out customers, four ways: for `$uid = 0` the value is *identical for every anonymous visitor*, so it authenticates nothing; it expires in 12–24 h while this business runs on a multi-day human loop; it breaks if the customer happens to be logged in; and rotating `NONCE_SALT` invalidates every link in flight.

Correct: a **selector/verifier capability URL** — the same pattern core uses for password resets. Mint a 16-char public `selector` plus a 43-char secret; store only `wp_hash($secret)`, so a leaked DB dump cannot approve anything. Verify with `hash_equals()`, rate-limit attempts, expire in 14 days.

**Email scanners prefetch every GET link** (Gmail, Defender ATP, corporate proxies). So the GET at `/odobrenie/` is strictly idempotent — it renders a preview and two buttons, and approves nothing. Approval is a POST, made single-use atomically:

```sql
UPDATE …_tokens SET used_at = %s WHERE selector = %s AND used_at IS NULL
```

One statement, so a double-click or racing prefetch cannot double-approve. Each revision mints a fresh token, so an old email can never approve a newer mockup. Set `Referrer-Policy: no-referrer` and host zero third-party assets on that page — the secret is in the query string.

---

## Architecture

```
wp-content/themes/reklamo/       → presentation only
wp-content/plugins/reklamo-core/ → all business logic
```

Order flow, statuses, uploads and emails live in the **plugin**, so they survive a theme change and the owner cannot break them. This split is the difference between a maintainable site and a theme that holds the business hostage.

**Order state machine** — encode transitions as an explicit table and enforce them in `woocommerce_order_status_changed`; a free-form dropdown plus seven statuses is how these builds rot.

`rq-received` → `rq-mockup-sent` ⇄ `rq-changes` → `rq-approved` → `rq-deposit-paid` → `rq-production` → `rq-final-due` → core `completed`

**HPOS is default and non-negotiable in how we write data.** Never `get_post_meta()`/`update_post_meta()` on an order — under HPOS those write orphan rows to a post that doesn't exist. Everything through `wc_get_order()` → `$order->get_meta()`/`update_meta_data()`/`save()`, and `$order->has_status()` for comparisons (statuses are stored prefixed `wc-` but returned unprefixed).

Three filters that fail *silently* and get blamed on unrelated bugs:

- `woocommerce_order_is_editable` — without it the owner cannot edit an order once it leaves `pending`.
- `woocommerce_actionable_order_statuses` / `woocommerce_excluded_report_order_statuses` — without these, Analytics reports **zero revenue** and the owner concludes the site is broken.
- `woocommerce_email_actions` — without registering our status hooks, emails work in dev and silently stop the day background processing is enabled.

Do **not** add custom statuses to `woocommerce_order_is_paid_statuses` (it triggers stock and revenue side effects) or to `woocommerce_valid_order_statuses_for_payment` (it puts a dead "Плати сега" link in emails). Track money as order meta plus order notes; `completed` is the only paid state.

**No-payment checkout**: a `WC_Payment_Gateway` subclass (`reklamo_request`) that never calls `payment_complete()`, plus an `AbstractPaymentMethodType` registration and ~60 lines of vanilla JS for the block checkout — no build step. Because the Store API can re-save the order after the gateway returns, force the initial status idempotently from **three** hooks: inside `process_payment()`, `woocommerce_checkout_order_processed`, and `woocommerce_store_api_checkout_order_processed`.

**Logo + note attach to the order LINE ITEM, not the order.** The logo belongs to a package, not an order — the moment two packages are bought together, order-level meta cannot represent it. Line-item meta also renders free in admin, emails and My Account, and a `unique_key` in the cart item data stops two different logos merging into qty 2 and silently destroying one.

**Owner-editability without ACF or a builder**, five layers:

1. `theme.json` with `"custom": false` on colour/typography/spacing — the palette is the only palette, so the site cannot be made ugly. Works in classic themes.
2. Block patterns for every content section (hero, "How it works", packages grid, FAQ).
3. `templateLock: "contentOnly"` on pattern wrappers — the owner sees only text and image fields, no style controls, nothing removable. **This is what replaces ACF**, using core only.
4. The 4 packages are ordinary **WooCommerce products** — no custom post type, so the owner learns one editing UI and gets media library, revisions and sale scheduling for free.
5. A `WC_Settings_Page` subclass for global scalars (IBAN, BIC, account holder, company ID (ЕИК), VAT no. (ДДС), responsible person (МОЛ), deposit %, deadlines), surfaced into content by three tiny dynamic blocks so the IBAN lives in **one** place, not copy-pasted onto six pages where it will eventually be wrong.

**Design rule:** every string the owner might want to change must be reachable from exactly four screens — Products, Pages, WooCommerce → Settings → Reklamo, WooCommerce → Settings → Emails. Any other hardcoded customer-facing string is a defect.

**Action Scheduler is bundled inside WooCommerce**, so reminder automation is available without violating the one-plugin rule. Use it, not WP-Cron — and set `DISABLE_WP_CRON` with a real system cron, because on a low-traffic site WP-Cron simply does not fire and every reminder silently never sends.

---

## Local development environment

Four services, all bound to `127.0.0.1` only — never the public IP:

| Service | Image | Port |
|---|---|---|
| `wp` | `wordpress:7.1-php8.3-apache` | 8080 |
| `db` | `mariadb:11.8` | — |
| `mail` | `axllent/mailpit:v1.31` | 8025 |
| `cli` | `wordpress:cli-2-php8.3` | — |

**Apache, not nginx, on purpose** — SuperHosting and Jump.bg run Apache/LiteSpeed with `.htaccess`, so the rules protecting customer logo files get genuinely exercised locally instead of discovered broken in production.

**PHP limits set deliberately LOW locally** (`upload_max_filesize = 64M`, `max_input_time = 60`) to mimic shared hosting. A permissive local environment would hide the exact bug we are engineering against.

### Reaching it from your machine

```bash
ssh -p 22022 -L 8080:127.0.0.1:8080 -L 8025:127.0.0.1:8025 dev@178.104.78.114
```

Then `http://localhost:8080` (site) and `http://localhost:8025` (Mailpit). `WP_HOME`/`WP_SITEURL` pinned to `http://localhost:8080` via `WORDPRESS_CONFIG_EXTRA`, so they live in config rather than the database and WordPress won't rewrite URLs on deploy.

### Repository layout

```
reklamo/
├── docker-compose.yml
├── .env.example
├── config/php/uploads.ini        # deliberately restrictive
├── scripts/{wp,setup.sh,seed.sh,reset.sh,lint.sh,make-fixtures.sh,lib.sh}
├── design/                       # the approved mockups
├── docs/order-flow.md
├── wp-content/themes/reklamo/
├── wp-content/plugins/reklamo-core/
├── tests/{e2e,php}/
└── composer.json                 # dev-only: phpcs, wpcs, phpunit
```

`.gitignore` covers WordPress core, `wp-content/plugins/woocommerce/`, `uploads/`, `wp-config.php`, `.env`. Only our two directories are tracked, so the owner can still click **Update** in the dashboard without fighting git.

### The database is not in git — the seed script is

The single most important discipline here. WordPress keeps configuration in the database, which git cannot track. **Every configuration change must be expressed as a WP-CLI command in `scripts/seed.sh`**, never only clicked in the dashboard. `scripts/reset.sh` then rebuilds the whole site from zero.

If a change exists only in your local database, it does not exist. That script is simultaneously the deployment procedure, the disaster-recovery procedure, and the specification of the site's configuration.

---

## Build sequence — walking skeleton first

**Phase 0 — Environment.** Docker stack, repo, `scripts/` scripts, WP + WooCommerce + `bg_BG` installed by WP-CLI, four packages seeded, Mailpit capturing mail. *Done when `scripts/reset.sh` on a clean checkout produces a working Bulgarian store with zero manual clicks.*

**Phase 1 — Walking skeleton.** Thinnest end-to-end slice, deliberately unhardened: single-file upload (no chunking yet) → no-payment gateway → order lands in `rq-received` → one Bulgarian email → approval link → status flips to `rq-approved`. *Done when the whole path is clickable in a browser.* This proves the risky joints — gateway/Store API interaction, token flow, line-item meta — before any of it is expensive to change.

**Phase 2 — Uploads hardened.** Chunked REST endpoint, magic-byte sniffer, SVG sanitizer, private storage + download endpoint, `reklamo_files` table, diagnostics screen. **Run the diagnostics probe on the real host and get sign-off before continuing.**

**Phase 3 — Full order flow.** All seven statuses + transition guard + the three silent-failure filters, admin columns and bulk actions, mockup metabox with revision history, full token table with rate limiting.

**Phase 4 — Emails.** Seven `WC_Email` classes, Bulgarian templates, SMTP via `phpmailer_init` (no plugin), mail log table. **Deliverability test to abv.bg, mail.bg, gmail and outlook.com is a launch gate** — SPF + DKIM + DMARC configured before launch, or approval links land in spam, orders die, and the owner concludes "сайтът не работи".

**Phase 5 — Theme + editability.** Classic theme to the mockups, `theme.json` guard rails, patterns with `templateLock`, settings page, dynamic blocks.

**Phase 6 — Bulgarian content, translation audit, terms & conditions/GDPR, owner training, Action Scheduler reminders, retention GC.**

---

## Phase 2 (reordered) — Design: theme + request page — DONE (2026-09-05)

Upload hardening (old Phase 2) is deferred until the hosting account is chosen, since its decisive step is the probe on the real host. The theme is independent of it, so it moves up.

**Decision (2026-09-05): the request step matches `design/preview_2.webp` exactly.** "Качи лого и визуализирай" is our own page: logo + note + name + email + consent + one button. It creates the WooCommerce order directly (`wc_create_order()`, gateway `reklamo_request`, status `rq-received`) — no WooCommerce checkout UI. Address and invoice details are collected later, with the deposit request, which is the document's own sequence. The block checkout stays installed as a fallback but is not linked. Consequence: the Additional Checkout Fields API is no longer the plan for ЕИК/ДДС/МОЛ; those become fields on the deposit step.

Scope:
1. Design tokens + base CSS from the mockup (gold `#b8892b`, cream, serif display / Inter body; fonts self-hosted, Cyrillic subsets).
2. Header (logo mark, nav, "Заяви визуализация" CTA) and footer (4 columns, contacts from settings, social, copyright) in PHP.
3. Company/contact settings tab (`WC_Settings_Page`): phone, email, city, social URLs — the footer and contact blocks read from one place.
4. Homepage as owner-editable block patterns with `templateLock: contentOnly`: hero, package grid (WooCommerce loop, restyled cards linking to the request page), "Как става поръчката?" 6 steps, trust strip, quick-start form, seeded into Начало.
5. Request page: dynamic block `reklamo/request-form` + sidebar pattern; the handler in the plugin (`Reklamo_Request`) validates, stores the logo, creates the order, redirects to the order-received page.
6. WooCommerce overrides: product card (`content-product.php`), shop archive, single product CTA → request page; order-received page styled.
7. Placeholder product images (owner replaces), inline SVG logo mark and icons.
8. Seed updates, E2E rewritten for the new flow, screenshots against the mockups.

Known deviation: the homepage quick-start form gets a package selector (the mockup has none, but an order needs a package).

Delivered: header/footer, homepage from five owner-editable patterns (hero, packages, steps, trust, quick-start), request page template matching `preview_2.webp`, package cards + shop archive + product CTA, styled order-received page, `Reklamo_Request` handler (nonce, honeypot, per-IP rate limit, server-side validation with values kept), `Reklamo_Settings` tab, self-hosted fonts, inline SVG icons, placeholder images, 104 new translated strings. E2E now covers: product → request page → order, validation refusals, homepage quick-start, admin mockup, approval idempotency/replay, final status (6 tests). Screenshots compared against both mockups.

Lessons:
- **Patterns are copied into content at seed time.** Anything that must translate at request time (trust strip) has to be a shortcode/dynamic block, not baked HTML; owner-content (hero text, steps) is right as baked blocks.
- The seed rebuilds the homepage only while it still holds the seed placeholder, so owner edits survive re-seeding.
- Theme and plugin each own their strings (`reklamo` / `reklamo-core`); run `make-pot` + `update-po` + `make-mo` for both after adding strings.

Still owner's job: real product photos (Products → edit), hero photo (edit the image in the hero pattern), real texts for the information pages.

## Phase 1 — status: DONE (2026-09-04)

The walking skeleton is clickable end to end and verified in a browser: product page → logo upload (`.ai`, sniffed as `application/pdf`) + designer note → block checkout with the `reklamo_request` gateway → order in `rq-received` (HPOS) with the logo claimed to the line item → Bulgarian "request received" email + core admin "new order" email → admin metabox uploads a mockup → Bulgarian "mockup ready" email with a one-time link → GET is idempotent (two scanner-style fetches change nothing) → POST approves exactly once → replay shows "already processed" → order in `rq-approved`. Second cycle (mockup #2, "request changes", logged out via curl) also passes. PHPCS clean; PHPUnit covers the token class; Playwright spec in `tests/e2e/`.

Deliberately unhardened (later phases): plain upload (no chunking), cheap MIME sanity only, no SVG sanitiser, three statuses, two emails, no reminders.

Lessons that changed the code:
- **Do not share a text domain between theme and plugin.** `WP_Textdomain_Registry` keeps one path per domain; the theme's registration displaced the plugin's and its `.mo` was never loaded. Plugin now uses `reklamo-core`; plugin `.mo` files are named `{domain}-{locale}.mo`.
- **`APACHE_RUN_USER=#1000` alone leaves PHP running as root** in the official image: Apache's `unixd` needs a passwd entry for the uid (AH02155) and silently keeps children as root. Compose now remaps `www-data` to uid/gid 1000 before starting Apache.
- **Deleting a bind-mounted directory under a running container** leaves the container holding the old inode; every later `chown`/`mkdir` on the host is invisible to it. `reset.sh` documents the order (down, then rm).
- Two hook names from the architecture research were wrong and are corrected from source: the filter is `wc_order_is_editable`, and the block gateway reads data via `wcSettings.getPaymentMethodData(name)`.
- The "request received" email must be first-entry-only: the order legitimately returns to `rq-received` after every "request changes".
- A metabox cannot contain its own `<form>` (it sits inside the order form); the mockup upload uses HTML5 `form="…"` attributes pointing at a form printed in `admin_footer`.

Open for Phase 2/3: upload dir diagnostics screen, chunking, magic-byte sniffer, SVG handling, remaining statuses/emails, `rq-changes` as a distinct status.

## Phase 0 — status: DONE (2026-09-04)

Verified on the dev VM: `scripts/setup.sh` → Bulgarian storefront on :8080, WP 7.1 + WooCommerce 11.1.0 (pinned), HPOS enabled, block Cart/Checkout, 4 real packages, menus, free-shipping zone BG, Mailpit capturing mail through the plugin's `phpmailer_init` path. A test order placed through the block checkout via Playwright landed in `wp_wc_orders` and produced two Bulgarian emails. `scripts/seed.sh` is idempotent (two consecutive runs, no changes). PHPCS clean.

Lessons that changed the scripts:
- WooCommerce refuses direct writes to some admin options → `opt()` warns and continues.
- WC-CLI `shipping_zone_location` has no write command → zone set via `wp eval` + `WC_Shipping_Zone` API.
- The WP-CLI install path leaves **HPOS unset** → seed creates tables and enables it via `DataSynchronizer`.
- Core `wp_mail()` fails outright with the default `wordpress@localhost` sender → plugin falls back to the WooCommerce From address.
- WP-CLI CSV quotes Cyrillic → strip quotes before exact-match; never `grep -q` under `pipefail`.
- WooCommerce/WP sample pages are **drafts** → look pages up with `post_name__in`, not `--name`.

Translation gaps already observed on the storefront (feeds the Phase 6 audit): "Showing all 4 results", "Contact information", "Phone (optional)", "Use same address for billing", "Payment options", "Place Order".

## Phase 0 in detail

Goal: **`git clone` → `scripts/setup.sh` → a working Bulgarian WooCommerce store on `http://localhost:8080`, no manual clicks.** Nothing here depends on the design mockups.

### Files created

| File | Purpose |
|---|---|
| `docker-compose.yml` | `wp`, `db`, `mail`, `cli` — all ports bound to `127.0.0.1` |
| `.env.example` → `.env` | DB creds, admin user/pass/email, `WP_URL=http://localhost:8080` |
| `config/php/uploads.ini` | **Restrictive on purpose**: `upload_max_filesize=64M`, `post_max_size=64M`, `max_input_time=60`, `memory_limit=256M` |
| `config/mariadb/low-mem.cnf` | `innodb_buffer_pool_size=128M` — the VM has ~1.9 GB free |
| `scripts/wp` | `docker compose run --rm cli wp "$@"` |
| `scripts/setup.sh` | Bring stack up, wait for health, `wp core install --locale=bg_BG`, install **pinned** WooCommerce 11.1.0, language packs, activate theme + plugin, then `seed.sh` |
| `scripts/seed.sh` | All dashboard configuration as `wp option update` / `wp wc …` commands (see below) |
| `scripts/reset.sh` | `docker compose down -v` → `setup.sh`. The master test. |
| `scripts/lint.sh` | PHPCS + WPCS via a throwaway `php:8.3-cli` container (no PHP on host) |
| `wp-content/themes/reklamo/` | Minimal activatable skeleton: `style.css`, `index.php`, `header.php`, `footer.php`, `functions.php` (`add_theme_support('woocommerce')`), `theme.json` v3 |
| `wp-content/plugins/reklamo-core/reklamo-core.php` | Bootstrap: `FeaturesUtil` declarations (`custom_order_tables`, `cart_checkout_blocks`) + `phpmailer_init` SMTP hook reading `REKLAMO_SMTP_*` constants |
| `composer.json` | dev-only: `wp-coding-standards/wpcs`, `phpcompatibility` |
| `.gitignore`, `README.md`, `docs/PLAN.md` | |

### Compose design choices

- **WordPress core lives in a gitignored `./wp/` bind mount, not a named volume**, with Apache running as `APACHE_RUN_USER=#1000` (supported by the official image). This means WooCommerce's source is greppable from the host — I'll be reading it constantly while writing the gateway, statuses and emails — and `wp-content/debug.log` is a plain file. Our theme and plugin are bind-mounted on top at their `wp-content` paths.
- **`./private/` (gitignored) mounted at `/var/www/private`** with `REKLAMO_PRIVATE_DIR` pointing at it — mirrors the production layout of "logo storage above web root" from day one.
- **`WORDPRESS_CONFIG_EXTRA`** carries: `WP_HOME`, `WP_SITEURL`, `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY=false`, `SCRIPT_DEBUG`, `DISABLE_WP_CRON` (real cron comes later), `REKLAMO_PRIVATE_DIR`, `REKLAMO_SMTP_HOST=mail`, `REKLAMO_SMTP_PORT=1025`.
- **Mail goes through the same `phpmailer_init` SMTP code path as production**, just pointed at Mailpit. So Phase 0 already exercises the exact mail code that will run on SuperHosting — only the constants differ.
- `cli` uses `wordpress:cli-2-php8.3`, run as `1000:1000`, sharing `./wp`, and is invoked with `run --rm` (never idles).

### What `seed.sh` configures

- Locale `bg_BG`, timezone `Europe/Sofia`, date format `d.m.Y`, permalinks `/%postname%/`.
- WooCommerce: currency **EUR**, symbol after the number with a space (`10,00 €`), decimal `,`, thousands ` `, country `BG`, **taxes off** (revisit — see Open questions), stock management **off** (made-to-order), skip the onboarding wizard, tracking and marketplace suggestions off, WooCommerce auto-updates off.
- Default gateways (BACS, cheque, COD) **disabled** — ours arrives in Phase 1.
- Cart/Checkout pages are created by WooCommerce on activation with the **block** versions (new-install default) — matches our checkout decision without extra work.
- Pages: Начало (set as static front page), Как работи, Качване на лого, Контакти, Общи условия, Политика за поверителност. Primary menu from those.
- Four products in category **Пакети**, `wp wc product create … --user=admin`.

### Definition of done

`scripts/reset.sh` on a fresh clone → `http://localhost:8080` shows a Bulgarian storefront with four products; wp-admin is in Bulgarian; WooCommerce reports HPOS enabled and block checkout active; placing a test order with the (temporarily enabled) BACS gateway produces an email visible in Mailpit at `:8025`; `scripts/lint.sh` passes on the skeleton.

---

## How to test it

**1. Reproducible rebuild — the master test.** `scripts/reset.sh` destroys the volume and rebuilds. If the site comes back complete, the repo is complete. It is the only thing that proves nothing is trapped in local state.

**2. Mailpit.** All ~7 Bulgarian emails captured, zero risk of mailing a real customer, with an API so tests assert on subject and body. This is how the Bulgarian copy gets verified and English leaks get caught.

**3. Playwright E2E.** The Playwright MCP server is already on this VM, so I can drive `http://localhost:8080` directly — no tunnel — and screenshot pages against `design/`. Critical path: pick package → upload 150 MB `.psd` → note → checkout → assert the "плащане не се извършва" copy → assert status → admin uploads mockup → assert email → click approval link → assert transition → deposit → production. Plus explicit tests for the two nastiest cases: **a prefetched GET must not approve**, and **a double-submitted POST must approve exactly once**.

**4. PHPUnit** for what a browser can't reach cleanly: token mint/verify (expiry, replay, tampering, timing), MIME sniffing per format, chunk reassembly integrity (hash reassembled vs original), and transition guards rejecting illegal jumps.

**5. PHPCS + WordPress Coding Standards.** Catches missing escaping and sanitisation — the largest source of WP vulnerabilities, and a live risk because we accept public file uploads.

**6. Realistic fixtures.** `scripts/make-fixtures.sh` generates large files **with genuine magic bytes**. A 200 MB file of zeros named `logo.psd` tests nothing — it has to reproduce the `.ai`→`application/pdf` mismatch and the ZIP-based `.cdr`, or the validation is untested. Include an SVG with an embedded `<script>` as a permanent regression test.

Dev-only exception: **Query Monitor** locally for debugging — gitignored, never deployed.

---

## Open questions (flagged, not guessed)

- **VAT (ДДС) — decide before real products exist.** Currently off. The likely right answer is "enter excl. VAT, show incl." (net prices match фактури, customer sees the real total), but it needs the owner's input on whether private individuals can buy.
- **Quantity tiers.** Promotional products normally price by volume (100 pens ≠ 500 pens). The document implies simple products. If tiers are needed these must be *variable* products — far cheaper to decide before seeding.
- **Bulgarian invoices (фактури).** B2B customers paying by bank transfer will need them. The Additional Checkout Fields API collects ЕИК/ДДС/МОЛ, but it supports only `text`/`select`/`checkbox`, values land at `_wc_other/…` keys no accounting export understands, and WooCommerce produces no compliant Bulgarian invoice. Not in the €78 scope as written.
- **Couriers.** Econt and Speedy are the norm; their plugins are free but *are plugins*. No-plugin path is flat-rate shipping plus manual booking.
- **The 14-day withdrawal right.** Personalised goods are exempt (EU CRD Art. 16(c) / ЗПК) **only if you say so** — needs an explicit checkout checkbox and an ОУ clause, or the shop is exposed on every custom order.
- **The human loop has no code, so it gets no schedule.** What happens when the customer never approves, wants a 6th revision, or cancels mid-production? Needs a documented revision limit and Action Scheduler nudges at 3/7/14 days.
- **Retention.** 10 orders × 250 MB = 2.5 GB against a typical 10–20 GB plan. Auto-delete logos 12 months after `completed`, disclosed in the ОУ.

---

## Verification

- **Phase 0:** `scripts/reset.sh` on a clean checkout yields a Bulgarian WooCommerce store at `http://localhost:8080` with four packages, all pages and working mail capture — no manual dashboard clicks.
- **Phase 1:** a human completes the full order→approval path in a browser; the order ends in `rq-approved` with the logo downloadable from wp-admin.
- **Phase 2:** a 250 MB layered PSD uploads successfully *on the real host*, and the canary check confirms the private directory is not publicly reachable.
- **Every phase:** `scripts/reset.sh` still works, PHPCS clean, E2E green.

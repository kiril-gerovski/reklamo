# GDPR and legal compliance plan

What Reklamo.bg holds about people today, where the gaps are, and the work that closes them.
Code items are ours; content items need the owner and a lawyer; the numbers in "Decisions" are
the owner's to set. Regulator: КЗЛД (Комисия за защита на личните данни). Law that applies
beside the GDPR: ЗЗЛД (personal data), ЗЗП (consumer protection), ЗЕТ (electronic commerce),
Закон за счетоводството (retention of accounting documents).

## 1. Inventory: personal data the site processes

| Data | Where it lives | Why | Kept today |
|---|---|---|---|
| Name, email | WooCommerce order (billing) | contact for the order, tracking link | forever |
| Company, ЕИК, ДДС №, МОЛ, phone, address, city, postcode | order billing/shipping | invoice and delivery | forever |
| Logo file, mockup files | `~/reklamo-private`, rows in `wp_reklamo_files` | the product | deleted 12 months after the order closes (`reklamo_retention_months`) |
| Notes to the designer, change-request messages | order item meta, order meta, order notes | the design work | forever |
| IP address, user agent | order (`customer_ip_address`, `customer_user_agent`), `created_ip` on file rows, the approval order note | fraud prevention, proof of approval | forever |
| Tracking URL, approval/details tokens | order meta, `wp_reklamo_tokens` | passwordless access to the order | link expires with the files; tokens never deleted |
| Rate-limit counters | transients keyed by `md5(ip)` | abuse protection | one hour |
| Every customer email | mailbox `office@reklamo.bg` on SuperHosting; shop notifications currently at an abv.bg mailbox | correspondence | mailbox retention |
| Everything above | SuperHosting account backups | disaster recovery | SuperHosting's backup window |

No customer accounts, no cookies set by our code, fonts self-hosted, no analytics, no external
requests from the storefront. WooCommerce's **Order Attribution** feature is enabled and sets
`sbjs_*` tracking cookies on every visitor without consent.

Consent to the Terms and the Privacy Policy is a required, unticked checkbox on the request form.
The fact of consent, its time and the policy version are **not recorded** on the order.

Already in place: Tools → Export / Erase Personal Data cover the files, links and tokens
(`Reklamo_Privacy`); erasure applies only to closed orders. Files sit outside the web root,
downloads go through one-time tokens, HTTPS is forced, uploads and approval attempts are rate
limited, `wp-config.php` is mode 600, WordPress core security releases install themselves.

## 2. Gaps, highest risk first

1. **Deleting an order from wp-admin leaves its files on disk**, its tokens, notes and scheduled
   reminders in the database. The retention sweep looks only at orders that still exist, so those
   files are never removed. (Found while cleaning up test orders.)
2. **Tracking cookies without consent**: WooCommerce Order Attribution.
3. **Order personal data is never anonymised.** Files go after 12 months; name, address, ЕИК,
   phone, IP stay in the order indefinitely. Erasure requests remove files and links, not the
   order fields.
4. **No consent record**: nothing proves which text the customer agreed to and when.
5. **Legal pages are placeholders**: Общи условия is one sentence, Политика за поверителност is
   one sentence. The consent checkbox links to them.
6. **No trader identification** in the footer or Terms: company name, ЕИК, address, contact are
   required by ЗЕТ чл. 4 and ЗЗП. The settings `reklamo_eik` and `reklamo_vat` do not exist yet.
7. **No withdrawal-right notice**: branded goods made to the customer's specification are exempt
   from the 14-day withdrawal right (ЗЗП чл. 57, т. 3) only if the consumer is told and agrees
   before ordering.
8. **Shop notifications go to a consumer mailbox** (abv.bg) outside the company's control, so
   customer names, emails and change requests are copied to a third-party provider with no
   processor agreement.
9. **IP addresses in three places** with no stated purpose or retention: order fields, file rows,
   approval note.
10. **Data export is partial**: consent, change-request messages and the approval IP note are not
    in the export.

## 3. Plan

### Phase A: code (plugin and seed)

| # | Change | Where | Verify |
|---|---|---|---|
| A1 | On order deletion (`woocommerce_before_delete_order`, also trash→delete), remove the order's file rows and files, tokens, scheduled reminders and order notes. | `Reklamo_Cleanup` | PHPUnit on the hook; e2e: place, delete, assert disk and tables empty; live: one order placed and deleted, counts checked over SSH |
| A2 | Seed `woocommerce_feature_order_attribution_enabled no`. Then confirm the storefront sets no cookies: load home, request page and order page in Playwright and assert the cookie jar is empty. If it is, the Privacy Policy states "only strictly necessary cookies" and **no cookie banner is needed**. | `scripts/seed.sh` (`opt`), e2e | cookie assertion in e2e; live check after deploy |
| A3 | Record consent on the order: `_reklamo_consent_at` (UTC), `_reklamo_consent_version` (a version string kept in Settings → Reklamo → Legal, bumped by the owner when the texts change), and the hashed IP. Show it in the admin box and include it in the export. | `Reklamo_Request`, `Reklamo_Admin_Order`, `Reklamo_Privacy`, settings | PHPUnit; e2e asserts the meta after an order |
| A4 | Withdrawal waiver: second required checkbox on the request form, "I understand the goods are made to my specification and I lose the right of withdrawal", stored like A3. | `Reklamo_Request` | e2e: refused without it, stored with it |
| A5 | Anonymise closed orders after a retention period: extend `apply_retention` to call WooCommerce's native `WC_Privacy_Erasers::remove_order_personal_data()` for orders closed longer than a **second** setting `reklamo_anonymize_months`, after the files are gone. Also strip our own meta: tracking URL, change-request history, consent IP, the IP in the approval note. Order totals and dates stay for accounting. | `Reklamo_Cleanup`, settings | PHPUnit with a fixture order; e2e off (time based); `wp eval` run on staging data |
| A6 | Extend the eraser: for closed orders also call `remove_order_personal_data()` and strip the meta from A5. Keep the "open orders are kept" rule. Extend the exporter with consent, change requests and the approval time. | `Reklamo_Privacy` | Tools → Erase Personal Data on a test email locally |
| A7 | IP minimisation: stop writing `created_ip` on file rows (drop the column in the next install step, or leave it null); keep the WooCommerce order IP and the approval-note IP because they are the proof of who approved, and let A5 remove them. | `Reklamo_Storage`, `Reklamo_Install` | PHPUnit |
| A8 | Trader identification: settings `reklamo_eik`, `reklamo_vat`, `reklamo_legal_address` (`opt_default`, owner fills); footer prints company name, ЕИК, ДДС №, address, email, phone; Terms page can pull them through a shortcode `[reklamo_company]` so the text never goes stale. | settings, `footer.php`, shortcode | screenshot on phone and desktop |
| A9 | Shop notifications to the account mailbox: seed `admin_email` and WooCommerce's new-order recipient as `opt_default` to `office@reklamo.bg`, and document why a consumer mailbox is not acceptable. Owner may forward from there. | `scripts/seed.sh`, `docs/DEPLOYMENT.md` | mail test after deploy |
| A10 | Privacy Policy page: build it from a template with placeholders filled from settings (controller, ЕИК, address, retention months) through `fill_if_empty`, so the numbers on the page always match the configuration. | `scripts/seed.sh`, shortcode for retention values | read the live page |

**Status: A1 to A10 are implemented.** How each one ended up:

- A1: `Reklamo_Cleanup::purge_order()` on `woocommerce_before_delete_order` (fired by both the HPOS
  and the legacy data store; trashing fires a different hook and keeps everything, since trash is
  reversible). Verified in e2e: trash + delete from the dashboard, then the file download and the
  tracking link answer 404.
- A2: seeded off. An e2e test opens home, shop, product, request and order pages in a fresh browser
  context and asserts the cookie jar is empty. The Privacy Policy therefore states "no cookie banner".
- A3: `Reklamo_Privacy::record_consent()` writes `_reklamo_consent_at`, `_reklamo_consent_version`
  (from Settings → Reklamo → Legal & privacy) and a keyed hash of the IP, shown in the admin box
  and exported.
- A4: second required checkbox `rq_waiver`, stored as `_reklamo_waiver_at`; refused with its own
  message when missing.
- A5: `apply_anonymization()` in the hourly cleanup calls `Reklamo_Privacy::anonymize_order()` for
  closed orders older than `reklamo_anonymize_months` (never below the file period) that lack the
  `_anonymized` flag. It deletes the files first, then hands the order to WooCommerce's
  `WC_Privacy_Erasers::remove_order_personal_data()`, whose hook runs `strip_order()`. Verified
  locally by backdating an order in `wp_wc_orders` (an order `save()` resets the modified date,
  so the setter route cannot be used for this).
- A6: the eraser anonymises closed orders the same way and reports open ones as retained; the
  exporter adds consent, waiver, approval time and change requests.
- A7: file rows no longer record an IP (`created_ip` stays null; the column remains for old rows).
- A8: settings `reklamo_eik`, `reklamo_vat`, `reklamo_legal_address`; footer line under the
  copyright when ЕИК is filled; shortcodes `[reklamo_company]` and `[reklamo_value key="…"]`
  (whitelisted keys only).
- A9: seed fills WooCommerce's new-order recipient with the sender mailbox when empty; the four
  plugin admin emails default to the sender mailbox too. `admin_email` stays the owner's choice, it
  only receives WordPress notices.
- A10: Terms and Privacy Policy templates seeded by `fill_if_placeholder`, replacing the one-line
  placeholders and leaving any owner-edited page alone. Every number in them is a shortcode.

What the customer-facing notes now say: the approval note carries the IP as proof of who approved
and is flagged (`_reklamo_personal` comment meta) so anonymisation replaces it; any other note that
happens to contain an address is masked by `mask_ips()`.

Order of work was A1 and A2 first (real exposure, small), then A3+A4 together (both touch the form),
then A8+A9+A10 (owner-facing texts), then A5+A6+A7 (retention and erasure).

### Phase B: content (owner with a lawyer)

- **Privacy Policy** (Политика за поверителност), in Bulgarian. Sections: controller identity and
  contact; what is collected (the inventory above); purposes and legal bases: performance of the
  contract for name, contact, delivery and invoice data (Art. 6(1)(b)); legal obligation for
  invoice data retention (Art. 6(1)(c)); legitimate interest for IP addresses, rate limiting and
  the approval proof (Art. 6(1)(f)); recipients and processors: SuperHosting.bg (hosting and mail,
  Bulgaria), the courier, the accountant; retention periods matching the settings from A5 and the
  Accountancy Act for invoices; the customer's rights and how to exercise them by email; the right
  to complain to КЗЛД; cookies: only strictly necessary, none from our code (pending A2).
- **Terms and Conditions** (Общи условия): trader identification; how an order is placed and when
  the contract is concluded (on approval of the mockup); prices include VAT; deposit and balance
  terms; production and delivery times; the withdrawal exemption for personalised goods with the
  waiver from A4; complaints and warranty; link to the ODR platform and КЗП.
- **Register of processing activities** (Art. 30): one page kept by the owner, listing the rows
  of the inventory table with purpose, legal basis, recipients and retention.
- **Processor agreements**: confirm SuperHosting's general terms include data-processing terms
  (they publish a GDPR annex); get one from the courier and the accountant if they handle customer
  data.
- **Breach procedure**: who notices, who decides, notification to КЗЛД within 72 hours, notification
  to customers when the risk is high. The backups and the private directory are the assets.

### Phase C: operations

- Send shop notifications to `office@reklamo.bg` (A9) and read them there or forward from there.
- One administrator account with a unique strong password; enable two-factor authentication when
  the owner is ready (WordPress ships it).
- Confirm the SuperHosting backup includes `~/reklamo-private` and note its retention, so the
  Privacy Policy can say how long deleted data survives in backups.
- Re-run the phone audit and the end-to-end flow after Phase A, and place one order that is then
  deleted, to prove A1 on the live site.

## 4. Decisions needed from the owner

| Decision | Default proposed | Note |
|---|---|---|
| Months after an order closes before its **files** are deleted | 12 (current) | already a setting |
| Months after an order closes before the order is **anonymised** | 36, seeded once as `reklamo_anonymize_months`, editable under Settings → Reklamo → Legal & privacy | invoices are issued outside the site, so the order need not carry the address for the accounting period; confirm with the accountant, since accounting documents are kept 10 years under the Accountancy Act |
| Company legal name, ЕИК, ДДС №, registered address | to fill | needed for footer, Terms and Policy |
| Mailbox for shop notifications | `office@reklamo.bg` | replaces the abv.bg address |
| Policy version string | `2026-09`, seeded once as `reklamo_legal_version` | bump under Settings → Reklamo → Legal & privacy when the texts change; recorded on every order |
| Lawyer review of the two texts | yes | the templates get the structure right, not the legal wording |

## 5. What this plan does not cover

Marketing consent and newsletters (none exist), customer accounts (none), payment processors
(bank transfer only, no card data touches the site), and employees' data.

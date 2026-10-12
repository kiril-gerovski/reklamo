#!/usr/bin/env bash
# THE configuration of the site, as code.
# Values below are Bulgarian on purpose: they are site CONTENT (what the customer sees), not code.
# Works locally (Docker) and on the server (WP_PATH=... REKLAMO_ENV=production scripts/seed.sh).
# WordPress keeps config in the database, which git cannot track — so every
# dashboard setting must be expressed here. If a setting exists only in a local
# database, it does not exist. Idempotent: re-running updates, never duplicates.
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; . ./.env; set +a
. scripts/lib.sh
cli_up
wc() { wp wc "$@" --user="$WP_ADMIN_USER"; }
# Warn-and-continue: WooCommerce refuses direct writes to a few of its admin
# options; a cosmetic setting must never abort the seed.
opt() { wp option update "$1" "$2" "${@:3}" >/dev/null 2>&1 || echo "  ! could not set $1"; }
# Owner-editable content (company details, bank data, prices, texts): seeded once, then
# whatever the owner sets in the dashboard wins on every later run. Missing *or empty* counts as
# unseeded — WordPress and WooCommerce pre-create several of these as empty strings, and `wp option
# add` refuses to touch a key that already exists.
opt_default() {
  [ -n "$(wp option get "$1" 2>/dev/null || true)" ] && return 0
  wp option update "$1" "$2" "${@:3}" >/dev/null 2>&1 || echo "  ! could not set $1"
}
# Owner content whose earlier seed value was a sample: replaced only while it is missing, empty or
# still exactly one of those samples, so a value the owner typed is never overwritten.
opt_replace() { # key new-value earlier-sample...
  local key=$1 new=$2 cur old; shift 2
  cur=$(wp option get "$key" 2>/dev/null || true)
  [ "$cur" = "$new" ] && return 0
  for old in "" "$@"; do
    if [ "$cur" = "$old" ]; then
      wp option update "$key" "$new" >/dev/null 2>&1 || echo "  ! could not set $key"
      return 0
    fi
  done
}

echo "→ general settings"
opt blogname "$WP_TITLE"
opt_default blogdescription "Промо пакети с вашето лого"
opt timezone_string "Europe/Sofia"
opt date_format "d.m.Y"
opt time_format "H:i"
opt start_of_week 1
opt WPLANG bg_BG
opt default_comment_status closed
opt default_ping_status closed
if [ "${REKLAMO_ENV:-local}" = production ]; then opt blog_public 1; else opt blog_public 0; fi   # indexing only on production
opt uploads_use_yearmonth_folders 1
# Browser-tab icon, from the theme's brand mark. Imported once; an icon the owner uploads stays.
if [ -z "$(wp option get site_icon 2>/dev/null || true)" ] || [ "$(wp option get site_icon 2>/dev/null || true)" = "0" ]; then
  icon_path=$(wp eval "\$p = get_theme_file_path( 'assets/img/icon.png' ); echo file_exists( \$p ) ? \$p : '';")
  if [ -n "$icon_path" ]; then
    icon_att=$(wp media import "$icon_path" --title="Reklamo.bg" --alt="Reklamo.bg" --porcelain 2>/dev/null | tail -n1)
    [ -n "$icon_att" ] && opt site_icon "$icon_att" && echo "  site icon set"
  fi
fi
wp rewrite structure '/%postname%/' >/dev/null
wp rewrite flush --hard >/dev/null 2>&1 || wp rewrite flush >/dev/null

echo "→ WooCommerce store settings"
opt_replace woocommerce_store_address "ул. Хризантема 15, ет. 2, ап. 8" "ул. Примерна 1"
opt_default woocommerce_store_city "София"
opt_default woocommerce_store_postcode "1000"
opt woocommerce_default_country "BG"
opt woocommerce_allowed_countries "specific"
opt woocommerce_specific_allowed_countries '["BG"]' --format=json
opt woocommerce_ship_to_countries ""     # ship to all allowed (= BG)
# Bulgaria is in the eurozone since 2026-01-01; dual BGN display obligation ended 2026-08-08.
opt woocommerce_currency "EUR"
opt woocommerce_currency_pos "right_space"   # 100,00 €
opt woocommerce_price_thousand_sep " "
opt woocommerce_price_decimal_sep ","
opt woocommerce_price_num_decimals 2
opt woocommerce_weight_unit "kg"
opt woocommerce_dimension_unit "cm"
# VAT 20%: prices are entered and shown INCLUDING tax (decided 2026-09-05).
opt woocommerce_calc_taxes "yes"
opt woocommerce_prices_include_tax "yes"
opt woocommerce_tax_based_on "base"
opt woocommerce_tax_display_shop "incl"
opt woocommerce_tax_display_cart "incl"
opt woocommerce_price_display_suffix "с ДДС"
opt woocommerce_tax_total_display "single"
opt woocommerce_tax_round_at_subtotal "no"
# Made-to-order: no stock.
opt woocommerce_manage_stock "no"
opt woocommerce_notify_low_stock "no"
opt woocommerce_notify_no_stock "no"
opt woocommerce_hide_out_of_stock_items "no"
# Checkout: guests, no forced accounts, no coupons.
opt woocommerce_enable_guest_checkout "yes"
opt woocommerce_enable_checkout_login_reminder "no"
opt woocommerce_enable_signup_and_login_from_checkout "no"
opt woocommerce_enable_myaccount_registration "no"
opt woocommerce_enable_coupons "no"
opt woocommerce_enable_reviews "no"
# Emails
opt_default woocommerce_email_from_name "Reklamo.bg"
opt_default woocommerce_email_from_address "office@reklamo.bg"
opt_default woocommerce_email_footer_text "Reklamo.bg — промо пакети с вашето лого"
# Shop notifications carry customer data: they go to the shop's own mailbox, never to a consumer
# address. Filled once when the recipient is empty; the owner may change it under WooCommerce → Emails.
wp eval '
$from = (string) get_option( "woocommerce_email_from_address" );
$o    = (array) get_option( "woocommerce_new_order_settings", array() );
if ( is_email( $from ) && empty( $o["recipient"] ) ) { $o["recipient"] = $from; update_option( "woocommerce_new_order_settings", $o ); echo "  new-order recipient → $from\n"; }
'
# Silence the noise
opt woocommerce_allow_tracking "no"
# Order Attribution sets sbjs_* tracking cookies on every visitor without consent; off keeps the storefront cookie-free.
opt woocommerce_feature_order_attribution_enabled "no"
opt woocommerce_show_marketplace_suggestions "no"
opt woocommerce_merchant_email_notifications "no"
opt woocommerce_onboarding_profile '{"skipped":true,"completed":true,"is_store_country_set":true}' --format=json
opt woocommerce_task_list_hidden "yes"
opt woocommerce_extended_task_list_hidden "yes"
opt woocommerce_admin_customize_store_completed "yes"
# New WooCommerce installs boot in "coming soon" mode, so a fresh install publishes the storefront.
# Later runs never touch it: going live is the owner's decision, and `update --seed` must not be
# able to publish a site that is deliberately still hidden.
if [ "${REKLAMO_INSTALL:-0}" = 1 ]; then
  opt woocommerce_coming_soon "no"
  opt woocommerce_store_pages_only "no"
  opt woocommerce_private_link "no"
elif [ "$(wp option get woocommerce_coming_soon 2>/dev/null || true)" = "yes" ]; then
  echo "  note: the store is in \"coming soon\" mode — publish it from WooCommerce → Settings → Site visibility"
fi
# Disable WooCommerce/WP auto-updates (pinned version; gateway JS sits on a moving API).
opt auto_update_plugins '[]' --format=json
opt auto_update_themes '[]' --format=json

echo "→ HPOS (High-Performance Order Storage)"
# The WP-CLI install path leaves HPOS unset. Create the tables and enable the
# feature the same way the Settings → Advanced → Features screen does.
wp eval '
$sync = wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
if ( ! $sync->check_orders_table_exists() ) { $sync->create_database_tables(); }
update_option( "woocommerce_custom_orders_table_enabled", "yes" );
update_option( "woocommerce_custom_orders_table_data_sync_enabled", "no" );
echo "  HPOS enabled: " . ( Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? "yes" : "NO" ) . "\n";
'

echo "→ company & process settings (WooCommerce → Settings → Reklamo)"
opt_default reklamo_company_name "Reklamo.bg"
opt_default reklamo_tagline "Промоционални пакети и брандирани продукти за твоя бизнес. Ясни цени, качествено изпълнение и персонално обслужване."
opt_replace reklamo_phone "+359 894 65 58 58" "+359 88 123 4567"
opt_default reklamo_email "office@reklamo.bg"
opt_replace reklamo_address "София, ул. Източна тангента 96" "София, България"
opt_default reklamo_hours "10:00 – 17:00"
opt_replace reklamo_facebook "" "https://facebook.com/"
opt_default reklamo_instagram "https://www.instagram.com/reklamo.bg/"
# Replaces the earlier seed placeholder only, so an owner's own link is never touched.
[ "$(wp option get reklamo_instagram 2>/dev/null || true)" = "https://instagram.com/" ] && opt reklamo_instagram "https://www.instagram.com/reklamo.bg/"
opt_replace reklamo_linkedin "" "https://linkedin.com/"
opt_default reklamo_mockup_deadline 24
# Payment is 100% before production; 50 was the earlier default.
opt_replace reklamo_deposit_pct 100 50
opt_default reklamo_free_corrections 2
opt_default reklamo_correction_fee 15
opt_default reklamo_production_days "5–10"
opt_default reklamo_free_shipping 400
opt_default reklamo_note_max 300

echo "→ tax rate (BG 20% standard)"
if [ "$(wc tax list --format=count 2>/dev/null || echo 0)" = "0" ]; then
  wc tax create --country=BG --rate=20 --name="ДДС" --class=standard --shipping=true >/dev/null && echo "  created BG 20%"
fi

echo "→ bank details (WooCommerce → Settings → Reklamo → Bank details)"
opt_replace reklamo_bank_name "УниКредит Булбанк АД" "УниКредит Булбанк"
opt_replace reklamo_iban "BG36UNCR70001523203323" "BG00UNCR00000000000000"
opt_default reklamo_bic "UNCRBGSF"
opt_replace reklamo_account_holder "ДИ АР ТИ ЕООД" "Рекламо ЕООД"
opt_default reklamo_reminder_days "3,7,14"
opt_default reklamo_stale_days 7
opt_default reklamo_max_upload_mb 300
opt_default reklamo_retention_months 12

echo "→ legal & privacy (WooCommerce → Settings → Reklamo → Legal & privacy)"
opt_default reklamo_legal_name "ДИ АР ТИ ЕООД"
opt_default reklamo_eik "205018634"
opt_default reklamo_vat "BG205018634"
opt_default reklamo_legal_address "гр. София 1000, р-н Красно село, ул. Хризантема 15, ет. 2, ап. 8"
opt_default reklamo_manager "Бойка Николаева Заркова"
opt_default reklamo_privacy_email "privacy@reklamo.bg"
opt_default reklamo_anonymize_months 36
# The terms and privacy texts changed in 2026-10; a version the owner set themselves stays.
opt_replace reklamo_legal_version "2026-10" "2026-09"

echo "→ payment gateways"
# Our no-payment gateway is the only one enabled. Title/description are site content (Bulgarian).
opt woocommerce_reklamo_request_settings '{"enabled":"yes","title":"Заявка без плащане","description":"На този етап не се извършва плащане. Ще подготвим визуализация за одобрение и ще ви изпратим банковите данни след това.","instructions":"Благодарим! Ще получите визуализация за одобрение до 24 работни часа."}' --format=json
opt woocommerce_bacs_settings   '{"enabled":"no"}' --format=json
opt woocommerce_cheque_settings '{"enabled":"no"}' --format=json
opt woocommerce_cod_settings    '{"enabled":"no"}' --format=json

echo "→ shipping: one zone (България), free shipping"
# WC-CLI's shipping_zone_location has no write command; use the PHP API instead.
wp eval '
$zone = null;
foreach ( WC_Shipping_Zones::get_zones() as $z ) {
	if ( "България" === $z["zone_name"] ) { $zone = new WC_Shipping_Zone( $z["id"] ); break; }
}
if ( ! $zone ) { $zone = new WC_Shipping_Zone(); $zone->set_zone_name( "България" ); $zone->save(); }
$zone->set_locations( array( array( "code" => "BG", "type" => "country" ) ) );
$zone->save();
$has = false;
foreach ( $zone->get_shipping_methods() as $m ) { if ( "free_shipping" === $m->id ) { $has = $m->instance_id; } }
if ( ! $has ) { $has = $zone->add_shipping_method( "free_shipping" ); }
update_option( "woocommerce_free_shipping_" . $has . "_settings", array( "title" => "Доставка", "requires" => "" ) );
echo "  zone " . $zone->get_id() . ", free_shipping instance " . $has . "\n";
'

echo "→ pages"
# post_name__in (not --name) so drafts are found too — WC/WP sample pages are drafts.
page_id() { wp post list --post_type=page --post_name__in="$1" --post_status=any --field=ID | head -n1; }
ensure_page() { # slug title [content]
  local id; id=$(page_id "$1")
  if [ -z "$id" ]; then
    id=$(wp post create --post_type=page --post_status=publish --post_name="$1" --post_title="$2" --post_content="${3:-}" --porcelain)
  fi
  echo "$id"
}
home_id=$(ensure_page nachalo "Начало" "<!-- wp:paragraph --><p>Избери пакет. Изпрати логото. Ние правим останалото.</p><!-- /wp:paragraph -->")
ensure_page kak-raboti "Как работи" >/dev/null
ensure_page za-biznesa "За бизнеса" >/dev/null
# Landing page behind the ПРОДУКТИ menu item. The native WooCommerce shortcode lists every
# top-level product category, so a category added later shows up without touching the page.
ensure_page produkti "Продукти" '<!-- wp:shortcode -->[product_categories parent="0" hide_empty="0" columns="3"]<!-- /wp:shortcode -->' >/dev/null
ensure_page za-nas "За нас" >/dev/null
ensure_page kontakti "Контакти" >/dev/null
ensure_page kachi-logo "Качи лого и визуализирай" >/dev/null
ensure_page dostavka-i-srokove "Доставка и срокове" >/dev/null
ensure_page plashtane "Плащане" >/dev/null
ensure_page chesto-zadavani-vaprosi "Често задавани въпроси" >/dev/null
ensure_page obshti-usloviya "Общи условия" >/dev/null
ensure_page politika-za-poveritelnost "Политика за поверителност" >/dev/null
fill_if_empty() { # slug content
  local id; id=$(page_id "$1"); [ -n "$id" ] || return 0
  [ -z "$(wp post get "$id" --field=post_content)" ] && wp post update "$id" --post_content="$2" >/dev/null || true
}
# Like fill_if_empty, and also replaces a page that still holds an earlier seed placeholder.
fill_if_placeholder() { # slug placeholder content
  local id cur; id=$(page_id "$1"); [ -n "$id" ] || return 0
  cur=$(wp post get "$id" --field=post_content)
  if [ -z "$cur" ] || [ "$cur" = "$2" ]; then wp post update "$id" --post_content="$3" >/dev/null && echo "  $1 filled from template"; fi
}
# Replaces a page's text only while it is empty or still holds one of the earlier seed texts
# (compared without whitespace, which WordPress may reflow) — an owner's edit is never touched.
refresh_if_seeded() { # slug new-content earlier-seed-content...
  local slug=$1 new=$2 id cur old; shift 2
  id=$(page_id "$slug"); [ -n "$id" ] || return 0
  cur=$(wp post get "$id" --field=post_content | tr -d '[:space:]')
  [ "$cur" = "$(printf '%s' "$new" | tr -d '[:space:]')" ] && return 0
  for old in "" "$@"; do
    if [ "$cur" = "$(printf '%s' "$old" | tr -d '[:space:]')" ]; then
      wp post update "$id" --post_content="$new" >/dev/null && echo "  $slug: text updated"
      return 0
    fi
  done
}
# "How it works": the six homepage steps, each explained in detail (deposit % and file limits mirror the seeded settings above).
kak_old=$(cat <<'HTML'
<!-- wp:shortcode -->
[reklamo_steps section="1"]
<!-- /wp:shortcode -->

<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">Избираш пакет, качваш логото си и получаваш визуализация до 24 работни часа. След одобрение плащаш 50% аванс по банков път, произвеждаме и доставяме. Ето какво се случва на всяка стъпка.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-1"} -->
<h2 class="wp-block-heading" id="stapka-1">1. Избираш пакет</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Всеки промо пакет е с фиксирано съдържание, количество и цена с ДДС — това, което виждаш на страницата, е крайната сума. Няма скрити разходи за подготовка на файлове или за брандиране; те са включени в цената. Ако пакетите не отговарят на нуждите ти, пиши ни и ще предложим вариант.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-2"} -->
<h2 class="wp-block-heading" id="stapka-2">2. Качваш логото</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Във формата „Качи лого“ прикачваш файла с логото, оставяш име и имейл и по желание — указания към дизайнера (цвят, разположение, размер). Приемаме AI, EPS, PDF, PSD, CDR, SVG, PNG и JPG до 300 MB. Най-добър резултат дават векторните формати (AI, EPS, PDF, SVG); при PNG или JPG е нужно логото да е с висока резолюция.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>На този етап не се извършва плащане и не се създава акаунт. Веднага след изпращането получаваш имейл с връзка към <strong>страницата на твоята поръчка</strong> — там следиш напредъка, виждаш визуализациите и плащанията. Запази този имейл: връзката е твоят достъп до поръчката.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-3"} -->
<h2 class="wp-block-heading" id="stapka-3">3. Получаваш визуализация</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Наш дизайнер подготвя визуализация (mockup) на продуктите от пакета с твоето лого — обикновено до 24 работни часа. Когато е готова, получаваш имейл, а визуализацията се появява и на страницата на поръчката.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-4"} -->
<h2 class="wp-block-heading" id="stapka-4">4. Одобряваш визията</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Разглеждаш визуализацията и решаваш направо от страницата на поръчката: <strong>„Одобрявам“</strong> или <strong>„Искам промени“</strong> с описание какво да се коригира. Дизайнерът подготвя нова версия и получаваш нов имейл. Няма ограничение в броя корекции — работим до пълно одобрение. Всички версии остават видими на страницата на поръчката, заедно с твоите коментари.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-5"} -->
<h2 class="wp-block-heading" id="stapka-5">5. Плащаш аванс</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>След одобрението виждаш дължимия аванс — 50% от стойността на поръчката — и банковите ни данни. Плащането е само по банков път, а номерът на поръчката е основанието за превода. На същата страница попълваш данните за фактура (фирма с ЕИК и МОЛ или частно лице) и адреса за доставка; можеш да ги коригираш, докато авансът бъде потвърден.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Потвърждаваме превода ръчно в работни дни и те уведомяваме по имейл. От този момент поръчката влиза в производство — ако след одобрението поискаш нова визуализация, авансът и данните се нулират и стъпката започва отначало.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-6"} -->
<h2 class="wp-block-heading" id="stapka-6">6. Доплащаш и получаваш</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Когато продуктите са готови, получаваш имейл с остатъка за плащане (другите 50%) и банковите данни. Изпращаме поръчката веднага след постъпване на превода, на адреса от данните за доставка. Фактурата се издава по данните, които си попълнил. Страницата на поръчката остава достъпна и след това — като архив на визуализациите и плащанията.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Имаш въпрос по време на поръчката?</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Пиши ни или се обади — данните са в <a href="/kontakti/">Контакти</a>. Посочи номера на поръчката и ще ти отговорим бързо. Подробности за сроковете има в <a href="/dostavka-i-srokove/">Доставка и срокове</a>, а за начина на плащане — в <a href="/plashtane/">Плащане</a>.</p>
<!-- /wp:paragraph -->
HTML
)
kak_new=$(cat <<'HTML'
<!-- wp:shortcode -->
[reklamo_steps section="1"]
<!-- /wp:shortcode -->

<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">Добавяш пакетите в количката, качваш логото си и получаваш визуализация до [reklamo_value key="mockup_deadline"] работни часа. След одобрение плащаш поръчката по банков път, произвеждаме и доставяме. Ето какво се случва на всяка стъпка.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-1"} -->
<h2 class="wp-block-heading" id="stapka-1">1. Избираш пакет</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Всеки промо пакет е с фиксирано съдържание, количество и цена с ДДС — това, което виждаш на страницата, е стойността на пакета. Подготовката на файловете и брандирането с едно лого са включени. Добавяш в количката толкова пакети, колкото искаш. Ако пакетите не отговарят на нуждите ти, пиши ни и ще предложим вариант.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-2"} -->
<h2 class="wp-block-heading" id="stapka-2">2. Качваш логото</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>В количката качваш логото и пишеш бележка към дизайнера (цвят, разположение, размер) за всеки продукт — едно лого може да е за няколко продукта. Препоръчваме векторни файлове AI, EPS, SVG или PDF; приемаме и PSD, CDR, PNG и JPG до [reklamo_value key="max_upload_mb"] MB, като проверяваме дали качеството им е подходящо за печат. После оставяш име, имейл и телефон.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>На този етап не се извършва плащане и не се създава акаунт. Веднага след изпращането получаваш имейл с връзка към <strong>страницата на твоята поръчка</strong> — там следиш напредъка, виждаш визуализациите и плащането. Запази този имейл: връзката е твоят достъп до поръчката.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-3"} -->
<h2 class="wp-block-heading" id="stapka-3">3. Получаваш визуализация</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Наш дизайнер подготвя визуализация на всеки продукт с твоето лого — обикновено до [reklamo_value key="mockup_deadline"] работни часа. Когато е готова, получаваш имейл, а визуализацията се появява и на страницата на поръчката. Визуализацията е ориентировъчна: възможни са минимални разлики в цветовете, размера и позиционирането на логото според материала и технологията за печат.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-4"} -->
<h2 class="wp-block-heading" id="stapka-4">4. Одобряваш визията</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Разглеждаш визуализацията и решаваш направо от страницата на поръчката: <strong>„Одобрявам“</strong> или <strong>„Искам промени“</strong> с описание какво да се коригира — за всеки продукт поотделно. Включени са първоначалната визуализация и до [reklamo_value key="free_corrections"] безплатни корекции; всяка следваща корекция е [reklamo_value key="correction_fee"] € и се договаря предварително. Грешки, допуснати от нас, поправяме безплатно и те не се броят. Всички версии остават видими на страницата на поръчката, заедно с твоите коментари.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-5"} -->
<h2 class="wp-block-heading" id="stapka-5">5. Плащаш поръчката</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>След като одобриш визуализациите на всички продукти, получаваш потвърждение на поръчката, сумата за плащане и банковите ни данни. За стартиране на производството е необходимо плащане в пълен размер на стойността на поръчката по банков път; номерът на поръчката е основанието за превода. На същата страница попълваш данните за фактура (фирма с ЕИК и МОЛ или частно лице) и адреса за доставка; можеш да ги коригираш, докато плащането бъде потвърдено.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Потвърждаваме превода ръчно в работни дни и те уведомяваме по имейл. От този момент поръчката влиза в производство — стандартният срок е [reklamo_value key="production_days"] работни дни. Ако след одобрението поискаш нова визуализация, сумата за плащане и данните се нулират и стъпката започва отначало.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"anchor":"stapka-6"} -->
<h2 class="wp-block-heading" id="stapka-6">6. Получаваш поръчката</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Когато продуктите са готови, изпращаме поръчката със Спиди, Еконт или при възможност BoxNow и Pigeon до адреса от данните за доставка и ти изпращаме информация за проследяване. При поръчка над [reklamo_value key="free_shipping"] € доставката е безплатна; под тази сума е за сметка на получателя по тарифите на куриера. Страницата на поръчката остава достъпна и след това — като архив на визуализациите и плащането.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Имаш въпрос по време на поръчката?</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Пиши ни или се обади — данните са в <a href="/kontakti/">Контакти</a>. Посочи номера на поръчката и ще ти отговорим бързо. Подробности за сроковете има в <a href="/dostavka-i-srokove/">Доставка и срокове</a>, а за начина на плащане — в <a href="/plashtane/">Плащане</a>.</p>
<!-- /wp:paragraph -->
HTML
)
fill_if_empty kak-raboti "$kak_new"
refresh_if_seeded kak-raboti "$kak_new" "$kak_old"
biz_v2=$(cat <<'HTML'
<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">От визитката, която оставяте след среща, до рекламния подарък, който остава при клиента — REKLAMO.BG Ви помага да представите бизнеса си професионално и запомнящо се.</p>
<!-- /wp:paragraph -->
HTML
)
biz_new=$(cat <<'HTML'
<!-- wp:heading -->
<h2 class="wp-block-heading">Рекламни решения за Вашия бизнес</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>REKLAMO.BG предлага рекламни продукти, персонализирани фирмени подаръци и печатни материали, с които Вашата компания може да се представи професионално и запомнящо се.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Изработваме календари, визитки, флаери и разнообразни брандирани продукти. Предлагаме и готови промо пакети с подбрани артикули, ясни количества и предварително определени цени.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>За по-мащабни проекти, фирмени събития, рекламни кампании или нестандартни комбинации от продукти можете да заявите <a href="/kontakti/">индивидуална оферта</a>.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Нашата цел е да Ви предложим удобно обслужване, внимание към дизайна и качествено изпълнение — от първоначалната идея до готовата поръчка.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>REKLAMO.BG — Вашата идея. Вашият бранд. Нашето изпълнение.</strong></p>
<!-- /wp:paragraph -->
HTML
)
refresh_if_seeded za-biznesa "$biz_new" "$biz_v2" '<!-- wp:paragraph --><p>Брандирани продукти за екипи, събития и клиенти — с фиксирани количества и ясни цени.</p><!-- /wp:paragraph -->'
kont_new=$(cat <<'HTML'
<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>Телефон:</strong> [reklamo_value key="phone"]</li>
<li><strong>Имейл:</strong> [reklamo_value key="email"]</li>
<li><strong>Адрес:</strong> [reklamo_value key="address"]</li>
<li><strong>Работно време:</strong> [reklamo_value key="hours"]</li>
</ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>Пишете ни или се обадете — при въпрос по поръчка посочете нейния номер. За лични данни и изтриване на качени лога: [reklamo_value key="privacy_email"].</p>
<!-- /wp:paragraph -->
HTML
)
refresh_if_seeded kontakti "$kont_new" '<!-- wp:paragraph --><p>Пишете ни на office@reklamo.bg или се обадете на +359 88 123 4567.</p><!-- /wp:paragraph -->'
# Delivery: one <h3> per stage and its text under it — the theme shows them as cards on this page
# and in the homepage band. Numbers come from the settings.
dost_old=$(cat <<'HTML'
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Визуализация до [reklamo_value key="mockup_deadline"] работни часа</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>След заявката нашият дизайнер подготвя визуализация с Вашето лого. Получавате я по имейл и на страницата на поръчката.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Производство след аванса</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Изработката започва веднага щом получим аванса от [reklamo_value key="deposit_pct"]%. Срокът за производство Ви съобщаваме с потвърждението на плащането.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Доставка с куриер в цяла България</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Изпращаме до адреса, който посочите в данните за доставка на страницата на поръчката.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Изпращане след доплащане</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Остатъкът се плаща по банков път, когато продуктите са готови. Поръчката тръгва веднага щом преводът постъпи, а фактурата се издава по Вашите данни.</p>
<!-- /wp:paragraph -->
HTML
)
dost_new=$(cat <<'HTML'
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Производство за [reklamo_value key="production_days"] работни дни</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Всеки персонализиран продукт се изработва специално за Вас, след одобрение на окончателната визуализация и получаване на плащането. Срокът започва от момента на плащането и не включва времето за доставка. При по-големи количества, нестандартни продукти или специфични изисквания срокът се уточнява индивидуално преди потвърждаване на поръчката.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Спиди, Еконт, BoxNow и Pigeon</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Доставките на територията на България се извършват чрез Спиди, Еконт и при възможност BoxNow и Pigeon. Можете да изберете предпочитан куриер и доставка до офис или адрес, когато съответната услуга е налична.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Безплатна доставка над [reklamo_value key="free_shipping"] €</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>При поръчка над [reklamo_value key="free_shipping"] € доставката е безплатна. Под тази сума цената на доставката е за сметка на получателя по тарифите на избрания куриер.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Проследяване и доставка извън България</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>След изпращане на поръчката ще получите информация за проследяване на пратката. За доставки извън България се изготвя индивидуална оферта след предварително запитване.</p>
<!-- /wp:paragraph -->
HTML
)
refresh_if_seeded dostavka-i-srokove "$dost_new" '<!-- wp:paragraph --><p>Производство и доставка в договорени срокове след получаване на аванса.</p><!-- /wp:paragraph -->' "$dost_old"
pl_new=$(cat <<'HTML'
<!-- wp:paragraph -->
<p>След като одобрите визуализацията на избраните продукти, ще получите потвърждение на поръчката и документ за плащане.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>За стартиране на производството е необходимо плащане в пълен размер на стойността на поръчката по банков път. Номерът на поръчката е основанието за превода. За получените плащания се издават съответните счетоводни документи.</p>
<!-- /wp:paragraph -->
<!-- wp:shortcode -->
[reklamo_bank_details]
<!-- /wp:shortcode -->
HTML
)
pl_old=$(cat <<'HTML'
<!-- wp:paragraph --><p>Плащане само по банков път: 50% аванс след одобрение на визуализацията, остатък преди доставка.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->
[reklamo_bank_details]
<!-- /wp:shortcode -->
HTML
)
refresh_if_seeded plashtane "$pl_new" '<!-- wp:paragraph --><p>Плащане само по банков път: 50% аванс след одобрение на визуализацията, остатък преди доставка.</p><!-- /wp:paragraph -->' "$pl_old"
# Payment page always carries the live bank details block (one source of truth).
pl_id=$(page_id plashtane)
if [ -n "$pl_id" ] && ! wp post get "$pl_id" --field=post_content | grep -q reklamo_bank_details; then
  wp post update "$pl_id" --post_content="$(wp post get "$pl_id" --field=post_content)
<!-- wp:shortcode -->
[reklamo_bank_details]
<!-- /wp:shortcode -->" >/dev/null
fi
# FAQ: every <h3> is a question and the paragraph under it the answer — the theme shows it as an
# accordion on this page and in the homepage's closing band, and as FAQPage schema. Numbers come from the settings.
faq_old=$(cat <<'HTML'
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Плащам ли нещо, когато изпращам заявката?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Не. Заявката и визуализацията са без плащане. Плащате аванс едва след като одобрите визуализацията.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">За колко време получавам визуализация?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>До [reklamo_value key="mockup_deadline"] работни часа след заявката. Изпращаме я по имейл и тя се появява и на страницата на Вашата поръчка.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Какви файлове с лого приемате?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>AI, EPS, PDF, PSD, CDR, SVG, PNG и JPG до [reklamo_value key="max_upload_mb"] MB. Най-добър резултат дават векторните формати (AI, EPS, PDF, SVG); PNG и JPG трябва да са с висока резолюция.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Колко корекции мога да поискам?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Колкото са нужни. Преди одобрение броят на корекциите не е ограничен — всяка нова версия получавате по имейл.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Как се плаща?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Само по банков път. След одобрение на визуализацията плащате аванс [reklamo_value key="deposit_pct"]% от стойността, а остатъка — преди изпращане. Номерът на поръчката е основанието за превода.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Кога започва производството?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Веднага щом получим аванса. Срокът за производство Ви съобщаваме с потвърждението на плащането.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Къде доставяте и трябва ли ми регистрация?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Доставяме с куриер до посочения от Вас адрес в България. Регистрация не е нужна: връзката в имейла отваря страницата на Вашата поръчка, където следите всяка стъпка.</p>
<!-- /wp:paragraph -->
HTML
)
faq_new=$(cat <<'HTML'
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Мога ли да видя как ще изглежда логото ми преди производството?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Да. След като изпратите логото си, подготвяме визуализация, която получавате за преглед и одобрение.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Колко корекции мога да поискам?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Предлагаме първоначална визуализация и до [reklamo_value key="free_corrections"] безплатни корекции. Всяка следваща корекция е [reklamo_value key="correction_fee"] € и се договаря предварително. Грешки, допуснати от нас, поправяме безплатно.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Какви файлове мога да изпратя?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Препоръчваме векторни файлове AI, EPS, SVG или PDF. Приемаме и PNG/JPG, като предварително проверяваме дали качеството им е подходящо за печат.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Мога ли да поръчам различно количество?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Да. Ако желаното количество не присъства сред готовите пакети, изпратете ни запитване за индивидуална оферта.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Кога започва производството?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>След окончателно одобрение на визуализацията, потвърждение на наличността и получаване на плащането. Стандартният срок е [reklamo_value key="production_days"] работни дни.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Мога ли да поръчам продукти, които не виждам в сайта?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Да. Изпратете ни описание, снимка или пример и ще проверим възможностите за изпълнение.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Мога ли да получа фактура на фирма?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Да. Въведете фирмените данни (ЕИК, ДДС № и МОЛ) на страницата на поръчката, след като одобрите визуализацията.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Какво става, ако получа продукт с дефект?</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Свържете се с нас и изпратете снимки и номера на поръчката. Ще разгледаме рекламацията и ще предложим решение съгласно приложимите законови изисквания.</p>
<!-- /wp:paragraph -->
HTML
)
refresh_if_seeded chesto-zadavani-vaprosi "$faq_new" '<!-- wp:paragraph --><p>Често задавани въпроси.</p><!-- /wp:paragraph -->' "$faq_old"
# Legal pages: structure and every number come from the settings through shortcodes, so the texts
# never contradict the configuration. A lawyer reviews the wording; the owner edits in the block editor.
# "About us": the company's own copy.
fill_if_empty za-nas "$(cat <<'HTML'
<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">REKLAMO.BG – реклама, която работи за Вашия бизнес.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>REKLAMO.BG предлага цялостни решения в областта на рекламата, печата и брандирането, създадени да помогнат на бизнеса да бъде разпознаваем, професионално представен и запомнящ се.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Предлагаме брандиране на рекламни и бизнес подаръци, както и изработка на календари, визитки, флаери и разнообразни печатни рекламни материали. Независимо дали става въпрос за малка поръчка, рекламна кампания, фирмено събитие или подаръци за клиенти и партньори, подхождаме индивидуално към всеки проект.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>За нас добрата реклама започва с правилната идея и завършва с качественото изпълнение. Затова обръщаме внимание на всеки детайл – от избора на подходящ продукт и подготовката на дизайна до печата, брандирането и готовия краен резултат.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>В REKLAMO.BG можете да избирате както отделни рекламни продукти, така и специално подбрани промо пакети, които комбинират различни артикули и услуги на атрактивна цена. Преди изработката подготвяме визуализация за одобрение, за да сте сигурни как ще изглежда Вашият бранд върху крайния продукт.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Нашата цел е да бъдем надежден рекламен партньор за Вашия бизнес – от визитката, която оставяте след среща, до рекламния подарък, който остава при клиента.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">REKLAMO.BG – Вашата идея. Вашият бранд. Нашето изпълнение.</p>
<!-- /wp:paragraph -->
HTML
)"

terms_old=$(cat <<'HTML'
<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">Тези общи условия уреждат поръчките на промоционални пакети, брандирани с логото на клиента, през сайта Reklamo.bg. С изпращането на заявка приемате условията по-долу.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Търговец</h2>
<!-- /wp:heading -->
<!-- wp:shortcode -->
[reklamo_company]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Продукти и цени</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Всеки пакет е с фиксирано съдържание, количество и цена. Цените са в евро и включват ДДС и брандирането с едно лого. Доставката в България е включена в цената.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Поръчка и одобрение</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Заявката се изпраща без плащане. До [reklamo_value key="mockup_deadline"] работни часа получавате визуализация за одобрение. Договорът се сключва с одобрението на визуализацията от Ваша страна. Броят корекции преди одобрение не е ограничен.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Плащане</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Плащането е само по банков път. След одобрение дължите аванс от [reklamo_value key="deposit_pct"]% от стойността; производството започва след постъпването му. Остатъкът се заплаща преди изпращане. Номерът на поръчката е основанието за превода. Фактура се издава по данните, които попълвате на страницата на поръчката.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Срокове и доставка</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Срокът за производство се съобщава с потвърждението на аванса. Доставката е с куриер до посочения адрес в България. Вижте и <a href="/dostavka-i-srokove/">Доставка и срокове</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Право на отказ</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Продуктите се изработват по индивидуална спецификация с Вашето лого. Съгласно чл. 57, т. 3 от Закона за защита на потребителите 14-дневното право на отказ не се прилага за такива стоки. Потвърждавате това изрично при изпращане на заявката. До одобрение на визуализацията можете да се откажете без разходи.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. Рекламации</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>За несъответствие с одобрената визуализация или дефект ни пишете на посочения имейл с номера на поръчката и снимки. Отговаряме в работни дни. При спор можете да се обърнете към Комисията за защита на потребителите (<a href="https://kzp.bg" target="_blank" rel="noopener">kzp.bg</a>).</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. Лични данни</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Как обработваме личните Ви данни е описано в <a href="/politika-za-poveritelnost/">Политиката за поверителност</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"muted"} -->
<p class="muted">Версия [reklamo_value key="legal_version"]. Прилага се българското право.</p>
<!-- /wp:paragraph -->
HTML
)
terms_new=$(cat <<'HTML'
<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">Тези общи условия уреждат поръчките на промоционални пакети, брандирани с логото на клиента, през сайта Reklamo.bg. С изпращането на заявка приемате условията по-долу.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Търговец</h2>
<!-- /wp:heading -->
<!-- wp:shortcode -->
[reklamo_company]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Продукти и цени</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Всеки пакет е с фиксирано съдържание, количество и цена. Цените са в евро и включват ДДС и брандирането с едно лого. Доставката не е включена в цената: при поръчка над [reklamo_value key="free_shipping"] € е безплатна, а под тази сума е за сметка на получателя по тарифите на избрания куриер.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Поръчка и одобрение</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Заявката се изпраща без плащане. До [reklamo_value key="mockup_deadline"] работни часа получавате визуализация за одобрение. Договорът се сключва с одобрението на визуализацията от Ваша страна. Визуализацията е ориентировъчна: възможни са минимални разлики в цветовете, размера и позиционирането на логото в зависимост от материала, технологията за печат и настройките на екрана.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Включени са първоначалната визуализация и до [reklamo_value key="free_corrections"] безплатни корекции на продукт. Всяка следваща корекция е [reklamo_value key="correction_fee"] € и се договаря предварително. Корекции на грешки, допуснати от Reklamo.bg, са безплатни и не се броят за корекции на клиента.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Плащане</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Плащането е само по банков път. След одобрение на визуализациите получавате потвърждение на поръчката и документ за плащане. За стартиране на производството е необходимо плащане в пълен размер на стойността на поръчката. Номерът на поръчката е основанието за превода. За получените плащания се издават съответните счетоводни документи по данните, които попълвате на страницата на поръчката.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Срокове и доставка</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Стандартният срок за производство е [reklamo_value key="production_days"] работни дни. Той започва от момента на плащането и не включва времето за доставка; при по-големи количества, нестандартни продукти или специфични изисквания се уточнява индивидуално. Доставките в България се извършват чрез Спиди, Еконт и при възможност BoxNow и Pigeon. За доставки извън България се изготвя индивидуална оферта. Вижте и <a href="/dostavka-i-srokove/">Доставка и срокове</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Право на отказ</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Продуктите се изработват по индивидуална спецификация с Вашето лого. Съгласно чл. 57, т. 3 от Закона за защита на потребителите 14-дневното право на отказ не се прилага за такива стоки. Потвърждавате това изрично при изпращане на заявката.</p>
<!-- /wp:paragraph -->
<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>Преди одобрение на визуализацията</strong> можете да се откажете без неустойка, освен ако предварително не е договорена отделна платена дизайнерска услуга.</li>
<li><strong>След плащането, но преди започване на производството</strong> се възстановяват 50% от платената сума.</li>
<li><strong>След започване на производството</strong> платената сума не се възстановява.</li>
</ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>Отказът се заявява писмено на имейла за контакт с номера на поръчката.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. Рекламации</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>За несъответствие с одобрената визуализация или дефект ни пишете на посочения имейл с номера на поръчката и снимки. Отговаряме в работни дни. При спор можете да се обърнете към Комисията за защита на потребителите (<a href="https://kzp.bg" target="_blank" rel="noopener">kzp.bg</a>).</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. Лични данни</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Как обработваме личните Ви данни е описано в <a href="/politika-za-poveritelnost/">Политиката за поверителност</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"muted"} -->
<p class="muted">Версия [reklamo_value key="legal_version"]. Прилага се българското право.</p>
<!-- /wp:paragraph -->
HTML
)
refresh_if_seeded obshti-usloviya "$terms_new" '<!-- wp:paragraph --><p>Общи условия.</p><!-- /wp:paragraph -->' "$terms_old"
priv_old=$(cat <<'HTML'
<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">Тази политика обяснява какви лични данни обработваме, когато поръчвате през Reklamo.bg, защо, колко време ги пазим и какви права имате съгласно Регламент (ЕС) 2016/679 (GDPR) и Закона за защита на личните данни.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Администратор</h2>
<!-- /wp:heading -->
<!-- wp:shortcode -->
[reklamo_company]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Какви данни обработваме</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>При заявка:</strong> име, имейл, файлът с логото и бележката към дизайнера.</li>
<li><strong>При одобрение и фактура:</strong> фирма, ЕИК, ДДС №, МОЛ или име на частно лице, телефон, адрес за доставка, изпратените визуализации и Вашите коментари към тях.</li>
<li><strong>Технически:</strong> IP адрес и браузър при изпращане на заявката и при одобрение на визуализация, като доказателство за сключения договор и за защита от злоупотреби; моментът и версията на условията, с които сте се съгласили.</li>
</ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>Не създаваме потребителски профили и не изискваме пароли. Достъпът до Вашата поръчка е чрез лична връзка, изпратена на имейла Ви; пазете я както парола.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Защо и на какво основание</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>Изпълнение на договора</strong> (чл. 6, пар. 1, б. „б“ GDPR): изготвяне на визуализация, производство, доставка, комуникация по поръчката и напомняния за нея.</li>
<li><strong>Законово задължение</strong> (б. „в“): издаване и съхранение на счетоводни документи.</li>
<li><strong>Легитимен интерес</strong> (б. „е“): защита от злоупотреби и доказване на одобрението (IP адрес, ограничение на броя заявки).</li>
</ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>Не изпращаме маркетингови съобщения и не продаваме данни.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Кой получава данните</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>СуперХостинг.БГ</strong> — хостинг и имейл сървър в България (обработващ лични данни).</li>
<li><strong>Куриер</strong> — име, телефон и адрес за доставката.</li>
<li><strong>Счетоводител</strong> — данните за фактура.</li>
</ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>Данните не се предават извън Европейския съюз.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Колко време пазим данните</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list">
<li>Логото и визуализациите се изтриват <strong>[reklamo_value key="retention_months"] месеца</strong> след приключване или отказ на поръчката; тогава спира да работи и личната връзка към поръчката.</li>
<li>Име, адрес, данни за контакт, коментари и IP адреси се анонимизират <strong>[reklamo_value key="anonymize_months"] месеца</strong> след приключване на поръчката; сумите и датите остават като статистика без връзка с лице.</li>
<li>Счетоводните документи (фактури) се пазят в сроковете по Закона за счетоводството, извън сайта.</li>
<li>Качени файлове без изпратена заявка се изтриват до 48 часа. Резервните копия на хостинга се пазят до 30 дни.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Вашите права</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Имате право на достъп, коригиране, изтриване, ограничаване на обработването, преносимост и възражение. Пишете ни на посочения имейл; отговаряме до един месец. Данните по активна поръчка не могат да бъдат изтрити преди тя да приключи или бъде отказана. Имате право на жалба до Комисията за защита на личните данни (<a href="https://www.cpdp.bg" target="_blank" rel="noopener">cpdp.bg</a>).</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. Бисквитки</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Сайтът не използва аналитични, рекламни или проследяващи бисквитки и не зарежда съдържание от трети страни. Стриктно необходими технически бисквитки се създават единствено при вход в административния панел. Затова няма банер за бисквитки.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. Сигурност</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Връзката е криптирана (HTTPS). Файловете се съхраняват извън публичната директория на сайта и се достъпват само чрез личната Ви връзка. Връзките за одобрение са еднократни и с срок. Достъп до данните има само екипът, който изпълнява поръчката.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"muted"} -->
<p class="muted">Версия [reklamo_value key="legal_version"]. При промяна публикуваме новата версия тук; версията, с която сте се съгласили, е записана в поръчката Ви.</p>
<!-- /wp:paragraph -->
HTML
)
priv_new=$(cat <<'HTML'
<!-- wp:paragraph {"className":"lead"} -->
<p class="lead">Тази политика обяснява какви лични данни обработваме, когато поръчвате през Reklamo.bg, защо, колко време ги пазим и какви права имате съгласно Регламент (ЕС) 2016/679 (GDPR) и Закона за защита на личните данни.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Администратор</h2>
<!-- /wp:heading -->
<!-- wp:shortcode -->
[reklamo_company]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Какви данни обработваме</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>При заявка:</strong> име, имейл, телефон, файловете с логата и бележките към дизайнера.</li>
<li><strong>При одобрение и фактура:</strong> фирма, ЕИК, ДДС №, МОЛ или име на частно лице, телефон, адрес за доставка, изпратените визуализации и Вашите коментари към тях.</li>
<li><strong>Технически:</strong> IP адрес и браузър при изпращане на заявката и при одобрение на визуализация, като доказателство за сключения договор и за защита от злоупотреби; моментът и версията на условията, с които сте се съгласили.</li>
</ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>Не създаваме потребителски профили и не изискваме пароли. Достъпът до Вашата поръчка е чрез лична връзка, изпратена на имейла Ви; пазете я както парола.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Защо и на какво основание</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>Изпълнение на договора</strong> (чл. 6, пар. 1, б. „б“ GDPR): изготвяне на визуализация, производство, доставка, комуникация по поръчката и напомняния за нея.</li>
<li><strong>Законово задължение</strong> (б. „в“): издаване и съхранение на счетоводни документи.</li>
<li><strong>Легитимен интерес</strong> (б. „е“): защита от злоупотреби и доказване на одобрението (IP адрес, ограничение на броя заявки).</li>
</ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>Не изпращаме маркетингови съобщения и не продаваме данни.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Кой получава данните</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list">
<li><strong>СуперХостинг.БГ</strong> — хостинг и имейл сървър в България (обработващ лични данни).</li>
<li><strong>Куриер</strong> — име, телефон и адрес за доставката.</li>
<li><strong>Счетоводител</strong> — данните за фактура.</li>
</ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>Данните не се предават извън Европейския съюз.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Колко време пазим данните</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list">
<li>Логото и визуализациите се изтриват <strong>[reklamo_value key="retention_months"] месеца</strong> след приключване или отказ на поръчката; тогава спира да работи и личната връзка към поръчката.</li>
<li>Име, адрес, данни за контакт, коментари и IP адреси се анонимизират <strong>[reklamo_value key="anonymize_months"] месеца</strong> след приключване на поръчката; сумите и датите остават като статистика без връзка с лице.</li>
<li>Счетоводните документи (фактури) се пазят в сроковете по Закона за счетоводството, извън сайта.</li>
<li>Качени файлове без изпратена заявка се изтриват до 48 часа. Резервните копия на хостинга се пазят до 30 дни.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Вашите права</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Имате право на достъп, коригиране, изтриване, ограничаване на обработването, преносимост и възражение. Отговаряме до един месец. Данните по активна поръчка не могат да бъдат изтрити преди тя да приключи или бъде отказана. Имате право на жалба до Комисията за защита на личните данни (<a href="https://www.cpdp.bg" target="_blank" rel="noopener">cpdp.bg</a>).</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Ако желаете да получите информация относно обработването на Вашите лични данни, да поискате корекция или изтриване на предоставени данни, както и премахване на качени лога и дизайнерски файлове, можете да се свържете с нас на <strong>[reklamo_value key="privacy_email"]</strong>. Ще разгледаме искането Ви и ще отговорим в сроковете, предвидени от приложимото законодателство.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. Бисквитки</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Сайтът не използва аналитични, рекламни или проследяващи бисквитки и не зарежда съдържание от трети страни. Стриктно необходими технически бисквитки се създават единствено при вход в административния панел. Затова няма банер за бисквитки.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. Сигурност</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Връзката е криптирана (HTTPS). Файловете се съхраняват извън публичната директория на сайта и се достъпват само чрез личната Ви връзка. Връзките за одобрение са еднократни и с срок. Достъп до данните има само екипът, който изпълнява поръчката.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"muted"} -->
<p class="muted">Версия [reklamo_value key="legal_version"]. При промяна публикуваме новата версия тук; версията, с която сте се съгласили, е записана в поръчката Ви.</p>
<!-- /wp:paragraph -->
HTML
)
refresh_if_seeded politika-za-poveritelnost "$priv_new" '<!-- wp:paragraph --><p>Политика за поверителност.</p><!-- /wp:paragraph -->' "$priv_old"
opt show_on_front "page"
opt page_on_front "$home_id"

# Request page: the design's single request step, rendered by the theme template.
req_id=$(page_id kachi-logo)
wp post meta update "$req_id" _wp_page_template "templates/page-request.php" >/dev/null
fill_if_empty kachi-logo "<!-- wp:paragraph --><p>Изпратете вашето лого и ние ще подготвим професионална визуализация на избрания пакет.</p><!-- /wp:paragraph -->"
opt reklamo_request_page_id "$req_id"

# Homepage: expand the theme's patterns into real, owner-editable blocks — only while the
# page still holds the seed placeholder, so owner edits are never overwritten.
wp eval '
$id = (int) get_option( "page_on_front" );
$p  = get_post( $id );
if ( $p && ( "" === trim( $p->post_content ) || str_contains( $p->post_content, "Ние правим останалото.</p>" ) ) ) {
	$reg = WP_Block_Patterns_Registry::get_instance();
	$out = "";
	foreach ( array( "reklamo/hero", "reklamo/packages", "reklamo/steps", "reklamo/trust", "reklamo/quick-start" ) as $slug ) {
		$pat = $reg->get_registered( $slug );
		if ( $pat ) { $out .= trim( $pat["content"] ) . "\n\n"; }
	}
	if ( $out ) { wp_update_post( array( "ID" => $id, "post_content" => $out ) ); echo "  homepage built from patterns\n"; }
}
'

# "Популярни промо пакети" on an already-built homepage listed any four products; with three packages
# a calendar took the fourth place. The seeded shortcode alone is swapped for the packages-only one.
wp eval '
$id  = (int) get_option( "page_on_front" );
$p   = get_post( $id );
$old = "[products limit=\"4\" columns=\"4\" orderby=\"menu_order\" order=\"ASC\"]";
if ( $p && str_contains( $p->post_content, $old ) ) {
	wp_update_post( array( "ID" => $id, "post_content" => str_replace( $old, "[products limit=\"4\" columns=\"4\" category=\"paketi\" orderby=\"menu_order\" order=\"ASC\"]", $p->post_content ) ) );
	echo "  homepage packages: packages only\n";
}
'

# The hero showed a drawn notebook and a fixed "Red Business Pack · 100 €" badge; that package is
# retired. While the hero still holds exactly that seeded block, it becomes the product shortcode.
wp eval '
$id = (int) get_option( "page_on_front" );
$p  = get_post( $id );
if ( $p && str_contains( $p->post_content, "hero-placeholder.svg" ) ) {
	$new = preg_replace(
		"~<!-- wp:image \{\"sizeSlug\":\"large\"\} -->\s*<figure class=\"wp-block-image size-large\"><img src=\"[^\"]*hero-placeholder\.svg\"[^>]*/></figure>\s*<!-- /wp:image -->\s*<!-- wp:group \{\"className\":\"hero__badge\"[^}]*\}\} -->.*?Red Business Pack.*?<!-- /wp:group -->~s",
		"<!-- wp:shortcode -->\n[reklamo_hero_product sku=\"PKG-MAGNET\" title=\"Metal Business Pack\" items=\"20 тефтера|20 химикалки\"]\n<!-- /wp:shortcode -->",
		$p->post_content
	);
	if ( $new && $new !== $p->post_content ) {
		wp_update_post( array( "ID" => $id, "post_content" => $new ) );
		echo "  homepage hero: Бизнес комплект Magnet\n";
	}
}
'

# An already-built homepage still carries the brand mark the pattern drew inline before the client
# supplied a logo. Swap that one block for the shortcode, which always renders the current asset.
wp eval '
$id = (int) get_option( "page_on_front" );
$p  = get_post( $id );
if ( $p && str_contains( $p->post_content, "brand__mark" ) && ! str_contains( $p->post_content, "[reklamo_mark" ) ) {
	$new = preg_replace(
		"~<!-- wp:html -->\s*<span class=\"brand__mark\">.*?</span>\s*<!-- /wp:html -->~s",
		"<!-- wp:shortcode -->\n[reklamo_mark size=\"76\"]\n<!-- /wp:shortcode -->",
		$p->post_content
	);
	if ( $new && $new !== $p->post_content ) {
		wp_update_post( array( "ID" => $id, "post_content" => $new ) );
		echo "  homepage brand mark switched to the shortcode\n";
	}
}
'


# WooCommerce creates Shop/Cart/Checkout/My account on activation (block versions).
# Make sure they exist, then give the shop page the design's name.
wc tool run install_pages >/dev/null 2>&1 || true
shop_id=$(wp option get woocommerce_shop_page_id 2>/dev/null || echo "")
if [ -n "$shop_id" ] && [ "$shop_id" != "0" ]; then
  wp post update "$shop_id" --post_title="Промо пакети" --post_name="promo-paketi" >/dev/null
fi
rename_wc_page() { # option title slug
  local id; id=$(wp option get "$1" 2>/dev/null || echo 0)
  [ -n "$id" ] && [ "$id" != "0" ] && wp post update "$id" --post_title="$2" --post_name="$3" >/dev/null || true
}
rename_wc_page woocommerce_cart_page_id      "Кошница"     "koshnitsa"
rename_wc_page woocommerce_checkout_page_id  "Поръчка"     "porachka"
rename_wc_page woocommerce_myaccount_page_id "Моят профил" "profil"
# Samples we do not want (we have our own terms / privacy pages).
for slug in sample-page privacy-policy refund_returns; do
  id=$(page_id "$slug"); [ -n "$id" ] && wp post delete "$id" --force >/dev/null || true
done
for k in woocommerce_terms_page_id; do opt "$k" "$(page_id obshti-usloviya)"; done

# Cart/Checkout fallback pages: WooCommerce stores their English block text as page content.
wp eval '
foreach ( array( "woocommerce_cart_page_id", "woocommerce_checkout_page_id" ) as $opt ) {
	$id = (int) get_option( $opt ); $p = $id ? get_post( $id ) : null;
	if ( ! $p ) { continue; }
	$c = str_replace(
		array( "Your cart is currently empty!", "New in store", "Browse store", "Return to shop" ),
		array( "Кошницата Ви е празна.", "Ново в магазина", "Разгледай магазина", "Обратно към магазина" ),
		$p->post_content
	);
	if ( $c !== $p->post_content ) { wp_update_post( array( "ID" => $id, "post_content" => $c ) ); echo "  translated block text on ", $p->post_name, "\n"; }
}
'

# Checkout page: "no payment" notice above the block, design's button label, no coupon/order-note UI.
wp eval '
$id = (int) get_option( "woocommerce_checkout_page_id" );
$p  = $id ? get_post( $id ) : null;
if ( $p ) {
	$c = $p->post_content;
	if ( false === strpos( $c, "reklamo-nopay-notice" ) ) {
		$c = "<!-- wp:paragraph {\"className\":\"reklamo-nopay-notice\",\"lock\":{\"move\":true,\"remove\":true}} -->\n<p class=\"reklamo-nopay-notice\">На този етап не се извършва плащане. Изпращате заявка и логото си — ще получите визуализация за одобрение до 24 работни часа.</p>\n<!-- /wp:paragraph -->\n\n" . $c;
	}
	// Blocks are stored as open/close pairs around a placeholder div.
	$c = preg_replace( "/<!-- wp:woocommerce\\/checkout-actions-block( \\{[^}]*\\})? -->/", "<!-- wp:woocommerce/checkout-actions-block {\"placeOrderButtonLabel\":\"Изпрати и заяви визуализация\"} -->", $c );
	$c = preg_replace( "/<!-- wp:woocommerce\\/checkout-order-note-block -->.*?<!-- \\/wp:woocommerce\\/checkout-order-note-block -->\\s*/s", "", $c );
	if ( $c !== $p->post_content ) { wp_update_post( array( "ID" => $id, "post_content" => $c ) ); echo "  checkout page updated\n"; }
}
' 
opt wp_page_for_privacy_policy "$(page_id politika-za-poveritelnost)"

echo "→ product categories"
ensure_cat() { # slug name
  local id; id=$(wc product_cat list --slug="$1" --field=id | head -n1)
  [ -n "$id" ] || id=$(wc product_cat create --name="$2" --slug="$1" --porcelain)
  echo "$id"
}
# The ПРОДУКТИ dropdown, in the order the design lists it. Underscores stand in for the
# spaces the word-split loop would otherwise break on.
PRODUCT_CATS="kalendari:Календари tefteri:Тефтери himikalki:Химикалки butilki-i-chashi:Бутилки_и_чаши zapalki:Запалки chanti:Чанти tekstil:Текстил tehnologii:Технологии ofis-artikuli:Офис_артикули"
for pair in $PRODUCT_CATS; do
  ensure_cat "${pair%%:*}" "$(echo "${pair#*:}" | tr '_' ' ')" >/dev/null
done
# WooCommerce ships an "Uncategorized" term and refuses to delete whichever one is the default,
# so hand the default to Пакети first and only then drop it — and only while it holds nothing.
opt default_product_cat "$(ensure_cat paketi "Пакети")"
uncat=$(wc product_cat list --slug=uncategorized --fields=id,count --format=csv | tail -n +2 | tr -d '"')
if [ -n "$uncat" ] && [ "${uncat#*,}" = "0" ]; then
  wp term delete product_cat "${uncat%%,*}" >/dev/null 2>&1 && echo "  removed the empty Uncategorized category"
fi

echo "→ menus"
# WP-CLI CSV quotes non-ASCII names → strip quotes; no grep -q (SIGPIPE under pipefail).
ensure_menu() { wp menu list --fields=name --format=csv | tail -n +2 | tr -d '"' | grep -x "$1" >/dev/null || wp menu create "$1" >/dev/null; }
menu_item_id() { # menu type object_id  →  the nav_menu_item's own id
  wp menu item list "$1" --fields=db_id,type,object_id --format=csv | tail -n +2 | tr -d '"' |
    awk -F, -v t="$2" -v o="$3" '$2==t && $3==o {print $1; exit}'
}
menu_add_term() { # menu term_id parent_item_id
  [ -n "$(menu_item_id "$1" taxonomy "$2")" ] && return 0
  wp menu item add-term "$1" product_cat "$2" --parent-id="$3" >/dev/null
}
menu_add_page() { # menu slug [position]
  local pid; pid=$(page_id "$2"); [ -n "$pid" ] || return 0
  wp menu item list "$1" --fields=object_id --format=csv | tail -n +2 | grep -x "$pid" >/dev/null && return 0
  wp menu item add-post "$1" "$pid" ${3:+--position="$3"} >/dev/null
}
ensure_menu "Главно меню"
# "Начало" first: a front-page link so visitors can get back home from the header.
wp menu item list "Главно меню" --fields=object_id --format=csv | tail -n +2 | grep -x "$home_id" >/dev/null || wp menu item add-post "Главно меню" "$home_id" --position=1 >/dev/null
[ -n "$shop_id" ] && { wp menu item list "Главно меню" --fields=object_id --format=csv | tail -n +2 | grep -x "$shop_id" >/dev/null || wp menu item add-post "Главно меню" "$shop_id" >/dev/null; }
menu_add_page "Главно меню" produkti 3
for s in kak-raboti za-biznesa kontakti; do menu_add_page "Главно меню" "$s"; done
# The nine categories hang under ПРОДУКТИ, in the design's order.
produkti_item=$(menu_item_id "Главно меню" post_type "$(page_id produkti)")
if [ -n "$produkti_item" ]; then
  for pair in $PRODUCT_CATS; do
    menu_add_term "Главно меню" "$(wc product_cat list --slug="${pair%%:*}" --field=id | head -n1)" "$produkti_item"
  done
fi
# The redesign replaced Вдъхновение with Продукти in the header.
# Done once, so an owner who puts it back keeps it.
if [ -z "$(wp option get reklamo_seeded_nav 2>/dev/null || true)" ]; then
  old=$(menu_item_id "Главно меню" post_type "$(page_id vdahnovenie)")
  [ -n "$old" ] && wp menu item delete "$old" >/dev/null 2>&1 && echo "  Вдъхновение removed from the header menu"
  opt reklamo_seeded_nav "1"
fi
wp menu location assign "Главно меню" primary >/dev/null 2>&1 || true

ensure_menu "Футър — Навигация"
[ -n "$shop_id" ] && { wp menu item list "Футър — Навигация" --fields=object_id --format=csv | tail -n +2 | grep -x "$shop_id" >/dev/null || wp menu item add-post "Футър — Навигация" "$shop_id" >/dev/null; }
for s in kak-raboti za-biznesa za-nas kontakti; do menu_add_page "Футър — Навигация" "$s"; done
# Вдъхновение was dropped from the site: out of every menu, and its page back to a draft while it
# still holds only the seed placeholder. Done once, so an owner who brings it back keeps it.
if [ -z "$(wp option get reklamo_seeded_no_inspiration 2>/dev/null || true)" ]; then
  insp=$(page_id vdahnovenie)
  if [ -n "$insp" ]; then
    for menu in "Главно меню" "Футър — Навигация" "Футър — Информация"; do
      item=$(menu_item_id "$menu" post_type "$insp")
      [ -n "$item" ] && wp menu item delete "$item" >/dev/null 2>&1 && echo "  Вдъхновение removed from $menu"
    done
    if [ "$(wp post get "$insp" --field=post_content)" = "<!-- wp:paragraph --><p>Идеи и примери за брандиране.</p><!-- /wp:paragraph -->" ]; then
      wp post update "$insp" --post_status=draft >/dev/null && echo "  Вдъхновение page unpublished (draft)"
    fi
  fi
  opt reklamo_seeded_no_inspiration "1"
fi
wp menu location assign "Футър — Навигация" footer-nav >/dev/null 2>&1 || true

ensure_menu "Футър — Информация"
for s in dostavka-i-srokove plashtane chesto-zadavani-vaprosi obshti-usloviya politika-za-poveritelnost; do menu_add_page "Футър — Информация" "$s"; done
wp menu location assign "Футър — Информация" footer-info >/dev/null 2>&1 || true

echo "→ products"
cat_id=$(ensure_cat paketi "Пакети")

product_id() { wc product list --sku="$1" --field=id | head -n1; }
# A product photo from the theme's assets/img, imported as the product image.
# Set only when the product has no image: the owner's own photos are never replaced.
set_product_image() { # sku path-under-assets-img alt
  local pid att path
  pid=$(product_id "$1"); [ -n "$pid" ] || return 0
  [ -n "$(wp post meta get "$pid" _thumbnail_id 2>/dev/null || true)" ] && return 0
  # Resolved and checked inside WP-CLI: locally that runs in the container, where the path differs.
  path=$(wp eval "\$p = get_theme_file_path( 'assets/img/$2.webp' ); echo file_exists( \$p ) ? \$p : '';")
  [ -n "$path" ] || { echo "  $2: image missing, skipped"; return 0; }
  att=$(wp media import "$path" --post_id="$pid" --featured_image --title="$3" --alt="$3" --porcelain 2>/dev/null | tail -n1)
  [ -n "$att" ] && echo "  $2: image set"
}

# Colours: WooCommerce's own global attribute "Цвят" (taxonomy pa_cvyat), used for variations of a
# product sold by the piece. The attribute and its sample colours are created when missing; each
# colour's swatch (term meta reklamo_hex, Products → Attributes → Цвят) only while it has none.
color_attr=$(wc product_attribute list --format=csv --fields=id,slug 2>/dev/null | awk -F, '$2=="pa_cvyat"{print $1}' | head -n1)
if [ -z "$color_attr" ]; then
  color_attr=$(wc product_attribute create --name="Цвят" --slug=cvyat --type=select --order_by=menu_order --porcelain)
  echo "  attribute Цвят created"
fi
n=0
for c in "cherven:Червен:#b3262e" "chern:Черен:#1f1f1f" "zelen:Зелен:#2f5d46" "sin:Син:#1f2f6b" "byal:Бял:#ffffff"; do
  slug=${c%%:*}; rest=${c#*:}; name=${rest%%:*}; hex=${rest#*:}
  n=$((n + 1))
  term=$(wp term get pa_cvyat "$slug" --by=slug --field=term_id 2>/dev/null || true)
  # The order WooCommerce lists the colours in (term meta "order"), set once with the term.
  [ -n "$term" ] || { term=$(wc product_attribute_term create "$color_attr" --name="$name" --slug="$slug" --porcelain); wp term meta update "$term" order "$n" >/dev/null; }
  [ -n "$(wp term meta get "$term" reklamo_hex 2>/dev/null || true)" ] || wp term meta update "$term" reklamo_hex "$hex" >/dev/null
done

# A sample product sold by the piece, as in the client's product-page mockup: four colours, a price
# per piece and a minimum of 50. Created once when its SKU is missing; the owner edits it afterwards.
if [ -z "$(product_id NB-001)" ]; then
  nb_cat=$(wc product_cat list --slug=tefteri --field=id | head -n1)
  nb_id=$(wc product create --type=variable --status=publish --sku=NB-001 --name="Класически тефтер" --slug=klasicheski-tefter \
    --short_description="<p>Практичен и стилен тефтер с място за вашето лого.</p>" \
    --description="<p>Класически тефтер с изчистен и елегантен дизайн, идеален избор за бизнес подаръци, корпоративни събития и ежедневна употреба. Може да бъде персонализиран с вашето лого чрез различни методи на брандиране.</p>" \
    --categories="[{\"id\":${nb_cat:-$cat_id}}]" --menu_order=30 --manage_stock=false \
    --attributes="[{\"id\":$color_attr,\"variation\":true,\"visible\":true,\"options\":[\"Червен\",\"Черен\",\"Зелен\",\"Син\"]}]" \
    --default_attributes="[{\"id\":$color_attr,\"option\":\"Червен\"}]" --porcelain)
  for name in Червен Черен Зелен Син; do
    wc product_variation create "$nb_id" --regular_price=3.20 --manage_stock=false \
      --attributes="[{\"id\":$color_attr,\"option\":\"$name\"}]" --porcelain >/dev/null
  done
  wp post meta update "$nb_id" _reklamo_min_qty 50 >/dev/null
  wp post meta update "$nb_id" _reklamo_specs "<p>Твърди корици, 96 листа на редове, ластик за затваряне и лента за отбелязване.</p>" >/dev/null
  echo "  Класически тефтер created (4 colours, 3,20 € per piece, minimum 50)"
fi
# Sizes: WooCommerce's global attribute "Количество" (pa_kolichestvo), 20 to 500 бр. Each product
# and package is variable on it, one price per size ("Prices by quantity" in the product).
size_attr=$(wc product_attribute list --format=csv --fields=id,slug 2>/dev/null | awk -F, '$2=="pa_kolichestvo"{print $1}' | head -n1)
if [ -z "$size_attr" ]; then
  size_attr=$(wc product_attribute create --name="Количество" --slug=kolichestvo --type=select --order_by=menu_order --porcelain)
  echo "  attribute Количество created"
fi
n=0
for c in "20-br:20 бр." "50-br:50 бр." "100-br:100 бр." "200-br:200 бр." "300-br:300 бр." "500-br:500 бр."; do
  slug=${c%%:*}; name=${c#*:}; n=$((n + 1))
  term=$(wp term get pa_kolichestvo "$slug" --by=slug --field=term_id 2>/dev/null || true)
  [ -n "$term" ] || { term=$(wc product_attribute_term create "$size_attr" --name="$name" --slug="$slug" --porcelain); wp term meta update "$term" order "$n" >/dev/null; }
done
# The catalogue from the client's photos (design/v3/web product): packages and products, created
# once with sample prices; the earlier sample packages and calendar tiers go to draft and their
# addresses redirect to the closest new product. Data and steps: scripts/seed-catalogue.php.
wp eval-file - < scripts/seed-catalogue.php
set_product_image NB-001 catalogue/tefter-contrast-red/main "Класически тефтер — червен, с лого"
# Colour photos: the sample has pictures for red and black; green and blue fall back to the main photo.
nb_id=$(product_id NB-001)
if [ -n "$nb_id" ]; then
  for pair in "cherven:Червен:catalogue/tefter-contrast-red/main" "chern:Черен:catalogue/tefter-vintage-black/main"; do
    slug=${pair%%:*}; rest=${pair#*:}; name=${rest%%:*}; img=${rest#*:}
    vid=$(wp eval "foreach ( wc_get_product( $nb_id )->get_children() as \$c ) { if ( '$slug' === ( wc_get_product( \$c )->get_variation_attributes()['attribute_pa_cvyat'] ?? '' ) ) { echo \$c; break; } }")
    [ -n "$vid" ] || continue
    [ -n "$(wp post meta get "$vid" _thumbnail_id 2>/dev/null || true)" ] && continue
    path=$(wp eval "\$p = get_theme_file_path( 'assets/img/$img.webp' ); echo file_exists( \$p ) ? \$p : '';")
    [ -n "$path" ] || continue
    att=$(wp media import "$path" --post_id="$nb_id" --title="Класически тефтер — $name" --alt="Класически тефтер — $name" --porcelain 2>/dev/null | tail -n1)
    [ -n "$att" ] && wp post meta update "$vid" _thumbnail_id "$att" >/dev/null && echo "  Класически тефтер: $name photo set"
  done
fi
# Fixed quantities only for now: the sample sold by the piece is hidden once; the owner may publish it again.
if [ "$(wp option get reklamo_seeded_nb_draft 2>/dev/null || true)" != "1" ] && [ -n "$nb_id" ]; then
  wp post update "$nb_id" --post_status=draft >/dev/null && echo "  Класически тефтер: hidden (draft)"
  wp option update reklamo_seeded_nb_draft 1 >/dev/null
fi
opt woocommerce_default_catalog_orderby "menu_order"

echo "→ flushing caches"
wp cache flush >/dev/null 2>&1 || true
wp rewrite flush >/dev/null 2>&1 || true
echo "✔ seed complete"

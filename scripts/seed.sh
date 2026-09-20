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
wp rewrite structure '/%postname%/' >/dev/null
wp rewrite flush --hard >/dev/null 2>&1 || wp rewrite flush >/dev/null

echo "→ WooCommerce store settings"
opt_default woocommerce_store_address "ул. Примерна 1"
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
opt_default reklamo_phone "+359 88 123 4567"
opt_default reklamo_email "office@reklamo.bg"
opt_default reklamo_address "София, България"
opt_default reklamo_facebook "https://facebook.com/"
opt_default reklamo_instagram "https://instagram.com/"
opt_default reklamo_linkedin "https://linkedin.com/"
opt_default reklamo_mockup_deadline 24
opt_default reklamo_deposit_pct 50
opt_default reklamo_note_max 300

echo "→ tax rate (BG 20% standard)"
if [ "$(wc tax list --format=count 2>/dev/null || echo 0)" = "0" ]; then
  wc tax create --country=BG --rate=20 --name="ДДС" --class=standard --shipping=true >/dev/null && echo "  created BG 20%"
fi

echo "→ bank details (WooCommerce → Settings → Reklamo → Bank details) — placeholders, owner fills the real ones"
opt_default reklamo_bank_name "УниКредит Булбанк"
opt_default reklamo_iban "BG00UNCR00000000000000"
opt_default reklamo_bic "UNCRBGSF"
opt_default reklamo_account_holder "Рекламо ЕООД"
opt_default reklamo_reminder_days "3,7,14"
opt_default reklamo_stale_days 7
opt_default reklamo_max_upload_mb 300
opt_default reklamo_retention_months 12

echo "→ legal & privacy (WooCommerce → Settings → Reklamo → Legal & privacy) — ЕИК, ДДС № and address are the owner's to fill"
opt_default reklamo_anonymize_months 36
opt_default reklamo_legal_version "2026-09"

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
ensure_page vdahnovenie "Вдъхновение" >/dev/null
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
# "How it works": the six homepage steps, each explained in detail (deposit % and file limits mirror the seeded settings above).
fill_if_empty kak-raboti "$(cat <<'HTML'
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
)"
fill_if_empty za-biznesa "<!-- wp:paragraph --><p>Брандирани продукти за екипи, събития и клиенти — с фиксирани количества и ясни цени.</p><!-- /wp:paragraph -->"
fill_if_empty vdahnovenie "<!-- wp:paragraph --><p>Идеи и примери за брандиране.</p><!-- /wp:paragraph -->"
fill_if_empty kontakti "<!-- wp:paragraph --><p>Пишете ни на office@reklamo.bg или се обадете на +359 88 123 4567.</p><!-- /wp:paragraph -->"
fill_if_empty dostavka-i-srokove "<!-- wp:paragraph --><p>Производство и доставка в договорени срокове след получаване на аванса.</p><!-- /wp:paragraph -->"
fill_if_empty plashtane "<!-- wp:paragraph --><p>Плащане само по банков път: 50% аванс след одобрение на визуализацията, остатък преди доставка.</p><!-- /wp:paragraph -->"
# Payment page always carries the live bank details block (one source of truth).
pl_id=$(page_id plashtane)
if [ -n "$pl_id" ] && ! wp post get "$pl_id" --field=post_content | grep -q reklamo_bank_details; then
  wp post update "$pl_id" --post_content="$(wp post get "$pl_id" --field=post_content)
<!-- wp:shortcode -->
[reklamo_bank_details]
<!-- /wp:shortcode -->" >/dev/null
fi
fill_if_empty chesto-zadavani-vaprosi "<!-- wp:paragraph --><p>Често задавани въпроси.</p><!-- /wp:paragraph -->"
# Legal pages: structure and every number come from the settings through shortcodes, so the texts
# never contradict the configuration. A lawyer reviews the wording; the owner edits in the block editor.
fill_if_placeholder obshti-usloviya "<!-- wp:paragraph --><p>Общи условия.</p><!-- /wp:paragraph -->" "$(cat <<'HTML'
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
)"
fill_if_placeholder politika-za-poveritelnost "<!-- wp:paragraph --><p>Политика за поверителност.</p><!-- /wp:paragraph -->" "$(cat <<'HTML'
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
)"
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

echo "→ menus"
# WP-CLI CSV quotes non-ASCII names → strip quotes; no grep -q (SIGPIPE under pipefail).
ensure_menu() { wp menu list --fields=name --format=csv | tail -n +2 | tr -d '"' | grep -x "$1" >/dev/null || wp menu create "$1" >/dev/null; }
menu_add_page() { # menu slug
  local pid; pid=$(page_id "$2"); [ -n "$pid" ] || return 0
  wp menu item list "$1" --fields=object_id --format=csv | tail -n +2 | grep -x "$pid" >/dev/null || wp menu item add-post "$1" "$pid" >/dev/null
}
ensure_menu "Главно меню"
# "Начало" first: a front-page link so visitors can get back home from the header.
wp menu item list "Главно меню" --fields=object_id --format=csv | tail -n +2 | grep -x "$home_id" >/dev/null || wp menu item add-post "Главно меню" "$home_id" --position=1 >/dev/null
[ -n "$shop_id" ] && { wp menu item list "Главно меню" --fields=object_id --format=csv | tail -n +2 | grep -x "$shop_id" >/dev/null || wp menu item add-post "Главно меню" "$shop_id" >/dev/null; }
for s in kak-raboti za-biznesa vdahnovenie kontakti; do menu_add_page "Главно меню" "$s"; done
wp menu location assign "Главно меню" primary >/dev/null 2>&1 || true

ensure_menu "Футър — Навигация"
[ -n "$shop_id" ] && { wp menu item list "Футър — Навигация" --fields=object_id --format=csv | tail -n +2 | grep -x "$shop_id" >/dev/null || wp menu item add-post "Футър — Навигация" "$shop_id" >/dev/null; }
for s in kak-raboti za-biznesa vdahnovenie kontakti; do menu_add_page "Футър — Навигация" "$s"; done
wp menu location assign "Футър — Навигация" footer-nav >/dev/null 2>&1 || true

ensure_menu "Футър — Информация"
for s in dostavka-i-srokove plashtane chesto-zadavani-vaprosi obshti-usloviya politika-za-poveritelnost; do menu_add_page "Футър — Информация" "$s"; done
wp menu location assign "Футър — Информация" footer-info >/dev/null 2>&1 || true

echo "→ products (from design/preview.webp)"
cat_id=$(wc product_cat list --slug=paketi --field=id | head -n1)
[ -n "$cat_id" ] || cat_id=$(wc product_cat create --name="Пакети" --slug="paketi" --porcelain)

product_id() { wc product list --sku="$1" --field=id | head -n1; }
# Sample packages exist only until the owner has products: created when the SKU is
# missing, never updated — names, prices and texts belong to the dashboard afterwards.
ensure_product() { # sku name price short_description menu_order [featured]
  [ -n "$(product_id "$1")" ] && return 0
  wc product create --type=simple --status=publish --sku="$1" --name="$2" --regular_price="$3" \
    --short_description="$4" --menu_order="$5" --manage_stock=false --sold_individually=false \
    --featured="${6:-false}" --categories="[{\"id\":$cat_id}]" --porcelain >/dev/null
}
# Package photos cut from design/preview.webp by scripts/crop-previews.php, one per package.
# Set only when the product has no image: the owner's own photos are never replaced.
set_product_image() { # sku slug alt
  local pid att path
  pid=$(product_id "$1"); [ -n "$pid" ] || return 0
  [ -n "$(wp post meta get "$pid" _thumbnail_id 2>/dev/null || true)" ] && return 0
  # Resolved and checked inside WP-CLI: locally that runs in the container, where the path differs.
  path=$(wp eval "\$p = get_theme_file_path( 'assets/img/packages/$2.webp' ); echo file_exists( \$p ) ? \$p : '';")
  [ -n "$path" ] || { echo "  $2: image missing, skipped"; return 0; }
  att=$(wp media import "$path" --post_id="$pid" --featured_image --title="$3" --alt="$3" --porcelain 2>/dev/null | tail -n1)
  [ -n "$att" ] && echo "  $2: image set"
}

ensure_product RBP "Red Business Pack"   100 "<ul><li>20 червени тефтера</li><li>20 червени химикалки</li></ul>" 1 true   # "Най-популярен" badge
ensure_product OSP "Office Starter Pack" 119 "<ul><li>20 чаши</li><li>20 химикалки</li></ul>" 2
ensure_product EVP "Event Pack"          149 "<ul><li>20 текстилни торби</li><li>20 метални бутилки</li></ul>" 3
ensure_product PRP "Premium Pack"        169 "<ul><li>20 бележника</li><li>20 метални химикалки</li></ul>" 4

set_product_image RBP red-business-pack   "Red Business Pack — червен тефтер и химикалка с лого"
set_product_image OSP office-starter-pack "Office Starter Pack — чаша и химикалка с лого"
set_product_image EVP event-pack          "Event Pack — текстилна торба и метална бутилка с лого"
set_product_image PRP premium-pack        "Premium Pack — бележник и метална химикалка с лого"
opt woocommerce_default_catalog_orderby "menu_order"

echo "→ flushing caches"
wp cache flush >/dev/null 2>&1 || true
wp rewrite flush >/dev/null 2>&1 || true
echo "✔ seed complete"

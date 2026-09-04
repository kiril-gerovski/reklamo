#!/usr/bin/env bash
# THE configuration of the site, as code.
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

echo "→ general settings"
opt blogname "$WP_TITLE"
opt blogdescription "Промо пакети с вашето лого"
opt timezone_string "Europe/Sofia"
opt date_format "d.m.Y"
opt time_format "H:i"
opt start_of_week 1
opt WPLANG bg_BG
opt default_comment_status closed
opt default_ping_status closed
opt blog_public 0                       # local: discourage indexing; flip to 1 on production
opt uploads_use_yearmonth_folders 1
wp rewrite structure '/%postname%/' >/dev/null
wp rewrite flush --hard >/dev/null 2>&1 || wp rewrite flush >/dev/null

echo "→ WooCommerce store settings"
opt woocommerce_store_address "ул. Примерна 1"
opt woocommerce_store_city "София"
opt woocommerce_store_postcode "1000"
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
# Taxes OFF for now — revisit before real products are entered (see docs/PLAN.md, Open questions).
opt woocommerce_calc_taxes "no"
opt woocommerce_prices_include_tax "no"
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
opt woocommerce_email_from_name "Reklamo.bg"
opt woocommerce_email_from_address "office@reklamo.bg"
opt woocommerce_email_footer_text "Reklamo.bg — промо пакети с вашето лого"
# Silence the noise
opt woocommerce_allow_tracking "no"
opt woocommerce_show_marketplace_suggestions "no"
opt woocommerce_merchant_email_notifications "no"
opt woocommerce_onboarding_profile '{"skipped":true,"completed":true,"is_store_country_set":true}' --format=json
opt woocommerce_task_list_hidden "yes"
opt woocommerce_extended_task_list_hidden "yes"
opt woocommerce_admin_customize_store_completed "yes"
# New WooCommerce installs boot in "coming soon" mode. We want the storefront live.
opt woocommerce_coming_soon "no"
opt woocommerce_store_pages_only "no"
opt woocommerce_private_link "no"
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

echo "→ payment gateways"
# BACS stays ON temporarily so a Phase 0 test order can be placed and emailed.
# Phase 1 replaces it with the reklamo_request no-payment gateway.
opt woocommerce_bacs_settings '{"enabled":"yes","title":"Банков превод","description":"Временен — заменя се във Фаза 1.","instructions":""}' --format=json
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
opt show_on_front "page"
opt page_on_front "$home_id"

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
# Samples we do not want (we have our own ОУ / privacy pages).
for slug in sample-page privacy-policy refund_returns; do
  id=$(page_id "$slug"); [ -n "$id" ] && wp post delete "$id" --force >/dev/null || true
done
for k in woocommerce_terms_page_id; do opt "$k" "$(page_id obshti-usloviya)"; done
opt wp_page_for_privacy_policy "$(page_id politika-za-poveritelnost)"

echo "→ menus"
# WP-CLI CSV quotes non-ASCII names → strip quotes; no grep -q (SIGPIPE under pipefail).
ensure_menu() { wp menu list --fields=name --format=csv | tail -n +2 | tr -d '"' | grep -x "$1" >/dev/null || wp menu create "$1" >/dev/null; }
menu_add_page() { # menu slug
  local pid; pid=$(page_id "$2"); [ -n "$pid" ] || return 0
  wp menu item list "$1" --fields=object_id --format=csv | tail -n +2 | grep -x "$pid" >/dev/null || wp menu item add-post "$1" "$pid" >/dev/null
}
ensure_menu "Главно меню"
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
cat_id=$(wc product_cat list --search="Пакети" --format=json | python3 -c 'import sys,json; c=[x for x in json.load(sys.stdin) if x["name"]=="Пакети"]; print(c[0]["id"] if c else "")')
[ -n "$cat_id" ] || cat_id=$(wc product_cat create --name="Пакети" --slug="paketi" --porcelain)

ensure_product() { # sku name price short_description menu_order
  local id; id=$(wc product list --sku="$1" --format=json | python3 -c 'import sys,json; p=json.load(sys.stdin); print(p[0]["id"] if p else "")')
  if [ -z "$id" ]; then
    wc product create --type=simple --status=publish --sku="$1" --name="$2" --regular_price="$3" \
      --short_description="$4" --menu_order="$5" --manage_stock=false --sold_individually=false \
      --categories="[{\"id\":$cat_id}]" --porcelain >/dev/null
  else
    wc product update "$id" --name="$2" --regular_price="$3" --short_description="$4" --menu_order="$5" >/dev/null
  fi
}
ensure_product RBP "Red Business Pack"   100 "<ul><li>20 червени тефтера</li><li>20 червени химикалки</li></ul>" 1
ensure_product OSP "Office Starter Pack" 119 "<ul><li>20 чаши</li><li>20 химикалки</li></ul>" 2
ensure_product EVP "Event Pack"          149 "<ul><li>20 текстилни торби</li><li>20 метални бутилки</li></ul>" 3
ensure_product PRP "Premium Pack"        169 "<ul><li>20 бележника</li><li>20 метални химикалки</li></ul>" 4
opt woocommerce_default_catalog_orderby "menu_order"

echo "→ flushing caches"
wp cache flush >/dev/null 2>&1 || true
wp rewrite flush >/dev/null 2>&1 || true
echo "✔ seed complete"

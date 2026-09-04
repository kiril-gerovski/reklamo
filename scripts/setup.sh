#!/usr/bin/env bash
# Zero → working Bulgarian WooCommerce store, no manual clicks.
# Idempotent: safe to re-run on an existing install.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  cp .env.example .env
  echo "→ created .env from .env.example"
fi
set -a; . ./.env; set +a

mkdir -p wp private
. scripts/lib.sh

echo "→ starting stack"
docker compose up -d db mail wp

echo "→ waiting for wp-config.php (entrypoint copies core on first boot)"
for _ in $(seq 1 60); do [ -f wp/wp-config.php ] && break; sleep 2; done
[ -f wp/wp-config.php ] || { echo "wp-config.php never appeared"; docker compose logs wp | tail -30; exit 1; }

echo "→ waiting for HTTP"
for _ in $(seq 1 60); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${WP_PORT:-8080}/" || true)
  case "$code" in 200|30*) break;; esac
  sleep 2
done

cli_up
if wp core is-installed >/dev/null 2>&1; then
  echo "→ WordPress already installed"
else
  echo "→ installing WordPress ($WP_URL)"
  wp core install \
    --url="$WP_URL" --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASS" --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email --locale=bg_BG
fi

echo "→ Bulgarian language pack"
wp language core install bg_BG >/dev/null 2>&1 || true
wp site switch-language bg_BG >/dev/null 2>&1 || wp language core activate bg_BG >/dev/null 2>&1 || true

if wp plugin is-installed woocommerce >/dev/null 2>&1; then
  echo "→ WooCommerce present ($(wp plugin get woocommerce --field=version))"
else
  echo "→ installing WooCommerce $WC_VERSION (pinned)"
  wp plugin install woocommerce --version="$WC_VERSION"
fi
wp plugin activate woocommerce >/dev/null
wp language plugin install woocommerce bg_BG >/dev/null 2>&1 || true

echo "→ activating our theme and plugin"
wp theme activate reklamo >/dev/null
wp plugin activate reklamo-core >/dev/null

# Stock themes/plugins ship with the image; remove the noise.
for p in akismet hello; do wp plugin is-installed "$p" >/dev/null 2>&1 && wp plugin delete "$p" >/dev/null || true; done
for t in $(wp theme list --status=inactive --field=name); do wp theme delete "$t" >/dev/null || true; done

if [ "${SKIP_SEED:-0}" = 1 ]; then echo "→ skipping seed (SKIP_SEED=1)"; else scripts/seed.sh; fi

echo
echo "✔ ready"
echo "  site:    $WP_URL"
echo "  admin:   $WP_URL/wp-admin  ($WP_ADMIN_USER / $WP_ADMIN_PASS)"
echo "  mailpit: http://localhost:${MAIL_PORT:-8025}"
echo "  tunnel:  ssh -p 22022 -L ${WP_PORT:-8080}:127.0.0.1:${WP_PORT:-8080} -L ${MAIL_PORT:-8025}:127.0.0.1:${MAIL_PORT:-8025} dev@178.104.78.114"

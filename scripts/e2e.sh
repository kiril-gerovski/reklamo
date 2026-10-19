#!/usr/bin/env bash
# Browser tests against the running local stack (scripts/setup.sh first).
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env ] && { set -a; . ./.env; set +a; }
export WP_URL="${WP_URL:-http://localhost:8080}" MAILPIT_URL="http://localhost:${MAIL_PORT:-8025}" WP_ADMIN_USER WP_ADMIN_PASS
if [ -n "$(scripts/wp option get reklamo_seed_locked 2>/dev/null || true)" ]; then
  echo "✘ the local stack holds a copy of the live site (scripts/backup.sh load) — the tests edit products; scripts/reset.sh brings the test data back" >&2
  exit 1
fi
[ -f tests/fixtures/logo.ai ] && [ -f tests/fixtures/big.psd ] || scripts/make-fixtures.sh 1 150 >/dev/null
# The by-the-piece sample is hidden on the shop (fixed quantities only) but still tested: shown for the run.
nb=$(scripts/wp post list --post_type=product --post_status=draft --meta_key=_sku --meta_value=NB-001 --field=ID 2>/dev/null | tr -dc '0-9' || true)
if [ -n "$nb" ]; then
  scripts/wp post update "$nb" --post_status=publish >/dev/null 2>&1
  trap '"$root"/scripts/wp post update "$nb" --post_status=draft >/dev/null 2>&1' EXIT
fi
root=$PWD; cd tests/e2e
[ -d node_modules ] || npm install --no-audit --no-fund --silent
npx playwright install chromium >/dev/null 2>&1 || npx playwright install chromium
npx playwright test "$@"

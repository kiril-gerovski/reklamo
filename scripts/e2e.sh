#!/usr/bin/env bash
# Browser tests against the running local stack (scripts/setup.sh first).
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env ] && { set -a; . ./.env; set +a; }
export WP_URL="${WP_URL:-http://localhost:8080}" MAILPIT_URL="http://localhost:${MAIL_PORT:-8025}" WP_ADMIN_USER WP_ADMIN_PASS
[ -f tests/fixtures/logo.ai ] && [ -f tests/fixtures/big.psd ] || scripts/make-fixtures.sh 1 150 >/dev/null
cd tests/e2e
[ -d node_modules ] || npm install --no-audit --no-fund --silent
npx playwright install chromium >/dev/null 2>&1 || npx playwright install chromium
npx playwright test "$@"

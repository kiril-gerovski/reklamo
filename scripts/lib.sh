# Shared helpers for scripts/*.sh — source, don't execute.
#
# Two modes:
#   local (default) — WP-CLI runs in the Docker "cli" service. One helper container is
#                     kept alive for the duration of the calling script (80 wp calls in
#                     seconds, not minutes) and removed on exit.
#   server          — set WP_PATH=/home/<user>/public_html and every wp call runs natively
#                     against that install through PHP_BIN (default: php on PATH). Used on
#                     the hosting account by scripts/host/*.sh and scripts/seed.sh.
#
# versions.env carries the WordPress/WooCommerce pins; .env may override them.

REKLAMO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

while IFS='=' read -r k v; do
  case "$k" in ''|'#'*) continue;; esac
  [ -n "${!k:-}" ] || export "$k=$v"
done < "$REKLAMO_ROOT/versions.env"

if [ -n "${WP_PATH:-}" ]; then
  : "${PHP_BIN:=php}"
  command -v "$PHP_BIN" >/dev/null || { echo "ERROR: PHP_BIN=$PHP_BIN is not executable" >&2; exit 1; }

  # Only a real phar is accepted, run through PHP_BIN so the CLI uses the site's PHP version.
  # SuperHosting's /usr/local/bin/wp-cli is a bash launcher that picks the newest PHP on the
  # box and ignores WP_CLI_PHP; the phar next to it is what we want. Same list as
  # ensure_wp_cli in scripts/host/common.sh.
  reklamo_is_phar() { head -c 200 "$1" 2>/dev/null | grep -q '<?php'; }
  reklamo_find_wp_cli() {
    local c
    for c in "${WP_CLI_PHAR:-}" /usr/local/bin/wp-cli.phar "$(command -v wp-cli 2>/dev/null)" "$(command -v wp 2>/dev/null)" "$HOME/.reklamo/wp-cli.phar"; do
      [ -n "$c" ] && [ -f "$c" ] && reklamo_is_phar "$c" && { echo "$c"; return 0; }
    done
    return 1
  }
  REKLAMO_WP_CLI="$(reklamo_find_wp_cli)" || { echo "ERROR: no WP-CLI phar found (wp-cli.phar, wp-cli, wp or ~/.reklamo/wp-cli.phar) — scripts/host/install.sh downloads one" >&2; exit 1; }
  WP_CMD=("$PHP_BIN" -d memory_limit=512M "$REKLAMO_WP_CLI")
  cli_up()   { :; }
  cli_down() { :; }
  wp() { "${WP_CMD[@]}" --path="$WP_PATH" "$@"; }
else
  # One helper per compose project, so a worktree stack never borrows the main one's CLI.
  REKLAMO_CLI="${COMPOSE_PROJECT_NAME:-reklamo}-cli"

  cli_up() {
    if ! docker ps -q -f name="^${REKLAMO_CLI}\$" | grep . >/dev/null; then
      docker compose run -d --rm --name "$REKLAMO_CLI" cli tail -f /dev/null >/dev/null
      REKLAMO_CLI_STARTED=1
    fi
    trap 'cli_down' EXIT
  }
  cli_down() {
    [ "${REKLAMO_CLI_STARTED:-0}" = 1 ] && docker rm -f "$REKLAMO_CLI" >/dev/null 2>&1 || true
  }
  wp() { docker exec -i "$REKLAMO_CLI" wp "$@"; }
fi

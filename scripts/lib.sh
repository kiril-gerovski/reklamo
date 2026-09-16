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

  # SuperHosting ships WP-CLI as /usr/local/bin/wp-cli. A phar is run through PHP_BIN so
  # the CLI uses the same PHP version as the website; a launcher script only gets the hint.
  reklamo_find_wp_cli() {
    local c
    for c in "${WP_CLI_PHAR:-}" "$(command -v wp-cli 2>/dev/null)" "$(command -v wp 2>/dev/null)" "$HOME/.reklamo/wp-cli.phar"; do
      [ -n "$c" ] && [ -f "$c" ] && { echo "$c"; return 0; }
    done
    return 1
  }
  REKLAMO_WP_CLI="$(reklamo_find_wp_cli)" || { echo "ERROR: WP-CLI not found (wp-cli, wp or ~/.reklamo/wp-cli.phar) — scripts/host/install.sh downloads it" >&2; exit 1; }
  if head -c 64 "$REKLAMO_WP_CLI" | grep -q php; then
    WP_CMD=("$PHP_BIN" -d memory_limit=512M "$REKLAMO_WP_CLI")
  else
    export WP_CLI_PHP="$PHP_BIN" WP_CLI_PHP_ARGS="-d memory_limit=512M"
    WP_CMD=("$REKLAMO_WP_CLI")
  fi
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

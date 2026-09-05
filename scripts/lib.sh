# Shared helpers for scripts/*.sh — source, don't execute.
#
# Two modes:
#   local (default) — WP-CLI runs in the Docker "cli" service. One helper container is
#                     kept alive for the duration of the calling script (80 wp calls in
#                     seconds, not minutes) and removed on exit.
#   server          — set WP_PATH=/home/<user>/public_html (and have `wp` on PATH) and
#                     every wp call runs natively against that install. Used on hosting.

if [ -n "${WP_PATH:-}" ]; then
  command -v wp >/dev/null || { echo "ERROR: WP_PATH is set but 'wp' (WP-CLI) is not on PATH" >&2; exit 1; }
  cli_up()   { :; }
  cli_down() { :; }
  wp() { command wp --path="$WP_PATH" "$@"; }
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

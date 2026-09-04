# Shared helpers for scripts/*.sh — source, don't execute.
# Keeps ONE WP-CLI container alive for the duration of the calling script,
# so 80 wp calls take seconds instead of minutes. Cleans up on exit.

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

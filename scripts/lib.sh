# Shared helpers for scripts/*.sh — source, don't execute.
# Keeps ONE WP-CLI container alive for the duration of the calling script,
# so 80 wp calls take seconds instead of minutes. Cleans up on exit.

cli_up() {
  if ! docker ps -q -f name='^reklamo-cli$' | grep -q .; then
    docker compose run -d --rm --name reklamo-cli cli tail -f /dev/null >/dev/null
    REKLAMO_CLI_STARTED=1
  fi
  trap 'cli_down' EXIT
}
cli_down() {
  [ "${REKLAMO_CLI_STARTED:-0}" = 1 ] && docker rm -f reklamo-cli >/dev/null 2>&1 || true
}
wp() { docker exec -i reklamo-cli wp "$@"; }

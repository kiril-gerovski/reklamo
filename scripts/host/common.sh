# Helpers for scripts/host/*.sh — source, don't execute. Everything here runs ON the
# hosting account (cPanel shell), from the repo root, with .env being the server .env.

say()  { printf '→ %s\n' "$*"; }
ok()   { printf '  ✔ %s\n' "$*"; }
warn() { printf '  ! %s\n' "$*" >&2; }
die()  { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

load_env() { set -a; . ./.env; set +a; }

env_get() { ( . ./.env >/dev/null 2>&1; printf '%s' "${!1:-}" ); }

# Rewrite KEY in .env (append when missing). Single-quoted so passwords survive sourcing.
env_set() { # key value
  local key=$1 value=$2 tmp line found=0
  value="'${value//\'/\'\\\'\'}'"
  tmp=$(mktemp)
  while IFS= read -r line || [ -n "$line" ]; do
    if [[ $line == "$key="* ]]; then printf '%s=%s\n' "$key" "$value"; found=1; else printf '%s\n' "$line"; fi
  done < .env > "$tmp"
  [ $found = 1 ] || printf '%s=%s\n' "$key" "$value" >> "$tmp"
  mv "$tmp" .env
  chmod 600 .env
}

# Ask for a value when the .env line is empty; -s hides the typing.
env_need() { # key prompt [-s]
  local key=$1 prompt=$2 value
  [ -n "$(env_get "$key")" ] && return 0
  [ -t 0 ] || die "$key is empty in .env and there is no terminal to ask for it"
  if [ "${3:-}" = -s ]; then read -r -s -p "$prompt: " value; echo; else read -r -p "$prompt: " value; fi
  [ -n "$value" ] || die "$key is required"
  env_set "$key" "$value"
}

random_token() { "$PHP_BIN" -r 'echo substr( str_replace( array( "/", "+", "=" ), "", base64_encode( random_bytes( 64 ) ) ), 0, (int) $argv[1] );' "${1:-28}"; }

# cPanel UAPI as the account itself; fails loudly with the API's own message.
uapi_call() { # Module function key=value…
  local bin out
  bin=$(command -v uapi 2>/dev/null || true)
  [ -n "$bin" ] || [ -x /usr/local/cpanel/bin/uapi ] && bin=${bin:-/usr/local/cpanel/bin/uapi}
  [ -n "$bin" ] || return 2
  out=$("$bin" --output=json "$@" 2>&1) || true
  if grep -q '"status" *: *1' <<<"$out"; then return 0; fi
  warn "uapi $1 $2: $(grep -o '"errors" *: *\[[^]]*\]' <<<"$out" || echo "$out")"
  return 1
}

# WP-CLI: prefer the host's wp-cli, otherwise a phar of our own under ~/.reklamo.
ensure_wp_cli() {
  local c
  for c in "${WP_CLI_PHAR:-}" "$(command -v wp-cli 2>/dev/null)" "$(command -v wp 2>/dev/null)" "$HOME/.reklamo/wp-cli.phar"; do
    [ -n "$c" ] && [ -f "$c" ] && return 0
  done
  say "downloading WP-CLI to ~/.reklamo/wp-cli.phar"
  mkdir -p "$HOME/.reklamo"
  curl -fsSL -o "$HOME/.reklamo/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  chmod +x "$HOME/.reklamo/wp-cli.phar"
}

db_backup() {
  local f
  mkdir -p "$HOME/reklamo-backups"
  f="$HOME/reklamo-backups/$(date +%Y%m%d-%H%M%S).sql.gz"
  wp db export - | gzip > "$f"
  ok "database backup $f"
}

# Bring WordPress and WooCommerce to the versions pinned in versions.env, either way.
converge_versions() {
  local cur_wp cur_wc
  cur_wp=$(wp core version)
  cur_wc=$(wp plugin get woocommerce --field=version 2>/dev/null || echo none)
  if [ "$cur_wp" = "$WP_VERSION" ] && [ "$cur_wc" = "$WC_VERSION" ]; then
    ok "WordPress $cur_wp, WooCommerce $cur_wc — as pinned"
    return 0
  fi
  db_backup
  if [ "$cur_wp" != "$WP_VERSION" ]; then
    say "WordPress $cur_wp → $WP_VERSION"
    wp core update --version="$WP_VERSION" --force
    wp core update-db
    wp language core update >/dev/null 2>&1 || true
  fi
  if [ "$cur_wc" != "$WC_VERSION" ]; then
    say "WooCommerce $cur_wc → $WC_VERSION"
    wp plugin install woocommerce --version="$WC_VERSION" --force
    wp plugin is-active woocommerce >/dev/null 2>&1 || wp plugin activate woocommerce --quiet
    wp wc update
    wp language plugin update woocommerce >/dev/null 2>&1 || true
  fi
}

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

# Merge a KEY=value file into .env: filled values win, empty ones keep what .env has
# (so uapi-generated DB credentials survive a re-upload). CPANEL_USER is expanded.
env_merge_file() { # file
  local k v
  while IFS='=' read -r k v || [ -n "$k" ]; do
    case "$k" in ''|'#'*) continue;; esac
    v=${v//CPANEL_USER/$(id -un)}
    case "$v" in "'"*"'"|'"'*'"') v=${v:1:-1};; esac
    [ -n "$v" ] && env_set "$k" "$v"
  done < "$1"
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

# WP-CLI: a phar the host already has, otherwise one of our own under ~/.reklamo.
# Same candidate list and phar test as scripts/lib.sh; launcher scripts are skipped.
ensure_wp_cli() {
  local c
  for c in "${WP_CLI_PHAR:-}" /usr/local/bin/wp-cli.phar "$(command -v wp-cli 2>/dev/null)" "$(command -v wp 2>/dev/null)" "$HOME/.reklamo/wp-cli.phar"; do
    [ -n "$c" ] && [ -f "$c" ] && head -c 200 "$c" 2>/dev/null | grep -q '<?php' && return 0
  done
  say "downloading WP-CLI to ~/.reklamo/wp-cli.phar"
  mkdir -p "$HOME/.reklamo"
  local url=https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  if curl --version >/dev/null 2>&1; then
    curl -fsSL -o "$HOME/.reklamo/wp-cli.phar" "$url"
  elif command -v wget >/dev/null; then
    wget -qO "$HOME/.reklamo/wp-cli.phar" "$url"
  else
    die "neither curl nor wget can run here — download wp-cli.phar to ~/.reklamo/ by other means"
  fi
  chmod +x "$HOME/.reklamo/wp-cli.phar"
}

version_lt() { "$PHP_BIN" -r 'exit( version_compare( $argv[1], $argv[2], "<" ) ? 0 : 1 );' "$1" "$2"; }

db_backup() {
  local f
  mkdir -p "$HOME/reklamo-backups"
  f="$HOME/reklamo-backups/$(date +%Y%m%d-%H%M%S).sql.gz"
  wp db export - | gzip > "$f"
  ok "database backup $f"
}

# Raise WordPress and WooCommerce to the versions pinned in versions.env. Never lowers
# them: a site that is ahead (minor security auto-update, dashboard update) is kept and
# the pin is reported as stale.
converge_versions() {
  local cur_wp cur_wc backed_up=0
  cur_wp=$(wp core version)
  cur_wc=$(wp plugin get woocommerce --field=version 2>/dev/null || echo 0)
  if version_lt "$cur_wp" "$WP_VERSION"; then
    db_backup; backed_up=1
    say "WordPress $cur_wp → $WP_VERSION"
    wp core update --version="$WP_VERSION"
    wp core update-db
    wp language core update >/dev/null 2>&1 || true
  elif [ "$cur_wp" != "$WP_VERSION" ]; then
    warn "WordPress $cur_wp is ahead of versions.env ($WP_VERSION) — kept; bump the pin after testing locally"
  else
    ok "WordPress $cur_wp as pinned"
  fi
  if version_lt "$cur_wc" "$WC_VERSION"; then
    [ $backed_up = 1 ] || db_backup
    say "WooCommerce $cur_wc → $WC_VERSION"
    wp plugin install woocommerce --version="$WC_VERSION" --force
    wp plugin is-active woocommerce >/dev/null 2>&1 || wp plugin activate woocommerce --quiet
    wp wc update
    wp language plugin update woocommerce >/dev/null 2>&1 || true
  elif [ "$cur_wc" != "$WC_VERSION" ]; then
    warn "WooCommerce $cur_wc is ahead of versions.env ($WC_VERSION) — kept; bump the pin after testing locally"
  else
    ok "WooCommerce $cur_wc as pinned"
  fi
}

# HTTP through curl when the account may run it; PHP streams otherwise (SuperHosting
# ships /usr/bin/curl as root-only). WP_HOST_IP pins the connection before DNS exists.
http_code() { # url [-L]
  if curl --version >/dev/null 2>&1; then
    local host=${1#*://}; host=${host%%/*}; local -a r=()
    [ -n "${WP_HOST_IP:-}" ] && r=(--resolve "$host:443:$WP_HOST_IP" --resolve "$host:80:$WP_HOST_IP")
    curl -sS --max-time 20 "${r[@]}" -o /dev/null -w '%{http_code}' ${2:-} "$1" 2>/dev/null || true
  else
    php_http "$1" "${WP_HOST_IP:-}" "code${2:-}"
  fi
}
http_body() { # url
  if curl --version >/dev/null 2>&1; then
    local host=${1#*://}; host=${host%%/*}; local -a r=()
    [ -n "${WP_HOST_IP:-}" ] && r=(--resolve "$host:443:$WP_HOST_IP" --resolve "$host:80:$WP_HOST_IP")
    curl -sS --max-time 20 "${r[@]}" "$1" 2>/dev/null || true
  else
    php_http "$1" "${WP_HOST_IP:-}" body
  fi
}
php_http() { # url ip mode(code|code-L|body)
  "$PHP_BIN" -r '
    [ $url, $ip, $mode ] = [ $argv[1], $argv[2], $argv[3] ];
    $host   = parse_url( $url, PHP_URL_HOST );
    $target = "" !== $ip ? preg_replace( "#://" . preg_quote( $host, "#" ) . "#", "://" . $ip, $url, 1 ) : $url;
    $ctx = stream_context_create( array(
      "http" => array( "header" => "Host: $host\r\n", "timeout" => 20, "ignore_errors" => true, "follow_location" => "code-L" === $mode ? 1 : 0, "max_redirects" => 5 ),
      "ssl"  => array( "peer_name" => $host, "SNI_enabled" => true, "verify_peer" => false, "verify_peer_name" => false ),
    ) );
    $body = @file_get_contents( $target, false, $ctx );
    $code = 0;
    foreach ( $http_response_header ?? array() as $h ) { if ( preg_match( "#^HTTP/\S+ (\d{3})#", $h, $m ) ) { $code = (int) $m[1]; } }
    echo "body" === $mode ? (string) $body : $code;
  ' "$1" "$2" "$3"
}

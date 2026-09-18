#!/usr/bin/env bash
# Read-only health report of the hosting install. Runs ON the server from ~/reklamo.
#   scripts/host/check.sh [--mail-test you@example.com]
# Exit code 1 when something needs attention.
set -euo pipefail
cd "$(dirname "$0")/../.."
. scripts/host/common.sh

mail_to=""
while [ $# -gt 0 ]; do
  case "$1" in --mail-test) mail_to=${2:?address}; shift 2;; *) die "unknown option $1";; esac
done

[ -f .env ] || die "no .env — run scripts/host/install.sh first"
load_env
ensure_wp_cli
. scripts/lib.sh
problems=0
bad() { warn "$*"; problems=$((problems + 1)); }

say "code"
ok "checkout $(git describe --tags --always) on $(git symbolic-ref --short -q HEAD || echo 'detached HEAD')"
[ -z "$(git status --porcelain --untracked-files=no)" ] || bad "checkout has local changes: $(git status --short --untracked-files=no | tr '\n' ' ')"
for pair in themes/reklamo plugins/reklamo-core; do
  link="$WP_PATH/wp-content/$pair"
  [ -L "$link" ] && [ "$(readlink "$link")" = "$PWD/wp-content/$pair" ] && ok "$pair symlinked" || bad "$link is not a symlink into this checkout"
done

say "versions"
cli_php=$("$PHP_BIN" -r 'echo PHP_VERSION;')
ok "CLI PHP $cli_php ($PHP_BIN)"
cur_wp=$(wp core version); cur_wc=$(wp plugin get woocommerce --field=version 2>/dev/null || echo 0)
if version_lt "$cur_wp" "$WP_VERSION"; then bad "WordPress $cur_wp behind the pin $WP_VERSION (scripts/host/update.sh)"
elif [ "$cur_wp" != "$WP_VERSION" ]; then warn "WordPress $cur_wp ahead of versions.env ($WP_VERSION) — bump the pin after testing locally"
else ok "WordPress $cur_wp"; fi
if version_lt "$cur_wc" "$WC_VERSION"; then bad "WooCommerce $cur_wc behind the pin $WC_VERSION (scripts/host/update.sh)"
elif [ "$cur_wc" != "$WC_VERSION" ]; then warn "WooCommerce $cur_wc ahead of versions.env ($WC_VERSION) — bump the pin after testing locally"
else ok "WooCommerce $cur_wc"; fi
[ "$(wp theme list --status=active --field=name)" = reklamo ] && ok "theme reklamo active" || bad "theme reklamo is not active"
wp plugin is-active reklamo-core >/dev/null 2>&1 && ok "plugin reklamo-core active" || bad "plugin reklamo-core is not active"

say "configuration"
case "$REKLAMO_PRIVATE_DIR" in
  "$WP_PATH"|"$WP_PATH"/*) bad "REKLAMO_PRIVATE_DIR is inside the web root";;
  *) [ -w "$REKLAMO_PRIVATE_DIR" ] && ok "private dir $REKLAMO_PRIVATE_DIR outside the web root, writable" || bad "private dir $REKLAMO_PRIVATE_DIR is not writable";;
esac
wp config has REKLAMO_DISABLE_RATE_LIMITS --type=constant 2>/dev/null && bad "REKLAMO_DISABLE_RATE_LIMITS is defined — never in production" || ok "rate limits on"
[ "$(wp config get DISABLE_WP_CRON --type=constant 2>/dev/null)" = 1 ] && ok "WP-Cron disabled" || bad "DISABLE_WP_CRON is not set"
if crontab -l >/dev/null 2>&1; then
  crontab -l 2>/dev/null | grep -qF "$WP_PATH && $PHP_BIN wp-cron.php" && ok "cron job present" || bad "no cron job for $WP_PATH/wp-cron.php"
else
  warn "crontab not readable here — confirm the wp-cron.php job in cPanel → Cron Jobs"
fi
[ "$(stat -c %a "$WP_PATH/wp-config.php")" = 600 ] && ok "wp-config.php is 600" || bad "wp-config.php is $(stat -c %a "$WP_PATH/wp-config.php"), expected 600"
grep -qs 'BEGIN WordPress' "$WP_PATH/.htaccess" && ok ".htaccess has the WordPress rewrite block" || bad "$WP_PATH/.htaccess lacks the WordPress rewrite block — run: scripts/wp rewrite flush --hard"
[ "$(wp option get woocommerce_coming_soon)" = no ] && ok "storefront live (not coming soon)" || bad "WooCommerce coming-soon mode is on"
[ "$(wp option get blog_public)" = 1 ] && ok "search engines allowed" || bad "blog_public is 0 — seed.sh was not run with REKLAMO_ENV=production"
due=$(wp cron event list --format=count 2>/dev/null || echo 0)
ok "$due scheduled WP-Cron events (run by the system cron)"

say "web"
code=$(http_code "$WP_URL/" -L)
if [ "$code" = 200 ]; then
  ok "$WP_URL/ → 200"
  code=$(http_code "$WP_URL/kachi-logo/")
  [ "$code" = 200 ] && ok "pretty permalinks work (/kachi-logo/ → 200)" || bad "/kachi-logo/ → $code: rewrite rules not applied (.htaccess / mod_rewrite)"
  code=$(http_code "$WP_URL/wp-content/themes/reklamo/style.css")
  [ "$code" = 200 ] && ok "theme assets served through the symlink" || bad "theme style.css → $code: Apache does not follow the symlink (FollowSymLinks / SymLinksIfOwnerMatch)"
  probe="reklamo-probe-$(random_token 12).php"
  printf '%s\n' '<?php header( "Content-Type: text/plain" ); echo json_encode( array( "php" => PHP_VERSION, "server" => $_SERVER["SERVER_SOFTWARE"] ?? "", "ini" => array_combine( $k = array( "upload_max_filesize", "post_max_size", "max_execution_time", "max_input_time", "memory_limit" ), array_map( "ini_get", $k ) ) ) );' > "$WP_PATH/$probe"
  out=$(http_body "$WP_URL/$probe")
  rm -f "$WP_PATH/$probe"
  if [ -n "$out" ] && "$PHP_BIN" -r 'exit( json_decode( $argv[1] ) ? 0 : 1 );' "$out"; then
    web_php=$("$PHP_BIN" -r 'echo json_decode( $argv[1] )->php;' "$out")
    [ "${web_php%.*}" = "${cli_php%.*}" ] && ok "web PHP $web_php ($("$PHP_BIN" -r 'echo json_decode( $argv[1] )->server;' "$out"))" \
      || bad "web PHP $web_php but CLI PHP $cli_php — align PHP Manager (web) with PHP_BIN in .env"
    "$PHP_BIN" -r 'foreach ( json_decode( $argv[1] )->ini as $k => $v ) { printf( "    %-20s %s\n", $k, $v ); }' "$out"
    "$PHP_BIN" -r 'exit( wp_convert_hr_to_bytes_probe( json_decode( $argv[1] )->ini->upload_max_filesize ) >= 4 * 1024 * 1024 ? 0 : 1 ); function wp_convert_hr_to_bytes_probe( $v ) { $n = (int) $v; switch ( strtolower( substr( trim( $v ), -1 ) ) ) { case "g": $n *= 1024; case "m": $n *= 1024; case "k": $n *= 1024; } return $n; }' "$out" \
      && ok "upload_max_filesize covers the 2 MB upload chunks" || bad "upload_max_filesize below 4M — raise it in PHP Manager"
  else
    bad "PHP probe did not run at $WP_URL/$probe (got: ${out:-nothing})"
  fi
  [ -n "${REKLAMO_PRIVATE_DIR:-}" ] && { code=$(http_code "$WP_URL/$(basename "$REKLAMO_PRIVATE_DIR")/"); [ "$code" = 404 ] && ok "$(basename "$REKLAMO_PRIVATE_DIR")/ not reachable over HTTP" || bad "$WP_URL/$(basename "$REKLAMO_PRIVATE_DIR")/ answers $code"; }
else
  warn "$WP_URL/ → $code — DNS not pointed here yet? Set WP_HOST_IP in .env to probe through the server IP"
fi

if [ -n "$mail_to" ]; then
  say "mail test → $mail_to"
  [[ $mail_to =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || die "--mail-test needs an email address, got: $mail_to"
  res=$(REKLAMO_MAIL_TO="$mail_to" wp eval 'echo wp_mail( getenv( "REKLAMO_MAIL_TO" ), "Reklamo SMTP test", "ok " . gmdate( "c" ) ) ? "sent" : "FAILED: " . Reklamo_Mail::last_error();' 2>&1 | grep -v Deprecated || true)
  case "$res" in sent*) ok "$res — check the inbox and the spam folder";; *) bad "$res";; esac
fi

echo
[ "$problems" = 0 ] && echo "✔ all checks passed" || { echo "✘ $problems item(s) need attention"; exit 1; }

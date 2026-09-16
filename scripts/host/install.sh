#!/usr/bin/env bash
# Zero → live Reklamo.bg on a SuperHosting (cPanel) account. Runs ON the server from
# the repo checkout (~/reklamo). Idempotent: re-run after a failure, or to converge an
# existing install with a changed .env. Never touches customer files or orders.
set -euo pipefail
cd "$(dirname "$0")/../.."
. scripts/host/common.sh

cpuser=$(id -un)
if [ ! -f .env ]; then
  sed "s/CPANEL_USER/$cpuser/g" .env.server.example > .env
  chmod 600 .env
  say "created .env from .env.server.example — answer the prompts, edit .env later if needed"
fi
env_need WP_URL "Site URL (https://reklamo.bg)"
env_need WP_ADMIN_USER "WordPress admin login"
env_need WP_ADMIN_EMAIL "WordPress admin email"
env_need SMTP_USER "Mailbox for outgoing mail, created in cPanel → Email Accounts"
env_need SMTP_PASS "Password of that mailbox" -s
[ -n "$(env_get SMTP_HOST)" ] || env_set SMTP_HOST "$(hostname -f 2>/dev/null || hostname)"
load_env
[ "${REKLAMO_ENV:-}" = production ] || die "REKLAMO_ENV must be production in the server .env"
[ -n "${WP_PATH:-}" ] || die "WP_PATH is empty in .env"
case "${REKLAMO_PRIVATE_DIR:?REKLAMO_PRIVATE_DIR is empty in .env}" in
  "$WP_PATH"|"$WP_PATH"/*) die "REKLAMO_PRIVATE_DIR must be outside $WP_PATH";;
esac

say "PHP"
if [ ! -x "$PHP_BIN" ]; then
  warn "PHP_BIN=$PHP_BIN does not exist. Installed CLI binaries:"
  ls -d /opt/cpanel/ea-php*/root/usr/bin/php 2>/dev/null >&2 || true
  die "set PHP_BIN in .env to the version selected in cPanel → PHP Manager by SuperHosting"
fi
"$PHP_BIN" -r 'exit( version_compare( PHP_VERSION, "8.1", ">=" ) ? 0 : 1 );' || die "PHP ≥ 8.1 required, $PHP_BIN is $("$PHP_BIN" -r 'echo PHP_VERSION;')"
missing=$("$PHP_BIN" -r 'foreach ( array( "mysqli", "dom", "fileinfo", "mbstring", "curl", "openssl", "zip", "json" ) as $e ) { if ( ! extension_loaded( $e ) ) { echo $e, " "; } }')
[ -z "$missing" ] || die "PHP extensions missing for $PHP_BIN: $missing — enable them in PHP Manager → PHP modules"
"$PHP_BIN" -r 'exit( extension_loaded( "gd" ) || extension_loaded( "imagick" ) ? 0 : 1 );' || warn "neither gd nor imagick is loaded — product image thumbnails need one of them"
ok "CLI PHP $("$PHP_BIN" -r 'echo PHP_VERSION;') ($PHP_BIN)"

say "tools"
command -v git >/dev/null || die "git is not available"
command -v curl >/dev/null || die "curl is not available"
ensure_wp_cli
. scripts/lib.sh
ok "WP-CLI $(wp cli version | awk '{print $2}') via ${WP_CMD[0]}"

say "directories"
mkdir -p "$WP_PATH" "$REKLAMO_PRIVATE_DIR"
chmod 750 "$REKLAMO_PRIVATE_DIR"
ok "$REKLAMO_PRIVATE_DIR (outside the web root)"

say "database"
if [ -z "${DB_PASSWORD:-}" ]; then
  name="${cpuser}_reklamo"
  pass=$(random_token 28)
  uapi_call Mysql create_database name="$name" || { [ $? = 2 ] && die "DB_* are empty in .env and cPanel's uapi is unavailable — create a database in cPanel → MySQL Databases and fill DB_NAME/DB_USER/DB_PASSWORD"; }
  uapi_call Mysql create_user name="$name" password="$pass" || true
  uapi_call Mysql set_privileges_on_database user="$name" database="$name" privileges=ALL%20PRIVILEGES || die "could not grant privileges on $name"
  env_set DB_NAME "$name"; env_set DB_USER "$name"; env_set DB_PASSWORD "$pass"
  load_env
  ok "created $name through uapi"
fi
"$PHP_BIN" -r 'mysqli_report( MYSQLI_REPORT_OFF ); $m = new mysqli( $argv[1], $argv[2], $argv[3], $argv[4] ); if ( $m->connect_errno ) { fwrite( STDERR, $m->connect_error . "\n" ); exit( 1 ); }' \
  "${DB_HOST:-localhost}" "$DB_USER" "$DB_PASSWORD" "$DB_NAME" || die "cannot connect to database $DB_NAME as $DB_USER"
ok "connected to $DB_NAME"

say "WordPress core $WP_VERSION"
if [ ! -f "$WP_PATH/wp-load.php" ]; then
  for f in index.html index.htm default.html; do [ -f "$WP_PATH/$f" ] && mv "$WP_PATH/$f" "$WP_PATH/$f.pre-reklamo"; done
  # No localised zip for every release; the bg_BG pack is installed after core install.
  wp core download --version="$WP_VERSION" --skip-content
fi
mkdir -p "$WP_PATH/wp-content/themes" "$WP_PATH/wp-content/plugins" "$WP_PATH/wp-content/uploads"
if [ ! -f "$WP_PATH/wp-config.php" ]; then
  wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" --dbhost="${DB_HOST:-localhost}" --dbprefix=wp_
fi
chmod 600 "$WP_PATH/wp-config.php"

say "wp-config.php constants (from .env)"
cfg() { wp config set "$@" --type=constant --quiet; }
cfg WP_HOME "$WP_URL"
cfg WP_SITEURL "$WP_URL"
cfg WP_ENVIRONMENT_TYPE production
cfg WP_DEBUG false --raw
cfg DISALLOW_FILE_EDIT true --raw
cfg FORCE_SSL_ADMIN true --raw
cfg WP_MEMORY_LIMIT 256M
cfg WP_AUTO_UPDATE_CORE minor
cfg DISABLE_WP_CRON true --raw
cfg REKLAMO_PRIVATE_DIR "$REKLAMO_PRIVATE_DIR"
cfg REKLAMO_SMTP_HOST "$SMTP_HOST"
cfg REKLAMO_SMTP_PORT "${SMTP_PORT:-465}" --raw
cfg REKLAMO_SMTP_SECURE "${SMTP_SECURE:-ssl}"
cfg REKLAMO_SMTP_USER "$SMTP_USER"
cfg REKLAMO_SMTP_PASS "$SMTP_PASS"
# The per-IP limits on the request form and uploader are part of the site's protection.
wp config has REKLAMO_DISABLE_RATE_LIMITS --type=constant 2>/dev/null && wp config delete REKLAMO_DISABLE_RATE_LIMITS --type=constant
ok "written"

generated_pass=""
if wp core is-installed >/dev/null 2>&1; then
  say "WordPress already installed"
else
  say "installing WordPress at $WP_URL"
  pass=${WP_ADMIN_PASS:-}
  [ -n "$pass" ] || { pass=$(random_token 20); generated_pass=$pass; }
  wp core install --url="$WP_URL" --title="$WP_TITLE" --admin_user="$WP_ADMIN_USER" --admin_password="$pass" --admin_email="$WP_ADMIN_EMAIL" --skip-email
fi
wp language core install bg_BG >/dev/null 2>&1 || true
wp site switch-language bg_BG >/dev/null 2>&1 || wp language core activate bg_BG >/dev/null 2>&1 || true

say "linking theme and plugin from the checkout"
for pair in themes/reklamo plugins/reklamo-core; do
  target="$PWD/wp-content/$pair"; link="$WP_PATH/wp-content/$pair"
  if [ -L "$link" ]; then
    [ "$(readlink "$link")" = "$target" ] || ln -sfn "$target" "$link"
  elif [ -e "$link" ]; then
    die "$link exists and is not a symlink — move it away, it would shadow the git checkout"
  else
    ln -s "$target" "$link"
  fi
  ok "$link → $target"
done

say "WooCommerce $WC_VERSION"
if ! wp plugin is-installed woocommerce >/dev/null 2>&1; then
  wp plugin install woocommerce --version="$WC_VERSION"
fi
wp plugin is-active woocommerce >/dev/null 2>&1 || wp plugin activate woocommerce --quiet
wp language plugin install woocommerce bg_BG >/dev/null 2>&1 || true
converge_versions

say "activating theme and plugin"
wp theme activate reklamo --quiet
wp plugin activate reklamo-core --quiet
for p in akismet hello; do wp plugin is-installed "$p" >/dev/null 2>&1 && wp plugin delete "$p" --quiet || true; done
for t in $(wp theme list --status=inactive --field=name); do wp theme delete "$t" --quiet || true; done

say "PHP limits for the site (.user.ini)"
if [ ! -f "$WP_PATH/.user.ini" ]; then
  printf 'upload_max_filesize = 128M\npost_max_size = 128M\nmax_execution_time = 120\nmax_input_time = 300\nmemory_limit = 256M\n' > "$WP_PATH/.user.ini"
  ok "written — check.sh reports whether the web server honours it"
else
  ok "already present, left as is"
fi

say "cron (WP-Cron is disabled, real cron every 5 minutes)"
cron_line="*/5 * * * * cd $WP_PATH && $PHP_BIN wp-cron.php >/dev/null 2>&1"
current=$(crontab -l 2>/dev/null || true)
if grep -qF "$WP_PATH && $PHP_BIN wp-cron.php" <<<"$current"; then
  ok "already scheduled"
elif { grep -vF "wp-cron.php" <<<"$current"; echo "$cron_line"; } | sed '/^$/d' | crontab - 2>/dev/null; then
  ok "scheduled"
else
  warn "could not write the crontab — add it in cPanel → Cron Jobs: $cron_line"
fi

say "site configuration (scripts/seed.sh)"
scripts/seed.sh

echo
scripts/host/check.sh || true

echo
echo "✔ installed"
echo "  site:   $WP_URL"
echo "  admin:  $WP_URL/wp-admin  ($WP_ADMIN_USER)"
[ -n "$generated_pass" ] && echo "  password: $generated_pass   ← shown once, not stored anywhere"
echo "  next:   cPanel → SSL/TLS Status (AutoSSL), Email Deliverability (SPF/DKIM), then docs/DEPLOYMENT.md § After install"

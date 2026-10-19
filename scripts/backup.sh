#!/usr/bin/env bash
# Copies of the live site on your workstation: database, media library, customer files and the
# rest of public_html (core, WooCommerce, .htaccess…; kept for disaster recovery, never applied).
# Secrets stay on the server: wp-config.php and ~/reklamo/.env are not copied.
# Reads the same DEPLOY_* settings as scripts/deploy.sh (.env.deploy).
#   scripts/backup.sh pull [--daily]    new snapshot from the live site into $REKLAMO_BACKUP_DIR
#                                       (--daily: only when there is none from today yet, for cron)
#   scripts/backup.sh list              the snapshots you have
#   scripts/backup.sh load [snapshot]   put a snapshot into the LOCAL stack (default: the latest)
#   scripts/backup.sh restore <snapshot>   put a snapshot back on the LIVE site (asks first)
# Snapshots live outside the repo (default ~/reklamo-backups) because they hold customer data.
set -euo pipefail
cd "$(dirname "$0")/.."
if [ -f .env.deploy ]; then
  while IFS='=' read -r k v; do
    case "$k" in DEPLOY_*) [ -n "${!k:-}" ] || export "$k=$v";; esac
  done < .env.deploy
fi
: "${DEPLOY_PORT:=1022}" "${DEPLOY_DIR:=reklamo}"
: "${REKLAMO_BACKUP_DIR:=$HOME/reklamo-backups}" "${REKLAMO_BACKUP_KEEP:=30}"
LIVE_URL=https://reklamo.bg

die() { echo "✘ $*" >&2; exit 1; }
say() { echo "→ $*"; }
need_remote() {
  : "${DEPLOY_USER:?set DEPLOY_USER in .env.deploy}" "${DEPLOY_HOST:?set DEPLOY_HOST in .env.deploy}"
}
# No -t: a pseudo-terminal would corrupt the binary streams.
remote() { ssh -q -p "$DEPLOY_PORT" "$DEPLOY_USER@$DEPLOY_HOST" "$@" 2> >(grep -v '^mesg: ttyname' >&2); }
# The server's paths, from the .env the install wrote there.
remote_env() { remote "cd $DEPLOY_DIR && . ./.env && echo \"\$WP_PATH\" && echo \"\$REKLAMO_PRIVATE_DIR\""; }

snapshot() { # name or empty → directory
  local s=${1:-latest}
  [ -d "$REKLAMO_BACKUP_DIR/$s" ] || die "no snapshot '$s' in $REKLAMO_BACKUP_DIR (scripts/backup.sh list)"
  s=$(cd "$REKLAMO_BACKUP_DIR/$s" && pwd -P)
  for f in db.sql.gz uploads.tar.gz private.tar.gz; do
    [ -s "$s/$f" ] || die "$s is incomplete: $f missing"
  done
  echo "$s"
}

cmd=${1:-}; shift || true
case "$cmd" in
  pull)
    need_remote
    if [ "${1:-}" = --daily ] && [ -e "$REKLAMO_BACKUP_DIR/latest" ] &&
       [ "$(readlink "$REKLAMO_BACKUP_DIR/latest" | cut -c1-8)" = "$(date +%Y%m%d)" ]; then
      exit 0
    fi
    mkdir -p "$REKLAMO_BACKUP_DIR"; chmod 700 "$REKLAMO_BACKUP_DIR"
    { read -r wp_path; read -r private_dir; } < <(remote_env)
    [ -n "$wp_path" ] && [ -n "$private_dir" ] || die "could not read WP_PATH / REKLAMO_PRIVATE_DIR from the server .env"
    name=$(date +%Y%m%d-%H%M%S); dir="$REKLAMO_BACKUP_DIR/$name"; tmp="$dir.partial"
    mkdir -p "$tmp"
    trap 'rm -rf "${tmp:?}"' EXIT
    say "database"
    remote "cd $DEPLOY_DIR && scripts/wp db export - --single-transaction --quiet" | gzip > "$tmp/db.sql.gz"
    gunzip -c "$tmp/db.sql.gz" | tail -n 1 | grep -q "Dump completed" || die "the database dump is truncated"
    say "media library"
    remote "tar czf - -C $(printf %q "$wp_path/wp-content") uploads" > "$tmp/uploads.tar.gz"
    say "customer files"
    remote "tar czf - -C $(printf %q "$(dirname "$private_dir")") $(printf %q "$(basename "$private_dir")")" > "$tmp/private.tar.gz"
    say "site files (public_html without uploads, wp-config.php and error_log)"
    remote "tar czf - -C $(printf %q "$(dirname "$wp_path")") --exclude=$(printf %q "$(basename "$wp_path")")/wp-content/uploads --exclude=$(printf %q "$(basename "$wp_path")")/wp-config.php --exclude=$(printf %q "$(basename "$wp_path")")/error_log $(printf %q "$(basename "$wp_path")")" > "$tmp/site.tar.gz"
    for f in uploads private site; do gzip -t "$tmp/$f.tar.gz" || die "$f archive is damaged"; done
    {
      echo "taken   $(date '+%Y-%m-%d %H:%M:%S')"
      echo "from    $DEPLOY_USER@$DEPLOY_HOST ($wp_path)"
      echo "code    $(remote "cd $DEPLOY_DIR && git describe --tags --always")"
      echo "orders  $(remote "cd $DEPLOY_DIR && scripts/wp eval 'echo count( wc_get_orders( array( \"limit\" => -1, \"return\" => \"ids\", \"status\" => \"any\" ) ) );'" || echo '?')"
      echo "products $(remote "cd $DEPLOY_DIR && scripts/wp post list --post_type=product --post_status=any --format=count" || echo '?')"
    } > "$tmp/info.txt"
    mv "$tmp" "$dir"; trap - EXIT
    chmod -R go-rwx "$dir"
    ln -sfn "$name" "$REKLAMO_BACKUP_DIR/latest"
    # Keep the newest $REKLAMO_BACKUP_KEEP; older ones go, erased customer data with them.
    find "$REKLAMO_BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name '20*' ! -name '*.partial' | sort -r |
      tail -n +"$((REKLAMO_BACKUP_KEEP + 1))" | while read -r old; do rm -rf "${old:?}"; echo "  removed old snapshot $(basename "$old")"; done
    cat "$dir/info.txt"
    echo "✔ snapshot $dir ($(du -sh "$dir" | cut -f1))"
    ;;

  list)
    [ -d "$REKLAMO_BACKUP_DIR" ] || die "no snapshots yet — scripts/backup.sh pull"
    latest=$(readlink "$REKLAMO_BACKUP_DIR/latest" 2>/dev/null || true)
    for d in $(find "$REKLAMO_BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name '20*' ! -name '*.partial' | sort); do
      n=$(basename "$d")
      printf '%s  %6s  %s%s\n' "$n" "$(du -sh "$d" | cut -f1)" \
        "$(grep -E '^(orders|products)' "$d/info.txt" 2>/dev/null | tr -s ' ' | paste -sd ' ' -)" \
        "$([ "$n" = "$latest" ] && echo '  ← latest')"
    done
    ;;

  load)
    snap=$(snapshot "${1:-}")
    [ -f .env ] || die "no local .env — run scripts/setup.sh first"
    set -a; . ./.env; set +a
    local_url=${WP_URL:-http://localhost:8080}
    say "local stack"
    docker compose up -d --wait >/dev/null
    mkdir -p "$REKLAMO_BACKUP_DIR/local"
    before="$REKLAMO_BACKUP_DIR/local/before-load-$(date +%Y%m%d-%H%M%S).sql.gz"
    scripts/wp db export - --quiet 2>/dev/null | gzip > "$before" && echo "  the local database was saved to $before"
    say "database from $(basename "$snap")"
    scripts/wp db reset --yes --quiet
    gunzip -c "$snap/db.sql.gz" | scripts/wp db import - --quiet
    scripts/wp search-replace "$LIVE_URL" "$local_url" --all-tables --precise --quiet
    scripts/wp search-replace "${LIVE_URL#https:}" "${local_url#http:}" --all-tables --precise --quiet
    scripts/wp search-replace "${LIVE_URL//\//\\/}" "${local_url//\//\\/}" --all-tables --precise --quiet
    # Even a snapshot taken before the lock existed must never be seeded over.
    scripts/wp option update reklamo_seed_locked 1 --autoload=no --quiet
    say "media library and customer files"
    # Replaced, not merged: the local copy should match the live site exactly.
    # Contents only: private/ is a bind mount and must keep its inode.
    rm -rf wp/wp-content/uploads; find private -mindepth 1 -delete
    tar xzf "$snap/uploads.tar.gz" -C wp/wp-content
    tar xzf "$snap/private.tar.gz" --strip-components=1 -C private
    scripts/wp cache flush --quiet || true
    scripts/wp rewrite flush --quiet || true
    echo "✔ the local site at $local_url now holds the live data of $(basename "$snap")"
    echo "  log in with your live account; emails go to Mailpit, not to customers."
    echo "  scripts/seed.sh refuses this database (it is locked); scripts/reset.sh brings the test data back."
    ;;

  restore)
    need_remote
    [ -n "${1:-}" ] || die "name the snapshot to restore (scripts/backup.sh list) — restoring to live is never a default"
    snap=$(snapshot "$1")
    { read -r wp_path; read -r private_dir; } < <(remote_env)
    echo "This REPLACES the live database of $LIVE_URL with $(basename "$snap"):"
    sed 's/^/  /' "$snap/info.txt"
    echo "Orders, products and settings changed on the live site after that moment are lost"
    echo "(a dump of the current live database is taken first, in ~/reklamo-backups on the server)."
    read -r -p "Type reklamo.bg to continue: " answer
    [ "$answer" = reklamo.bg ] || die "cancelled"
    say "saving the current live database on the server"
    remote "cd $DEPLOY_DIR && mkdir -p ~/reklamo-backups && f=~/reklamo-backups/before-restore-\$(date +%Y%m%d-%H%M%S).sql.gz && scripts/wp db export - --single-transaction --quiet | gzip > \$f && echo \"  \$f\""
    remote "cd $DEPLOY_DIR && scripts/wp maintenance-mode activate --quiet"
    trap 'remote "cd $DEPLOY_DIR && scripts/wp maintenance-mode deactivate --quiet" || true' EXIT
    say "database"
    gunzip -c "$snap/db.sql.gz" | remote "cd $DEPLOY_DIR && scripts/wp db import - --quiet"
    say "media library and customer files (added and overwritten, nothing deleted)"
    remote "tar xzf - -C $(printf %q "$wp_path/wp-content")" < "$snap/uploads.tar.gz"
    remote "tar xzf - -C $(printf %q "$(dirname "$private_dir")")" < "$snap/private.tar.gz"
    remote "cd $DEPLOY_DIR && scripts/wp cache flush --quiet; scripts/wp rewrite flush --hard --quiet" || true
    remote "cd $DEPLOY_DIR && scripts/wp maintenance-mode deactivate --quiet"
    trap - EXIT
    echo "✔ $LIVE_URL restored from $(basename "$snap")"
    ;;

  *) sed -n '2,11p' "$0" >&2; exit 1 ;;
esac

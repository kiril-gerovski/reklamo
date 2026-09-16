#!/usr/bin/env bash
# Drive the hosting account from your workstation over SSH. Needs .env.deploy
# (copy .env.deploy.example). SSH access must be enabled in my.superhosting.bg first.
#   scripts/deploy.sh install                 fresh account → live site (clone + install)
#   scripts/deploy.sh update [tag|branch]     ship a new version
#   scripts/deploy.sh check [--mail-test a@b] health report
#   scripts/deploy.sh wp <args…>              WP-CLI on the server
#   scripts/deploy.sh ssh                     interactive shell
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env.deploy ] || { echo "no .env.deploy — cp .env.deploy.example .env.deploy and fill it in" >&2; exit 1; }
set -a; . ./.env.deploy; set +a
: "${DEPLOY_USER:?}" "${DEPLOY_HOST:?}" "${DEPLOY_PORT:=1022}" "${DEPLOY_DIR:=reklamo}" "${DEPLOY_REF:=main}"

remote() { ssh -t -p "$DEPLOY_PORT" "$DEPLOY_USER@$DEPLOY_HOST" "$@"; }
quoted() { printf '%q ' "$@"; }

cmd=${1:-}; shift || true
case "$cmd" in
  install)
    : "${DEPLOY_REPO:?DEPLOY_REPO is empty in .env.deploy}"
    scp -q -P "$DEPLOY_PORT" scripts/host/bootstrap.sh "$DEPLOY_USER@$DEPLOY_HOST:reklamo-bootstrap.sh"
    remote "bash reklamo-bootstrap.sh $(quoted "$DEPLOY_REPO" "$DEPLOY_DIR" "$DEPLOY_REF"); rc=\$?; rm -f reklamo-bootstrap.sh; exit \$rc"
    ;;
  update) remote "cd $DEPLOY_DIR && scripts/host/update.sh $(quoted "$@")" ;;
  check)  remote "cd $DEPLOY_DIR && scripts/host/check.sh $(quoted "$@")" ;;
  wp)     remote "cd $DEPLOY_DIR && scripts/wp $(quoted "$@")" ;;
  ssh)    remote ;;
  *) sed -n '2,9p' "$0" >&2; exit 1 ;;
esac

#!/usr/bin/env bash
# Drive the hosting account from your workstation over SSH. Settings come from DEPLOY_*
# environment variables, with .env.deploy (copy .env.deploy.example) filling the gaps.
# SSH access must be enabled in my.superhosting.bg first.
#   scripts/deploy.sh install                 fresh account → live site (clone + install)
#   scripts/deploy.sh update [tag|branch]     ship a new version
#   scripts/deploy.sh check [--mail-test a@b] health report
#   scripts/deploy.sh wp <args…>              WP-CLI on the server
#   scripts/deploy.sh ssh                     interactive shell
set -euo pipefail
cd "$(dirname "$0")/.."
if [ -f .env.deploy ]; then
  while IFS='=' read -r k v; do
    case "$k" in DEPLOY_*) [ -n "${!k:-}" ] || export "$k=$v";; esac
  done < .env.deploy
fi
: "${DEPLOY_USER:?set DEPLOY_USER (cPanel user) in the environment or .env.deploy}"
: "${DEPLOY_HOST:?set DEPLOY_HOST (domain or serverNN.superhosting.bg)}"
: "${DEPLOY_PORT:=1022}" "${DEPLOY_DIR:=reklamo}" "${DEPLOY_REF:=main}"
: "${DEPLOY_REPO:=$(git remote get-url origin 2>/dev/null || true)}"

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

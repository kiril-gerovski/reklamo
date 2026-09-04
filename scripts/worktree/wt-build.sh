#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

usage() {
    cat <<'USAGE'
wt-build.sh <branch> [options]

Applies worktree code to its running stack. PHP is bind-mounted, so most edits
are live already; the default here restarts the wp container (picks up php.ini
and wp-config changes) and flushes the object + rewrite caches. Touches no
worktree, no branch, no database.

Options:
  --seed         Re-run scripts/seed.sh (you changed configuration-as-code)
  --lint         Run scripts/lint.sh (PHPCS) in the worktree first
  --reset        Destroy the stack's database and WordPress core and rebuild from
                 zero with scripts/reset.sh. DESTROYS orders and uploads. Implies --seed.
  --yes, -y      Skip the --reset confirmation prompt
  --no-restart   Skip the restart and cache flush
  -h, --help     This text

The stack must already be up. Create it with wt-new.sh first.
USAGE
}

BRANCH=""; SEED=0; LINT=0; RESET=0; RESTART=1; ASSUME_YES=0
while [[ $# -gt 0 ]]; do
    case "$1" in
    --seed)       SEED=1; shift ;;
    --lint)       LINT=1; shift ;;
    --reset)      RESET=1; shift ;;
    --yes|-y)     ASSUME_YES=1; shift ;;
    --no-restart) RESTART=0; shift ;;
    -h|--help)    usage; exit 0 ;;
    -*)           die "unknown option: $1" ;;
    *)            [[ -z "$BRANCH" ]] || die "unexpected argument: $1"; BRANCH="$1"; shift ;;
    esac
done
[[ -n "$BRANCH" ]] || { usage; exit 1; }

require_docker
WT="$(worktree_path "$BRANCH")"
[[ -f "$WT/.env" ]] || die "no stack for '$BRANCH'; create it with $WT_DIR/wt-new.sh $BRANCH"
PORT="$(env_get "$WT" WP_PORT)"

if [[ "$LINT" == "1" ]]; then
    info "lint"
    ( cd "$WT" && scripts/lint.sh )
fi

if [[ "$RESET" == "1" ]]; then
    echo
    echo "About to destroy the stack $(project_name "$BRANCH"): database (all orders), WordPress"
    echo "core, uploaded logos. It is rebuilt from the worktree's scripts. The worktree,"
    echo "branch and your code are untouched."
    echo
    confirm "Type 'yes' to proceed: " "$ASSUME_YES"
    ( cd "$WT" && scripts/reset.sh )
    info "ready at http://localhost:$PORT"
    exit 0
fi

stack_running "$BRANCH" || die "the stack for '$BRANCH' is not running; start it with $WT_DIR/wt-new.sh $BRANCH"

if [[ "$SEED" == "1" ]]; then
    info "re-applying configuration (scripts/seed.sh)"
    ( cd "$WT" && scripts/seed.sh )
fi

if [[ "$RESTART" == "1" ]]; then
    info "restarting wp"
    compose "$BRANCH" restart wp
    wait_for_http "http://127.0.0.1:$PORT/"
    info "flushing caches"
    compose "$BRANCH" run --rm -T cli wp cache flush >/dev/null
    compose "$BRANCH" run --rm -T cli wp rewrite flush >/dev/null
fi

info "ready at http://localhost:$PORT"

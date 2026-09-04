#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

usage() {
    cat <<'USAGE'
wt-drop.sh <branch> [options]

Destroys a worktree stack: containers, volumes (database, uploaded logos),
the worktree directory and the branch.

Options:
  --into <target>    Branch the merge check runs against (default: main)
  --keep-worktree    Tear down the stack but keep the worktree and branch
  --yes, -y          Skip the confirmation prompt (required when stdin is not a TTY)
  --force            Proceed even if the branch is not merged into <target>
  -h, --help         This text

Refuses main/master. Refuses a dirty worktree -- commit or discard first.
USAGE
}

BRANCH=""; TARGET="main"; KEEP=0; FORCE=0; ASSUME_YES=0
while [[ $# -gt 0 ]]; do
    case "$1" in
    --into)          TARGET="$2"; shift 2 ;;
    --keep-worktree) KEEP=1; shift ;;
    --yes|-y)        ASSUME_YES=1; shift ;;
    --force)         FORCE=1; shift ;;
    -h|--help)       usage; exit 0 ;;
    -*)              die "unknown option: $1" ;;
    *)               [[ -z "$BRANCH" ]] || die "unexpected argument: $1"; BRANCH="$1"; shift ;;
    esac
done
[[ -n "$BRANCH" ]] || { usage; exit 1; }
is_protected "$BRANCH" && die "refusing to drop protected branch '$BRANCH'"
require_docker

WT="$(worktree_path "$BRANCH")"
HAS_WT=0; [[ -d "$WT" ]] && HAS_WT=1
HAS_STACK=0; [[ -f "$WT/.env" ]] && HAS_STACK=1
[[ "$HAS_WT" == "1" ]] || die "no worktree for '$BRANCH' at $WT"

if [[ "$KEEP" == "0" ]] && git -C "$REPO_ROOT" show-ref --verify --quiet "refs/heads/$BRANCH" &&
   ! git -C "$REPO_ROOT" merge-base --is-ancestor "$BRANCH" "$TARGET" 2>/dev/null; then
    echo "'$BRANCH' is NOT merged into '$TARGET'" >&2
    [[ "$FORCE" == "1" ]] || die "merge it first ($WT_DIR/wt-merge.sh $BRANCH), or pass --force to destroy it anyway"
    echo "--force given; the unmerged commits will be lost" >&2
fi

echo; echo "About to destroy:"
[[ "$HAS_STACK" == "1" ]] && echo "  stack     $(project_name "$BRANCH") (containers + database + uploaded files)"
if [[ "$KEEP" == "0" ]]; then
    echo "  worktree  $WT"
    echo "  branch    $BRANCH"
fi
echo
confirm "Type 'yes' to proceed: " "$ASSUME_YES"

if [[ "$HAS_STACK" == "1" ]]; then
    info "stopping stack and dropping volumes"
    docker rm -f "$(project_name "$BRANCH")-cli" >/dev/null 2>&1 || true
    compose "$BRANCH" --profile cli down -v --remove-orphans
fi

if [[ "$KEEP" == "0" ]]; then
    # Regenerable, gitignored, and (wp/) owned by the container user — clear them
    # so `git worktree remove` sees a clean tree.
    rm -rf "$WT/wp" "$WT/private" "$WT/vendor" "$WT/.playwright-mcp" "$WT/.env"
    info "removing worktree"
    git -C "$REPO_ROOT" worktree remove "$WT"
    if git -C "$REPO_ROOT" show-ref --verify --quiet "refs/heads/$BRANCH"; then
        info "deleting branch '$BRANCH'"
        git -C "$REPO_ROOT" branch -D "$BRANCH"
    fi
fi
info "done"

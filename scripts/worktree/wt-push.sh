#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

usage() {
    cat <<'USAGE'
wt-push.sh <branch> -m <message> [options]

Commits every change in the branch's worktree and pushes the branch to origin.
If the worktree is already committed, it is only pushed (when ahead of origin).

This is the ONLY script here that commits and pushes, and only for the worktree
branch you name. It refuses protected branches and never touches the main
checkout on :8080.

Options:
  -m, --message <msg>   Commit message (required when the worktree is dirty)
  --no-verify           Pass --no-verify to git commit (skip hooks)
  --dry-run             Show what would be committed and pushed, change nothing
  -h, --help            This text
USAGE
}

BRANCH=""; MESSAGE=""; DRY_RUN=0; NO_VERIFY=0
while [[ $# -gt 0 ]]; do
    case "$1" in
    -m|--message) [[ $# -ge 2 ]] || die "$1 needs a value"; MESSAGE="$2"; shift 2 ;;
    --no-verify)  NO_VERIFY=1; shift ;;
    --dry-run)    DRY_RUN=1; shift ;;
    -h|--help)    usage; exit 0 ;;
    -*)           die "unknown option: $1" ;;
    *)            [[ -z "$BRANCH" ]] || die "unexpected argument: $1"; BRANCH="$1"; shift ;;
    esac
done
[[ -n "$BRANCH" ]] || { usage; exit 1; }

is_protected "$BRANCH" && die "refusing to commit or push to protected branch '$BRANCH'"

WT="$(worktree_path "$BRANCH")"
[[ -d "$WT" ]] || die "no worktree for '$BRANCH'; create one with $WT_DIR/wt-new.sh $BRANCH"
worktree_registered "$WT" || die "$WT is not a registered worktree"
CURRENT="$(branch_of "$WT")"
[[ "$CURRENT" == "$BRANCH" ]] || die "$WT is on '$CURRENT', not '$BRANCH'"

DIRTY=0; [[ -n "$(git -C "$WT" status --porcelain)" ]] && DIRTY=1
AHEAD=0
if git -C "$WT" show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
    [[ "$(git -C "$WT" rev-list --count "origin/$BRANCH..$BRANCH")" != "0" ]] && AHEAD=1
else
    AHEAD=1   # never pushed
fi

if [[ "$DIRTY" == "0" && "$AHEAD" == "0" ]]; then
    info "nothing to do: worktree is clean and level with origin/$BRANCH"; exit 0
fi
[[ "$DIRTY" == "0" || -n "$MESSAGE" || "$DRY_RUN" == "1" ]] ||
    die "uncommitted changes but no -m <message> given"

if [[ "$DIRTY" == "1" ]]; then
    echo "==> $(git -C "$WT" status --porcelain | wc -l | tr -d ' ') file(s) to commit on '$BRANCH':"
    git -C "$WT" status --short | sed 's/^/      /'
elif [[ "$AHEAD" == "1" ]]; then
    echo "==> already committed, $(git -C "$WT" rev-list --count "origin/$BRANCH..$BRANCH" 2>/dev/null || echo '?') commit(s) to push"
fi

if [[ "$DRY_RUN" == "1" ]]; then echo; info "dry run: nothing was committed or pushed"; exit 0; fi

if [[ "$DIRTY" == "1" ]]; then
    info "committing"
    git -C "$WT" add -A
    args=(-m "$MESSAGE"); [[ "$NO_VERIFY" == "1" ]] && args+=(--no-verify)
    git -C "$WT" commit "${args[@]}"
fi

info "pushing '$BRANCH' to origin"
git -C "$WT" push -u origin "$BRANCH"

echo
info "origin/$BRANCH is now $(git -C "$WT" rev-parse --short HEAD)"
cat <<EOT

Fold it into main when ready:
  $WT_DIR/wt-merge.sh $BRANCH

EOT

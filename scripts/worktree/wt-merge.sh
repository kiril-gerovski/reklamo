#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

usage() {
    cat <<'USAGE'
wt-merge.sh <branch> [options]

Folds a worktree branch into a target branch in the main checkout. Runs all
checks first, then leaves a STAGED merge. It never commits and never pushes --
you finish with the printed `git commit` line.

Options:
  --into <target>   Branch to merge into (default: main)
  --skip-tests      Skip PHPCS
  -h, --help        This text

Requirements:
  - the worktree is clean and fully committed
  - the main checkout is clean and already on <target>
    (the script will not switch it -- that would swap the code :8080 is serving)
USAGE
}

BRANCH=""; TARGET="main"; SKIP_TESTS=0
while [[ $# -gt 0 ]]; do
    case "$1" in
    --into)       TARGET="$2"; shift 2 ;;
    --skip-tests) SKIP_TESTS=1; shift ;;
    -h|--help)    usage; exit 0 ;;
    -*)           die "unknown option: $1" ;;
    *)            [[ -z "$BRANCH" ]] || die "unexpected argument: $1"; BRANCH="$1"; shift ;;
    esac
done
[[ -n "$BRANCH" ]] || { usage; exit 1; }
[[ "$BRANCH" != "$TARGET" ]] || die "cannot merge '$BRANCH' into itself"

git -C "$REPO_ROOT" show-ref --verify --quiet "refs/heads/$BRANCH" || die "no local branch '$BRANCH'"
git -C "$REPO_ROOT" show-ref --verify --quiet "refs/heads/$TARGET" || die "'$TARGET' is not a local branch"
[[ "$(git -C "$REPO_ROOT" rev-list --count "$TARGET..$BRANCH")" != "0" ]] ||
    die "nothing to merge: '$BRANCH' has no commits beyond '$TARGET'"

WT="$(worktree_path "$BRANCH")"
[[ -d "$WT" ]] || die "no worktree for '$BRANCH' at $WT"
worktree_registered "$WT" || die "$WT is not a registered worktree"

assert_clean "$WT" "worktree ($WT)"
assert_clean "$REPO_ROOT" "main checkout ($REPO_ROOT)"

CURRENT="$(branch_of "$REPO_ROOT")"
[[ "$CURRENT" == "$TARGET" ]] || die "$REPO_ROOT is on '$CURRENT', not '$TARGET'.
Switch it yourself if that is what you want -- this script will not, because it
swaps the code the :8080 stack is serving:
  git -C $REPO_ROOT switch $TARGET"

git -C "$REPO_ROOT" fetch --quiet origin || true
if git -C "$REPO_ROOT" show-ref --verify --quiet "refs/remotes/origin/$TARGET"; then
    behind="$(git -C "$REPO_ROOT" rev-list --count "$TARGET..origin/$TARGET")"
    [[ "$behind" == "0" ]] || echo "WARNING: $TARGET is $behind commit(s) behind origin/$TARGET" >&2
fi

if ! git -C "$REPO_ROOT" merge-tree --write-tree "$TARGET" "$BRANCH" >/dev/null 2>&1; then
    echo "CONFLICT: merging '$BRANCH' into '$TARGET' does not apply cleanly:" >&2
    git -C "$REPO_ROOT" merge-tree --write-tree --name-only "$TARGET" "$BRANCH" 2>/dev/null | tail -n +2 >&2 || true
    die "resolve the conflicts on '$BRANCH' first (nothing was modified)"
fi

if [[ "$SKIP_TESTS" == "0" ]]; then
    if [[ -x "$WT/scripts/lint.sh" ]]; then
        info "PHPCS in the worktree"
        ( cd "$WT" && scripts/lint.sh )
    else
        echo "WARNING: $WT/scripts/lint.sh missing, skipping lint" >&2
    fi
fi

info "staging merge of '$BRANCH' into '$TARGET'"
if ! git -C "$REPO_ROOT" merge --no-ff --no-commit "$BRANCH"; then
    echo "ERROR: merge failed after the dry run passed; rolling back" >&2
    git -C "$REPO_ROOT" merge --abort || true
    exit 1
fi

SEED_CHANGED=0
git -C "$REPO_ROOT" diff --cached --name-only | grep -x 'scripts/seed.sh' >/dev/null && SEED_CHANGED=1

cat <<EOT

Merge is STAGED, not committed. Nothing was pushed.

Finish it yourself:
  git -C $REPO_ROOT merge --continue

Back out instead:
  git -C $REPO_ROOT merge --abort

After committing, make it live on :8080 (code is bind-mounted; only config needs applying):
EOT
[[ "$SEED_CHANGED" == "1" ]] && echo "  $REPO_ROOT/scripts/seed.sh        # scripts/seed.sh changed in this merge"
cat <<EOT
  $REPO_ROOT/scripts/wp cache flush && $REPO_ROOT/scripts/wp rewrite flush

Then clean up:
  $WT_DIR/wt-drop.sh $BRANCH

EOT

#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib.sh"

usage() {
    cat <<'USAGE'
wt-new.sh <branch> [options]

Creates a git worktree for <branch> under .worktrees/ and brings up an isolated
WordPress + WooCommerce stack running that worktree's theme and plugin, on its
own port with its own database and Mailpit, leaving the main stack on :8080
untouched.

Options:
  --port N      Publish WordPress on port N (default: first free from 8081;
                Mailpit is always N+1000)
  --no-seed     Install WordPress + WooCommerce but skip scripts/seed.sh
  --list        Show every worktree stack and exit
  -h, --help    This text

Notes:
  - The new branch starts from local `main` (falls back to origin/main).
    Uncommitted work in the main checkout is NOT in the worktree — commit first.
  - One extra stack at a time is the realistic limit: the VM has ~7.6 GB RAM,
    the ERP stack uses most of it, and each WordPress stack needs ~500 MB.
  - Port 8080 is refused; it is the main dev stack.
USAGE
}

list_stacks() {
    [[ -d "$WORKTREE_ROOT" ]] || { echo "no worktree stacks"; return 0; }
    printf '%-28s %-6s %-6s %-8s %s\n' BRANCH WP MAIL STATUS PATH
    local d name port mail status
    for d in "$WORKTREE_ROOT"/*/; do
        [[ -f "$d/.env" ]] || continue
        name="$(basename "$d")"; port="$(env_get "$d" WP_PORT)"; mail="$(env_get "$d" MAIL_PORT)"
        code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/" 2>/dev/null || true)"
        case "$code" in 200|30*) status=up;; *) status=down;; esac
        printf '%-28s %-6s %-6s %-8s %s\n' "$name" "$port" "$mail" "$status" ".worktrees/$name"
    done
}

ensure_worktree() {
    local branch="$1" path; path="$(worktree_path "$branch")"
    if [[ -d "$path" ]]; then
        worktree_registered "$path" || die "$path exists but is not a registered worktree"
        [[ "$(branch_of "$path")" == "$branch" ]] || die "$path is on '$(branch_of "$path")', not '$branch'"
        return
    fi
    mkdir -p "$WORKTREE_ROOT"
    git -C "$REPO_ROOT" fetch --quiet origin >&2 || true
    if git -C "$REPO_ROOT" show-ref --verify --quiet "refs/heads/$branch"; then
        git -C "$REPO_ROOT" worktree add "$path" "$branch" >&2
    elif git -C "$REPO_ROOT" show-ref --verify --quiet "refs/remotes/origin/$branch"; then
        git -C "$REPO_ROOT" worktree add --track -b "$branch" "$path" "origin/$branch" >&2
    else
        local base=main
        git -C "$REPO_ROOT" show-ref --verify --quiet refs/heads/main || base=origin/main
        git -C "$REPO_ROOT" worktree add -b "$branch" "$path" "$base" >&2
    fi
}

write_env() {
    local branch="$1" port="$2" wt="$3"
    # Start from the branch's own .env.example so new keys arrive with the code,
    # then pin what makes this stack distinct.
    [[ -f "$wt/.env.example" ]] || die "$wt has no .env.example — this branch predates the dev scripts; merge main into it first"
    grep -vE '^(COMPOSE_PROJECT_NAME|WP_PORT|MAIL_PORT|WP_URL)=' "$wt/.env.example" > "$wt/.env"
    cat >> "$wt/.env" <<ENV
COMPOSE_PROJECT_NAME=$(project_name "$branch")
WP_PORT=$port
MAIL_PORT=$((port + MAIL_OFFSET))
WP_URL=http://localhost:$port
ENV
}

BRANCH=""; PORT=""; SEED=1
while [[ $# -gt 0 ]]; do
    case "$1" in
    --port)      PORT="$2"; shift 2 ;;
    --no-seed)   SEED=0; shift ;;
    --list)      list_stacks; exit 0 ;;
    -h|--help)   usage; exit 0 ;;
    -*)          die "unknown option: $1" ;;
    *)           [[ -z "$BRANCH" ]] || die "unexpected argument: $1"; BRANCH="$1"; shift ;;
    esac
done
[[ -n "$BRANCH" ]] || { usage; exit 1; }

is_protected "$BRANCH" && die "refusing to build a throwaway stack for protected branch '$BRANCH'"
[[ "$PORT" != "$MAIN_PORT" ]] || die "port $MAIN_PORT is the main dev stack"
require_docker

ensure_worktree "$BRANCH"
WT="$(worktree_path "$BRANCH")"
info "worktree: $WT"

[[ -x "$WT/scripts/setup.sh" ]] || die "$WT/scripts/setup.sh is missing — this branch predates the dev scripts; merge main into it first"

if [[ -f "$WT/.env" && -z "$PORT" ]]; then
    PORT="$(env_get "$WT" WP_PORT)"
    info "reusing recorded port $PORT"
else
    [[ -n "$PORT" ]] || PORT="$(pick_port)"
    write_env "$BRANCH" "$PORT" "$WT"
fi
[[ "$PORT" != "$MAIN_PORT" ]] || die "refusing port $MAIN_PORT"

info "starting stack $(project_name "$BRANCH") on port $PORT"
( cd "$WT" && SKIP_SEED=$(( 1 - SEED )) scripts/setup.sh )

cat <<EOT

  branch    $BRANCH
  url       http://localhost:$PORT
  admin     http://localhost:$PORT/wp-admin  ($(env_get "$WT" WP_ADMIN_USER) / $(env_get "$WT" WP_ADMIN_PASS))
  mailpit   http://localhost:$((PORT + MAIL_OFFSET))
  edit in   $WT
  tunnel    ssh -p 22022 -L $PORT:127.0.0.1:$PORT -L $((PORT + MAIL_OFFSET)):127.0.0.1:$((PORT + MAIL_OFFSET)) dev@178.104.78.114
  rebuild   $WT_DIR/wt-build.sh $BRANCH
  push      $WT_DIR/wt-push.sh $BRANCH -m "<message>"
  merge     $WT_DIR/wt-merge.sh $BRANCH
  destroy   $WT_DIR/wt-drop.sh $BRANCH

EOT

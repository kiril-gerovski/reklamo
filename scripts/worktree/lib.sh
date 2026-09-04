#!/usr/bin/env bash
# Shared by wt-*.sh. Single repo, so a "worktree" is one directory under
# .worktrees/<branch> carrying its own docker compose project (own volumes,
# own ports) — the main checkout on :8080 is never touched.

WT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$WT_DIR/../.." && pwd)"
WORKTREE_ROOT="$REPO_ROOT/.worktrees"

PROTECTED_BRANCHES=(main master)
MAIN_PORT=8080          # the main dev stack; refused for worktrees
PORT_RANGE_START=8081
PORT_RANGE_END=8099
MAIL_OFFSET=1000        # Mailpit UI = WP port + 1000 (8081 -> 9081)

die()  { echo "ERROR: $*" >&2; exit 1; }
info() { echo "==> $*"; }
slug() { echo "$1" | tr '/' '-'; }

worktree_path() { echo "$WORKTREE_ROOT/$(slug "$1")"; }
project_name()  { echo "wt-$(slug "$1")"; }

is_protected() {
    local p
    for p in "${PROTECTED_BRANCHES[@]}"; do [[ "$1" == "$p" ]] && return 0; done
    return 1
}

require_docker() {
    command -v docker >/dev/null || die "docker is not installed"
    docker info >/dev/null 2>&1 || die "cannot reach the docker daemon as $(id -un); is the 'docker' group active in this shell?"
    docker compose version >/dev/null 2>&1 || die "the docker compose plugin is missing"
}

# Read KEY from a worktree's .env (no sourcing — values may contain anything).
env_get() { sed -n "s/^$2=//p" "$1/.env" 2>/dev/null | head -n1; }

port_in_use() { (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; }

port_claimed() {
    local f
    for f in "$WORKTREE_ROOT"/*/.env; do
        [[ -f "$f" ]] || continue
        grep -x "WP_PORT=$1" "$f" >/dev/null && return 0
    done
    return 1
}

pick_port() {
    local port
    for ((port = PORT_RANGE_START; port <= PORT_RANGE_END; port++)); do
        port_in_use "$port" && continue
        port_in_use "$((port + MAIL_OFFSET))" && continue
        port_claimed "$port" && continue
        echo "$port"; return 0
    done
    die "no free port between $PORT_RANGE_START and $PORT_RANGE_END"
}

# docker compose scoped to a worktree: its compose file, its .env, its relative paths.
compose() {
    local wt; wt="$(worktree_path "$1")"; shift
    [[ -f "$wt/.env" ]] || die "no stack for this branch (missing $wt/.env); run $WT_DIR/wt-new.sh first"
    docker compose --project-directory "$wt" --env-file "$wt/.env" -f "$wt/docker-compose.yml" "$@"
}

stack_running() {
    local wt; wt="$(worktree_path "$1")"
    [[ -f "$wt/.env" ]] || return 1
    compose "$1" ps --status running --services 2>/dev/null | grep -x wp >/dev/null
}

wait_for_http() {
    local url="$1" i code
    info "waiting for $url"
    for i in $(seq 1 60); do
        code="$(curl -s -o /dev/null -w '%{http_code}' "$url" || true)"
        case "$code" in 200|30*) return 0;; esac
        sleep 2
    done
    die "$url did not come up"
}

confirm() {
    local prompt="$1" assume_yes="$2" answer
    if [[ "$assume_yes" == "1" ]]; then echo "Proceeding (--yes)."; return 0; fi
    [[ -t 0 ]] || die "stdin is not a TTY, so the confirmation prompt cannot be answered.
Re-run interactively, or pass --yes to skip the prompt."
    read -rp "$prompt" answer
    [[ "$answer" == "yes" ]] || die "aborted"
}

worktree_registered() { git -C "$REPO_ROOT" worktree list --porcelain | grep -x "worktree $1" >/dev/null; }
branch_of()           { git -C "$1" rev-parse --abbrev-ref HEAD 2>/dev/null; }
assert_clean() {
    [[ -z "$(git -C "$1" status --porcelain)" ]] || die "$2 has uncommitted changes:
$(git -C "$1" status --short)"
}

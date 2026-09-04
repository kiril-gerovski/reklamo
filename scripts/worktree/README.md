# Worktree stacks

Per-branch throwaway environments. `wt-new.sh <branch>` creates a git worktree at
`.worktrees/<branch>` and brings up a **second, fully isolated** WordPress + WooCommerce
stack from it — own Compose project (`wt-<branch>`), own database volume, own WordPress
core, own Mailpit — on the first free port from 8081 (Mailpit on port + 1000). The main
stack on :8080 keeps serving the main checkout and is never touched.

| Script | Does | Touches git? |
|---|---|---|
| `wt-new.sh <branch> [--port N] [--no-seed]` | worktree + stack up (runs the worktree's `scripts/setup.sh`) | creates branch/worktree |
| `wt-new.sh --list` | table of stacks | no |
| `wt-build.sh <branch> [--seed] [--lint] [--reset] [--no-restart]` | restart + cache flush; re-seed; lint; or rebuild from zero | no |
| `wt-push.sh <branch> -m <msg> [--dry-run]` | commit everything in the worktree, push the branch | **the only script that commits/pushes** |
| `wt-merge.sh <branch> [--into main]` | checks + PHPCS, then `git merge --no-ff --no-commit` in the main checkout | stages a merge, never commits |
| `wt-drop.sh <branch> [--yes] [--force]` | `down -v`, remove worktree + branch | deletes branch |

The `/wt-new`, `/wt-build`, `/wt-push`, `/wt-merge`, `/wt-drop` Claude Code skills in
`.claude/skills/` are thin wrappers around these. The repo root is the workspace root, so
they are discovered directly — nothing to install.

## Why it works

`docker-compose.yml` reads `COMPOSE_PROJECT_NAME`, `WP_PORT`, `MAIL_PORT` and `WP_URL`
from `.env`; the volumes are relative paths. `wt-new.sh` writes a `.env` into the
worktree with a distinct project name and ports, and every `scripts/*.sh` run **from the
worktree directory** therefore operates on that stack. The WP-CLI helper container is
named per project (`wt-<branch>-cli`), so stacks never share one.

## Limits

- **RAM.** The VM has ~7.6 GB and the ERP stack takes most of it. One worktree stack
  beside the main one is realistic; two is not.
- **The branch must contain `scripts/`.** A worktree runs *its own* `scripts/setup.sh`.
  A branch cut before the dev scripts existed is refused with a clear message — merge
  `main` into it first.
- **Uncommitted work in the main checkout is not in the worktree.** New branches start
  from local `main`'s last commit.

## Browsing a worktree stack from your machine

```bash
ssh -p 22022 -L 8081:127.0.0.1:8081 -L 9081:127.0.0.1:9081 dev@178.104.78.114
```
(`wt-new.sh` prints the exact line for the port it picked.)

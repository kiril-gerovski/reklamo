---
name: wt-build
description: Apply code/config changes to a running worktree stack in place - restart, flush caches, optionally re-seed or lint. Use instead of wt-new when the stack already exists and only the code changed.
---

# wt-build

Run, forwarding the user's arguments verbatim:

```
scripts/worktree/wt-build.sh $ARGUMENTS
```

`$ARGUMENTS` is `<branch> [--seed] [--lint] [--reset] [--no-restart]`.

Use this, not `/wt-new`, whenever a stack for the branch is already up. It never
touches worktrees, branches or (without `--reset`) the database.

PHP in the theme and plugin is bind-mounted, so most edits are already live. Pick flags
by what changed:

- **PHP / templates / CSS only** — no flags (restart + cache flush is enough)
- **`scripts/seed.sh` or anything that is configuration-as-code** — `--seed`
- **want PHPCS first** — `--lint`
- **`docker-compose.yml`, `config/php/uploads.ini`, `.env`** — no flags; the restart
  picks them up (compose changes may need `docker compose up -d` in the worktree)

`--reset` is different in kind and **destructive**: it drops the database (every test
order) and WordPress core and rebuilds from the worktree's scripts. Use it only when the
user asks for a clean stack. Pass `--yes` alongside it; the typed-`yes` prompt cannot be
answered from a non-interactive shell, and never pipe `yes` into the script.

If it reports the stack is not running, the fix is `/wt-new <branch>`, not this command.

Report the URL back and say what you applied.

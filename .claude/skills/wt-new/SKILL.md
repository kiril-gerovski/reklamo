---
name: wt-new
description: Create a git worktree for a branch and bring up an isolated WordPress + WooCommerce stack in Docker on its own port, leaving the main stack on :8080 untouched. Use when the user wants to develop or test a branch separately.
---

# wt-new

Run, forwarding the user's arguments verbatim:

```
scripts/worktree/wt-new.sh $ARGUMENTS
```

`$ARGUMENTS` is `<branch> [--port N] [--no-seed]`.
With no arguments, run `wt-new.sh --list` and show the table instead.

This takes a few minutes on a cold stack (WordPress install, WooCommerce download,
seed). Do not interrupt it or run it in the background.

The branch starts from local `main`. Uncommitted work in the main checkout is not in
the worktree — if the user expects it there, say so before running; do **not** commit
anything to get it there.

Report back:

- the URL and port, Mailpit URL (port + 1000), and the admin login from the summary
- the worktree path under `.worktrees/`, so the user knows where to edit
- the `ssh -L` tunnel line for the new ports (the user browses from another machine)
- if it failed, the failing step and the relevant lines from `wt-new.sh --list` plus
  `docker compose --project-directory .worktrees/<branch> logs wp`

Do not seed, reset or otherwise touch the main stack (`:8080`) as part of this.

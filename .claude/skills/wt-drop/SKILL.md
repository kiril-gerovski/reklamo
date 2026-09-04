---
name: wt-drop
description: Destroy a worktree stack - containers, volumes, database, the worktree and its branch. Use when a branch is merged and its throwaway environment is no longer needed.
---

# wt-drop

Run, forwarding the user's arguments verbatim:

```
scripts/worktree/wt-drop.sh $ARGUMENTS
```

`$ARGUMENTS` is `<branch> [--into <target>] [--keep-worktree] [--force]`.

This is destructive and irreversible: `docker compose down -v` drops the database and
uploaded files, then the worktree directory and the branch are removed.

The script prompts for a typed `yes`, which cannot be answered from a non-interactive
shell. Pass `--yes` to skip it — the user invoking `/wt-drop` with a branch name IS the
confirmation. Never pipe `yes` into the script; use the flag.

Every other guard still applies with `--yes`: protected branches are refused, an unmerged
branch is refused without `--force`, and a dirty worktree is refused by git. If any of
those fire, report it and stop — do not work around it.

Never pass `--force` on your own judgement. It discards commits; only pass it if the user
asked for it in this turn.

Report back what was actually destroyed, and confirm with:

```
docker volume ls | grep wt-
git worktree list
```

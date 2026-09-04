---
name: wt-merge
description: Merge a worktree branch into main (or another branch) in the main checkout, leaving the merge staged for the user to commit. Use when a branch developed via wt-new is ready to fold back.
---

# wt-merge

Run, forwarding the user's arguments verbatim:

```
scripts/worktree/wt-merge.sh $ARGUMENTS
```

`$ARGUMENTS` is `<branch> [--into <target>] [--skip-tests]`. Target defaults to `main`.

**Never run `git commit` yourself, and never `git push`.** The script deliberately stops
at `git merge --no-ff --no-commit`. Committing is the user's job, without exception.

Report back:

- that the merge is staged, and the exact `git merge --continue` / `--abort` lines the
  script printed
- whether `scripts/seed.sh` changed (the script says so) — then the main stack needs
  `scripts/seed.sh` after the commit, otherwise only a cache flush
- on failure: whether it stopped at a dirty tree, the main checkout on the wrong branch,
  a conflict, or a failing lint — and quote the conflicting paths or PHPCS output

If the script refuses because the main checkout is on the wrong branch, tell the user the
`git switch` command; do not run it — it swaps the code `:8080` is serving.

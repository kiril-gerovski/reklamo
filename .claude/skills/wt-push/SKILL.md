---
name: wt-push
description: Commit and push a worktree branch to origin. Use when the user wants work on a wt-new branch published, e.g. before opening a PR.
---

# wt-push

Run, forwarding the user's arguments verbatim:

```
scripts/worktree/wt-push.sh $ARGUMENTS
```

`$ARGUMENTS` is `<branch> -m <message> [--no-verify] [--dry-run]`.

**This is the one sanctioned way for you to commit and push in this repo.** The rule
everywhere else is absolute: never run `git commit` or `git push` on your own judgement,
never on `main`, never in the main checkout. Invoking this skill with a branch name is the
user's authorization, scoped to that worktree branch — the script refuses protected
branches and never touches the main checkout on `:8080`.

`-m` is required whenever the worktree has uncommitted changes. Do not invent a message
to get past that error: if the user did not give one, ask.

Use `--dry-run` first if the user is unsure what is about to be committed; it prints the
file list and changes nothing.

Report back:

- whether it committed, only pushed (already committed, ahead of origin), or had
  nothing to do
- the short SHA now on `origin/<branch>`
- on failure: whether it stopped at a missing worktree, a worktree on the wrong branch,
  a protected branch, a failing commit hook, or a rejected push — and quote the git output

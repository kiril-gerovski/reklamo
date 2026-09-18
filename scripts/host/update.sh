#!/usr/bin/env bash
# Ship a new version to the hosting account. Runs ON the server from ~/reklamo.
#   scripts/host/update.sh              fast-forward the deployed branch
#   scripts/host/update.sh v1.4.0       deploy a tag (detached checkout; pass a branch to leave it)
#   scripts/host/update.sh --seed       also run scripts/seed.sh (off by default: the owner
#                                       edits settings in the dashboard, the seed would enforce ours)
# Theme and plugin are symlinked, so the checkout IS the deploy. WordPress/WooCommerce
# follow versions.env (database backed up first). Maintenance mode covers the switch.
set -euo pipefail
cd "$(dirname "$0")/../.."
. scripts/host/common.sh

ref="" seed=0
for a in "$@"; do
  case "$a" in --seed) seed=1;; -*) die "unknown option $a";; *) ref=$a;; esac
done

[ -f .env ] || die "no .env — run scripts/host/install.sh first"
load_env
[ "${REKLAMO_ENV:-}" = production ] || die "REKLAMO_ENV must be production in the server .env"
ensure_wp_cli
. scripts/lib.sh

[ -z "$(git status --porcelain --untracked-files=no)" ] || die "the checkout has local changes — production is deploy-only:
$(git status --short --untracked-files=no)"

say "fetching"
git fetch -q --tags --prune origin
old=$(git rev-parse HEAD)
if [ -z "$ref" ]; then
  branch=$(git symbolic-ref --short -q HEAD) || die "a tag is deployed ($(git describe --tags --always)) — name the tag or branch to move to"
  target="origin/$branch"
elif git show-ref -q --verify "refs/tags/$ref"; then
  target="refs/tags/$ref"; branch=""
elif git show-ref -q --verify "refs/remotes/origin/$ref"; then
  target="origin/$ref"; branch=$ref
else
  die "unknown tag or branch: $ref"
fi
new=$(git rev-parse "$target^{commit}")
if [ "$old" = "$new" ]; then
  ok "already at $(git describe --tags --always) — re-applying configuration only"
else
  say "changes $(git rev-parse --short "$old") → $(git rev-parse --short "$new")"
  git --no-pager log --oneline --no-decorate "$old..$new" | sed 's/^/  /'
fi

wp maintenance-mode activate --quiet
trap 'wp maintenance-mode deactivate --quiet' EXIT

if [ -z "$branch" ]; then
  git checkout -q --detach "$target"
else
  git checkout -q "$branch"
  git merge -q --ff-only "$target"
fi
set -a; . ./versions.env; set +a

say "WordPress / WooCommerce pins"
converge_versions

if [ "$seed" = 1 ]; then
  say "site configuration (scripts/seed.sh)"
  scripts/seed.sh
else
  ok "seed skipped (pass --seed to apply scripts/seed.sh)"
fi

say "flushing"
wp cache flush --quiet || true
wp rewrite flush --hard --quiet || true

wp maintenance-mode deactivate --quiet
trap - EXIT

echo
scripts/host/check.sh || true
echo
echo "✔ deployed $(git describe --tags --always)"

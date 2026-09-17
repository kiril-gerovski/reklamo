#!/usr/bin/env bash
# First contact with a fresh SuperHosting account: put the repo on the server and hand
# over to scripts/host/install.sh. Standalone on purpose — nothing else exists yet.
#   bash bootstrap.sh <repo-url> [dir] [ref]
# scripts/deploy.sh install copies this file over and runs it for you.
set -euo pipefail
repo=${1:?usage: bootstrap.sh <repo-url> [dir] [ref]}
dir=${2:-reklamo}
ref=${3:-main}
cd "$HOME"

die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
command -v git >/dev/null || die "git is not available in this shell — is this a plan with SSH (СуперПро / СуперХостинг)?"

if [[ $repo == git@github.com:* || $repo == ssh://git@github.com* ]]; then
  mkdir -p .ssh && chmod 700 .ssh
  # Outbound port 22 is blocked on SuperHosting shared servers; GitHub also listens on 443.
  if ! grep -qs '^Host github.com' .ssh/config; then
    printf 'Host github.com\n  Hostname ssh.github.com\n  Port 443\n  User git\n  IdentityFile ~/.ssh/reklamo_deploy\n  IdentitiesOnly yes\n' >> .ssh/config
    chmod 600 .ssh/config
  fi
  if [ ! -f .ssh/reklamo_deploy ]; then
    ssh-keygen -q -t ed25519 -N '' -C "reklamo-deploy@$(hostname)" -f .ssh/reklamo_deploy
    echo
    echo "Add this key as a READ-ONLY deploy key on the GitHub repository (Settings → Deploy keys):"
    echo
    cat .ssh/reklamo_deploy.pub
    echo
    read -r -p "Press Enter once it is added… " _
  fi
  # GitHub answers the auth test with exit 1 even on success, so judge the text, not the status.
  out=$(ssh -T -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20 git@github.com 2>&1 || true)
  grep -q 'successfully authenticated' <<<"$out" \
    || die "GitHub did not accept ~/.ssh/reklamo_deploy.pub — add it as a deploy key and re-run. GitHub said: $out"
fi

if [ -d "$dir/.git" ]; then
  echo "→ $dir exists, fetching"
  git -C "$dir" fetch --tags --prune origin
  git -C "$dir" checkout -q "$ref"
  git -C "$dir" symbolic-ref -q HEAD >/dev/null && git -C "$dir" merge -q --ff-only "origin/$ref"
else
  echo "→ cloning $repo ($ref) into ~/$dir"
  git clone -q --branch "$ref" "$repo" "$dir"
fi

exec "$dir/scripts/host/install.sh"

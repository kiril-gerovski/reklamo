#!/usr/bin/env bash
# The master test: destroy everything and rebuild from the repo alone.
# If the site comes back complete, nothing is trapped in local state.
set -euo pipefail
cd "$(dirname "$0")/.."
echo "→ tearing down containers, database volume, WordPress core, private files"
# down FIRST, then remove: deleting a bind-mounted dir under a running container leaves it
# holding the old inode, and every later chown/mkdir on the host is invisible to it.
docker compose --profile cli down -v --remove-orphans
rm -rf wp private
exec scripts/setup.sh

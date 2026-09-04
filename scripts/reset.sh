#!/usr/bin/env bash
# The master test: destroy everything and rebuild from the repo alone.
# If the site comes back complete, nothing is trapped in local state.
set -euo pipefail
cd "$(dirname "$0")/.."
echo "→ tearing down containers, database volume, WordPress core, private files"
docker compose --profile cli down -v --remove-orphans
rm -rf wp private
exec scripts/setup.sh

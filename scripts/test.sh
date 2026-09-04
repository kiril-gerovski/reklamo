#!/usr/bin/env bash
# PHPUnit for the pure-PHP parts of the plugin, in a container (no PHP on the host).
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -x vendor/bin/phpunit ]; then
  echo "→ installing dev tooling"
  docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 install --no-interaction --quiet
fi
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit "$@"

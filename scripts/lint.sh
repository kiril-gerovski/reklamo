#!/usr/bin/env bash
# PHPCS + WordPress Coding Standards, without PHP on the host.
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -d vendor ]; then
  echo "→ installing dev tooling"
  docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 install --no-interaction --quiet
fi
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpcs "$@"

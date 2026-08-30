#!/usr/bin/env bash
# Runs Composer through the pinned official image, per rules/php.md's "no PHP
# interpreter on the machine" guidance — bind-mounts the repo and runs as the
# host UID/GID so vendor/ and composer.lock aren't left root-owned.
#
# Uses `docker run`'s own argv passthrough (not a "sh -c \"composer $*\""
# string), so an argument like `--ignore "src/Generated/*"` reaches Composer
# with its quoting intact instead of being re-split and glob-expanded by an
# intermediate shell.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
docker run --rm -u "$(id -u):$(id -g)" \
  -e COMPOSER_HOME=/tmp/composer-home -e HOME=/tmp \
  -e GIT_CONFIG_COUNT=1 \
  -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/app \
  -v "$(pwd):/app" -w /app \
  --entrypoint composer \
  composer:2.9.5 "$@"

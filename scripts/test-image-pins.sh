#!/usr/bin/env sh
# Proves each image the scripts run is pinned once, in docker/<name>/Dockerfile
# where Dependabot's docker ecosystem reads it, and that the scripts take their
# reference from there instead of carrying a second copy.
set -eu

cd "$(dirname "$0")/.."

fail=0
for name in composer phpdoc; do
  ref=$(./scripts/image.sh "$name")
  if ! printf '%s' "$ref" | grep -Eq '^[a-z0-9./_-]+:[A-Za-z0-9._-]+@sha256:[0-9a-f]{64}$'; then
    echo "test-image-pins: $name resolved to '$ref', not image:tag@sha256:digest" >&2
    fail=1
  fi
done

if grep -n 'sha256:' scripts/composer.sh scripts/docs.sh >&2; then
  echo "test-image-pins: a script carries its own pin" >&2
  fail=1
fi

exit "$fail"

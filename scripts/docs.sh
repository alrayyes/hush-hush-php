#!/usr/bin/env bash
# Builds the API reference into site/ with phpDocumentor's official image.
# It isn't a Composer dependency: phpdocumentor/phpdocumentor requires
# league/flysystem ^1, which has no fix for PKSA-w9tt-7782-78jx and which
# roave/security-advisories refuses to install (#295).
#
# The image tag and digest are pinned in docker/phpdoc/Dockerfile, where
# Dependabot's docker ecosystem raises the bump.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

PHPDOC_IMAGE=$(./scripts/image.sh phpdoc)

docker run --rm --network none -u "$(id -u):$(id -g)" \
  -v "$(pwd):/data" \
  "$PHPDOC_IMAGE" run "$@"

#!/usr/bin/env bash
# Builds the API reference into site/ with phpDocumentor's official image.
# It isn't a Composer dependency: phpdocumentor/phpdocumentor requires
# league/flysystem ^1, which has no fix for PKSA-w9tt-7782-78jx and which
# roave/security-advisories refuses to install (#295).
#
# The image tag and digest are pinned inline, so Dependabot can't see them:
# bump both by hand when phpDocumentor releases.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

PHPDOC_IMAGE="phpdoc/phpdoc:3.10.0@sha256:312ebf61ed88a6ea79aac768e43c9e9af0dd5bf3e710ed6d40ab8e53bfeeb121"

docker run --rm --network none -u "$(id -u):$(id -g)" \
  -v "$(pwd):/data" \
  "$PHPDOC_IMAGE" run "$@"

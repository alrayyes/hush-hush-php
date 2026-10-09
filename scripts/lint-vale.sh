#!/usr/bin/env sh
# Style: house voice, weasel words, corporate speak, the cliches proselint
# knows. Advice, not a gate - Vale only fails on error-severity alerts
# (MinAlertLevel in .vale.ini), which is why this script's own exit code is
# the real signal and nothing here downgrades it.
set -eu

# The official image, pinned by tag and digest so a moved tag can't change the run
# unnoticed. The comment is what Renovate reads to bump both together.
IMAGE=jdkato/vale:v3.17.1@sha256:7dba3c9104ba366f172d119022c4ec53a005f7d14dc1b80e285421a3f0b71657 # renovate: datasource=docker depName=jdkato/vale

SET="README.md CONTRIBUTING.md CLAUDE.md SECURITY.md"

cd "$(dirname "$0")/.."

# With file arguments (the pre-commit hook passes the staged ones) lint only
# those that are in the set above, and fetch nothing: the style packages must
# already be in styles/, from an earlier run of this script with no arguments
# (pre-push does that).
if [ "$#" -gt 0 ]; then
  files=""
  for f in "$@"; do
    case " $SET " in *" $f "*) files="$files $f" ;; esac
  done
  [ -n "$files" ] || exit 0
  sync=""
else
  files="$SET"
  sync="vale sync && "
fi

if command -v vale >/dev/null 2>&1; then
  [ -z "$sync" ] || vale sync
  # shellcheck disable=SC2086
  vale $files
else
  docker run --rm -v "$PWD:/work" -w /work --entrypoint sh "$IMAGE" \
    -c "${sync}vale $files"
fi

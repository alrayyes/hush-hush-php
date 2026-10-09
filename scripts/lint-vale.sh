#!/usr/bin/env sh
# Style: house voice, weasel words, corporate speak, the cliches proselint
# knows. Advice, not a gate - Vale only fails on error-severity alerts
# (MinAlertLevel in .vale.ini), which is why this script's own exit code is
# the real signal and nothing here downgrades it.
#
# No arguments: sync the style packages and lint the whole set (pre-push, CI).
# With file arguments: lint just those and fetch nothing (pre-commit, so a
# commit judges only what it contains). The packages must already be synced,
# which any earlier no-argument run does.
set -eu

VERSION=v3.17.1
IMAGE="jdkato/vale:$VERSION"

cd "$(dirname "$0")/.."

if [ "$#" -eq 0 ]; then
  sync="vale sync && "
  files="README.md CONTRIBUTING.md CLAUDE.md SECURITY.md"
else
  sync=""
  files="$*"
  if [ ! -d styles/Google ]; then
    echo "Vale styles aren't synced yet: run ./scripts/lint-vale.sh once." >&2
    exit 1
  fi
fi

if command -v vale >/dev/null 2>&1; then
  # shellcheck disable=SC2086
  eval "$sync" vale $files
else
  docker run --rm -v "$PWD:/work" -w /work --entrypoint sh "$IMAGE" \
    -c "${sync}vale $files"
fi

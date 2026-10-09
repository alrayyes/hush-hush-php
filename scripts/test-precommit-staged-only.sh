#!/usr/bin/env sh
# Proves pre-commit judges only what the commit contains: a dirty, deliberately
# broken file that isn't staged must neither fail the hook nor be rewritten.
# Needs a clean tree and synced Vale styles (./scripts/lint-vale.sh does both
# the sync and a full lint). Restores everything it touches.
set -eu

cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
  echo "test-precommit-staged-only: tree isn't clean, refusing to touch it" >&2
  exit 1
fi

dirty=SECURITY.md
staged=CONTRIBUTING.md

cleanup() {
  git reset -q HEAD -- "$staged" "$dirty" 2>/dev/null || true
  git checkout -q -- "$staged" "$dirty"
}
trap cleanup EXIT

# Unstaged and broken three ways: Prettier would rewrite it, markdownlint and
# Vale would flag it. If any pre-commit job read it, the hook would fail or
# the bytes would change.
printf '\n#bad heading   \n\n\n* item\n- item  \nThis is very utterly really bad.\n' >>"$dirty"
before=$(sha256sum "$dirty")

# A valid change to a different file, staged.
printf '\nHook test.\n' >>"$staged"
git add "$staged"

lefthook run pre-commit

after=$(sha256sum "$dirty")
if [ "$before" != "$after" ]; then
  echo "FAIL: pre-commit rewrote the unstaged $dirty" >&2
  exit 1
fi
echo "PASS: pre-commit ignored the unstaged $dirty"

#!/usr/bin/env sh
# pre-commit judges only what the commit contains. Dirties the tree with
# broken files, stages a different clean one, runs the pre-commit hook and
# checks it passed and left the dirty files byte for byte alone. Then checks
# lefthook.yml for a pre-commit command that scans the whole tree or fetches
# over the network.
set -eu

cd "$(dirname "$0")/.."

log=$(mktemp)
cleanup() {
  git rm -q --cached -f staged-ok.md 2>/dev/null || true
  git checkout -q -- README.md
  rm -f staged-ok.md dirty.md "$log"
}
trap cleanup EXIT

# Unstaged and broken: a modified tracked file and an untracked one.
printf '\n#bad heading\n\n\n\n* item\n- item\n' >>README.md
printf '#   Not formatted\n*   x\n' >dirty.md
before=$(cksum README.md dirty.md)

# Staged and clean.
printf '# Ok\n\nA clean file.\n' >staged-ok.md
git add staged-ok.md

if ! bunx lefthook run pre-commit >"$log" 2>&1; then
  cat "$log"
  echo "FAIL: pre-commit failed because of an unstaged file"
  exit 1
fi
if [ "$before" != "$(cksum README.md dirty.md)" ]; then
  echo "FAIL: pre-commit modified an unstaged file"
  exit 1
fi

# Every pre-commit command takes the staged files (or runs on the one file its
# glob names) and none fetches anything.
section=$(awk '/^pre-commit:/{p=1;next} /^[a-z]/{p=0} p' lefthook.yml)
if printf '%s\n' "$section" | grep -E '^\s+run:' | grep -vE '\{staged_files\}|composer.sh normalize'; then
  echo "FAIL: pre-commit command above doesn't take {staged_files}"
  exit 1
fi
if printf '%s\n' "$section" | grep -E '^\s+run:' | grep -E 'go run|vale sync|curl'; then
  echo "FAIL: pre-commit command above fetches over the network"
  exit 1
fi
echo "ok: pre-commit judged only the staged file"

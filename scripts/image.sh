#!/usr/bin/env sh
# Prints the image reference pinned in docker/<name>/Dockerfile. Those one-line
# Dockerfiles exist so Dependabot's docker ecosystem can track the pins; the
# scripts that run the images read them back from here, so a pin lives once.
set -eu

cd "$(dirname "$0")/.."

sed -n 's/^FROM //p' "docker/$1/Dockerfile"

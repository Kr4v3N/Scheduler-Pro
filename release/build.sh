#!/usr/bin/env bash
set -euo pipefail

# Build a WordPress-installable release zip for Scheduler Pro, plus its
# sha256 checksum, from the current working copy.
#
# Usage: release/build.sh [version]
# Defaults to the version declared in scheduler-pro.php's plugin header.
# Only ships production files: scheduler-pro.php, uninstall.php,
# readme.md, includes/, assets/, languages/. Never ships tests/, docs/,
# .gitignore, CLAUDE.md, todo.md, or anything else local to this working
# copy — a previous manual build had leaked .gitignore into the zip;
# building from an explicit file list rather than the whole directory
# fixes that.

cd "$(dirname "$0")/.."
ROOT="$(pwd)"

VERSION="${1:-$(grep -oP '(?<=Version:)\s*\K[0-9]+(\.[0-9]+)*' scheduler-pro.php)}"
SLUG="scheduler-pro"
WORKDIR="$(mktemp -d)"
STAGE="$WORKDIR/$SLUG"
OUT="$ROOT/release/${SLUG}-${VERSION}.zip"

mkdir -p "$STAGE"
cp "$ROOT/scheduler-pro.php" "$ROOT/uninstall.php" "$ROOT/readme.md" "$STAGE/"
cp -r "$ROOT/includes" "$ROOT/assets" "$ROOT/languages" "$STAGE/"

rm -f "$OUT" "$OUT.sha256"
( cd "$WORKDIR" && zip -rq "$OUT" "$SLUG" )
( cd "$ROOT/release" && sha256sum "$(basename "$OUT")" > "$(basename "$OUT").sha256" )

rm -rf "$WORKDIR"
echo "Built $OUT"

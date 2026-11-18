#!/bin/sh
# ModInspect release over git on the HostAtom account (run on the host, over SSH, as the account user).
# Usage:   sh hostatom_release.sh release-2026-11-18        (a git tag of https://github.com/thuliam/modinspect)
# Rollback: sh hostatom_release.sh --rollback               (points `current` at the previous release folder)
#
# Layout (base is the account root from docs/DEPLOYMENT_RUNBOOK_HOSTATOM.md):
#   $BASE/releases/<tag>/   a fresh clone of the tag (code is read-only to the web user)
#   $BASE/shared/.env       created BY THE OWNER, never by this script, never committed
#   $BASE/shared/storage/   logs, cache, sessions, import (outside the document root, survives releases)
#   $BASE/current           symlink to the live release; the web document root is $BASE/current/public
# The script never reads, prints or writes the contents of .env, and never touches the database.
set -eu

BASE="${MODINSPECT_BASE:-/home/thuliam/domains/thuliamdev.com/modinspect}"
REPO="https://github.com/thuliam/modinspect.git"

if [ "${1:-}" = "--rollback" ]; then
    [ -L "$BASE/previous" ] || { echo "No previous release recorded." >&2; exit 1; }
    cur="$(readlink "$BASE/current")"; prev="$(readlink "$BASE/previous")"
    ln -sfn "$prev" "$BASE/current.new" && mv -T "$BASE/current.new" "$BASE/current"
    ln -sfn "$cur" "$BASE/previous"
    echo "Rolled back: current -> $prev (was $cur). Re-run the smoke checks."
    exit 0
fi

TAG="${1:?usage: hostatom_release.sh <git-tag> | --rollback}"
case "$TAG" in *[!A-Za-z0-9._-]*) echo "Bad tag name." >&2; exit 2 ;; esac
[ -f "$BASE/shared/.env" ] || { echo "Missing $BASE/shared/.env (the owner creates it from .env.example)." >&2; exit 1; }
mkdir -p "$BASE/releases" "$BASE/shared/storage"
DEST="$BASE/releases/$TAG"
[ ! -e "$DEST" ] || { echo "Release $TAG already exists." >&2; exit 1; }

git clone --quiet --depth 1 --branch "$TAG" "$REPO" "$DEST"
echo "Cloned $TAG at $(git -C "$DEST" rev-parse --short HEAD)"

# not for the public host: product photos with unconfirmed rights (runbook section 7), tests, design drafts, docs, ci, git data
rm -rf "$DEST/public/assets/products-mfr" "$DEST/tests" "$DEST/design" "$DEST/docs" "$DEST/.git" "$DEST/.github"

# shared state outside the release
rm -rf "$DEST/storage"
ln -s "$BASE/shared/storage" "$DEST/storage"
ln -s "$BASE/shared/.env" "$DEST/.env"

# the code must parse on the host's PHP before it goes live
find "$DEST/app" "$DEST/public" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
php -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);' || { echo "PHP is older than 8.1." >&2; exit 1; }

# cut-over: remember the old one, then switch atomically
if [ -L "$BASE/current" ]; then ln -sfn "$(readlink "$BASE/current")" "$BASE/previous"; fi
ln -sfn "$DEST" "$BASE/current.new" && mv -T "$BASE/current.new" "$BASE/current"
echo "Live: current -> $DEST. Now run the smoke checks (docs/DEPLOYMENT_RUNBOOK_HOSTATOM.md section 5)."

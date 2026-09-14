#!/usr/bin/env bash
#
# QR Menu Suite — production ZIP packaging script.
#
# Usage:
#   bash build/build-zip.sh [output-dir]
#
# Builds a deterministic production ZIP of the plugin using `git archive`
# on HEAD (so only committed, tracked files are ever included — untracked
# secrets, local configs, and build artifacts on disk can't leak in) and
# then strips dev/test/CI-only paths via a pathspec. Requires no build
# tools, no Docker, and does not modify the working tree or repository.
#
# Output: <output-dir>/qr-menu-suite-<version>.zip
# The zip's root folder is "qr-menu-suite/", ready to upload as a WP plugin.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

OUT_DIR="${1:-$REPO_ROOT/build/dist}"
SLUG="qr-menu-suite"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Error: not inside a git repository." >&2
    exit 1
fi

MAIN_FILE="qr-menu-suite.php"
if [[ ! -f "$MAIN_FILE" ]]; then
    echo "Error: $MAIN_FILE not found at repo root." >&2
    exit 1
fi

VERSION="$(grep -m1 -oP '(?<=Version:\s{)\S+' "$MAIN_FILE" 2>/dev/null || true)"
if [[ -z "$VERSION" ]]; then
    VERSION="$(grep -m1 -oP '^\s*\*\s*Version:\s*\K\S+' "$MAIN_FILE")"
fi
if [[ -z "$VERSION" ]]; then
    echo "Error: could not determine plugin version from $MAIN_FILE." >&2
    exit 1
fi

# Paths excluded from the production package. Anything not listed here
# (PHP, CSS, JS, images, templates, .mo/.po translations, module folders)
# ships as-is, unchanged.
EXCLUDES=(
    ':(exclude)tests'
    ':(exclude)tests/**'
    ':(exclude)security-audit'
    ':(exclude)security-audit/**'
    ':(exclude)scripts'
    ':(exclude)scripts/**'
    ':(exclude)build'
    ':(exclude)build/**'
    ':(exclude).github'
    ':(exclude).github/**'
    ':(exclude).git*'
    ':(exclude)**/.git*'
    ':(exclude)**/docker*'
    ':(exclude)**/Docker*'
    ':(exclude)**/.devcontainer'
    ':(exclude)**/.devcontainer/**'
    ':(exclude)**/docker-compose*.yml'
    ':(exclude)**/docker-compose*.yaml'
    ':(exclude)**/Dockerfile*'
    ':(exclude)**/*.env'
    ':(exclude)**/*.env.*'
    ':(exclude)**/.env'
    ':(exclude)**/.env.*'
    ':(exclude)**/*secret*'
    ':(exclude)**/*credential*'
    ':(exclude)**/*.log'
    ':(exclude)**/*.cache'
    ':(exclude)**/.DS_Store'
    ':(exclude)**/node_modules/**'
    ':(exclude)**/vendor/**'
)

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

STAGE_DIR="$WORK_DIR/$SLUG"
mkdir -p "$STAGE_DIR"

# Export only tracked, committed files (HEAD) minus the excludes above.
git archive HEAD -- . "${EXCLUDES[@]}" | tar -x -C "$STAGE_DIR"

mkdir -p "$OUT_DIR"
ZIP_PATH="$OUT_DIR/${SLUG}-${VERSION}.zip"
rm -f "$ZIP_PATH"

(
    cd "$WORK_DIR"
    find "$SLUG" -exec touch -h -d '2000-01-01 00:00:00' {} +
    zip -X -r -q "$ZIP_PATH" "$SLUG"
)

echo "Built: $ZIP_PATH"
echo "Version: $VERSION"
du -h "$ZIP_PATH" | cut -f1 | xargs -I{} echo "Size: {}"

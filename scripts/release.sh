#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────────────
# Tekton Release Script
# Builds the Svelte admin UI and packages a WordPress-ready
# zip file into releases/tekton-{version}.zip
# ──────────────────────────────────────────────────────────

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VERSION=$(grep -oP "Version:\s*\K[0-9.]+" "$PLUGIN_DIR/tekton.php" | head -1)

if [ -z "$VERSION" ]; then
  echo "Error: Could not read version from tekton.php"
  exit 1
fi

RELEASE_NAME="tekton-${VERSION}"
TMP_DIR=$(mktemp -d)
BUILD_DIR="$TMP_DIR/tekton"

echo "Building Tekton v${VERSION}..."

# 1. Install dependencies and build
cd "$PLUGIN_DIR"
npm ci --silent
npm run build

# 2. Create temp build directory with only distributable files
mkdir -p "$BUILD_DIR"

# PHP plugin files
cp "$PLUGIN_DIR/tekton.php" "$BUILD_DIR/"
cp -r "$PLUGIN_DIR/includes" "$BUILD_DIR/"
cp -r "$PLUGIN_DIR/bridge-theme" "$BUILD_DIR/"
cp -r "$PLUGIN_DIR/assets" "$BUILD_DIR/"

# Built admin UI (dist only, no source)
mkdir -p "$BUILD_DIR/admin"
cp -r "$PLUGIN_DIR/admin/dist" "$BUILD_DIR/admin/dist"

# Component library and templates (if they exist)
[ -d "$PLUGIN_DIR/component-library" ] && cp -r "$PLUGIN_DIR/component-library" "$BUILD_DIR/"
[ -d "$PLUGIN_DIR/templates" ] && cp -r "$PLUGIN_DIR/templates" "$BUILD_DIR/"

# 3. Zip it
cd "$TMP_DIR"
mkdir -p "$PLUGIN_DIR/releases"
zip -qr "$PLUGIN_DIR/releases/${RELEASE_NAME}.zip" tekton/

# 4. Clean up
rm -rf "$TMP_DIR"

echo "Release created: releases/${RELEASE_NAME}.zip"
echo "Size: $(du -h "$PLUGIN_DIR/releases/${RELEASE_NAME}.zip" | cut -f1)"

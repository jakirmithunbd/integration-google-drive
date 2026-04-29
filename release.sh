#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# release.sh — bump version, commit, tag, and push to trigger GitHub release
#
# Usage:
#   bash release.sh 1.4.5
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

PLUGIN_FILE="integration-google-drive.php"
VERSION="${1:-}"

# ── Validate input ────────────────────────────────────────────────────────────
if [ -z "$VERSION" ]; then
  echo "Usage: bash release.sh <version>"
  echo "Example: bash release.sh 1.4.5"
  exit 1
fi

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "❌ Version must be in format X.Y.Z (e.g. 1.4.5)"
  exit 1
fi

TAG="v$VERSION"

# ── Check working tree is clean ───────────────────────────────────────────────
if [ -n "$(git status --porcelain)" ]; then
  echo "❌ Working tree is not clean. Commit or stash your changes first."
  git status --short
  exit 1
fi

# ── Check tag doesn't already exist ──────────────────────────────────────────
if git rev-parse "$TAG" >/dev/null 2>&1; then
  echo "❌ Tag $TAG already exists."
  exit 1
fi

# ── Bump version in plugin header ────────────────────────────────────────────
CURRENT=$(grep -m1 "^\s*\* Version:" "$PLUGIN_FILE" | awk '{print $NF}')
echo "Current version : $CURRENT"
echo "New version     : $VERSION"
echo ""

# sed replace the Version line in the plugin header (perl for macOS/Linux compat)
perl -i -pe "s/(\* Version:\s+)\Q${CURRENT}\E/\${1}${VERSION}/" "$PLUGIN_FILE"

# Verify the change was applied
UPDATED=$(grep -m1 "^\s*\* Version:" "$PLUGIN_FILE" | awk '{print $NF}')
if [ "$UPDATED" != "$VERSION" ]; then
  echo "❌ Failed to update version in $PLUGIN_FILE"
  exit 1
fi
echo "✅ Updated $PLUGIN_FILE  ($CURRENT → $VERSION)"

# ── Commit and tag ────────────────────────────────────────────────────────────
git add "$PLUGIN_FILE"
git commit -m "Release $TAG"
git tag "$TAG"

echo ""
echo "✅ Committed and tagged $TAG"
echo ""

# ── Push ──────────────────────────────────────────────────────────────────────
read -r -p "Push commit + tag to origin? [y/N] " confirm
if [[ "$confirm" =~ ^[Yy]$ ]]; then
  git push origin HEAD
  git push origin "$TAG"
  echo ""
  echo "🚀 Pushed! GitHub Actions will build and publish the release."
  echo "   Watch it at: https://github.com/jakirmithunbd/integration-google-drive/actions"
  echo "   Download at: https://github.com/jakirmithunbd/integration-google-drive/releases/latest/download/integration-google-drive.zip"
else
  echo ""
  echo "Skipped push. When ready, run:"
  echo "  git push origin HEAD && git push origin $TAG"
fi

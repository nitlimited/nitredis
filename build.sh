#!/usr/bin/env bash
# =============================================================================
#  NitRedis — Build & Release Script
#  Nusite I.T Consulting Limited
#
#  Usage:
#    ./build.sh                  # patch bump  1.0.0 → 1.0.1
#    ./build.sh minor            # minor bump  1.0.0 → 1.1.0
#    ./build.sh major            # major bump  1.0.0 → 2.0.0
#    ./build.sh 1.2.3            # exact version
#    ./build.sh --no-bump        # repackage current version, no git tag
#
#  What it does:
#    1. Reads the current version from nitredis.php
#    2. Calculates (or uses) the new version
#    3. Updates the version in nitredis.php, readme.txt, and the drop-in stub
#    4. Creates a clean zip (excludes dev files)
#    5. Commits the version bump and creates a git tag
#    6. Optionally pushes to GitHub (triggers update checker on live sites)
# =============================================================================

set -euo pipefail

# ── Config ────────────────────────────────────────────────────────────────────
PLUGIN_SLUG="nitredis"
PLUGIN_FILE="nitredis.php"
README_FILE="readme.txt"
DROPIN_STUB="includes/object-cache.php"
DIST_DIR="../dist"          # where the zip lands (outside plugin folder)
# ─────────────────────────────────────────────────────────────────────────────

# Colours
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()    { echo -e "${CYAN}▸${RESET} $*"; }
success() { echo -e "${GREEN}✔${RESET} $*"; }
warn()    { echo -e "${YELLOW}⚠${RESET} $*"; }
error()   { echo -e "${RED}✖${RESET} $*"; exit 1; }
header()  { echo -e "\n${BOLD}$*${RESET}"; }

# ── Ensure we're in the plugin root ──────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

[[ -f "$PLUGIN_FILE" ]] || error "Cannot find $PLUGIN_FILE — run this script from the plugin root."

# ── Read current version ──────────────────────────────────────────────────────
CURRENT_VERSION=$(grep "define( 'NITREDIS_VERSION'" "$PLUGIN_FILE" \
    | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")

[[ -n "$CURRENT_VERSION" ]] || error "Could not parse NITREDIS_VERSION from $PLUGIN_FILE"

header "NitRedis Build Script"
info "Current version: ${BOLD}$CURRENT_VERSION${RESET}"

# ── Calculate new version ─────────────────────────────────────────────────────
BUMP_TYPE="${1:-patch}"
NO_BUMP=false
EXACT_VERSION=""

if [[ "$BUMP_TYPE" == "--no-bump" ]]; then
    NO_BUMP=true
    NEW_VERSION="$CURRENT_VERSION"
elif [[ "$BUMP_TYPE" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    EXACT_VERSION="$BUMP_TYPE"
    NEW_VERSION="$EXACT_VERSION"
else
    IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
    case "$BUMP_TYPE" in
        major) MAJOR=$((MAJOR+1)); MINOR=0; PATCH=0 ;;
        minor) MINOR=$((MINOR+1)); PATCH=0 ;;
        patch) PATCH=$((PATCH+1)) ;;
        *)     error "Unknown bump type '$BUMP_TYPE'. Use: patch | minor | major | x.y.z | --no-bump" ;;
    esac
    NEW_VERSION="${MAJOR}.${MINOR}.${PATCH}"
fi

if [[ "$NO_BUMP" == false ]]; then
    info "New version:     ${BOLD}$NEW_VERSION${RESET} (${BUMP_TYPE} bump)"
else
    info "No version bump — repackaging ${BOLD}$NEW_VERSION${RESET}"
fi

# ── Confirm ───────────────────────────────────────────────────────────────────
if [[ "$NO_BUMP" == false ]]; then
    echo ""
    read -rp "  Proceed? (y/N) " CONFIRM
    [[ "$CONFIRM" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 0; }
fi

# ── Update version strings ────────────────────────────────────────────────────
if [[ "$NO_BUMP" == false && "$NEW_VERSION" != "$CURRENT_VERSION" ]]; then
    header "Updating version strings"

    # nitredis.php — plugin header
    sed -i.bak "s/ \* Version:     .*/ * Version:     $NEW_VERSION/" "$PLUGIN_FILE"
    # nitredis.php — constant
    sed -i.bak "s/define( 'NITREDIS_VERSION',   '.*' )/define( 'NITREDIS_VERSION',   '$NEW_VERSION' )/" "$PLUGIN_FILE"
    rm -f "${PLUGIN_FILE}.bak"
    success "Updated $PLUGIN_FILE"

    # readme.txt — Stable tag
    if [[ -f "$README_FILE" ]]; then
        sed -i.bak "s/^Stable tag: .*/Stable tag: $NEW_VERSION/" "$README_FILE"
        # Also update the changelog heading if present
        sed -i.bak "s/^= $CURRENT_VERSION =/= $CURRENT_VERSION =/" "$README_FILE"
        rm -f "${README_FILE}.bak"
        success "Updated $README_FILE"

        # Prepend new changelog entry
        CHANGELOG_ENTRY="\n= $NEW_VERSION =\n* Version bump to $NEW_VERSION.\n"
        if grep -q "== Changelog ==" "$README_FILE"; then
            sed -i.bak "/== Changelog ==/a\\
\\
= $NEW_VERSION =\\
* Version bump to $NEW_VERSION." "$README_FILE"
            rm -f "${README_FILE}.bak"
        fi
    fi

    # Drop-in stub — NITREDIS_DROP_IN_VERSION
    if [[ -f "$DROPIN_STUB" ]]; then
        sed -i.bak "s/define( 'NITREDIS_DROP_IN_VERSION', '.*' )/define( 'NITREDIS_DROP_IN_VERSION', '$NEW_VERSION' )/" "$DROPIN_STUB"
        rm -f "${DROPIN_STUB}.bak"
        success "Updated $DROPIN_STUB"
    fi
fi

# ── Build clean zip ───────────────────────────────────────────────────────────
header "Building zip"

mkdir -p "$DIST_DIR"
ZIP_NAME="${PLUGIN_SLUG}-${NEW_VERSION}.zip"
ZIP_PATH="${DIST_DIR}/${ZIP_NAME}"

# Remove any previous build of the same version.
rm -f "$ZIP_PATH"

# Build from parent directory so the zip contains nitredis/ folder at the root.
cd ..

zip -r "$ZIP_PATH" "$PLUGIN_SLUG/" \
    --exclude "*.git*" \
    --exclude "*/.DS_Store" \
    --exclude "*/Thumbs.db" \
    --exclude "*/__MACOSX*" \
    --exclude "*/node_modules/*" \
    --exclude "*/vendor/*/test*" \
    --exclude "*/vendor/*/Test*" \
    --exclude "*/.env*" \
    --exclude "*/build.sh" \
    --exclude "*/dist/*" \
    --exclude "*/*.log" \
    --exclude "*/*.bak" \
    > /dev/null

cd "$SCRIPT_DIR"

ZIP_SIZE=$(du -sh "$ZIP_PATH" | cut -f1)
success "Created ${BOLD}$ZIP_PATH${RESET} ($ZIP_SIZE)"

# ── Git commit + tag ──────────────────────────────────────────────────────────
if [[ "$NO_BUMP" == false ]]; then
    header "Git"

    if git rev-parse --git-dir > /dev/null 2>&1; then
        # Stage only the version-changed files.
        git add "$PLUGIN_FILE" "$README_FILE" "$DROPIN_STUB" 2>/dev/null || true

        if git diff --cached --quiet; then
            warn "Nothing to commit (version files unchanged)."
        else
            git commit -m "chore: bump version to $NEW_VERSION"
            success "Committed version bump"
        fi

        TAG_NAME="v${NEW_VERSION}"

        if git rev-parse "$TAG_NAME" > /dev/null 2>&1; then
            warn "Tag $TAG_NAME already exists — skipping tag creation."
        else
            git tag -a "$TAG_NAME" -m "Release $TAG_NAME"
            success "Created tag ${BOLD}$TAG_NAME${RESET}"
        fi

        # ── Push to GitHub ────────────────────────────────────────────────────
        echo ""
        read -rp "  Push commit + tag to GitHub? (y/N) " PUSH_CONFIRM
        if [[ "$PUSH_CONFIRM" =~ ^[Yy]$ ]]; then
            REMOTE=$(git remote | head -1)
            BRANCH=$(git rev-parse --abbrev-ref HEAD)
            git push "$REMOTE" "$BRANCH"
            git push "$REMOTE" "$TAG_NAME"
            success "Pushed ${BRANCH} and ${TAG_NAME} to ${REMOTE}"

            # ── Create GitHub Release + attach zip ────────────────────────────
            # Requires GitHub CLI (https://cli.github.com) — `gh auth login` first.
            echo ""
            if command -v gh &> /dev/null; then
                read -rp "  Create GitHub Release and attach zip via gh CLI? (y/N) " GH_CONFIRM
                if [[ "$GH_CONFIRM" =~ ^[Yy]$ ]]; then
                    # Extract release notes from readme.txt changelog section if present
                    RELEASE_NOTES=""
                    if [[ -f "$README_FILE" ]]; then
                        RELEASE_NOTES=$(awk "/^= ${NEW_VERSION} =/,/^= [0-9]/" "$README_FILE"                             | grep -v "^= " | sed '/^$/d' | head -20 | tr '
' ' ')
                    fi
                    [[ -z "$RELEASE_NOTES" ]] && RELEASE_NOTES="Release ${TAG_NAME}"

                    gh release create "$TAG_NAME"                         "$ZIP_PATH"                         --title "NitRedis ${TAG_NAME}"                         --notes "$RELEASE_NOTES"

                    success "GitHub Release created with zip attached"
                    echo ""
                    info "Live sites will see the update within 12 hours."
                else
                    warn "Skipped GitHub Release creation."
                    _print_manual_release_instructions
                fi
            else
                warn "GitHub CLI (gh) not found — create the release manually:"
                _print_manual_release_instructions
            fi
        else
            warn "Skipped push. Run manually:"
            echo "    git push origin \$(git rev-parse --abbrev-ref HEAD)"
            echo "    git push origin v${NEW_VERSION}"
        fi
    else
        warn "Not a git repository — skipping commit and tag."
        warn "Version files have been updated; commit them manually."
    fi
fi

# ── Helper: print manual release instructions ────────────────────────────────
_print_manual_release_instructions() {
    echo ""
    echo "  To publish the release manually:"
    echo "  1. Go to https://github.com/\$(git remote get-url origin | sed 's/.*github.com[:/]//' | sed 's/.git$//')/releases/new"
    echo "  2. Select tag: ${TAG_NAME:-vX.Y.Z}"
    echo "  3. Set title: NitRedis ${TAG_NAME:-vX.Y.Z}"
    echo "  4. Attach the zip: ${ZIP_PATH}"
    echo "  5. Publish the release"
    echo ""
    warn "IMPORTANT: Attach the zip as a release asset — do NOT rely on the"
    warn "auto-generated source zip. WordPress needs the nitredis/ folder at"
    warn "the zip root or the plugin installer will break."
}

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}${BOLD}✔ Build complete${RESET}"
echo -e "  Plugin:  ${BOLD}${PLUGIN_SLUG} v${NEW_VERSION}${RESET}"
echo -e "  Package: ${BOLD}${ZIP_PATH}${RESET}"
echo ""

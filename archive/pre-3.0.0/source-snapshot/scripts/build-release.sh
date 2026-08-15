#!/bin/sh
# Package the four coordinated components plus the Content Studio add-on (and
# a combined suite bundle) into installable release zips. Only files tracked in git are ever packaged
# (via `git archive`), so untracked build litter can never leak into a
# release by accident. See docs/release-checklist.md for the full process
# this script is step 3 of.
#
# Usage: sh scripts/build-release.sh

set -eu

ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
cd "$ROOT_DIR"

if ! git rev-parse --git-dir >/dev/null 2>&1; then
    echo "error: not a git repository" >&2
    exit 1
fi

# ----------------------------------------------------------------------------
# Version coordination check — refuse to package a suite that disagrees with
# itself. Mirrors docs/release-checklist.md section 2.
# ----------------------------------------------------------------------------
ver_wpistic_theme=$(grep -m1 '^Version:' themes/wpistic/style.css | sed 's/^Version: *//')
ver_bt_theme=$(grep -m1 '^Version:' themes/brother-tours/style.css | sed 's/^Version: *//')
ver_wpistic_const=$(grep -m1 "define( 'WPISTIC_VERSION'" themes/wpistic/functions.php | sed -E "s/.*'WPISTIC_VERSION', *'([^']+)'.*/\1/")
ver_formistic=$(grep -m1 '^ \* Version:' plugins/formistic/formistic.php | sed -E 's/^ \* Version: *//')
ver_tm=$(grep -m1 '^ \* Version:' plugins/wpistic-tour-manager/wpistic-tour-manager.php | sed -E 's/^ \* Version: *//')
ver_formistic_stable=$(grep -m1 '^Stable tag:' plugins/formistic/readme.txt | sed 's/^Stable tag: *//')
ver_tm_stable=$(grep -m1 '^Stable tag:' plugins/wpistic-tour-manager/readme.txt | sed 's/^Stable tag: *//')
ver_btcs=$(grep -m1 '^ \* Version:' plugins/brother-tours-content-studio/brother-tours-content-studio.php | sed -E 's/^ \* Version: *//')

versions="$ver_wpistic_theme $ver_bt_theme $ver_wpistic_const $ver_formistic $ver_tm $ver_formistic_stable $ver_tm_stable"
first=$ver_wpistic_theme
for v in $versions; do
    if [ "$v" != "$first" ]; then
        echo "error: version mismatch across components — refusing to package" >&2
        echo "  themes/wpistic/style.css:            $ver_wpistic_theme" >&2
        echo "  themes/brother-tours/style.css:       $ver_bt_theme" >&2
        echo "  themes/wpistic/functions.php:         $ver_wpistic_const" >&2
        echo "  plugins/formistic/formistic.php:      $ver_formistic" >&2
        echo "  plugins/wpistic-tour-manager/*.php:   $ver_tm" >&2
        echo "  plugins/formistic/readme.txt:         $ver_formistic_stable" >&2
        echo "  plugins/wpistic-tour-manager/readme.txt: $ver_tm_stable" >&2
        exit 1
    fi
done
VERSION=$first
echo "Packaging version $VERSION"

RELEASE_DIR="$ROOT_DIR/release"
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT

rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

# ----------------------------------------------------------------------------
# archive_subtree <tree-path> <root-folder-name> <dest-dir>
#
# Extracts the tracked contents of <tree-path> (a path within HEAD) into
# <dest-dir>/<root-folder-name>/, preserving only what git tracks.
# ----------------------------------------------------------------------------
archive_subtree() {
    tree_path=$1
    root_name=$2
    dest_dir=$3
    mkdir -p "$dest_dir"
    git archive --format=tar --prefix="$root_name/" "HEAD:$tree_path" | tar -x -C "$dest_dir"
}

# Development-only files that must never ship, even though they are tracked
# in git (kept in the repo for developers, not for the packaged product).
# Paths are relative to the root folder created by archive_subtree above.
prune_common() {
    pkg_root=$1
    find "$pkg_root" -iname '_preview*.html' -delete
    find "$pkg_root" -type d -iname 'tests' -exec rm -rf {} +
    find "$pkg_root" -iname '*.map' -delete
    find "$pkg_root" -iname '.DS_Store' -delete
}

zip_package() {
    src_dir=$1   # directory containing exactly the root folder
    root_name=$2
    zip_path=$3
    ( cd "$src_dir" && zip -rq -X "$zip_path" "$root_name" )
}

echo ""
echo "=== wpistic ($VERSION) ==="
WORK="$STAGE/wpistic-theme"
archive_subtree themes/wpistic wpistic "$WORK"
prune_common "$WORK/wpistic"
zip_package "$WORK" wpistic "$RELEASE_DIR/wpistic-$VERSION.zip"

echo "=== brother-tours ($VERSION) ==="
WORK="$STAGE/brother-tours-theme"
archive_subtree themes/brother-tours brother-tours "$WORK"
prune_common "$WORK/brother-tours"
zip_package "$WORK" brother-tours "$RELEASE_DIR/brother-tours-$VERSION.zip"

echo "=== formistic ($VERSION) ==="
WORK="$STAGE/formistic-plugin"
archive_subtree plugins/formistic formistic "$WORK"
prune_common "$WORK/formistic"
zip_package "$WORK" formistic "$RELEASE_DIR/formistic-$VERSION.zip"

echo "=== wpistic-tour-manager ($VERSION) ==="
WORK="$STAGE/wpistic-tour-manager-plugin"
archive_subtree plugins/wpistic-tour-manager wpistic-tour-manager "$WORK"
prune_common "$WORK/wpistic-tour-manager"
zip_package "$WORK" wpistic-tour-manager "$RELEASE_DIR/wpistic-tour-manager-$VERSION.zip"

echo "=== brother-tours-content-studio ($ver_btcs) ==="
WORK="$STAGE/brother-tours-content-studio-plugin"
archive_subtree plugins/brother-tours-content-studio brother-tours-content-studio "$WORK"
prune_common "$WORK/brother-tours-content-studio"
zip_package "$WORK" brother-tours-content-studio "$RELEASE_DIR/brother-tours-content-studio-$ver_btcs.zip"

echo "=== brother-tours-suite ($VERSION) — all four + docs ==="
SUITE="$STAGE/brother-tours-suite/brother-tours-suite"
mkdir -p "$SUITE/themes" "$SUITE/plugins"

archive_subtree themes/wpistic wpistic "$SUITE/themes"
archive_subtree themes/brother-tours brother-tours "$SUITE/themes"
archive_subtree plugins/formistic formistic "$SUITE/plugins"
archive_subtree plugins/wpistic-tour-manager wpistic-tour-manager "$SUITE/plugins"
archive_subtree plugins/brother-tours-content-studio brother-tours-content-studio "$SUITE/plugins"
archive_subtree docs docs "$SUITE"
# Historical build-log documents, kept in the repo for provenance but never
# part of a shipped release — see docs/release-checklist.md section 4.
rm -f "$SUITE/docs/implementation-plan.md" "$SUITE/docs/source-inventory.md"

cp README.md CHANGELOG.md "$SUITE/"

prune_common "$SUITE"
zip_package "$STAGE/brother-tours-suite" brother-tours-suite "$RELEASE_DIR/brother-tours-suite-$VERSION.zip"

echo ""
echo "=== checksums ==="
( cd "$RELEASE_DIR" && sha256sum -- *.zip > checksums.sha256 )
cat "$RELEASE_DIR/checksums.sha256"

echo ""
echo "=== verifying checksums ==="
( cd "$RELEASE_DIR" && sha256sum -c checksums.sha256 )

echo ""
echo "=== verifying root-folder shape ==="
for pair in "wpistic:wpistic" "brother-tours:brother-tours" "formistic:formistic" "wpistic-tour-manager:wpistic-tour-manager" "brother-tours-content-studio:brother-tours-content-studio" "brother-tours-suite:brother-tours-suite"; do
    base=${pair%%:*}
    root=${pair##*:}
    first_entry=$(unzip -Z1 "$RELEASE_DIR/$base-$VERSION.zip" | head -1)
    if [ "$first_entry" != "$root/" ]; then
        echo "error: $base-$VERSION.zip root entry is '$first_entry', expected '$root/'" >&2
        exit 1
    fi
    echo "OK: $base-$VERSION.zip -> $root/"
done

echo ""
echo "Release packages written to $RELEASE_DIR/"
ls -la "$RELEASE_DIR"

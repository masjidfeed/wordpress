#!/usr/bin/env bash
#
# Packages the masjid-app plugin into a distributable zip in dist/.
#
# Usage: scripts/package.sh
#
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_slug="masjid-app"
plugin_dir="$root_dir/wp-content/plugins/$plugin_slug"

cd "$root_dir"

version="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$plugin_dir/$plugin_slug.php" | head -n1 | tr -d '[:space:]')"
if [ -z "$version" ]; then
    echo "Error: unable to detect plugin version from $plugin_slug.php." >&2
    exit 1
fi

if [ ! -f "$plugin_dir/vendor-prefixed/autoload.php" ]; then
    echo "vendor-prefixed/ is missing. Building scoped dependencies..."
    composer install --no-interaction
    php scripts/build-release.php
fi

dist_dir="$root_dir/dist"
mkdir -p "$dist_dir"
zip_path="$dist_dir/$plugin_slug-$version.zip"

staging_dir="$(mktemp -d "${TMPDIR:-/tmp}/$plugin_slug.XXXXXX")"
trap 'rm -rf "$staging_dir"' EXIT

rsync -a --delete-excluded \
    --exclude-from="$plugin_dir/.distignore" \
    --exclude='.DS_Store' \
    "$plugin_dir/" "$staging_dir/$plugin_slug/"

rm -f "$zip_path"
(cd "$staging_dir" && zip -rq "$zip_path" "$plugin_slug")

echo "Created $zip_path"

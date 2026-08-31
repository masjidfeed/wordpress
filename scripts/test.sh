#!/usr/bin/env bash
#
# Runs the PHPUnit test suite for the masjid-app plugin.
#
# Usage: scripts/test.sh [phpunit arguments...]
#
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_dir="$root_dir/wp-content/plugins/masjid-app"

cd "$root_dir"

if [ ! -f "$plugin_dir/vendor-prefixed/autoload.php" ]; then
    echo "vendor-prefixed/ is missing. Building scoped dependencies..."
    composer install --no-interaction
    php scripts/build-release.php
fi

if [ -x "$root_dir/vendor/bin/phpunit" ]; then
    phpunit_bin="$root_dir/vendor/bin/phpunit"
elif command -v phpunit >/dev/null 2>&1; then
    phpunit_bin="phpunit"
else
    echo "Error: phpunit not found. Install it or add it to vendor/bin via composer." >&2
    exit 1
fi

echo "Running PHPUnit tests..."
exec "$phpunit_bin" "$@"

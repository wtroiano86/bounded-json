#!/bin/sh
set -eu

package_root="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
cd "$package_root"
mkdir -p tmp
distribution_dir="$(mktemp -d "$package_root/tmp/distribution.XXXXXX")"
trap 'rm -rf -- "$distribution_dir"' EXIT

composer archive --format=zip --file=bounded-json --dir="$distribution_dir" --no-interaction
php .github/scripts/prepare-consumer.php "$distribution_dir"
composer install --working-dir="$distribution_dir/consumer" --no-dev --no-interaction --prefer-dist --no-progress
composer check-platform-reqs --working-dir="$distribution_dir/consumer" --no-dev
php .github/scripts/verify-consumer.php "$distribution_dir/consumer"
php .github/scripts/verify-consumer.php "$distribution_dir/consumer" cached

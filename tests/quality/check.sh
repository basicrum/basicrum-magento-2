#!/usr/bin/env sh
set -eu
module_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
cd "$module_root"
quality_vendor="$module_root/tests/quality/vendor"
# Start in the isolated tool project so PHPStan never picks a partial root
# autoloader produced by the separate production-classmap check.
cd "$module_root/tests/quality"
php "$quality_vendor/bin/phpstan" analyse --configuration="$module_root/phpstan.neon" \
    --autoload-file="$quality_vendor/autoload.php" --no-progress --memory-limit=1G
cd "$module_root"
php "$quality_vendor/bin/phpcs" --runtime-set installed_paths \
    "$quality_vendor/magento/magento-coding-standard,$quality_vendor/magento/php-compatibility-fork,$quality_vendor/phpcsstandards/phpcsutils"

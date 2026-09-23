#!/usr/bin/env sh
set -eu
test "${BASICRUM_DISPOSABLE_MAGENTO:-}" = 1
test "${MAGENTO_ROOT:-}" = /var/www/html
module_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)
artifact="$module_root/.test-results/package/basicrum-analytics.zip"
php "$module_root/tests/integration/check-artifact.php" "$artifact"
cd "$MAGENTO_ROOT"
# Exact destination inside this disposable stack. Do not delete stale files:
# candidate verification below rejects them instead of concealing a bad upgrade.
mkdir -p app/code/Basicrum/Analytics
unzip -qo "$artifact" -d app/code/Basicrum/Analytics
php "$module_root/tests/integration/check-installed-candidate.php"
php bin/magento module:enable Basicrum_Analytics

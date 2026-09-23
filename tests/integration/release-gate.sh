#!/usr/bin/env sh
set -eu

if [ "${BASICRUM_DISPOSABLE_MAGENTO:-}" != "1" ]; then
    echo "Set BASICRUM_DISPOSABLE_MAGENTO=1 only for a disposable Magento installation." >&2
    exit 1
fi

if [ "${BASICRUM_RELEASE_TAG:-}" != "0.1.0" ] && [ "${BASICRUM_RELEASE_TAG:-}" != "v0.1.0" ]; then
    echo "BASICRUM_RELEASE_TAG must be the new Phase 1 tag 0.1.0 (or v0.1.0); 0.0.2 must not be reused." >&2
    exit 1
fi

if [ -z "${MAGENTO_ROOT:-}" ] || [ ! -x "${MAGENTO_ROOT}/bin/magento" ]; then
    echo "MAGENTO_ROOT must point to a disposable Magento installation." >&2
    exit 1
fi
MAGENTO_ROOT=$(CDPATH= cd -- "$MAGENTO_ROOT" && pwd)
export MAGENTO_ROOT

if [ -z "${MAGENTO_STOREFRONT_URL:-}" ]; then
    echo "MAGENTO_STOREFRONT_URL is required." >&2
    exit 1
fi

if [ -z "${MAGENTO_ADMIN_URL:-}" ]; then
    echo "MAGENTO_ADMIN_URL must be the disposable installation's Admin login URL." >&2
    exit 1
fi

if [ -z "${MAGENTO_ADMIN_USERNAME:-}" ] || [ -z "${MAGENTO_ADMIN_PASSWORD:-}" ]; then
    echo "Disposable MAGENTO_ADMIN_USERNAME and MAGENTO_ADMIN_PASSWORD values are required." >&2
    exit 1
fi

module_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
magento="${MAGENTO_ROOT}/bin/magento"
candidate_commit=$(sh "$module_root/tests/integration/check-candidate.sh")
echo "Checking Basicrum candidate ${candidate_commit} for ${BASICRUM_RELEASE_TAG}."
artifact=${BASICRUM_ARTIFACT:-$module_root/.test-results/package/basicrum-magento-2.zip}
php "$module_root/tests/integration/check-artifact.php" "$artifact"
php "$module_root/tests/integration/check-installed-candidate.php"

cd "$MAGENTO_ROOT"
php "$module_root/tests/integration/check-baseline.php"
sh "$module_root/tests/integration/install-csp-fixture.sh"
"$magento" setup:upgrade
"$magento" setup:di:compile
"$magento" setup:static-content:deploy -f en_US
php "$module_root/tests/integration/config-save.php"

cd "$module_root"
BASICRUM_DEPLOY_STATIC=0 tests/integration/configure-disposable.sh

# Do not certify a checkout or installed copy that changed while the gate ran.
final_commit=$(sh "$module_root/tests/integration/check-candidate.sh")
if [ "$final_commit" != "$candidate_commit" ]; then
    echo "The candidate commit changed during the release gate; rerun it." >&2
    exit 1
fi
php "$module_root/tests/integration/check-installed-candidate.php"
php "$module_root/tests/integration/check-artifact.php" "$artifact"
echo "PASS: release gate for ${BASICRUM_RELEASE_TAG}, candidate commit ${candidate_commit}."

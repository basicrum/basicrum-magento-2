#!/usr/bin/env sh
set -eu

if [ "${BASICRUM_DISPOSABLE_MAGENTO:-}" != "1" ]; then
    echo "Set BASICRUM_DISPOSABLE_MAGENTO=1 only for a disposable Magento installation." >&2
    exit 1
fi

if [ -z "${MAGENTO_ROOT:-}" ] || [ ! -x "${MAGENTO_ROOT}/bin/magento" ]; then
    echo "MAGENTO_ROOT must point to a disposable Magento installation." >&2
    exit 1
fi

if [ -z "${MAGENTO_STOREFRONT_URL:-}" ]; then
    echo "MAGENTO_STOREFRONT_URL is required." >&2
    exit 1
fi

module_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
cd "$module_root"

magento="${MAGENTO_ROOT}/bin/magento"

if ! enabled_modules=$("$magento" module:status --enabled); then
    echo "Unable to read enabled Magento modules from ${MAGENTO_ROOT}." >&2
    exit 1
fi

if ! printf '%s\n' "$enabled_modules" | grep -Fxq 'Basicrum_Analytics'; then
    echo "Basicrum_Analytics is not registered and enabled in ${MAGENTO_ROOT}." >&2
    echo "Install this checkout at app/code/Basicrum/Analytics with exact casing and run setup:upgrade." >&2
    exit 1
fi

# Fail before configuration writes instead of allowing npx to fetch an unpinned runner.
if [ ! -x "$module_root/node_modules/.bin/playwright" ]; then
    echo "Pinned Playwright is missing. Run npm ci in the module checkout first." >&2
    exit 1
fi

"$magento" config:set basicrum/general/enabled 1
"$magento" config:set basicrum/general/beacon_endpoint https://collector.basicrum.test/beacon
"$magento" config:set basicrum/general/brum_site_id 550e8400-e29b-41d4-a716-446655440000
"$magento" config:set basicrum/consent/enabled 1
"$magento" config:set basicrum/privacy/strip_query_string 1
"$magento" config:set basicrum/performance/wait_after_onload 0
"$magento" config:set basicrum/performance/delay_ms 0
"$magento" config:set basicrum/developer/development_mode 0
"$magento" cache:clean config layout block_html full_page

if [ "${BASICRUM_DEPLOY_STATIC:-0}" = "1" ]; then
    "$magento" setup:static-content:deploy -f en_US
fi

MAGENTO_STOREFRONT_URL="$MAGENTO_STOREFRONT_URL" \
MAGENTO_BEACON_URL=https://collector.basicrum.test/beacon \
MAGENTO_SITE_ID=550e8400-e29b-41d4-a716-446655440000 \
    "$module_root/node_modules/.bin/playwright" test --config=playwright.integration.config.js

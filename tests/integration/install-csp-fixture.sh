#!/usr/bin/env sh
set -eu

test "${BASICRUM_DISPOSABLE_MAGENTO:-}" = 1 || {
    echo 'CSP fixture installation requires BASICRUM_DISPOSABLE_MAGENTO=1.' >&2
    exit 1
}
test -n "${MAGENTO_ROOT:-}" && test -x "$MAGENTO_ROOT/bin/magento" || {
    echo 'MAGENTO_ROOT must point to a disposable Magento installation.' >&2
    exit 1
}

module_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
MAGENTO_ROOT=$(CDPATH= cd -- "$MAGENTO_ROOT" && pwd)
source="$module_root/tests/integration/fixtures/Basicrum/CspTest"
target="$MAGENTO_ROOT/app/code/Basicrum/CspTest"
mkdir -p "$target"
cp -R "$source/." "$target/"
# Do not certify a fixture containing stale files, or silently delete them.
diff -qr "$source" "$target" || {
    echo 'CSP fixture differs from the source; inspect and remove stale fixture files before retrying.' >&2
    exit 1
}
"$MAGENTO_ROOT/bin/magento" module:enable Basicrum_CspTest
echo 'CSP test fixture installed. Run setup:upgrade and setup:di:compile before browser tests.'

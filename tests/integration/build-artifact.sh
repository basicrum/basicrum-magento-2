#!/usr/bin/env sh
set -eu
module_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
cd "$module_root"
mkdir -p .test-results/package
candidate_commit=$(sh tests/integration/check-candidate.sh)
git archive --format=zip --output=.test-results/package/basicrum-magento-2.zip "$candidate_commit"
php tests/integration/check-artifact.php .test-results/package/basicrum-magento-2.zip

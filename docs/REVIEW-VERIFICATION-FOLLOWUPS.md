# Joint-review verification follow-ups

Implemented locally on 2026-09-22 after the Codex, Opus 5.5 Max, and focused
Grok review. These notes are for the central parity task; the shared ledger
and reference plugins were not changed.

## Changes

- **R-009: candidate identity.** The release gate requires a clean committed
  module checkout, resolves Magento's registered module path, and hashes both
  trees' package files. Stale, missing, extra, or internally symlinked source
  fails before Magento writes. The gate repeats the identity checks afterward
  and records the successful candidate SHA/tag in its output. Development/output
  exclusions and copied-install requirements are documented in the integration
  README. This is source verification, not an installable-artifact certification.
- **R-007/R-008: actual cached-page evidence.** The storefront test warms the
  anonymous cookie/vary context, requires a native FPC `HIT`, verifies fresh
  consent and cookie/beacon silence on that response, then checks the cached
  page's beacon identity and redaction. Missing/MISS/UNCACHEABLE responses fail.
- **R-008/R-009: reproducible native runner and CI discovery.** The setup harness
  requires the installed Playwright executable before configuration writes.
  CI loads/discovers the integration tests without claiming native execution.
- **R-005/R-009: environment-specific HTTP exception.** Magento's `TypePool`
  marks only `basicrum/developer/development_mode` as environment-specific for
  configuration export. Defaults, scopes, stored settings, and runtime policy
  are unchanged. Previously exported files are not silently rewritten.

## Verification actually run

- PHP 8.2, 8.3, and 8.4: **18 groups passed on each**, including mismatched
  package files, missing local runner, and release-guard mutation ordering.
- `CI=1 npm test`: **43 checks passed without retries**, including the actual
  readable/minified loaders, real Boomerang, negative cache-evidence assertions,
  and the clean-candidate guard exercised against a temporary real Git repository.
  Minification checks passed.
- Integration `--list`: **22 tests discovered**. This checks loading only.
- PHP/PHTML lint on PHP 8.3, shell syntax, and `git diff --check`: passed.
- Magento 2.4.9 native DI XSD validation passed. Its actual `TypePool` accepted
  the environment classification read from the candidate XML. This was an
  isolated native-class check, not an `app:config:dump` or installed merged-DI run.
- The actual candidate checker resolved the local installation's registered
  module and **correctly rejected its different package copy**. Differences
  included top-level metadata and `etc/di.xml`; PHP and browser assets matched.
  The clean-checkout guard also correctly rejected this uncommitted worktree.
- Targeted native storefront run: **1 passed, 1 failed**. Enforcing empty-cart
  checkout CSP passed. The homepage test reached and passed the real FPC HIT,
  fresh-consent silence, cookie cleanup, and redacted identity assertions, but
  failed at teardown because the network guard blocked existing Braintree
  scripts. The test remains red; no external-host exception was added.

## Not run and remaining boundaries

The full native gate on Magento 2.4.7-p10 / PHP 8.3 was not run: the pinned
installation is unavailable, and the candidate was uncommitted during verification. The
current local Magento 2.4.9 / PHP 8.5.6 store is supplemental only and was not
resynced. The new native save-suite assertion for the installed `TypePool`,
configuration export, DI compilation/static deployment for this revision,
full native browser suite, Composer checks, and remote GitHub Actions were not
rerun. The local native browser guard still requires removal of external
payment scripts in the disposable installation before it can pass.

Automatic cache invalidation (CR-D-002), cross-store/Varnish compatibility, and
installable release-artifact verification remain outside this follow-up. No
store settings were changed, no order was created, and no live beacon was
forwarded. No release or remote deployment was performed.

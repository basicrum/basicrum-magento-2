# Reliability and maintainability follow-ups

Implemented locally on 2026-09-22. These notes supplement the original Phase 1
snapshot; they do not claim release certification.

## Reliability findings

1. **Fail-closed browser interception (R-008).** Every native browser suite uses
   a shared context-level guard, including Admin and popups. Expected beacons
   are fulfilled locally; unexpected destinations and misplaced measurement
   payloads fail the test. Service workers and WebSockets are blocked. A small
   Node built-in proxy restricts transport to the disposable storefront/Admin,
   including redirect chains that bypass Playwright's normal routing. Five
   loopback regression tests cover interception, popups, redirects, WebSockets,
   and denied HTTPS tunnels. No production dependency was added.
2. **Native configuration-save coverage (R-001/R-002/R-005/R-008).** Ten checks
   use Magento's actual Admin save model and scoped configuration, not doubles:
   defaults, backend rejection/normalization, same-form HTTP decisions, bounded
   wait, website/store overrides and re-inheritance, consent, missing identity,
   and invalid endpoint/identity imports. Each case rolls back its database
   writes; the original Basicrum rows are verified afterward. Config caching is
   disabled only in the test process. Third-party observer/cache side effects
   are not transactionally reversible, so a disposable installation is required.
3. **Enforced release baseline (R-009).** The release script checks the installed
   Magento edition/patch, PHP, Composer, MariaDB, and OpenSearch before upgrade
   or configuration writes. `baseline.env` remains the declared baseline, with
   no override flag. Fast tests check each version mismatch and execute the
   real shell gate with a failing baseline command to verify mutation ordering.

## Worth simplifying

1. **Remove obsolete consent modes (R-001/R-006/R-010).** Removed the selector,
   source model, legacy options/notices, and unused runtime metadata. The
   consent-required switch and public manual callbacks are unchanged. Existing
   `basicrum/consent/mode` rows remain untouched but are no longer read. No old
   string can grant consent, and no compatibility shim or data migration was added.
2. **One configuration decision (R-002/R-005).** Admin status and storefront
   eligibility now share `Config::getStatus()`. Endpoint HTTPS normalization is
   shared between save and runtime. Removed unused parallel defaults; Magento
   XML owns install defaults, and the existing constant owns Boomerang version.
   Normalization still supplies fail-closed behavior for invalid runtime input.
3. **Test behavior, not incidental source spelling (R-008/R-009).** Removed
   repository-wide branding scans and assertions about exact shell snippets,
   plus unused test doubles. Kept targeted package metadata, provenance,
   behavioral shell-guard, native-rendering, and public-contract assertions.

## Verification actually run

- PHP 8.2, 8.3, and 8.4: 17 fast test groups passed on each; PHP/PHTML lint passed.
- `CI=1 npm test`: 41 Chromium tests passed without retries, including the real
  packaged readable/minified loaders, bundled Boomerang, rendered wait plugin,
  and network-guard regressions. Minification checks passed.
- Composer 2.10 strict validation and strict optimized production autoload:
  passed in an isolated container copy; 13 production classes, no test doubles.
- Local Magento Open Source 2.4.9 / PHP 8.5.6: DI compilation passed; all 10
  native save checks passed and the original Basicrum rows were restored.
  The existing legacy mode row was retained and the order count remained 3.
  This newer installation is supplemental, outside the declared PHP baseline.
- Final native browser run on that installation: **17 passed, 4 failed,
  1 skipped**. All four failures were the network guard blocking external
  Braintree `client.min.js` / `paypal-checkout.min.js` requests. Admin rendering
  (including the removed selector) and enforcing-checkout CSP passed. The full
  native browser suite is therefore **not passing**. Earlier runs also exposed
  those assets on other pages; timing changes how many tests observe them.
- The actual baseline checker rejected the local 2.4.9 installation before any
  release mutation, as required. Shell syntax and `git diff --check` passed.

## Remaining verification and boundaries

The local disposable store still loads external Braintree scripts. Disable
those integrations in the test installation and rerun the native browser
suite; no external-host exceptions were added and unrelated payment settings
were not changed by this work. No live collector request was forwarded.

The full release gate on Magento 2.4.7-p10 / PHP 8.3 has not run: that installation
is not available locally. Positive native baseline/service-version checks,
static deployment for this revision, and remote GitHub Actions are not reported
as passing. Existing CI automatically picks up the new fast tests; native checks
remain a separately required release gate. The opt-in order-creating checkout
journey was not rerun, and no order was created.

Automatic full-page-cache invalidation remains explicitly deferred as
CR-D-002. No loader lifecycle, page-type vocabulary, callback, active setting
path, namespace, license, or reference-plugin behavior was changed. WordPress,
Magento 1, and the shared parity ledger were left unchanged. These notes are
for the central parity task to incorporate. No release or deployment to a remote
environment was performed for these follow-ups.

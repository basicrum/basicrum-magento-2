# Opus review follow-ups — 2026-09-22

All six approved follow-ups are implemented locally. These notes supplement,
not replace, the earlier Phase 1 and page-type alignment execution records.

## Implemented

1. **Documentation (R-007/R-010):** replaced stale frontend-DI wording with
   global collector registration plus a frontend runtime guard; corrected the
   empty-address-book redirect/forward description and native layout header
   timing; added compiled-DI regeneration to installation/upgrade guidance.
2. **CSP regressions (R-007/R-008):** reject collector-array replacement in
   every area DI file. Native checks require an enforcing checkout response
   before following its empty-cart redirect, preserve core `'self'` and a
   known Magento PayPal whitelist source, and exclude Basicrum's collector
   origin from Admin CSP at default, website, and store scope.
3. **Rendered wait behavior (R-005/R-008):** global browser setup invokes PHP
   to render the production `footer.phtml` using the real Config, Footer, and
   page detector. Tests execute that inline output with both packaged consent
   loaders and the real Boomerang artifact. Controlled browser time verifies
   silence before the delay, completion afterward, timer cancellation on
   withdrawal, no same-page re-grant, and omission for default/off/zero settings.
   The copied JavaScript wait-plugin fixture is removed.
4. **Production autoload (R-009):** Composer excludes `/tests/` from classmap
   generation. CI runs strict optimized production autoload generation and
   checks that module classes remain present while test doubles are absent.
5. **Display-only Admin rows (R-002/R-008):** a shared renderer removes scope
   labels and inheritance/restore flags from Monitoring Status, Boomerang
   Version, and Manual Callback API. Native website/store assertions also
   prove actual configuration fields retain their inheritance checkboxes.
6. **Flaky privacy checks (R-009):** CI keeps one browser retry for diagnostics,
   but `failOnFlakyTests` makes a pass-on-retry fail the job. Native integration
   tests already run without retries.

No runtime consent contract, collection defaults, saved configuration paths,
page labels, bundled assets, or license declarations were changed by these
follow-ups. No configuration migration was introduced.

## Checks actually run

- Focused PHP harness: **16 groups passed on each of PHP 8.2, 8.3, and 8.4**.
  Module and test PHP/PHTML syntax checks passed on all three versions.
- `npm test`: minified-artifact verification and **36 Chromium tests passed**.
  `CI=1 npm test` also passed all 36 without a retry.
- Composer 2.10 in an isolated checkout copy: strict metadata validation and
  `dump-autoload --optimize --strict-psr --no-dev --no-scripts --no-plugins
  --no-interaction` succeeded. The generated map contained production classes
  and no test doubles. This check does not install Magento dependencies.
- Local Magento Open Source **2.4.9 from the Mage-OS mirror / PHP 8.5.6**:
  dependency-injection compilation and config/layout/block/full-page cache
  cleaning succeeded after updating the disposable module copy.
- Native Chromium integration: **21 passed, 1 deliberately skipped**. This
  included sample-data page/beacon checks, the new enforcing checkout response,
  storefront consent/cache behavior, and authenticated Admin CSP/rendering at
  default, website, and store scope. All measurement requests were intercepted.
  The skipped test is the separately opted-in order-creating checkout journey;
  the database order count stayed at 3 and saved Basicrum settings were unchanged.
- JavaScript and integration-shell syntax checks and `git diff --check` passed.

## Limits and remaining work

- GitHub Actions itself was not run. The commands were exercised locally;
  adding CI steps is not evidence of a remote workflow pass.
- The declared **2.4.7-p10 / PHP 8.3** native release gate was not run: the
  available installation is 2.4.9 / PHP 8.5.6. Supplemental local success does
  not widen Composer's PHP constraint or certify proprietary Adobe Commerce.
- The rendered-wait browser fixtures replace Magento's block/HTML renderer
  with test doubles, but never replace the production PHP template or its
  JavaScript. They are not a native Magento wait-enabled storefront run;
  the local store's wait settings remained off/zero. Native layout/CSP is
  independently exercised by the integration suite.
- Checkout CSP is checked on its enforcing empty-cart redirect. The complete
  order-creating journey was not rerun; its earlier successful run remains
  documented in `PAGE-TYPE-ALIGNMENT.md`.
- No fresh static deployment was needed because these follow-ups changed no
  static assets. Package installation/release publishing, authenticated customer
  journeys, automatic consent adapters, the optimizer matrix, and deferred
  full-page-cache invalidation analysis remain outside this follow-up.
- WordPress, Magento 1, and the central parity ledger were left unchanged.
  The review-ID mappings above are completion notes for the central task.

The existing branch work is preserved. Nothing was committed, pushed, deployed
outside the disposable local installation, or published by this follow-up.

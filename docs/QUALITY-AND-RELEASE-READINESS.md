# Quality and release-readiness follow-up — 2026-09-23

Implements approved items **1, 4 and 5**. No WordPress/Magento 1 files or shared
parity ledger entries were changed. No monitoring feature, consent policy,
configuration path, page label or Boomerang artifact was changed.

## Ideas adapted, not copied

- [JustBetter Sentry](https://github.com/justbetter/magento2-sentry/tree/31614cb1fc685279e16b03d0491408caa88025f6):
  Magento-aware level-8 PHPStan and lowest/stable dependency resolution. Basicrum
  uses real Magento component types, never its lightweight test doubles. There
  is no PHPStan baseline or blanket error suppression. Runtime configuration
  has an explicit shared PHPDoc array shape rather than a new DTO/service layer.
- [GENE New Relic RUM](https://github.com/genecommerce/module-newrelic-rum-page-type/tree/8c4903066a30b7fbb375fc8015044c9962dd3135):
  repeatable native Magento integration alongside standards/static analysis.
  Basicrum retains its stronger native scoped-save, consent, actual beacon,
  enforcing/Admin CSP, cache-HIT and rendered-Admin assertions.
- [Chessio Matomo](https://github.com/fnogatz/magento2-matomo/tree/3aaef162f129473c988d43154cf45567dd23609e):
  keep visitor-specific state out of cacheable HTML. A native two-context test
  checks a fresh cache entry populated by a request carrying measurement cookies,
  then another visitor's first HIT, which must remain silent until its own grant.

No competitor source code/assets were imported or relicensed. Boomerang's
existing artifact and BSD notices remain unchanged.

## Implementation and parity-review mapping

- **R-007/R-008/R-009 — evidence tied to deployed code:** an ephemeral nonce
  challenge binds the browser URL to the verified installation and checks FPM
  PHP. Actual script response bodies must hash to the candidate loader and
  Boomerang assets. Negative tests reject another root/PHP line, redirects,
  stale bytes, unexpected scripts and failed asset responses. The challenge is
  test-only and is removed even on failure.
- **R-009 — maintainability:** Magento-aware PHPStan level 8 and Magento coding
  standard 41, isolated locked tooling, PHP 8.2/8.3/8.4 lowest/stable CI plus a
  locked PHP 8.3 job. Static analysis exposed a concrete-model `getWebsite()`
  dependency; inheritance now uses the public `getWebsiteId()`/store-manager
  contract. Production shape/type annotations are precise. Standards exceptions
  are narrow and explained: pure Config validators, native URL parsing and two
  indivisible translation keys. No blanket PHPStan suppression was added.
- **R-009 — package/native gate:** matching Composer/Git export boundaries keep
  development files out. Every ZIP entry and installed production file is
  compared with committed-blob SHA-256 hashes before and after the gate. The ZIP is
  installed through Magento's documented manual `app/code` path, followed by
  upgrade, DI compilation, static deployment, native saves and browser checks.
  The disposable stack disables Braintree rather than allowing external scripts
  through the browser guard. CI uses the same harness.

## Initial iteration verification record

- Focused PHP: 18 groups pass on each of PHP 8.2, 8.3 and 8.4.
- Fast Chromium/real-Boomerang and helper tests: 45 pass; minified artifacts match.
- PHPStan level 8: passes on PHP 8.3 with both locked stable and lowest resolved
  real Magento components. Magento coding-standard scan: zero code violations.
  PHPCS itself reports upstream CSS/GraphQL sniff deprecations; these do not
  represent ignored module findings.
- Pinned native installation: version checks, upgrade, DI compilation, English
  static deployment, 10 native save groups, and 19 native browser tests pass.
  Four tests are intentionally skipped: three sample-data routes and the opt-in
  order-creating checkout. No order was created and no collector was contacted.
- Production ZIP built, manifest verified and installed in that native stack.
  Both Composer and Git archives pass the same production-file manifest check.
- The complete strict native gate passed against temporary clean test-snapshot
  commit `00908c24fe43e81cf2bf7cf961c1b0b1fa7e1ff3`, including final installed/ZIP
  rechecks. This commit exists only in an ignored local fixture repository, not
  on the working branch and not as a release tag. Tested ZIP SHA-256:
  `336ef485b29eaf3594f9caca50442eaa462cd91b1befe8b80f18da20dc46678d`.
  The log is retained locally at `.test-results/native-gate-final.log`.
- Strict Composer validation and optimized production autoload generation pass;
  the classmap contains 13 production classes and no test doubles. Generated
  verification directories are also excluded from production autoload discovery.
- Remote workflows were added but have **not** been run for this uncommitted work.
  The seven-cell remote component matrix is not being reported as locally run.

## Opus review follow-ups — 2026-09-23

All seven accepted suggestions are addressed without changing production runtime
behavior or the reference plugins/shared ledger:

1. **R-009, committed packaging:** build the ZIP with `git archive` from the clean
   candidate commit. Compute expected SHA-256 hashes from committed blobs, not
   local disk contents. Regression fixtures include both tracked-ignore and local
   Git excludes, dirty files, and four deliberately damaged ZIPs.
2. **R-007/R-008, cache isolation:** after an authoritative grant and a real
   measurement cookie, a second tab of the first visitor populates a unique URL
   and must receive MISS. Another visitor's first request to that URL must be HIT
   and remain silent until its own grant. No server consent cookie or persisted
   Basicrum decision is introduced.
3. **R-009, accurate dependency claims:** deliberately take the narrower wording
   option. Images and Magento versions are pinned, but no application dependency
   lock or bit-for-bit reproducibility is claimed.
4. **R-009, installed artifact boundary:** reject all extra installed files,
   including development directories and hidden files. Checkout-based development
   testing stays separate from strict distribution certification.
5. **R-009, restart:** bake only `/module` Git trust into the image's system config;
   document retained-volume `up -d` and rebuilding the image when needed.
6. **R-009, ownership:** run bind-mounted `npm ci` with the host caller's UID/GID,
   using a per-UID disposable cache rather than creating root-owned dependencies.
7. **R-009, tool compatibility:** identify locked/locally tested component versions
   below, document the locked PHP requirement, and print resolved versions in CI.

Follow-up checks actually run:

- PHP 8.2/8.3/8.4: 18 groups pass on each, including real Git commit/archive fixtures.
  CI now installs Git explicitly for these tests.
- Fast Chromium/helper suite: 45 pass, without retries; loader minification matches.
- Native storefront subset: 9 pass (three consecutive runs of three tests).
  The initial same-tab post-consent navigation produced a proxy-blocked beacon
  attempt during document unload. The final same-cookie-jar/new-tab scenario
  avoids that interception race without relaxing the guard or mocking Boomerang.
- Four ZIP corruption regressions pass and are included in the locked quality CI job.
- PHPStan level 8 and Magento coding standards pass with the locked PHP 8.3 tools;
  PHPCS emits its existing upstream sniff-deprecation notices.
- Full native gate passes: baseline checks, upgrade, DI compilation, static
  deployment, 10 scoped-save groups, 19 browser checks and 4 explicit skips
  (three sample-data routes and the order-creating checkout). No collector was
  contacted and no order was created.
- Gate candidate: isolated fixture commit
  `48ed0392bb77ff544a53f84b65274c26019921e1`, not a working-branch commit/tag.
  ZIP SHA-256: `e90cbc0d991ad9673c711813f6166bc84e9b18a0794916deacc4cf90590de749`.
  Evidence: `.test-results/opus-followups-native-gate.log`.
- Image rebuild and host-UID npm installation pass. Dependency files remain
  host-owned (501:20 on this Mac); a Linux-host bind-mount run is not claimed.
- Actual `down`/`up -d` recreation passes with retained application/database volumes:
  Git trust comes only from `/etc/gitconfig`, baseline verification passes, and
  all three storefront checks pass again. Current production files hash-match
  the tested snapshot. The dedicated stack is stopped after verification.
- PHP/PHTML lint, shell syntax, Compose validation, integration discovery and
  `git diff --check` pass.

The complete remote CI matrix and a new empty-volume provisioning run were not
executed in this follow-up. The native run reused the dedicated disposable store.
No source commit, push, release, deployment or license approval was made.

## Fresh-provisioning CI correction — 2026-09-23

The first [native CI run](https://github.com/basicrum/basicrum-magento-2/actions/runs/35846942665/job/107135254576)
installed Magento successfully, then failed before installing Basicrum: the
provisioning script wrote `admin/usage/enabled` after disabling the module that
declares that field. Remove the redundant write, keeping Admin Analytics disabled
and preserving fail-fast handling for real configuration errors.

The new fast regression executes the actual provisioning script with CLI doubles;
it checks both successful completion and failure propagation. All 46 fast checks
and 18 focused PHP 8.3 groups pass. The actual `start.sh` bootstrap also completes
on brand-new application/database volumes in the separate Compose project
`basicrum-native-ci-fix-20260923`, including Nginx startup. Existing installation
volumes are untouched. The local bootstrap log is retained at
`.test-results/fresh-provision-ci-fix.log`; this is fresh-provisioning evidence,
not a claim that a remote rerun has passed.

## Tested compatibility, not inferred compatibility

| Combination | Evidence in this follow-up |
| --- | --- |
| Magento Open Source 2.4.7-p10, PHP CLI/FPM 8.3.31, Composer 2.10, MariaDB 10.11, OpenSearch 2.19.4 | Actual isolated installation, native configuration and browser checks |
| Luma, en_US, built-in FPC, deployed static files | Actual Admin/storefront, enforcing checkout CSP, consent/beacon/cookie/redaction and two-visitor HIT checks |
| PHP 8.2 / 8.4 | Focused PHP checks only; not native Magento runs |
| Locked tools on PHP 8.3: framework 103.0.9 / backend 102.0.9 / config 101.2.9 / CSP 100.4.8 / store 101.1.9 | Static analysis against 2.4.9-line components; lock requires PHP 8.3/8.4 within the tooling project's constraint |
| Locally resolved lowest tools on PHP 8.3: framework 103.0.7 / backend 102.0.7 / config 101.2.7 / CSP 100.4.6 / store 101.1.7 | Static analysis against 2.4.7 GA components, not the native 2.4.7-p10 dependency set |
| CI lowest/stable resolutions on PHP 8.2/8.3/8.4 | Versions resolve afresh and are printed in CI; the full remote matrix has not been run locally |
| Adobe Commerce, other Magento patch lines, Hyvä, headless/PWA, Varnish, CDN/optimizer combinations | Not verified |
| Sample products/categories/CMS, authenticated customer journeys, successful checkout | Not exercised in this baseline fixture |

## Remaining release requirements and deliberate boundaries

The native Magento project version and Docker image digests are pinned, not the
full application dependency closure. Provisioning still resolves transitive
Composer dependencies; upstream updates/advisories can change or block a fresh
install. No bit-for-bit reproducibility or committed application lock is claimed.

The existing Composer MIT declaration is unchanged. A rights-holder-approved
root LICENSE still needs the owner's copyright holder/year confirmation; no
approval or legal ownership has been invented. This remains a release blocker,
not a technical-test failure.

The execution evidence above was collected before committing or pushing the
pipeline implementation. Final release certification must be rerun against the exact clean commit to be tagged;
a working-tree or isolated test-snapshot pass cannot authorize a later commit.
Pushing CI definitions does not itself certify, publish, tag or deploy a release.

The browser guard is request isolation, not a server-side egress firewall.
The test stack has synthetic credentials, local-only HTTPS, SMTP disabled and
no cron worker; do not expose it publicly or reuse its credentials. Existing
local Magento installations are unchanged.
The test stack was stopped after verification; its application/database volumes
and local evidence were retained. No temporary challenge PHP files remain.

Quantitative LCP/CLS/INP correctness and browser-lifecycle/Firefox/WebKit expansion
remain outside approved items 1/4/5. CR-D-002 automatic cache invalidation remains
deferred; operational cache cleaning is still required after configuration
changes. A successful native technical gate is not a claim of universal theme,
platform, performance-metric, legal or release readiness.

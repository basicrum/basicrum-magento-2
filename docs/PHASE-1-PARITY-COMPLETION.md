# Magento 2 Phase 1 parity completion notes

These notes are for incorporation into the central Basicrum parity task. The
shared parity ledger and both reference plugins were intentionally left
unchanged.

## Review mapping

- **R-001:** connected immediate and consent-controlled settings to loader
  selection; added canonical public callbacks, fail-closed page-level consent,
  idempotent allow, denial/withdrawal handling, wait cancellation, cookie
  cleanup, and documented reload-to-regrant behavior.
- **R-002:** added required UUIDv4 Brum Site ID, save-time and runtime identity
  validation, fail-closed rendering, safe JSON, scoped settings, admin inactive
  states, and `brum_site_id` beacons.
- **R-005:** added HTTPS enforcement with explicit development HTTP exception,
  optional query stripping, configurable zero-to-30-second Wait After Onload,
  `instrument_xhr=false`, and the byte-identical reviewed Boomerang artifact
  with provenance and BSD notices.
- **R-006 (manual portion only):** documented and exposed the working manual
  callback contract. Automatic Magento consent-provider adapters remain later
  work.
- **R-007 (foundation only):** retained `SecureHtmlRenderer`, used Magento
  static asset URLs, documented cache/static-deployment behavior, and added a
  disposable-store check for the real layout/CSP path. A broad optimizer and
  full-page-cache compatibility matrix remains later work.
- **R-008:** added focused PHP/template tests, real-artifact browser tests with
  intercepted beacons, and a guarded disposable Magento storefront-to-beacon
  harness.
- **R-009 (Phase 1 foundation):** added CI for PHP and browser checks plus
  Boomerang provenance/checksum verification. Installable package build/smoke
  and publishing remain later work.
- **R-010 (Phase 1 documentation):** aligned new customer copy to Basicrum and
  Brum Site ID, reconciled runtime requirements, and documented privacy,
  consent, cache, lifecycle, upgrade, and verification behavior.

## Compatibility decisions

There is no data migration. Existing paths and values remain stored. Explicit
`consent/enabled=0` selects immediate loading; `1` selects consent-controlled
loading. Legacy mode names remain visible but behave only as manual-integration
metadata and never grant consent. Existing sites require the new Site ID before
monitoring resumes. The unstored hardcoded five-second wait becomes explicit
off/zero settings; administrators can opt back into 5000 ms.

## Deferred boundaries

D-001 page vocabulary, D-002 staff exclusion, D-003 placement/readiness queues,
and D-004 cross-plugin wording remain unchanged. Automatic provider adapters,
a broad optimizer matrix, package/release publishing, and module-level license
text approval are not completed by Phase 1.

## Verification recorded at completion

- Focused PHP harness on PHP 8.3: 8 groups passed.
- PHP syntax checks: all module and test PHP/PHTML files passed.
- XML well-formedness: module, admin, and layout XML passed.
- Loader minification/provenance checks: passed.
- Chromium browser suite: 28 tests passed against readable/minified loaders
  and the real reviewed Boomerang artifact with intercepted local beacons.
- Composer 2.10 validation: valid with the pre-existing recommendation to omit
  the explicit package `version` field.

No disposable Magento 2 installation was available in the workspace, so the
native storefront harness was added but not run. Remote GitHub Actions were
also not run from this local implementation.

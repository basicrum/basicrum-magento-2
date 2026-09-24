# Magento 2 Phase 1 parity completion notes

These notes are for incorporation into the central Basicrum parity task. The
shared parity ledger and both reference plugins were intentionally left
unchanged.

This is the original Phase 1 completion snapshot. The current release target
is `0.1.1`; `0.1.0` was withdrawn and cannot be reused on Packagist. Follow the
[current release gate](../tests/integration/README.md#required-pre-release-native-gate)
instead of the historical release references below.

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
  static asset URLs, added the validated effective Beacon Endpoint origin to
  storefront `connect-src` and `img-src`, documented cache/static-deployment
  behavior, and added a disposable-store check for the real layout/CSP path. A
  broad optimizer and full-page-cache compatibility matrix remains later work.
- **R-008:** added focused PHP/template tests, real-artifact browser tests with
  intercepted beacons, and a guarded disposable Magento storefront-to-beacon
  harness. The native pre-release gate additionally requires Magento upgrade,
  DI compilation, static deployment, and authenticated Admin rendering.
- **R-009 (Phase 1 foundation):** added CI for strict Composer 2.10, PHP, and
  browser checks plus Boomerang provenance/checksum verification. The guarded
  native Magento check is required separately before the new `0.1.0` tag.
  Installable package build/smoke and publishing remain later work.
- **R-010 (Phase 1 documentation):** aligned new customer copy to Basicrum and
  Brum Site ID, reconciled runtime requirements, and documented privacy,
  consent, cache, lifecycle, upgrade, and verification behavior.

## Compatibility decisions

There is no data migration. Existing paths and values remain stored. Explicit
`consent/enabled=0` selects immediate loading; `1` selects consent-controlled
loading. The obsolete consent-mode selector and runtime metadata have been
removed; any old mode rows remain untouched in the database but are ignored and
never grant consent. Existing sites require the new Site ID before
monitoring resumes. Endpoint queries remain compatible, while embedded URL
credentials and fragments now fail validation. The unstored hardcoded
five-second wait becomes explicit off/zero settings; administrators can opt
back into 5000 ms.

Before the first public release, the technical module identifier and PHP
namespace were intentionally normalized to the “Basicrum” spelling. This is a
breaking identifier change, accepted because there are no extension
installations to migrate. Lowercase `basicrum/*` configuration paths remain
unchanged. The existing `0.0.2` tag is not reused: the guarded Phase 1 release
candidate is `0.1.0`, and Composer derives the package version from that future
immutable VCS tag.

## Selective follow-up from upstream PR #13

Five compatible ideas were adapted without replacing the WordPress-derived
Phase 1 behavior: a dynamic storefront CSP collector; explicit Config,
Backend, CSP, and Store module dependencies; the concrete Magento HTTP
response type for page detection; template/CSS-based Admin logo rendering and
centralized Boomerang version display; and an unreleased-first changelog.
Consent removal, a token setting, asset renaming, page-vocabulary/interface
changes, narrower runtime constraints, and license assertions from that pull
request were not borrowed.

## Deferred boundaries

The later, explicitly approved Magento 2-to-Magento 1 `p_type` alignment is
recorded in `PAGE-TYPE-ALIGNMENT.md` for central-task incorporation under
D-001, with additional real-beacon coverage under R-008. It implements all 27
Magento 1 labels using Magento 2 actions,
preserves `p_gen=mage2`, and defines unmapped/unknown handling. This does not
implement a WordPress-led shared taxonomy or migrate historical report data.
Magento 1, WordPress, and the shared ledger are unchanged.

Native checkout verification also found and corrected an R-007 CSP
registration defect: the frontend DI array replaced Magento's core collectors.
Global DI registration plus a frontend-only runtime guard now preserves them;
the browser harness checks the real merged policy header. See the alignment
notes for current execution results and limitations, separate from the original
Phase 1 verification snapshot below.

The approved Opus review follow-ups are recorded in `OPUS-REVIEW-FOLLOWUPS.md`.
They strengthen R-007/R-008 CSP and rendered-wait coverage, R-009 production
autoload/CI checks, R-002 scoped Admin presentation, and R-010 documentation.
That report contains the newer local execution results and explicitly separates
the supplemental 2.4.9 store from the still-required pinned release baseline.

The subsequent reliability and simplification work is recorded in
`RELIABILITY-AND-MAINTAINABILITY.md`: native configuration-save checks,
fail-closed browser traffic, enforced release-baseline versions, and removal
of obsolete consent-mode and duplicate configuration/test machinery.

D-002 staff exclusion, D-003 placement/readiness queues, and D-004 cross-plugin
wording remain deferred. Automatic provider adapters,
a broad optimizer matrix, package/release publishing, and module-level license
text approval are not completed by Phase 1. The full-page-cache finding
CR-D-002 recorded in `DEFERRED-CODE-REVIEW-FINDINGS.md` remains explicitly
deferred; CR-D-001's dynamic storefront CSP policy is implemented.

## Verification recorded at completion

- Focused PHP harness on PHP 8.2, 8.3, and 8.4: 15 groups passed on each version.
- PHP syntax checks: all module and test PHP/PHTML files passed on PHP 8.2, 8.3, and 8.4.
- XML well-formedness: module, admin, and layout XML passed.
- Loader minification/provenance checks: passed.
- Chromium browser suite: 28 tests passed against readable/minified loaders
  and the real reviewed Boomerang artifact with intercepted local beacons.
- Composer 2.10 strict validation (`--strict --no-check-publish`): valid;
  release versions are derived from VCS tags rather than an explicit package
  `version` field.

No disposable Magento 2 installation was available in the workspace, so the
native storefront/Admin release gate was added but not run. Its setup upgrade,
DI compilation, static deployment, cached storefront, intercepted beacon, and
authenticated Admin assertions therefore remain unverified here. Remote GitHub
Actions were also not run from this local implementation.

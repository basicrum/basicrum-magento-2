# Changelog

Notable changes to the Basicrum Analytics module are recorded here.

## [Unreleased]

### Added

- Fail-closed required Beacon Endpoint and UUIDv4 Brum Site ID configuration,
  scoped validation, and Admin monitoring status.
- Manual consent-controlled loading with the public opt-in and opt-out
  callbacks, plus deliberate immediate loading.
- Optional query-string redaction and a bounded, opt-in Wait After Onload.
- Focused PHP, real-loader browser, CI, and guarded disposable Magento test
  foundations.
- A guarded native-Magento `0.1.0` release gate covering upgrade, DI
  compilation, static deployment, storefront beacons, and Admin rendering.
- A frontend-only dynamic CSP collector that adds the validated effective
  Beacon Endpoint origin to `connect-src` and `img-src` only while monitoring
  is active.

### Changed

- **Breaking:** normalized the technical Magento module identifier and PHP
  namespace to the “Basicrum” spelling: `Basicrum_Analytics` and
  `Basicrum\\Analytics`. The lowercase configuration paths remain unchanged.
  This pre-release break was accepted because the extension has no
  installations to migrate.
- Removed the explicit Composer package version. Release versions now come
  from immutable VCS tags. The breaking rename remains unreleased and requires
  a new `0.1.0` tag rather than reuse of `0.0.2`.
- Production HTTP Beacon Endpoints normalize to HTTPS; the explicit
  development exception preserves HTTP.
- The reviewed Boomerang 1.815.60 artifact and configuration now emit
  `brum_site_id` while preserving `p_gen=mage2` and existing `p_type` values.
- Magento Config, Backend, CSP, and Store dependencies are declared explicitly
  in Composer metadata and module sequencing.
- Page-type detection injects Magento's concrete HTTP response and uses a
  strict 404 status comparison without changing the public page vocabulary or
  helper methods.
- Admin logo markup and styles live in a template and namespaced stylesheet;
  the displayed Boomerang version uses the module's central version constant.

### Compatibility and deferred work

- Existing configuration paths and stored legacy consent-mode values are
  preserved; legacy values do not grant consent.
- There is no configuration data migration. Existing enabled stores remain
  inactive until the new required Brum Site ID is valid.
- Automatic consent-provider adapters, full-page-cache invalidation analysis,
  a broad optimizer matrix, installable package/release publishing, and the
  central parity ledger's D-001 through D-004 items remain deferred as
  documented in the README and Phase 1 completion notes.

## [0.0.2]

- Previous module baseline.

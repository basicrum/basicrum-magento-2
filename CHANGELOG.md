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

- **Breaking:** Magento 2 `p_type` now uses Magento 1's exact 27 named labels
  for equivalent native pages, including `Checkout Success`, account/address,
  wishlist, guest-order, and PayPal billing-agreement pages. HTTP 404 takes
  precedence; unmapped native actions keep an explicit diagnostic fallback.
  No historical beacon/reporting data is migrated. `p_gen=mage2` is unchanged.
- Updated the Admin logo to the supplied 200 by 200 pixel Basicrum branding
  asset, displayed at 48 by 48 pixels.
- Visitor Consent and Privacy expand on every visit to the Admin configuration
  page, keeping their settings and guidance immediately visible.
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
  `brum_site_id` while preserving `p_gen=mage2`.
- Magento Config, Backend, CSP, and Store dependencies are declared explicitly
  in Composer metadata and module sequencing.
- Page-type detection injects Magento's concrete HTTP response and uses a
  strict 404 status comparison. Public helper methods remain available with
  Magento 1-aligned label comparisons.
- Admin logo markup and styles live in a template and namespaced stylesheet;
  the displayed Boomerang version uses the module's central version constant.

### Fixed

- Exclude test doubles from Composer's production classmap; CI now checks strict
  optimized autoload generation and verifies that no test classes leak into it.
- Remove inheritance controls and scope labels from display-only Admin status,
  Boomerang version, and manual callback instructions, preserving inheritance
  for saved settings.
- Execute the PHP-rendered Wait After Onload script in real-Boomerang browser
  tests, including delayed completion and withdrawal cancellation. Flaky browser
  retries now fail CI rather than masking the initial failure.
- Register the CSP collector alongside Magento's global collectors with an
  explicit frontend-only runtime guard. The former frontend DI array replaced
  core collectors, dropping existing policy sources and blocking checkout's
  own requests under enforced CSP. Native browser checks now assert that core
  policy sources survive, require an enforcing empty-cart checkout response,
  exclude the collector from Admin CSP, and include an opt-in offline checkout
  journey. Documentation reflects global registration, native header timing,
  address-book redirects, and compiled-DI upgrade requirements.

### Compatibility and deferred work

- Existing configuration paths and stored legacy consent-mode values are
  preserved; legacy values do not grant consent.
- There is no configuration data migration. Existing enabled stores remain
  inactive until the new required Brum Site ID is valid.
- Automatic consent-provider adapters, full-page-cache invalidation analysis,
  a broad optimizer matrix, installable package/release publishing, and the
  central parity ledger's D-002 through D-004 items remain deferred. D-001's
  Magento 2-to-Magento 1 label alignment is now implemented; a shared
  cross-platform taxonomy and historical reporting migration remain deferred.

## [0.0.2]

- Previous module baseline.

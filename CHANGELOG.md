# Changelog

Notable changes to Basicrum for Magento 2 are recorded here.

## [Unreleased]

### Added

- Magento-aware PHPStan level 8, Magento coding standards, precise runtime
  configuration types, and lowest/stable component-dependency CI checks.
- Magento-version/image-pinned Mage-OS-mirror native test stack, production ZIP verification,
  storefront/FPM installation binding, served-asset hashing, and two-visitor FPC
  consent isolation tests. Development tooling is excluded from distribution.
- Fail-closed required Beacon Endpoint and UUIDv4 Brum Site ID configuration,
  scoped validation, and Admin monitoring status.
- Manual consent-controlled loading with the public opt-in and opt-out
  callbacks, plus deliberate immediate loading.
- Optional query-string redaction and a bounded, opt-in Wait After Onload.
- Focused PHP, real-loader browser, CI, and guarded disposable Magento test
  foundations.
- A guarded native-Magento `0.1.0` release gate covering upgrade, DI
  compilation, static deployment, storefront beacons, and Admin rendering.
- Native configuration-save tests covering validation, same-form HTTP policy,
  scoped inheritance, and invalid imported values, with transaction rollback.
- A frontend-only dynamic CSP collector that adds the validated effective
  Beacon Endpoint origin to `connect-src` and `img-src` only while monitoring
  is active.

### Changed

- **Breaking packaging change:** the Composer name is now
  `basicrum/basicrum-magento-2`, with the public title "Basicrum for Magento 2"
  and distribution filename `basicrum-magento-2.zip`. The old Composer name
  conflicts with the new package; no compatibility replacement is declared.
  Magento module/namespace/ACL identifiers and configuration paths are unchanged
  by this packaging change. Packagist registration and release publication are
  separate, pending maintainer steps; historical tags are not rewritten.
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
- Removed the obsolete consent-mode selector and compatibility handling. The
  consent-required switch and public manual callbacks remain unchanged; any old
  database rows are ignored, not deleted.
- Admin status and runtime eligibility share one decision. XML owns install
  defaults; unused duplicate defaults and the stored Boomerang-version default
  are removed. Tests focus on behavior instead of broad branding/source scans.

### Fixed

- Fresh disposable Magento provisioning no longer writes the Admin Usage setting
  after disabling its owning module. Configuration failures still stop the job.
- Release ZIPs and expected hashes now come from committed files; ignored local
  files cannot enter the archive. Installed distributions reject extra development
  and hidden files. The cache-isolation test populates a fresh entry after consent.
- Persist the disposable stack's Git trust configuration across container recreation
  and install npm dependencies as the host user. Document unlocked application
  dependencies and the actual static-analysis component/PHP requirements.
- Resolve store-to-website inheritance through public `StoreInterface` and
  store-manager APIs, without relying on concrete store-model methods.
- Browser integration checks now intercept at context scope and restrict
  transport to the disposable store, including redirects. Unexpected traffic
  fails the test; collectors are never added to the proxy allowlist.
- The release gate checks installed Magento, PHP, Composer, MariaDB, and
  OpenSearch against `baseline.env` before running upgrade or configuration writes.
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

- Active configuration paths are preserved. Stored legacy consent-mode values
  are left untouched but no longer read; legacy values do not grant consent.
- There is no configuration data migration. Existing enabled stores remain
  inactive until the new required Brum Site ID is valid.
- Automatic consent-provider adapters, full-page-cache invalidation analysis,
  a broad optimizer matrix, release publishing, and the
  central parity ledger's D-002 through D-004 items remain deferred. D-001's
  Magento 2-to-Magento 1 label alignment is now implemented; a shared
  cross-platform taxonomy and historical reporting migration remain deferred.

## [0.0.2]

- Previous module baseline.

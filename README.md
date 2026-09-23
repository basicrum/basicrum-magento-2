# Basicrum Analytics

Basicrum adds Boomerang real user monitoring (RUM) to a Magento 2 storefront.
Monitoring is fail-closed: no Basicrum storefront scripts are emitted unless
the module is enabled and the effective store-scope Beacon Endpoint and UUIDv4
Brum Site ID are valid.

## Supported baseline

The Phase 1 integration baseline is Magento Open Source **2.4.7-p10** with
**PHP 8.3** and Composer 2.10. Platform version requirements are declared in
`tests/integration/baseline.env`; container images are pinned by digest. The full
application dependency set is not locked: first-time provisioning resolves
transitive Composer dependencies, so upstream changes can affect a new install.
Focused PHP checks run on PHP 8.2,
8.3, and 8.4. Composer metadata allows Magento framework 103.x so the module
can be evaluated on adjacent Magento 2.4 release lines, but only the pinned
PHP 8.3 combination is declared for disposable Magento integration testing.
This combination has now been exercised with Luma and built-in full-page cache,
including the installed distribution ZIP. Adobe Commerce, Hyvä, headless/PWA,
Varnish, and other Magento/PHP combinations are **not** certified by that run.
The source repository's `docs/QUALITY-AND-RELEASE-READINESS.md` records the exact
verification scope, skips, and remaining release requirements.

## Installation

The Composer package is being renamed to `basicrum/basicrum-magento-2`.
This source change does not register the new Packagist listing or publish a
release. After the new package's `0.1.0` release is published, install it with:

```sh
composer require 'basicrum/basicrum-magento-2:^0.1'
```

The version constraint deliberately excludes the historical `0.0.x` releases,
which may also appear under the new name when Packagist imports the repository.
Do not drop the constraint to make an unpublished release install. Until
`0.1.0` is available, use a reviewed source checkout for development only.
See the [package migration checklist](https://github.com/basicrum/basicrum-magento-2/blob/main/docs/PACKAGE-NAMING-MIGRATION.md)
for the separate maintainer steps and existing-installation considerations.

For a manual source installation, place this module at the exact path below.
The casing is required on case-sensitive filesystems:

```text
app/code/Basicrum/Analytics
```

Then enable and initialize the module:

```sh
bin/magento module:enable Basicrum_Analytics
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Regenerate compiled DI after installation or upgrade, including developer
installations where DI was previously compiled. This applies changed constructor
metadata and the global CSP collector registration. In production mode, deploy
static content using the store's normal deployment process as well.

## Configuration

Open **Stores > Configuration > Basicrum > Basicrum Analytics**. Every setting supports
Magento default, website, and store inheritance. Display-only status, version,
and callback instructions have no inheritance controls or stored values.
Visitor Consent and Privacy open expanded each time you visit the page. You
can collapse them while working; they reopen on your next visit.

Required settings:

- **Enable Basicrum**: new installations default to No.
- **Beacon Endpoint**: a valid HTTP or HTTPS collector URL without embedded
  credentials or a fragment. Endpoint query strings remain supported for
  compatibility. HTTPS is enforced unless the explicit development exception
  is enabled.
- **Brum Site ID**: a UUIDv4 copied from the Basicrum backoffice.

If any effective value is disabled, missing, malformed, or unsafe, the
Monitoring Status row explains the inactive state and the storefront template
emits nothing. Values are validated on save and again at render time so
programmatic or stale configuration cannot bypass the runtime gate.

Collection controls:

- **Require Consent Before Monitoring** defaults to Yes. Select No only for a
  deliberate immediate-loading policy.
- **Strip Query Strings** defaults to No. When enabled, Boomerang replaces
  complete query strings in page, navigation, referrer, and resource URLs with
  `?qs-redacted` before beacon transmission.
- **Wait After Onload** defaults to No with a zero delay. Its configured delay
  is bounded to 30,000 milliseconds.
- **Allow HTTP Beacon Endpoint** defaults to No and is intended only for local
  development. Without it, saved and effective HTTP endpoints are upgraded to
  HTTPS. Magento classifies this exception as environment-specific: configuration
  dumps put it in `app/etc/env.php`, not shared `app/etc/config.php`. Do not promote
  development `env.php` values to production. This follows Magento's native
  [configuration deployment rules](https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/deployment/technical-details).
  Existing database values and previously exported files are not rewritten;
  review any old shared export of `basicrum/developer/development_mode` before
  re-exporting configuration. The default, configuration path, and scope
  inheritance are unchanged.

The runtime emits Magento 1's exact named `p_type` values for equivalent
Magento 2 pages (for example, `Home`, `Product`, `Search`, and
`Checkout Success`), while retaining `p_gen=mage2` and the configured
`brum_site_id`. Unknown actions use `unmapped_<lowercase full action name>`;
an unavailable action uses `unknown`. The [page-type alignment notes](docs/PAGE-TYPE-ALIGNMENT.md)
list all mappings, native-route adaptations, and reporting impact.
Boomerang uses `instrument_xhr=false`,
Continuity and ResourceTiming with `splitAtPath`, and Secure/SameSite Strict
cookie settings, matching the reviewed Basicrum configuration.

When the effective runtime configuration is active, the module adds only the
normalized Beacon Endpoint origin (scheme, host, and optional port) to the
storefront `connect-src` and `img-src` CSP policies. Paths and query strings
are not copied into CSP. Inactive or invalid configuration adds no collector
origin. This covers Boomerang's send-beacon/XHR and image-fallback transports;
it does not weaken other directives or add a wildcard.

## Consent integration

Phase 1 provides a manual, page-level callback contract. Basicrum does not
display a banner, decide whether consent is legally required, infer consent
from a cookie or legacy mode string, or persist its own consent decision.

When the site's external consent tool authoritatively allows performance
monitoring on the current page, call:

```js
if (typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function") {
  window.OPT_IN_BASICRUM_LOADER_WRAPPER();
}
```

On denial, expiry, or withdrawal, call:

```js
if (typeof window.OPT_OUT_BASICRUM_LOADER_WRAPPER === "function") {
  window.OPT_OUT_BASICRUM_LOADER_WRAPPER();
}
```

The consent wrapper is inert until allow. Repeated allow calls load Boomerang
at most once. Denial before the first allow cleans measurement and legacy
consent cookies without preventing a later allow on that page. Withdrawal
during download prevents the arriving bundle from initializing. Withdrawal
after initialization disables further collection, cancels a pending Wait After
Onload timer, and removes `RT`, `BA`, `BRUM_CONSENT`, and `BOOMR_CONSENT`
cookies where JavaScript can reach them. Data already transmitted cannot be
retracted.

After withdrawal once loading has started, re-grant requires a page reload.
This intentionally prevents a same-page restart from a partially initialized
state. The callbacks are registered by the footer loader; calls made before
registration are not queued. Connect both allow and deny/change events in the
site's consent tool on every page.

Automatic consent-provider adapters are not part of Phase 1.

## Upgrade behavior from 0.0.2

No data migration renames, deletes, or heuristically rewrites stored settings.
Review the following before enabling the upgraded module:

- The Composer package name changes from `basicrum/basicrum-analytics` to
  `basicrum/basicrum-magento-2`. The two packages must not be installed together;
  Composer does not prevent that combination during the naming transition.
  Remove the old requirement and check the resolved lock file for old-name
  transitive dependencies when the new release is available; this is not an
  automatic or backward-compatible replacement. A manual `app/code` copy must not coexist
  with a Composer installation either. No stored configuration is deleted.

- Magento 2 `p_type` labels now match Magento 1, including capitalization and
  spaces. This intentionally changes existing report groupings; historical
  beacons are not migrated and no legacy-label mode is provided. Update any
  report filters and purge cached HTML after upgrading. WordPress and Magento
  1 labels are unchanged.

- Before the first public release, the technical module identifier and PHP
  namespace were normalized to the “Basicrum” spelling. This is an intentional
  breaking rename; the supported identifiers are `Basicrum_Analytics` and
  `Basicrum\\Analytics`. Lowercase `basicrum/*` configuration paths are
  unchanged. The exact manual installation path is documented above.

- Existing `basicrum/general/beacon_endpoint` values remain in place but now
  receive save-time and runtime validation. Invalid values make monitoring
  inactive. HTTP becomes HTTPS unless the development exception is explicit.
- `basicrum/general/brum_site_id` is new and required. Existing enabled stores
  stay inactive until a valid UUIDv4 value is configured at the appropriate
  scope.
- Existing `basicrum/consent/enabled=0` means deliberate immediate loading.
  Value `1` means consent-controlled loading. Invalid or absent effective
  values fail to consent-controlled behavior.
- The obsolete `basicrum/consent/mode` selector and runtime handling are
  removed. Existing database rows are left untouched but ignored, including
  `manual`, `explicit`, `implicit`, `cookie`, and `gdpr`. Only the consent-required
  switch controls loading; none of these old strings counts as consent. Manual
  callbacks remain the supported integration.
- The old five-second wait was hardcoded and had no stored setting. It is
  replaced with `basicrum/performance/wait_after_onload` and `delay_ms`, both
  defaulting to off/zero. Administrators who need the former timing must
  explicitly enable it and enter 5000 ms.

After changing module configuration, clean Magento configuration, layout,
block HTML, and full-page caches. Production deployments must also publish the
new static assets and invalidate any CDN or optimizer cache that can retain old
HTML or JavaScript. Magento's versioned static asset URLs provide browser cache
invalidation only after the deployment/content version changes.

The template uses Magento's `SecureHtmlRenderer`, the loader and Boomerang
assets are same-origin module assets, and the validated collector origin is
added dynamically to storefront CSP. The disposable-store check exercises the
actual layout and CSP path, but Phase 1 does not claim compatibility with a
broad set of third-party script delay/combine/optimizer extensions.

## Testing

Fast checks:

```sh
docker run --rm -v "$PWD:/module:ro" -w /module php:8.3-cli php tests/php/run.php
npm ci
npm test
```

The fast PHP harness uses test doubles to cover defaults, validation, save
normalization, runtime gates, scope inheritance, CSP origin policy, all Magento 1-aligned page-type mappings
and fallbacks, template
serialization/loader selection, and artifact provenance. Browser tests execute
the packaged readable and minified loaders and the real bundled Boomerang
against intercepted local requests. Global setup renders the actual PHP footer
template using PHP 8.3 in Docker (Docker must be running), then the browser
executes its inline configuration and Wait After Onload plugin. Set
`BASICRUM_TEST_PHP=php` (or an absolute executable path) to use an installed
PHP CLI instead. The Chromium CI job explicitly provisions PHP 8.3 and selects
it through this setting; fixture rendering does not pull or start a Docker image
in that job. A missing or failing selected PHP executable fails setup without
falling back to Docker.
Magento block/renderer doubles are used here; native rendering is covered by
the separate integration suite. The checks cover pre-consent silence, one-time
loading, denial and withdrawal races, cookie cleanup, query redaction, and
beacon identity, delayed sending, and cancellation of the rendered wait timer.

For the actual layout/template/static-content/CSP/storefront-to-beacon path,
use the guarded disposable-store harness in `tests/integration/README.md`.
It also exercises Magento's real Admin configuration-save model, backend
validation, and default/website/store inheritance inside rolled-back database
transactions. Browser traffic is limited to the disposable storefront/Admin;
the expected beacon is fulfilled locally and unexpected destinations fail the
test. External payment scripts must be disabled in that test installation.
The native suite also renders a test-only, non-cacheable Magento page under
enforcing CSP with inline scripts disabled. It checks matching bootstrap
nonces, real first-party script execution and consent-gated beacons, and a
blocked unnonced inline negative control. The fixture is never packaged with
the extension. This is separate from the report-only homepage FPC checks; it
does not certify nonce handling on cacheable pages or custom strict-dynamic
policies. See the integration README for fixture installation and scope.
The regular CI workflow runs strict Composer 2.10 validation, a validating VCS
import regression before and after the default-branch rename, and optimized
production classmap checks plus the fast PHP and Chromium checks. The VCS test
uses the real Composer importer on a temporary local Git repository containing
the candidate metadata and a synthetic historical tag; it needs no network or
Packagist account. Run it with:

```sh
docker run --rm --network none -v "$PWD:/app:ro" -w /app composer:2.10 php tests/php/check-composer-import.php /usr/bin/composer
```

This is not a live Packagist update or a Magento Composer installation. The Chromium
job uploads an HTML report with traces from failed test attempts, retained for
seven days. A flaky pass still fails the job; retries do not hide failures.
Additional CI checks run Magento-aware PHPStan level 8 and Magento coding
standards against real Magento components, with lowest/stable dependency
resolution on PHP 8.2–8.4 and a locked PHP 8.3 job. These component checks are
not full-platform compatibility certification. The committed tooling lock requires
PHP 8.3 or 8.4 and resolves framework 103.0.9 (the Magento 2.4.9 component line).
The locally tested PHP 8.3 lowest resolution uses framework 103.0.7 (2.4.7 GA),
not 2.4.7-p10. CI's lowest/stable jobs resolve afresh for their PHP version; their
logs report the exact component versions. No PHPStan run against the native
2.4.7-p10 dependency set is claimed. Run the locked tools locally on PHP 8.3/8.4:

```sh
composer --working-dir=tests/quality install --no-interaction --no-scripts
sh tests/quality/check.sh
```

The native CI workflow provisions a separate Magento-version/image-pinned stack from the
anonymous Mage-OS mirror; it does not use production credentials. It disables
external Braintree scripts only in that test stack. See the integration README
for setup, synthetic credentials, localhost-only ports, and cleanup.
Before tagging Phase 1 as `0.1.0`, the documented native
release gate is also required: it requires a clean candidate checkout, verifies
the distribution ZIP and registered installed module match it, and enforces all versions in `baseline.env`,
then runs Magento upgrade, DI compilation, static deployment, native save tests,
storefront/beacon assertions with a proven full-page-cache HIT, and an authenticated
Admin rendering check. A temporary challenge binds the browser URL to that
installation and its web PHP version; served loader/Boomerang bytes must match
the candidate. A visitor with measurement cookies populates a fresh cache entry;
another visitor's first request to that URL must be a HIT and remain silent until
its own allow callback. It rechecks candidate identity afterward and records the
tested commit SHA in its success output. Run `npm ci` first: the native runner
uses only the installed Playwright binary, never an automatic download.
The production archive is built from the clean Git commit, never loose working-tree
files. It excludes tests, CI, developer tooling/configuration and generated output,
and retains production code/assets and license notices. Installed-package checks
reject all extra files, including leftover development directories and hidden files.
No remote CI job is reported as passing merely because its definition was added.

## Privacy and lifecycle notes

Boomerang may collect page/navigation/referrer/resource URLs, performance
timings, browser/network characteristics, and the configured Basicrum identity,
then send them to the configured Beacon Endpoint. With consent-controlled
loading, the Boomerang monitoring bundle, beacon transmission, and measurement
cookies do not start before the current page receives allow. The inert consent
wrapper is present so the external tool can signal that decision. Immediate
mode has no such gate.

Disabling the module and cleaning page caches stops future script emission.
Uninstall behavior and settings deletion are not automated in Phase 1; removing
module files does not delete configuration or data already sent to a collector.
Store operators remain responsible for their consent tool, privacy disclosure,
collector access, retention, and deletion processes.

## Third-party software and license

The reviewed Boomerang 1.815.60 artifact and loader provenance, checksum, and
BSD license are recorded in `THIRD-PARTY-NOTICES.txt` and
`view/frontend/web/js/boomr/LICENSE.txt`.

The existing Composer metadata declares this module as MIT. This repository
still does not contain an approved module-level license text; that pre-existing
distribution gap must be resolved by the rights holder before release. Phase 1
does not silently relicense the module.

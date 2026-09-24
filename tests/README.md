# Development checks

These instructions are for maintainers working from a source checkout, not for
installing Basicrum on a store. Run commands from the repository root. Browser
checks require Node.js 20 or newer; the default PHP fixture setup uses Docker.
See [native Magento integration](integration/README.md) for the disposable
installation and release gate.

Fast checks:

```sh
docker run --rm -v "$PWD:/module:ro" -w /module php:8.3-cli php tests/php/run.php
npm ci
npm test
```

The fast PHP harness uses test doubles to cover defaults, validation, save
normalization, runtime gates, scope inheritance, CSP origin policy, all Magento
1-aligned page-type mappings and fallbacks, template serialization/loader
selection, and artifact provenance. Browser tests execute
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
The regular CI workflow runs strict Composer 2.10 validation, validating VCS
import regression checks, and optimized production classmap checks plus the
fast PHP and Chromium checks. The VCS test uses the real Composer importer on
a temporary local Git repository containing
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
Before publishing a release, run the documented native release gate against
the exact candidate commit. It requires a clean candidate checkout, verifies
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

# Disposable Magento integration check

The declared Phase 1 baseline is pinned in `baseline.env`: Magento Open Source
2.4.7-p10 on PHP 8.3, with the listed Composer, MariaDB, and OpenSearch lines.
Adobe's current system-requirements table lists PHP 8.2 and 8.3 for the 2.4.7
release line. This is a baseline, not a claim that every patch or platform
combination has been verified.

Install this checkout as `app/code/Basicrum/Analytics` (module
`Basicrum_Analytics`), enable it, run `setup:upgrade`, and make its storefront
reachable before running this harness. Then:

```sh
BASICRUM_DISPOSABLE_MAGENTO=1 \
MAGENTO_ROOT=/absolute/path/to/disposable-magento \
MAGENTO_STOREFRONT_URL=https://magento.test/ \
tests/integration/configure-disposable.sh
```

Set `BASICRUM_DEPLOY_STATIC=1` when the installation uses production static
content rather than developer-mode asset materialization. The script changes
Basicrum settings and cleans Magento caches, so it refuses to run unless the
explicit disposable-installation guard is present.

The browser opens the rendered storefront, verifies the consent loader is in
the page, confirms no Boomerang request, beacon, `RT`, or `BA` cookie occurs
before allow, calls the public API twice, intercepts the local beacon, and
asserts URL redaction plus `p_type`, `p_gen`, and `brum_site_id`. It then checks
withdrawal cookie cleanup, reloads the page to exercise a warm full-page-cache
response, and proves that the new page still waits for a fresh allow decision.
It therefore covers the real layout, template, CSP path, static asset URL,
cached HTML, and bundled Boomerang rather than a copied fixture.

## Required pre-release native gate

The `0.0.2` identity must not be reused for this breaking, previously
unreleased technical-namespace change. The Phase 1 release candidate is
`0.1.0`, and the guarded release script accepts only `0.1.0` (with an optional
`v` tag prefix). Change that explicit policy in review if the intended release
number changes; do not bypass the gate or add a Composer `version` field.

Before creating the tag, first require the fast CI jobs to pass, then run the
following against the pinned disposable Magento baseline:

```sh
BASICRUM_DISPOSABLE_MAGENTO=1 \
BASICRUM_RELEASE_TAG=0.1.0 \
MAGENTO_ROOT=/absolute/path/to/disposable-magento \
MAGENTO_STOREFRONT_URL=https://magento.test/ \
MAGENTO_ADMIN_URL=https://magento.test/admin/ \
MAGENTO_ADMIN_USERNAME=basicrum-release-check \
MAGENTO_ADMIN_PASSWORD='disposable-secret' \
tests/integration/release-gate.sh
```

Use the installation's real secret Admin login path in `MAGENTO_ADMIN_URL`.
The browser follows Magento's own Stores > Configuration navigation so it does
not bypass Admin secret-key URLs. The disposable Admin account must be able to
open that page, and login challenges such as two-factor authentication or
CAPTCHA must be disabled for this isolated test account.

The gate runs `setup:upgrade`, dependency-injection compilation, and an English
static-content deployment before applying the test configuration. Playwright
then exercises the rendered storefront and intercepted beacon, reloads a warm
full-page-cache response, logs into Magento Admin, and verifies that the
Basicrum logo and required configuration/status fields render. Any command,
login, rendering assertion, or browser check failure blocks the tag.

This repository does not provision Magento or store Admin credentials in CI.
Consequently the native gate remains a separately required release check on a
maintained disposable installation; adding the script does not mean it has
already passed.

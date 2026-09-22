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

The CSP assertions require the baseline's enabled `Magento_Paypal` module:
`www.paypal.com` from its `csp_whitelist.xml` must survive alongside core
`'self'` and the Basicrum collector in storefront fetch directives. A fresh
empty-cart `checkout/` request is inspected without following its redirect,
so the check requires an actual enforcing `Content-Security-Policy` header,
not the cart page's report-only header. This check creates no order.

Admin checks require at least one website/store view with single-store mode
off and permission to view all three scopes. They verify that the collector
origin is absent from Admin CSP, that the three display-only rows have no
inheritance controls/scope labels, and that real settings retain those controls.
Use a dedicated collector origin that no other module whitelists in Admin;
otherwise origin absence cannot isolate Basicrum's contribution. Scope
switching is read-only: the test never saves configuration.

## Page-type alignment checks

The suite also checks Magento 1-aligned labels in real beacons from public
Magento 2 pages. It covers native route differences, HTTP 404, explicit
unmapped fallback, and redirect destinations: a logged-out account is `Login`,
and checkout/success without a valid cart/order session is `Cart`.

Set `MAGENTO_SAMPLE_DATA=1` to include the Luma sample CMS page `about-us`,
product `fusion-backpack.html`, and category `gear/bags.html`. These are skipped
without that flag; all require installed, indexed, active sample data.

To check an already configured disposable store without rewriting settings,
run Playwright directly. `MAGENTO_BEACON_URL` and `MAGENTO_SITE_ID` can override
the default test endpoint and identity; they must match the effective store
configuration. Consent-controlled loading and query redaction must be enabled.
The browser intercepts the configured endpoint and returns a local response;
it does not forward beacons to that collector.

```sh
MAGENTO_STOREFRONT_URL=https://magento.test/ \
MAGENTO_BEACON_URL=https://collector.basicrum.test/beacon \
MAGENTO_SITE_ID=550e8400-e29b-41d4-a716-446655440000 \
MAGENTO_SAMPLE_DATA=1 \
npx playwright test --config=playwright.integration.config.js
```

`checkout-page-types.spec.js` is separately guarded. In addition to the URL
and identity settings, it requires **both** `BASICRUM_DISPOSABLE_MAGENTO=1`
and `MAGENTO_TEST_CHECKOUT=1`. It assumes Luma, the in-stock Fusion Backpack
sample product, guest checkout, US/California shipping, Flat Rate shipping,
and the offline Check / Money order payment method. It creates one test order
per successful run (and a quote on unsuccessful runs), using synthetic
`example.test` contact details. Use only on disposable data with outbound mail
captured locally; the fixture does not delete orders or alter store settings.
No live payment provider is selected. It verifies actual `Checkout` and
`Checkout Success` beacons, not a manually substituted browser variable.

Authenticated account/address/order/wishlist and PayPal agreement journeys
are not included in this public browser matrix. Their exact labels, alongside
all other mappings, are covered by the focused PHP tests. A missing journey
fixture is not evidence of end-to-end verification for that page.

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

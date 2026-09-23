# Disposable Magento integration check

The declared Phase 1 baseline is pinned in `baseline.env`: Magento Open Source
2.4.7-p10 on PHP 8.3, with the listed Composer, MariaDB, and OpenSearch lines.
Adobe's current system-requirements table lists PHP 8.2 and 8.3 for the 2.4.7
release line. This is a baseline, not a claim that every patch or platform
combination has been verified.

## Magento-version/image-pinned local/CI stack

`sh tests/integration/docker/start.sh` creates the dedicated Compose project
`basicrum-release-baseline` using pinned image digests and the anonymous Mage-OS
mirror. It provisions exactly 2.4.7-p10, installs the pinned Playwright runner,
and leaves existing Magento stacks alone. Docker, Compose, OpenSSL and several
GB of disk/RAM are required. Provisioning refuses to overwrite an installed
application; reuse it with the commands below, not `start.sh` again.
The full Magento Composer dependency closure is **not** locked in this repository.
Fresh provisioning resolves transitive dependencies from the mirror; upstream
releases/advisories can change that resolution or make installation fail. Image
digests and the Magento version pin do not promise bit-for-bit reproducibility.

The test browser runs inside the PHP container at `https://web:8443/`; the host
binding is loopback-only port **9443**, never 443 or the existing local shop's
8443. This is a test appliance, not a replacement developer shop. Database and
search ports are not published. Its checked-in credentials are synthetic and
must never be reused elsewhere. Admin 2FA, Admin analytics, Braintree extensions
and outbound SMTP are disabled **only in this disposable installation**.
`Magento_Paypal` stays enabled for the core CSP regression assertion. No browser
allowlist exception is made for Braintree or any other external service.

For subsequent runs after `down`, first recreate the containers with
`docker compose -f tests/integration/docker/compose.yaml up -d`.
Application/database volumes are retained; do not reprovision them. Git's
`safe.directory` is baked into the PHP image and survives container recreation.
After harness Dockerfile changes, run `docker compose -f tests/integration/docker/compose.yaml build php`
before `up -d`. First-time `start.sh` installs npm dependencies using the caller's
UID/GID so the bind-mounted checkout does not acquire root-owned `node_modules`.
To refresh dependencies later, run `npm ci` as the host user, or use the same
UID/GID-aware `exec` command from `start.sh`. Existing root-owned dependencies
from older harness runs need their ownership repaired before this unprivileged install.

From a clean committed checkout with the containers running:

```sh
docker compose -f tests/integration/docker/compose.yaml exec -T -w /module php sh tests/integration/build-artifact.sh
docker compose -f tests/integration/docker/compose.yaml exec -T -w /module php php tests/integration/test-artifact.php
docker compose -f tests/integration/docker/compose.yaml exec -T php sh /module/tests/integration/docker/install-artifact.sh
docker compose -f tests/integration/docker/compose.yaml exec -T -w /module -e BASICRUM_RELEASE_TAG=0.1.0 php sh tests/integration/release-gate.sh
docker compose -f tests/integration/docker/compose.yaml down
```

`down` stops/removes this stack's containers/network but retains its database
and application volumes. It does not stop other projects or remove their data.
The installer expands the verified Git-commit ZIP at `app/code/Basicrum/Analytics`;
it tests the documented manual package installation, not a published Packagist
release. Every stale extra file, including in development directories, causes
identity verification to fail, not silent deletion.

The `Pinned native Magento` CI workflow runs the same provision/archive/install/
gate sequence on pull requests and manual dispatch. A workflow definition alone
is not evidence that a remote run passed. The default fixture does not import
sample data or create orders: three sample routes and the opt-in checkout
journey are explicitly skipped. See `docs/QUALITY-AND-RELEASE-READINESS.md` for
the actual execution record and tested/untested matrix.

Install this checkout as `app/code/Basicrum/Analytics` (module
`Basicrum_Analytics`), enable it, run `setup:upgrade`, and make its storefront
reachable before running this harness. Then:

```sh
npm ci
BASICRUM_DISPOSABLE_MAGENTO=1 \
MAGENTO_ROOT=/absolute/path/to/disposable-magento \
MAGENTO_STOREFRONT_URL=https://magento.test/ \
tests/integration/configure-disposable.sh
```

Set `BASICRUM_DEPLOY_STATIC=1` when the installation uses production static
content rather than developer-mode asset materialization. The script changes
Basicrum settings and cleans Magento caches, so it refuses to run unless the
explicit disposable-installation guard is present.
It also requires the locally installed Playwright runner before changing settings;
missing dependencies fail with an `npm ci` instruction, without downloading a
different runner.

The browser opens the rendered storefront, verifies the consent loader is in
the page, confirms no Boomerang request, beacon, `RT`, or `BA` cookie occurs
before allow, calls the public API twice, intercepts the local beacon, and
asserts URL redaction plus `p_type`, `p_gen`, and `brum_site_id`. It then checks
withdrawal cookie cleanup and requires an actual `X-Magento-Cache-Debug: HIT`
on the subsequent navigation. It proves that the cached page waits for a fresh
allow decision, stays cookie/beacon-silent beforehand, and then sends the same
expected identity and redaction fields.
It therefore covers the real layout, template, CSP path, static asset URL,
cached HTML, and bundled Boomerang rather than a copied fixture.

Every browser test first binds its URL to `MAGENTO_ROOT` using a random, exclusive,
short-lived PHP challenge in that installation's `pub` directory. It checks the
nonce and **web/FPM** PHP line, not only CLI PHP. Redirects, another document root,
or a mismatched PHP line fail. The challenge is removed in `finally`, including
after failures. It emits no configuration or secrets, and is never packaged
with the module. The disposable Nginx configuration permits only that tightly
named test path; a separately provisioned installation must configure the same
test-only location. Never add that location to a live shop. Direct browser runs
now also require `BASICRUM_DISPOSABLE_MAGENTO=1` and `MAGENTO_ROOT`.

Basicrum script responses are hashed from the bytes actually returned to the
browser and compared with the candidate's packaged loader/Boomerang files.
Missing, unexpected or stale scripts fail; the primary storefront test requires
both the consent wrapper and the real Boomerang response. Server-side source
identity alone can no longer pass while stale deployed static content is served.
Script merging or rewriting is not supported by this exact-byte baseline.

An additional test establishes two independent anonymous browser contexts. The
first visitor grants consent and receives a measurement cookie, then requests a
unique URL that must be a cache MISS. The second visitor's first request to that
URL must be a HIT with the same inline configuration, no Boomerang request, and
no measurement cookie or beacon until its own allow callback. This exercises
cache population by a request carrying measurement state, not only a page
cached before consent. The callback itself still does not persist consent.
The first visitor uses a second tab in its existing cookie jar for the MISS;
this avoids conflating the cache test with navigation-time beacon interception.
Both granted pages withdraw after the assertions. The network guard remains strict.

Enable full-page caching and use a cacheable homepage. For built-in FPC, the
disposable installation must be in developer mode so Magento emits its debug
header. Magento's Varnish VCL also emits that header; any proxy in front must
preserve it. Do not manufacture a `HIT` header in the web server. Missing headers,
`MISS`, and `UNCACHEABLE` fail rather than skipping coverage. An extra pre-consent
navigation warms the anonymous visitor's cookie/vary context. Browser routing
disables the browser HTTP cache, so it cannot substitute for server FPC here.
This check covers the selected store/homepage, not cross-store cache isolation,
automatic invalidation after settings changes (CR-D-002), or a Varnish matrix.

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

## Network isolation

All browser contexts, including popups, intercept the configured beacon origin
and path and return a local response. Other direct requests are allowed only
to `MAGENTO_STOREFRONT_URL` and `MAGENTO_ADMIN_URL` origins; unexpected beacon
identity payloads are blocked even at another path on those origins. Unexpected
requests fail the test, with URLs logged without query strings or POST bodies.
Service workers and WebSockets are blocked.

A loopback test proxy permits only the storefront/Admin host and port pairs,
never the collector. This also stops external destinations reached through
redirect chains, which Playwright's route handler alone does not re-intercept.
Use dedicated disposable origins and same-origin assets. Disable external
payment/analytics scripts in that installation; do not whitelist their hosts
to make a failed test pass. This is browser-request isolation, not an OS-level
firewall or a sandbox for Magento's server-side integrations. Keep outbound
email and other server-side services isolated separately.

## Native configuration-save checks

With this checkout installed and native DI current, run:

```sh
BASICRUM_DISPOSABLE_MAGENTO=1 \
MAGENTO_ROOT=/absolute/path/to/disposable-magento \
php tests/integration/config-save.php
```

This boots Magento's Admin area and uses the actual `Magento\Config\Model\Config`
save model, not the lightweight PHP doubles. It checks defaults, endpoint/UUID
validation, HTTP normalization and same-form changes, bounded waits, website/store
overrides and re-inheritance, deliberate immediate consent, and fail-closed
runtime behavior after invalid imports. It does not automate submission of the
Admin HTML form; the browser suite separately covers its rendering.

Each case runs within an outer database transaction and rolls back all Basicrum
fixture rows, including after validation exceptions. Configuration caching is
disabled only in that PHP process so fixture values cannot enter shared config
cache. Original rows are compared after rollback. Use disposable data only:
native save events can invalidate caches or trigger third-party observers, and
those non-database side effects are not rolled back. No order is created.

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
MAGENTO_ROOT=/absolute/path/to/disposable-magento \
BASICRUM_DISPOSABLE_MAGENTO=1 \
MAGENTO_BEACON_URL=https://collector.basicrum.test/beacon \
MAGENTO_SITE_ID=550e8400-e29b-41d4-a716-446655440000 \
MAGENTO_SAMPLE_DATA=1 \
npx --no-install playwright test --config=playwright.integration.config.js
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

The gate requires Git and a clean, committed module checkout, including no
untracked files. First build the Git-commit ZIP using `build-artifact.sh` and
install its contents. `BASICRUM_ARTIFACT` can select a ZIP path; the default is
`.test-results/package/basicrum-analytics.zip`. The gate hashes every ZIP entry
against the candidate's production manifest. Before any upgrade/configuration
write it also resolves `Basicrum_Analytics` through
Magento's actual `ComponentRegistrar` and compares SHA-256 hashes of package
files (including PHP, XML, templates, assets, notices, and top-level metadata).
Missing, modified, and any extra installed files fail, including ignored/development
files. This strict distribution gate is not for a full checkout or checkout symlink;
the separate `configure-disposable.sh` development runner still supports those.
The expected manifest hashes committed Git blobs, not working-tree files.
Exclusions come from the committed `composer.json` archive boundary, never installed
metadata; `.gitattributes` applies the same boundary to the Git ZIP. Ignored or
untracked local files cannot become expected archive entries. Tests, development
tools/configuration, CI and generated output are not shipped. PHP, XML, templates,
assets, README, changelog and third-party notices remain verified. Internal source
symlinks are rejected; the module directory itself may be a symlink.

The gate then compares the installed Magento
Open Source patch exactly and the PHP, Composer, MariaDB, and OpenSearch
major/minor lines with `baseline.env`. Missing versions, a different edition,
or a mismatch fail the gate; there is no bypass flag. Supplemental tests on
another platform do not certify this release baseline.

The gate then runs `setup:upgrade`, dependency-injection compilation, an English
static-content deployment, and the native configuration-save tests before
applying the browser test configuration. Playwright
then exercises the rendered storefront and intercepted beacon, reloads a warm
full-page-cache response, logs into Magento Admin, and verifies that the
Basicrum logo and required configuration/status fields render. Any command,
login, rendering assertion, or browser check failure blocks the tag.
Afterward it rechecks the clean checkout/commit, installed files, and ZIP identity.
The final success line records the candidate SHA and intended tag; retain the
command output as release evidence and tag only that exact commit. Do not edit
or resync either tree during the gate. These checks tie source evidence to the
candidate and to the tested distribution bytes. The success line certifies only
the native technical checks, not legal approval, a published Composer install,
untested themes, or optional skipped journeys. The rights-holder-approved module
LICENSE remains a separate release requirement.

Fast CI retains native browser test discovery (`--list`); the separate native
workflow provisions and executes Magento. Neither discovery nor merely adding
a workflow is evidence of a passing native run.

# Disposable Magento integration check

The declared Phase 1 baseline is pinned in `baseline.env`: Magento Open Source
2.4.7-p10 on PHP 8.3, with the listed Composer, MariaDB, and OpenSearch lines.
Adobe's current system-requirements table lists PHP 8.2 and 8.3 for the 2.4.7
release line. This is a baseline, not a claim that every patch or platform
combination has been verified.

Install this checkout as `BasicRum_Analytics`, enable it, run `setup:upgrade`,
and make its storefront reachable before running this harness. Then:

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

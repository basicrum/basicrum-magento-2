const { test: base, expect } = require("@playwright/test");
const { guardNetwork } = require("./beacons");
const { localProxy } = require("./local-proxy");
const { verifyInstallation } = require("./installation-proof");
const { verifyAsset } = require("./served-assets");

// Automatic: the guard is installed before any page, popup or navigation.
const test = base.extend({
  localNetwork: async ({}, use) => {
    const proxy = await localProxy([process.env.MAGENTO_STOREFRONT_URL, process.env.MAGENTO_ADMIN_URL]);
    try {
      await use(proxy);
      // Runs after context teardown, so late requests cannot escape the check.
      expect(proxy.blocked, "Unexpected browser/transport requests were blocked").toEqual([]);
    } finally {
      await proxy.close();
    }
  },
  proxy: async ({ localNetwork }, use) => use(localNetwork.options),
  verifiedInstallation: [async ({ playwright, localNetwork }, use) => {
    if (process.env.MAGENTO_STOREFRONT_URL) {
      const request = await playwright.request.newContext({ proxy: localNetwork.options, ignoreHTTPSErrors: true });
      try {
        // The Admin path is a route, not a second document root. It must share this origin.
        const storefront = new URL(process.env.MAGENTO_STOREFRONT_URL);
        if (process.env.MAGENTO_ADMIN_URL) {
          expect(new URL(process.env.MAGENTO_ADMIN_URL).origin).toBe(storefront.origin);
        }
        await verifyInstallation(request, process.env.MAGENTO_ROOT, [storefront.href]);
      } finally {
        await request.dispose();
      }
    }
    await use();
  }, { auto: true }],
  assetEvidence: [async ({ context, verifiedInstallation }, use) => {
    const checks = [];
    context.on("response", response => {
      // Capture failures as values immediately; report them after all pending reads.
      checks.push(verifyAsset(response).catch(error => error));
    });
    const verify = async () => {
      const results = await Promise.all(checks);
      const failure = results.find(result => result instanceof Error);
      if (failure) throw failure;
      return results.filter(Boolean);
    };
    await use(verify);
    await verify();
  }, { auto: true }],
  beaconTraffic: [async ({ context, localNetwork }, use) => {
    const traffic = await guardNetwork(context, { blocked: localNetwork.blocked });
    await use(traffic);
  }, { auto: true }]
});

module.exports = { test, expect };

const { test: base, expect } = require("@playwright/test");
const { guardNetwork } = require("./beacons");
const { localProxy } = require("./local-proxy");

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
  beaconTraffic: [async ({ context, localNetwork }, use) => {
    const traffic = await guardNetwork(context, { blocked: localNetwork.blocked });
    await use(traffic);
  }, { auto: true }]
});

module.exports = { test, expect };

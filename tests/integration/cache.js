const { expect } = require("@playwright/test");

function expectFullPageCacheHit(response) {
  expect(response.status(), "Cached storefront navigation must succeed").toBe(200);
  expect(response.headers()["x-magento-cache-debug"],
    "A real full-page-cache HIT is required. Enable FPC and expose X-Magento-Cache-Debug " +
    "(developer mode for built-in FPC, or Magento's Varnish VCL); do not synthesize this header."
  ).toBe("HIT");
}

module.exports = { expectFullPageCacheHit };

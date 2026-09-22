const { test, expect } = require("@playwright/test");
const { expectFullPageCacheHit } = require("../integration/cache");

test("cache evidence requires a successful native HIT, not just a reload", () => {
  const response = (status, value) => ({ status: () => status, headers: () => ({ "x-magento-cache-debug": value }) });
  expect(() => expectFullPageCacheHit(response(200, "HIT"))).not.toThrow();
  for (const value of [undefined, "MISS", "UNCACHEABLE", "HIT, MISS"]) {
    expect(() => expectFullPageCacheHit(response(200, value))).toThrow("A real full-page-cache HIT is required");
  }
  expect(() => expectFullPageCacheHit(response(500, "HIT"))).toThrow("Cached storefront navigation must succeed");
});

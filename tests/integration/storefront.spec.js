const { test, expect } = require("@playwright/test");

const storefrontUrl = process.env.MAGENTO_STOREFRONT_URL;
const beaconUrl = "https://collector.basicrum.test/beacon";
const siteId = "550e8400-e29b-41d4-a716-446655440000";

test.skip(!storefrontUrl, "MAGENTO_STOREFRONT_URL is required");

function requestParameters(request) {
  const parameters = new URL(request.url()).searchParams;
  const postData = request.postData();
  if (postData) {
    for (const [key, value] of new URLSearchParams(postData)) {
      parameters.set(key, value);
    }
  }
  return parameters;
}

test("rendered Magento storefront stays silent until allow and sends the expected beacon", async ({ context, page }) => {
  const errors = [];
  const beacons = [];
  let boomerangRequests = 0;

  page.on("pageerror", (error) => errors.push(error.message));
  page.on("request", (request) => {
    if (request.url().includes("BasicRum_Analytics/js/boomr/boomerang-")) {
      boomerangRequests += 1;
    }
  });
  await page.route(`${beaconUrl}*`, (route) => {
    beacons.push(route.request());
    return route.fulfill({
      status: 204,
      headers: { "access-control-allow-origin": "*" },
      body: ""
    });
  });

  const url = new URL(storefrontUrl);
  url.searchParams.set("basicrum_private", "must-not-leak");
  await page.goto(url.toString(), { waitUntil: "domcontentloaded" });
  await page.waitForFunction(() => typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function");
  await page.waitForTimeout(500);

  expect(boomerangRequests).toBe(0);
  expect(beacons).toHaveLength(0);
  expect((await context.cookies(url.toString())).some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);
  expect(await page.locator('script[src*="consent-boomerang-loader-v1-15.min.js"]').count()).toBe(1);

  await page.evaluate(() => {
    window.OPT_IN_BASICRUM_LOADER_WRAPPER();
    window.OPT_IN_BASICRUM_LOADER_WRAPPER();
  });
  await expect.poll(() => beacons.length, { timeout: 15000 }).toBeGreaterThan(0);
  expect(boomerangRequests).toBe(1);

  const parameters = requestParameters(beacons[0]);
  expect(parameters.get("p_type")).toBe("home");
  expect(parameters.get("p_gen")).toBe("mage2");
  expect(parameters.get("brum_site_id")).toBe(siteId);
  expect(parameters.get("u")).toContain("?qs-redacted");
  expect(`${beacons[0].url()}${beacons[0].postData() || ""}`).not.toContain("must-not-leak");

  await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
  expect((await context.cookies(url.toString())).some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);

  const beaconsBeforeReload = beacons.length;
  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForFunction(() => typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function");
  await page.waitForTimeout(500);
  expect(beacons).toHaveLength(beaconsBeforeReload);
  expect(await page.locator('script[src*="consent-boomerang-loader-v1-15.min.js"]').count()).toBe(1);

  await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
  await expect.poll(() => beacons.length, { timeout: 15000 }).toBeGreaterThan(beaconsBeforeReload);
  expect(boomerangRequests).toBe(2);
  expect(errors).toEqual([]);
});

const { test, expect } = require("./fixtures");
const { requestParameters, siteId } = require("./beacons");
const { expectStorefrontCsp } = require("./csp");

const storefrontUrl = process.env.MAGENTO_STOREFRONT_URL;

test.skip(!storefrontUrl, "MAGENTO_STOREFRONT_URL is required");

test("empty-cart checkout retains core and collector sources in enforcing CSP", async ({ request }) => {
  // A fresh API context has no cart. Inspect checkout's own 302, not the
  // report-only cart destination, without creating a quote or order.
  const response = await request.get(new URL("checkout/", storefrontUrl).href, { maxRedirects: 0 });
  expect(response.status()).toBe(302);
  expect(new URL(response.headers().location, storefrontUrl).pathname).toMatch(/\/checkout\/cart\/?$/);
  expectStorefrontCsp(response.headers()["content-security-policy"]);
});

test("rendered Magento storefront stays silent until allow and sends the expected beacon", async ({ context, page, beaconTraffic }) => {
  const errors = [];
  const { beacons } = beaconTraffic;
  let boomerangRequests = 0;

  page.on("pageerror", (error) => errors.push(error.message));
  page.on("request", (request) => {
    if (request.url().includes("Basicrum_Analytics/js/boomr/boomerang-")) {
      boomerangRequests += 1;
    }
  });
  const url = new URL(storefrontUrl);
  url.searchParams.set("basicrum_private", "must-not-leak");
  const response = await page.goto(url.toString(), { waitUntil: "domcontentloaded" });
  const headers = response.headers();
  const csp = headers["content-security-policy"] || headers["content-security-policy-report-only"];
  // Area-level DI once replaced all core collectors. Check the merged header,
  // not only Basicrum's isolated policy DTOs or the presence of a beacon.
  expectStorefrontCsp(csp);
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
  expect(parameters.get("p_type")).toBe("Home");
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

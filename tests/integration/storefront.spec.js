const { test, expect } = require("./fixtures");
const { guardNetwork, requestParameters, siteId } = require("./beacons");
const { expectStorefrontCsp } = require("./csp");
const { expectFullPageCacheHit } = require("./cache");
const { verifyAsset } = require("./served-assets");
const { randomUUID } = require("node:crypto");

const storefrontUrl = process.env.MAGENTO_STOREFRONT_URL;

function expectHomeBeacon(beacon) {
  const parameters = requestParameters(beacon);
  expect(parameters.get("p_type")).toBe("Home");
  expect(parameters.get("p_gen")).toBe("mage2");
  expect(parameters.get("brum_site_id")).toBe(siteId);
  expect(parameters.get("u")).toContain("?qs-redacted");
  expect(`${beacon.url()}${beacon.postData() || ""}`).not.toContain("must-not-leak");
}

test.skip(!storefrontUrl, "MAGENTO_STOREFRONT_URL is required");

test("empty-cart checkout retains core and collector sources in enforcing CSP", async ({ request }) => {
  // A fresh API context has no cart. Inspect checkout's own 302, not the
  // report-only cart destination, without creating a quote or order.
  const response = await request.get(new URL("checkout/", storefrontUrl).href, { maxRedirects: 0 });
  expect(response.status()).toBe(302);
  expect(new URL(response.headers().location, storefrontUrl).pathname).toMatch(/\/checkout\/cart\/?$/);
  expectStorefrontCsp(response.headers()["content-security-policy"]);
});

test("rendered Magento storefront stays silent until allow and sends the expected beacon", async ({ context, page, beaconTraffic, assetEvidence }) => {
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

  // Warm the cookie/vary context established by the first anonymous visit.
  // Routing disables browser HTTP caching; the final navigation below must hit server FPC.
  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForFunction(() => typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function");
  expect(boomerangRequests).toBe(0);
  expect(beacons).toHaveLength(0);

  await page.evaluate(() => {
    window.OPT_IN_BASICRUM_LOADER_WRAPPER();
    window.OPT_IN_BASICRUM_LOADER_WRAPPER();
  });
  await expect.poll(() => beacons.length, { timeout: 15000 }).toBeGreaterThan(0);
  expect(boomerangRequests).toBe(1);

  expectHomeBeacon(beacons[0]);
  expect(await assetEvidence()).toEqual(expect.arrayContaining([
    "js/loaders/consent-boomerang-loader-v1-15.min.js",
    "js/boomr/boomerang-1.815.60.cutting-edge.min.js"
  ]));

  await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
  expect((await context.cookies(url.toString())).some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);

  const beaconsBeforeReload = beacons.length;
  const cachedResponse = await page.reload({ waitUntil: "domcontentloaded" });
  expectFullPageCacheHit(cachedResponse);
  await page.waitForFunction(() => typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function");
  await page.waitForTimeout(500);
  expect(beacons).toHaveLength(beaconsBeforeReload);
  expect(boomerangRequests).toBe(1);
  expect((await context.cookies(url.toString())).some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);
  expect(await page.locator('script[src*="consent-boomerang-loader-v1-15.min.js"]').count()).toBe(1);

  await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
  await expect.poll(() => beacons.length, { timeout: 15000 }).toBeGreaterThan(beaconsBeforeReload);
  expect(boomerangRequests).toBe(2);
  expectHomeBeacon(beacons[beaconsBeforeReload]);
  expect(errors).toEqual([]);
});

test("independent visitors share cached configuration but never a consent grant", async ({ browser, page, beaconTraffic, localNetwork }) => {
  const secondContext = await browser.newContext({
    proxy: localNetwork.options, ignoreHTTPSErrors: true, serviceWorkers: "block"
  });
  const otherTraffic = await guardNetwork(secondContext, { blocked: localNetwork.blocked });
  const assetChecks = [];
  let otherBoomerangRequests = 0;
  secondContext.on("request", request => {
    if (request.url().includes("Basicrum_Analytics/js/boomr/boomerang-")) otherBoomerangRequests++;
  });
  secondContext.on("response", response => assetChecks.push(verifyAsset(response).catch(error => error)));
  const otherPage = await secondContext.newPage();
  const consentedProbePage = await page.context().newPage();
  const configScript = p => p.locator("script:not([src])").evaluateAll(scripts =>
    scripts.find(script => script.textContent.includes("w.basicRumBoomerangConfig ="))?.textContent
  );
  try {
    // Establish the second visitor's anonymous Magento vary context before the probe.
    await otherPage.goto(storefrontUrl);
    await page.goto(storefrontUrl);
    await page.reload();
    await page.waitForFunction(() => typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function");
    await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
    await expect.poll(() => beaconTraffic.beacons.length).toBeGreaterThan(0);
    expect((await page.context().cookies()).some(cookie => ["RT", "BA"].includes(cookie.name))).toBe(true);

    // Populate a new cache entry from a request carrying measurement cookies.
    // A pre-consent entry cannot prove isolation from a consented visitor.
    const probe = new URL(storefrontUrl);
    probe.searchParams.set("basicrum_fpc_probe", randomUUID());
    // Use another tab in the same cookie jar: this isolates cache population
    // from Chromium's navigation-time sendBeacon interception limitations.
    const populated = await consentedProbePage.goto(probe.href);
    expect(populated.status()).toBe(200);
    expect(populated.headers()["x-magento-cache-debug"]).toBe("MISS");
    const cached = await otherPage.goto(probe.href);
    expectFullPageCacheHit(cached);
    await otherPage.waitForFunction(() => typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function");
    await otherPage.waitForTimeout(500);
    const firstConfig = await configScript(consentedProbePage);
    expect(firstConfig).toContain("w.basicRumBoomerangConfig =");
    expect(await configScript(otherPage)).toBe(firstConfig);
    expect(otherTraffic.beacons).toHaveLength(0);
    expect(otherBoomerangRequests).toBe(0);
    expect((await secondContext.cookies()).some(cookie => ["RT", "BA"].includes(cookie.name))).toBe(false);
    expect(await otherPage.evaluate(() => !!window.BOOMR?.version)).toBe(false);
    await otherPage.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
    await expect.poll(() => otherTraffic.beacons.length).toBeGreaterThan(0);
    expect(otherBoomerangRequests).toBe(1);
    expect(requestParameters(otherTraffic.beacons[0]).get("brum_site_id")).toBe(siteId);
    const assets = await Promise.all(assetChecks);
    const error = assets.find(result => result instanceof Error);
    if (error) throw error;
    expect(assets).toContain("js/boomr/boomerang-1.815.60.cutting-edge.min.js");
    await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
    await otherPage.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
  } finally {
    await consentedProbePage.close();
    await secondContext.close();
  }
});

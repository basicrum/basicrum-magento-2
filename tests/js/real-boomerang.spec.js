const fs = require("node:fs");
const path = require("node:path");
const { test, expect } = require("@playwright/test");

const root = path.resolve(__dirname, "../..");
const shopUrl = "https://shop.example.test/";
const boomerangUrl = "https://shop.test/static/version123/Basicrum_Analytics/js/boomr/boomerang-1.815.60.cutting-edge.min.js";
const beaconUrl = "https://collector.example.test/beacon";
const siteId = "550e8400-e29b-41d4-a716-446655440000";
const realBoomerang = fs.readFileSync(
  path.join(root, "view/frontend/web/js/boomr/boomerang-1.815.60.cutting-edge.min.js"),
  "utf8"
);

function loaderPath(file) {
  return path.join(root, "view/frontend/web/js/loaders", file);
}

function requestParameters(request) {
  const parameters = new URL(request.url).searchParams;
  if (request.postData) {
    for (const [key, value] of new URLSearchParams(request.postData)) {
      parameters.set(key, value);
    }
  }
  return parameters;
}

async function prepareRealPage(page, options = {}) {
  let releaseDownload;
  let markDownloadStarted;
  let boomerangRequests = 0;
  const beaconRequestData = [];
  const pageUrl = options.pageUrl || shopUrl;
  const resourceUrl = options.resourceUrl;
  const downloadGate = options.holdDownload
    ? new Promise((resolve) => { releaseDownload = resolve; })
    : Promise.resolve();
  const downloadStarted = new Promise((resolve) => { markDownloadStarted = resolve; });

  await page.route(`${shopUrl}*`, (route) => route.fulfill({
    contentType: "text/html",
    body: `<!doctype html><html><head><link rel="icon" href="data:,">${
      resourceUrl ? `<link rel="stylesheet" href="${resourceUrl}">` : ""
    }</head><body></body></html>`
  }));
  if (resourceUrl) {
    await page.route(resourceUrl, (route) => route.fulfill({
      status: 200,
      contentType: "text/css; charset=utf-8",
      body: "body { color: #222; }"
    }));
  }
  await page.route(`${beaconUrl}*`, (route) => {
    beaconRequestData.push({
      url: route.request().url(),
      postData: route.request().postData()
    });
    return route.fulfill({
      status: 204,
      headers: { "access-control-allow-origin": "*" },
      body: ""
    });
  });
  await page.route(boomerangUrl, async (route) => {
    boomerangRequests += 1;
    markDownloadStarted();
    await downloadGate;
    await route.fulfill({
      status: 200,
      contentType: "application/javascript; charset=utf-8",
      body: realBoomerang
    });
  });

  await page.goto(pageUrl, options.referrerUrl ? { referer: options.referrerUrl } : undefined);
  // This is freshly rendered by the real Config, Footer and footer.phtml in
  // global setup, not a JavaScript reimplementation of the PHP wait plugin.
  const fixtures = JSON.parse(fs.readFileSync(path.join(root, ".test-results/rendered-footer.json"), "utf8"));
  const fixture = options.fixture || (options.stripQueryString ? "redacted" : "default");
  expect(fixtures[fixture]).toBeTruthy();
  await page.addScriptTag({ content: fixtures[fixture] });

  return {
    downloadStarted,
    releaseDownload: () => releaseDownload && releaseDownload(),
    boomerangRequests: () => boomerangRequests,
    beaconRequests: () => beaconRequestData.length,
    beaconRequestData: () => beaconRequestData
  };
}

async function waitForRealBoomerang(page) {
  await expect.poll(
    () => page.evaluate(() => window.BOOMR && window.BOOMR.version)
  ).toBe("1.815.60");
}

async function pauseWaitClock(page) {
  await page.clock.install({ time: new Date("2026-01-01T00:00:00Z") });
  await page.clock.pauseAt(new Date("2026-01-01T00:00:01Z"));
}

async function waitForRenderedTimer(page) {
  await expect.poll(async () => {
    // Advance deferred Boomerang initialization, without advancing the 1s wait.
    await page.clock.runFor(10);
    return page.evaluate(() => {
      const plugin = window.BOOMR?.plugins?.WaitAfterOnload;
      return Boolean(plugin && plugin.timer !== null && !plugin.complete);
    });
  }).toBe(true);
}

for (const standardLoader of ["boomerang-loader-v15.js", "boomerang-loader-v15.min.js"]) {
  test(`real Boomerang redacts URLs and emits Magento identity: ${standardLoader}`, async ({ page }) => {
    const secret = "BASICRUM_PRIVATE_QUERY_VALUE_7c0f1e";
    const referrerUrl = `${shopUrl}previous?source=${secret}`;
    const resourceUrl = `https://resource.example.test/private.css?token=${secret}`;
    const gate = await prepareRealPage(page, {
      pageUrl: `${shopUrl}?customer=${secret}&campaign=test`,
      stripQueryString: true,
      referrerUrl,
      resourceUrl
    });

    await page.addScriptTag({ path: loaderPath(standardLoader) });
    await gate.downloadStarted;
    await waitForRealBoomerang(page);
    await expect.poll(() => gate.beaconRequests(), { timeout: 10000 }).toBeGreaterThan(0);

    const request = gate.beaconRequestData()[0];
    const parameters = requestParameters(request);
    expect(parameters.get("u")).toBe(`${shopUrl}?qs-redacted`);
    expect(parameters.get("p_type")).toBe("Product");
    expect(parameters.get("p_gen")).toBe("mage2");
    expect(parameters.get("brum_site_id")).toBe(siteId);
    expect(parameters.get("r")).toContain("?qs-redacted");
    expect(parameters.get("restiming")).toContain("qs-redacted");
    expect(JSON.stringify(gate.beaconRequestData())).not.toContain(secret);
  });
}

for (const consentLoader of [
  "consent-boomerang-loader-v1-15.js",
  "consent-boomerang-loader-v1-15.min.js"
]) {
  test.describe(`real Boomerang consent lifecycle: ${consentLoader}`, () => {
    test("the rendered wait plugin delays the beacon, then completes", async ({ page }) => {
      await pauseWaitClock(page);
      const gate = await prepareRealPage(page, { fixture: "delayed" });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      await waitForRealBoomerang(page);
      await waitForRenderedTimer(page);
      await page.clock.runFor(500);
      expect(gate.beaconRequests()).toBe(0);
      expect(await page.evaluate(() => window.BOOMR.plugins.WaitAfterOnload.is_complete())).toBe(false);
      await page.clock.runFor(600);
      await expect.poll(() => gate.beaconRequests()).toBe(1);
      expect(await page.evaluate(() => window.BOOMR.plugins.WaitAfterOnload.is_complete())).toBe(true);
      expect(await page.evaluate(() => window.BOOMR.plugins.WaitAfterOnload.timer)).toBe(null);
      expect(requestParameters(gate.beaconRequestData()[0]).get("brum_site_id")).toBe(siteId);
    });

    for (const fixture of ["default", "disabledWait", "zeroWait"]) {
      test(`rendered ${fixture} configuration omits the wait plugin`, async ({ page }) => {
        const gate = await prepareRealPage(page, { fixture });
        expect(await page.evaluate(() => window.BOOMR.plugins?.WaitAfterOnload)).toBeUndefined();
        await page.addScriptTag({ path: loaderPath(consentLoader) });
        await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
        await expect.poll(() => gate.beaconRequests()).toBeGreaterThan(0);
      });
    }

    test("is silent before allow and loads once after repeated allow", async ({ context, page }) => {
      const gate = await prepareRealPage(page);
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.waitForTimeout(300);
      expect(gate.boomerangRequests()).toBe(0);
      expect(gate.beaconRequests()).toBe(0);
      expect((await context.cookies(shopUrl)).some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);

      await page.evaluate(() => {
        window.OPT_IN_BASICRUM_LOADER_WRAPPER();
        window.OPT_IN_BASICRUM_LOADER_WRAPPER();
      });
      await gate.downloadStarted;
      await waitForRealBoomerang(page);
      await expect.poll(() => gate.beaconRequests(), { timeout: 10000 }).toBeGreaterThan(0);
      expect(gate.boomerangRequests()).toBe(1);

      const parameters = requestParameters(gate.beaconRequestData()[0]);
      expect(parameters.get("p_gen")).toBe("mage2");
      expect(parameters.get("brum_site_id")).toBe(siteId);
      expect((await context.cookies(shopUrl)).some((cookie) => cookie.name === "RT")).toBe(true);
      expect((await context.cookies(shopUrl)).some((cookie) => cookie.name === "BRUM_CONSENT")).toBe(false);
    });

    test("denial before loading remains eligible for a later allow", async ({ page }) => {
      const gate = await prepareRealPage(page);
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      expect(gate.boomerangRequests()).toBe(0);
      expect(gate.beaconRequests()).toBe(0);

      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      await waitForRealBoomerang(page);
      await expect.poll(() => gate.beaconRequests(), { timeout: 10000 }).toBeGreaterThan(0);
    });

    test("withdrawal during download leaves the arrived bundle inert", async ({ context, page }) => {
      const gate = await prepareRealPage(page, { holdDownload: true });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      gate.releaseDownload();
      await waitForRealBoomerang(page);
      await page.waitForTimeout(1000);

      expect(gate.beaconRequests()).toBe(0);
      expect((await context.cookies(shopUrl)).some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);
      expect(await page.evaluate(() => window.basicRumInitConfig || null)).toBe(null);
    });

    test("withdrawal after initialization cancels the pending page-load beacon", async ({ context, page }) => {
      await pauseWaitClock(page);
      const gate = await prepareRealPage(page, { fixture: "delayed" });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      await waitForRealBoomerang(page);
      await waitForRenderedTimer(page);
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      expect(await page.evaluate(() => window.BOOMR.plugins.WaitAfterOnload.timer)).toBe(null);
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await page.clock.runFor(1500);
      expect(await page.evaluate(() => window.BOOMR.plugins.WaitAfterOnload.is_complete())).toBe(false);

      expect(gate.beaconRequests()).toBe(0);
      expect(gate.boomerangRequests()).toBe(1);
      expect((await context.cookies(shopUrl)).some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);
      expect(await page.evaluate(() => window.basicRumBoomerangConfig)).toBe(null);
    });

    test("withdrawal after a beacon disables further sends and blocks same-page re-grant", async ({ context, page }) => {
      const gate = await prepareRealPage(page);
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      await waitForRealBoomerang(page);
      await expect.poll(() => gate.beaconRequests(), { timeout: 10000 }).toBeGreaterThan(0);

      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      const countAfterWithdrawal = gate.beaconRequests();
      await page.evaluate(() => {
        window.OPT_IN_BASICRUM_LOADER_WRAPPER();
        window.BOOMR.sendBeacon();
      });
      await page.waitForTimeout(500);

      expect(gate.beaconRequests()).toBe(countAfterWithdrawal);
      expect(gate.boomerangRequests()).toBe(1);
      expect((await context.cookies(shopUrl)).some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);
    });
  });
}

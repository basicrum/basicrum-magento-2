const path = require("node:path");
const { test: base, expect } = require("@playwright/test");

const test = base.extend({
  page: async ({ page }, use) => {
    const unexpectedRequests = [];
    await page.route("**/*", async (route) => {
      unexpectedRequests.push(route.request().url());
      await route.abort("blockedbyclient");
    });
    await use(page);
    expect(unexpectedRequests).toEqual([]);
  }
});

const root = path.resolve(__dirname, "../..");
const shopUrl = "https://shop.example.test/";
const boomerangUrl = "https://assets.example.test/boomerang.js";
const bundleStub = `
window.__bundleExecutions = (window.__bundleExecutions || 0) + 1;
window.BOOMR = window.BOOMR || {};
window.BOOMR.version = "test";
window.BOOMR.window = window;
window.BOOMR.init = function(config) {
  window.__initCalls = (window.__initCalls || 0) + 1;
  window.__lastConfig = config;
};
window.BOOMR.disable = function() {
  window.__disableCalls = (window.__disableCalls || 0) + 1;
};
window.BOOMR.utils = {
  removeCookie: function(name) {
    window.__utilityCookieRemovals = window.__utilityCookieRemovals || [];
    window.__utilityCookieRemovals.push(name);
  }
};
window.basicRumInitConfig = window.basicRumBoomerangConfig;
if (window.basicRumInitConfig) {
  window.BOOMR.init(window.basicRumInitConfig);
}
`;

function loaderPath(file) {
  return path.join(root, "view/frontend/web/js/loaders", file);
}

async function preparePage(page, options = {}) {
  let releaseDownload;
  let markDownloadStarted;
  let boomerangRequests = 0;
  const downloadGate = options.holdDownload
    ? new Promise((resolve) => { releaseDownload = resolve; })
    : Promise.resolve();
  const downloadStarted = new Promise((resolve) => { markDownloadStarted = resolve; });

  await page.route(shopUrl, (route) => route.fulfill({
    contentType: "text/html",
    body: '<!doctype html><html><head><link rel="icon" href="data:,"></head><body></body></html>'
  }));
  await page.route(boomerangUrl, async (route) => {
    boomerangRequests += 1;
    markDownloadStarted();
    await downloadGate;
    await route.fulfill({ contentType: "application/javascript", body: bundleStub });
  });

  if (options.cookies) {
    await page.context().addCookies(options.cookies.map((name) => ({
      name,
      value: "legacy",
      domain: "shop.example.test",
      path: "/"
    })));
  }

  await page.goto(shopUrl);
  await page.evaluate((url) => {
    window.BOOMR = { url };
    window.basicRumBoomerangConfig = { beacon_url: "https://collector.example.test/beacon" };
    window.__initCalls = 0;
    window.__bundleExecutions = 0;
    window.__disableCalls = 0;
    window.__utilityCookieRemovals = [];
  }, boomerangUrl);

  return {
    downloadStarted,
    boomerangRequests: () => boomerangRequests,
    releaseDownload: () => releaseDownload && releaseDownload()
  };
}

for (const standardLoader of ["boomerang-loader-v15.js", "boomerang-loader-v15.min.js"]) {
  test(`immediate loader executes Boomerang once: ${standardLoader}`, async ({ page }) => {
    const harness = await preparePage(page);
    await page.addScriptTag({ path: loaderPath(standardLoader) });
    await page.waitForFunction(() => window.__initCalls === 1);
    await page.addScriptTag({ path: loaderPath(standardLoader) });
    await page.waitForTimeout(100);

    await expect.poll(() => page.evaluate(() => ({
      initCalls: window.__initCalls,
      executions: window.__bundleExecutions
    }))).toEqual({ initCalls: 1, executions: 1 });
    expect(harness.boomerangRequests()).toBe(1);
  });
}

for (const loader of [
  "boomerang-loader-v15.js",
  "boomerang-loader-v15.min.js",
  "consent-boomerang-loader-v1-15.js",
  "consent-boomerang-loader-v1-15.min.js"
]) {
  test(`requires an own nonempty Boomerang URL: ${loader}`, async ({ page }) => {
    const harness = await preparePage(page);
    for (const urlState of ["missing", "empty", "inherited"]) {
      await page.evaluate(({ state, url }) => {
        window.BOOMR = state === "inherited" ? Object.create({ url })
          : state === "empty" ? { url: "" } : {};
      }, { state: urlState, url: boomerangUrl });
      await page.addScriptTag({ path: loaderPath(loader) });
      if (loader.startsWith("consent-")) {
        await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      }
      expect(await page.evaluate(() => ({
        executions: window.__bundleExecutions,
        snippetExecuted: Boolean(window.BOOMR.snippetExecuted),
        injectedScripts: document.querySelectorAll("#boomr-scr-as, #boomr-if-as, #boomr-async").length,
        preloads: document.querySelectorAll('link[rel="preload"]').length
      }))).toEqual({ executions: 0, snippetExecuted: false, injectedScripts: 0, preloads: 0 });
      expect(harness.boomerangRequests()).toBe(0);
    }
  });
}

for (const consentLoader of [
  "consent-boomerang-loader-v1-15.js",
  "consent-boomerang-loader-v1-15.min.js"
]) {
  test.describe(`consent wrapper: ${consentLoader}`, () => {
    test("stays inert despite a legacy allow cookie", async ({ page }) => {
      const harness = await preparePage(page, { cookies: ["BRUM_CONSENT"] });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.waitForTimeout(200);

      expect(await page.evaluate(() => ({
        initCalls: window.__initCalls,
        executions: window.__bundleExecutions,
        canonicalIn: typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER,
        canonicalOut: typeof window.OPT_OUT_BASICRUM_LOADER_WRAPPER,
        snippetExecuted: Boolean(window.BOOMR.snippetExecuted)
      }))).toEqual({
        initCalls: 0,
        executions: 0,
        canonicalIn: "function",
        canonicalOut: "function",
        snippetExecuted: false
      });
      expect(harness.boomerangRequests()).toBe(0);
    });

    test("repeated allow loads once and persists no Basicrum consent cookie", async ({ page }) => {
      const harness = await preparePage(page);
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => {
        window.OPT_IN_BASICRUM_LOADER_WRAPPER();
        window.OPT_IN_BASICRUM_LOADER_WRAPPER();
      });
      await page.waitForFunction(() => window.__initCalls === 1);
      await page.waitForTimeout(100);

      expect(await page.evaluate(() => ({
        initCalls: window.__initCalls,
        executions: window.__bundleExecutions,
        cookies: document.cookie
      }))).toEqual({ initCalls: 1, executions: 1, cookies: "" });
      expect(harness.boomerangRequests()).toBe(1);
    });

    test("denial before loading cleans cookies and permits a later allow", async ({ page }) => {
      const cookieNames = ["RT", "BA", "BRUM_CONSENT", "BOOMR_CONSENT"];
      await preparePage(page, { cookies: cookieNames });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());

      expect(await page.evaluate(() => ({
        cookies: document.cookie,
        configPresent: Boolean(window.basicRumBoomerangConfig),
        executions: window.__bundleExecutions
      }))).toEqual({ cookies: "", configPresent: true, executions: 0 });

      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await page.waitForFunction(() => window.__initCalls === 1);
      expect(await page.evaluate(() => window.__bundleExecutions)).toBe(1);
    });

    test("withdrawal during download prevents initialization and same-page re-grant", async ({ page }) => {
      const gate = await preparePage(page, { holdDownload: true });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      gate.releaseDownload();
      await page.waitForFunction(() => window.__bundleExecutions === 1);
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await page.waitForTimeout(100);

      expect(await page.evaluate(() => ({
        initCalls: window.__initCalls,
        executions: window.__bundleExecutions,
        config: window.basicRumBoomerangConfig
      }))).toEqual({ initCalls: 0, executions: 1, config: null });
    });

    test("withdrawal after initialization disables collection and clears cookies", async ({ page }) => {
      const cookieNames = ["RT", "BA", "BRUM_CONSENT", "BOOMR_CONSENT"];
      await preparePage(page, { cookies: cookieNames });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await page.waitForFunction(() => window.__initCalls === 1);
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());

      expect(await page.evaluate(() => ({
        initCalls: window.__initCalls,
        executions: window.__bundleExecutions,
        disableCalls: window.__disableCalls,
        cookies: document.cookie,
        removals: window.__utilityCookieRemovals.sort()
      }))).toEqual({
        initCalls: 1,
        executions: 1,
        disableCalls: 1,
        cookies: "",
        removals: ["BA", "BOOMR_CONSENT", "BRUM_CONSENT", "RT"]
      });
    });
  });
}

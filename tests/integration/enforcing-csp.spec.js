const { test, expect } = require("./fixtures");
const { expectEnforcingScriptCsp } = require("./csp");
const { beaconUrl, requestParameters, siteId } = require("./beacons");

const storefrontUrl = process.env.MAGENTO_STOREFRONT_URL;
test.skip(!storefrontUrl, "MAGENTO_STOREFRONT_URL is required");

test.beforeEach(async ({ page }) => {
  // Observe from document creation; never alter CSP, script nonces or production assets.
  await page.addInitScript(() => {
    window.__basicrumCspViolations = [];
    document.addEventListener("securitypolicyviolation", event => {
      window.__basicrumCspViolations.push({
        directive: event.effectiveDirective,
        disposition: event.disposition,
        blockedURI: event.blockedURI.split("?")[0],
        sourceFile: event.sourceFile.split("?")[0]
      });
    });
  });
});

async function openEnforcingPage(page) {
  const url = new URL("basicrumcsptest/", storefrontUrl);
  const response = await page.goto(url.href, { waitUntil: "load" });
  expect(response.status()).toBe(200);
  expect(new URL(page.url()).pathname).toBe(url.pathname);
  await expect(page.locator("#basicrum-csp-test")).toBeVisible();
  // The fixture is deliberately uncacheable, like checkout. Do not certify nonce reuse on FPC HITs.
  expect(response.headers()["cache-control"]).toMatch(/\bno-store\b/);
  expect(response.headers()["x-magento-cache-debug"]).not.toBe("HIT");
  // Text locators exclude script content; browsers also hide the nonce attribute.
  // Inspect the actual DOM text and nonce property without modifying the script.
  const nonces = await page.locator("script:not([src])").evaluateAll(scripts => scripts
    .filter(script => script.textContent.includes("w.basicRumBoomerangConfig ="))
    .map(script => script.nonce));
  expect(nonces).toHaveLength(1);
  const [nonce] = nonces;
  expectEnforcingScriptCsp(response.headers(), nonce);
  await page.waitForFunction(() => typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function");
  return nonce;
}

test("native enforcing CSP permits first-party scripts and consent-gated beacons with fresh nonces", async ({
  page, context, beaconTraffic, assetEvidence
}) => {
  const { beacons } = beaconTraffic;
  const errors = [];
  const bundles = [];
  page.on("pageerror", error => errors.push(error.message));
  page.on("request", request => {
    if (request.url().includes("Basicrum_Analytics/js/boomr/boomerang-")) bundles.push(request.url());
  });
  let previousNonce;
  for (let visit = 0; visit < 2; visit++) {
    const before = beacons.length;
    const nonce = await openEnforcingPage(page);
    expect(nonce).not.toBe(previousNonce);
    previousNonce = nonce;
    await page.waitForTimeout(500);
    expect(bundles).toHaveLength(visit);
    expect(beacons).toHaveLength(before);
    expect((await context.cookies()).filter(cookie => ["RT", "BA"].includes(cookie.name))).toEqual([]);
    expect(await page.evaluate(() => window.BOOMR.version)).toBeUndefined();
    expect(await page.evaluate(() => window.basicRumBoomerangConfig.beacon_url)).toBe(beaconUrl);

    const loader = page.locator('script[src*="Basicrum_Analytics/js/loaders/consent-boomerang-loader-v1-15.min.js"]');
    await expect(loader).toHaveCount(1);
    expect(new URL(await loader.getAttribute("src"), page.url()).origin).toBe(new URL(storefrontUrl).origin);
    await page.evaluate(() => {
      window.OPT_IN_BASICRUM_LOADER_WRAPPER();
      window.OPT_IN_BASICRUM_LOADER_WRAPPER();
    });
    await expect.poll(() => beacons.length).toBeGreaterThan(before);
    expect(bundles).toHaveLength(visit + 1);
    expect(new URL(bundles[visit]).origin).toBe(new URL(storefrontUrl).origin);
    expect(await page.evaluate(() => window.BOOMR.version)).toBe("1.815.60");
    const parameters = requestParameters(beacons[before]);
    expect(parameters.get("p_gen")).toBe("mage2");
    expect(parameters.get("p_type")).toBe("unmapped_basicrumcsptest_index_index");
    expect(parameters.get("brum_site_id")).toBe(siteId);
    expect(await assetEvidence()).toEqual(expect.arrayContaining([
      "js/loaders/consent-boomerang-loader-v1-15.min.js",
      "js/boomr/boomerang-1.815.60.cutting-edge.min.js"
    ]));
    await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
    expect((await context.cookies()).filter(cookie => ["RT", "BA"].includes(cookie.name))).toEqual([]);
    expect(await page.evaluate(() => window.__basicrumCspViolations)).toEqual([]);
    expect(errors).toEqual([]);
  }
});

test("native enforcing CSP actually blocks an unnonced inline control script", async ({ page, beaconTraffic }) => {
  await openEnforcingPage(page);
  expect(await page.evaluate(() => window.__basicrumCspViolations)).toEqual([]);
  // Deliberate negative control, not a replacement for the production bootstrap.
  await page.evaluate(() => {
    const script = document.createElement("script");
    script.textContent = "window.__basicrumUnnoncedControlRan = true;";
    document.body.appendChild(script);
  });
  await expect.poll(() => page.evaluate(() => window.__basicrumCspViolations.some(event =>
    event.disposition === "enforce" && event.blockedURI === "inline" &&
    ["script-src", "script-src-elem"].includes(event.directive)
  ))).toBe(true);
  expect(await page.evaluate(() => window.__basicrumUnnoncedControlRan)).toBeUndefined();
  expect(beaconTraffic.beacons).toEqual([]);
});

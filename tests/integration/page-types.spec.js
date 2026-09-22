const { test, expect } = require("@playwright/test");
const { interceptBeacons, requestParameters, siteId } = require("./beacons");

const storefrontUrl = process.env.MAGENTO_STOREFRONT_URL;
test.skip(!storefrontUrl, "MAGENTO_STOREFRONT_URL is required");

const pages = [
  ["", "Home"],
  ["catalogsearch/result/?q=bag", "Search"],
  ["catalogsearch/advanced/", "Advanced Search"],
  ["checkout/cart/", "Cart"],
  ["customer/account/login/", "Login"],
  ["customer/account/create/", "Register"],
  ["customer/account/logoutSuccess/", "Logout Success"],
  ["contact/", "Contact"],
  ["sales/guest/form/", "Orders and Returns"],
  ["customer/account/forgotpassword/", "Forgot Password"],
  ["basicrum-test-missing-page", "404 Not Found"],
  ["search/term/popular/", "unmapped_search_term_popular"],
  // Assert the rendered destination, not the originally requested URL.
  ["customer/account/", "Login"],
  ["checkout/onepage/success/", "Cart"],
  ["checkout/", "Cart"]
];

function pageTypeTest(path, label, needsSampleData = false) {
  test(`${path || "/"} emits ${label}`, async ({ page }) => {
    test.skip(needsSampleData && process.env.MAGENTO_SAMPLE_DATA !== "1", "Magento Luma sample data is required");
    const beacons = await interceptBeacons(page);
    await page.goto(new URL(path, storefrontUrl).toString(), { waitUntil: "domcontentloaded" });
    await page.waitForFunction(() => typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function");
    expect(beacons).toHaveLength(0);
    await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
    await expect.poll(() => beacons.length, { timeout: 15000 }).toBeGreaterThan(0);
    const parameters = requestParameters(beacons[0]);
    expect(parameters.get("p_type")).toBe(label);
    expect(parameters.get("p_gen")).toBe("mage2");
    expect(parameters.get("brum_site_id")).toBe(siteId);
  });
}

for (const [path, label] of pages) {
  pageTypeTest(path, label);
}
for (const [path, label] of [
  ["about-us", "CMS Page"],
  ["fusion-backpack.html", "Product"],
  ["gear/bags.html", "Category"]
]) {
  pageTypeTest(path, label, true);
}

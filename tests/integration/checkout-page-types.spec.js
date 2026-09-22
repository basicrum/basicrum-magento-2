const { test, expect } = require("./fixtures");
const { requestParameters, siteId } = require("./beacons");

const storefrontUrl = process.env.MAGENTO_STOREFRONT_URL;
test.skip(
  !storefrontUrl || process.env.BASICRUM_DISPOSABLE_MAGENTO !== "1" || process.env.MAGENTO_TEST_CHECKOUT !== "1",
  "Explicit disposable-store and offline checkout opt-ins are required; this test creates an order"
);

test("a real offline Luma checkout emits Checkout then Checkout Success", async ({ page, beaconTraffic }) => {
  test.setTimeout(90000);
  const { beacons } = beaconTraffic;

  async function expectPageBeacon(label) {
    await page.waitForFunction(() => typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === "function");
    const before = beacons.length;
    await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
    await expect.poll(() => beacons.slice(before).some((request) => requestParameters(request).get("p_type") === label), {
      timeout: 15000
    }).toBe(true);
    const parameters = requestParameters(beacons.slice(before).find((request) => requestParameters(request).get("p_type") === label));
    expect(parameters.get("p_gen")).toBe("mage2");
    expect(parameters.get("brum_site_id")).toBe(siteId);
  }

  await page.goto(new URL("fusion-backpack.html", storefrontUrl).toString());
  await page.getByRole("button", { name: "Add to Cart", exact: true }).click();
  await expect(page.locator(".message-success")).toContainText("Fusion Backpack");
  await page.goto(new URL("checkout/", storefrontUrl).toString());
  await expect(page.locator("#customer-email")).toBeVisible();
  await expectPageBeacon("Checkout");

  await page.locator("#customer-email").fill("basicrum-page-types@example.test");
  await page.locator('input[name="firstname"]').fill("Basicrum");
  await page.locator('input[name="lastname"]').fill("Test");
  await page.locator('input[name="street[0]"]').fill("123 Test Street");
  await page.locator('input[name="city"]').fill("Los Angeles");
  await page.locator('select[name="country_id"]').selectOption("US");
  await page.locator('select[name="region_id"]').selectOption({ label: "California" });
  await page.locator('input[name="postcode"]').fill("90001");
  await page.locator('input[name="telephone"]').fill("5550100100");
  await page.locator('input[value="flatrate_flatrate"]').check();
  await page.getByRole("button", { name: "Next", exact: true }).click();

  // Never choose a live payment provider. The fixture requires Check / Money order.
  const offlinePayment = page.locator(".payment-method").filter({ has: page.locator("#checkmo") });
  await expect(offlinePayment).toBeVisible();
  if (await page.locator("#checkmo").isVisible()) {
    await page.locator("#checkmo").check();
  }
  await expect(offlinePayment).toHaveClass(/_active/);
  await offlinePayment.getByRole("button", { name: "Place Order", exact: true }).click();
  await page.waitForURL(/\/checkout\/onepage\/success\//);
  await expectPageBeacon("Checkout Success");
});

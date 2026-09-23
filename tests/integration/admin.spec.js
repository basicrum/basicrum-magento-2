const { test, expect } = require("./fixtures");
const { expectAdminCsp } = require("./csp");

const adminUrl = process.env.MAGENTO_ADMIN_URL;
const adminUsername = process.env.MAGENTO_ADMIN_USERNAME;
const adminPassword = process.env.MAGENTO_ADMIN_PASSWORD;

test.skip(
  !adminUrl || !adminUsername || !adminPassword,
  "MAGENTO_ADMIN_URL and disposable Admin credentials are required"
);

test("Basicrum configuration renders in Magento Admin", async ({ page }) => {
  test.setTimeout(60000);
  await page.goto(adminUrl, { waitUntil: "domcontentloaded" });

  const username = page.locator("#username");
  if (await username.isVisible()) {
    await username.fill(adminUsername);
    await page.locator("#login").fill(adminPassword);
    await page.locator("button.action-login").click();
    await expect(username).toHaveCount(0);
  }

  const adminUsageDeny = page.locator(
    ".admin-usage-notification .action-secondary"
  );
  if (await adminUsageDeny.isVisible()) {
    await adminUsageDeny.click();
  }

  const configurationMenu = page.locator('[data-ui-id="menu-magento-config-system-config"] > a');
  const configurationUrl = await configurationMenu.getAttribute("href");
  expect(configurationUrl).toBeTruthy();
  await page.goto(configurationUrl, { waitUntil: "domcontentloaded" });

  const configTabs = page.locator("#system_config_tabs");
  // Magento initializes this group as a collapsible. Hidden links are not
  // accessible by role until the group is opened through its native control.
  // The tab's accessible name also contains Magento's expand/collapse icon.
  const basicrumTab = configTabs.getByRole("tab").filter({
    has: page.getByText("Basicrum", { exact: true })
  });
  await expect(basicrumTab).toBeVisible();
  if ((await basicrumTab.getAttribute("aria-expanded")) !== "true") {
    await basicrumTab.click();
  }
  await expect(basicrumTab).toHaveAttribute("aria-expanded", "true");
  const basicrumSection = configTabs.getByRole("link", { name: "Basicrum Analytics", exact: true });
  await expect(basicrumSection).toBeVisible();
  const [response] = await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle" }),
    basicrumSection.click()
  ]);
  expect(response).toBeTruthy();
  expectAdminCsp(response.headers());

  const generalSettings = page.locator('a[href="#basicrum_general-link"]');
  const logo = page.locator(".basicrum-config-logo");
  await expect
    .poll(async () => {
      if (!(await logo.isVisible())) {
        await generalSettings.click();
      }
      return logo.isVisible();
    })
    .toBe(true);
  await expect(logo.locator("span")).toHaveText("Basicrum Analytics");
  await expect(page.getByText("Monitoring Status", { exact: true })).toBeVisible();
  await expect(page.getByText("Beacon Endpoint", { exact: true })).toBeVisible();
  await expect(page.getByText("Brum Site ID", { exact: true })).toBeVisible();
  await expect(page.locator("#basicrum_consent_mode")).toHaveCount(0);

  const displayRows = [
    "basicrum_general_monitoring_status",
    "basicrum_general_boomerang_version",
    "basicrum_consent_integration_help"
  ];
  const expectDisplayOnlyRows = async () => {
    for (const id of displayRows) {
      const row = page.locator("#row_" + id);
      await expect(row).toHaveCount(1);
      await expect(row.locator('input, select, textarea, [data-config-scope]')).toHaveCount(0);
    }
  };
  await expectDisplayOnlyRows();

  // Exercise Magento's own switcher and secret-key URLs, without saving.
  for (const scope of ["website", "store-view"]) {
    await page.locator("#store-change-button").click();
    await page.locator('.store-switcher-' + scope + ' a[data-value]').first().click();
    const confirmation = page.locator(".modal-popup.confirm .action-accept");
    await expect(confirmation).toBeVisible();
    const navigation = page.waitForNavigation({ waitUntil: "networkidle" });
    await confirmation.click();
    const scopedResponse = await navigation;
    expect(scopedResponse).toBeTruthy();
    expectAdminCsp(scopedResponse.headers());
    await expectDisplayOnlyRows();
    // Real settings still inherit at both scopes; removing all checkboxes is
    // not an acceptable way to fix the display-only rows.
    for (const id of ["basicrum_general_beacon_endpoint", "basicrum_general_brum_site_id",
      "basicrum_consent_enabled", "basicrum_privacy_strip_query_string"]) {
      await expect(page.locator("#" + id + "_inherit")).toHaveCount(1);
    }
  }
});

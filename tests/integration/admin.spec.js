const { test, expect } = require("@playwright/test");

const adminUrl = process.env.MAGENTO_ADMIN_URL;
const adminUsername = process.env.MAGENTO_ADMIN_USERNAME;
const adminPassword = process.env.MAGENTO_ADMIN_PASSWORD;

test.skip(
  !adminUrl || !adminUsername || !adminPassword,
  "MAGENTO_ADMIN_URL and disposable Admin credentials are required"
);

test("Basicrum configuration renders in Magento Admin", async ({ page }) => {
  await page.goto(adminUrl, { waitUntil: "domcontentloaded" });

  const username = page.locator("#username");
  if (await username.isVisible()) {
    await username.fill(adminUsername);
    await page.locator("#login").fill(adminPassword);
    await page.locator("button.action-login").click();
    await expect(username).toHaveCount(0);
  }

  const storesMenu = page.locator('[data-ui-id="menu-magento-backend-stores"] > a');
  await expect(storesMenu).toBeVisible();
  await storesMenu.click();

  const configurationMenu = page.locator('[data-ui-id="menu-magento-config-system-config"] > a');
  await expect(configurationMenu).toBeVisible();
  await configurationMenu.click();

  const basicrumSection = page
    .locator("#system_config_tabs a")
    .filter({ hasText: "Basicrum Analytics" });
  await expect(basicrumSection).toBeVisible();
  await basicrumSection.click();

  await expect(page.getByText("Basicrum Analytics", { exact: true }).first()).toBeVisible();
  await expect(page.locator(".basicrum-config-logo")).toBeVisible();
  await expect(page.getByText("Monitoring Status", { exact: true })).toBeVisible();
  await expect(page.getByText("Beacon Endpoint", { exact: true })).toBeVisible();
  await expect(page.getByText("Brum Site ID", { exact: true })).toBeVisible();
});

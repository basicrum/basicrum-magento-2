const { defineConfig } = require("@playwright/test");

module.exports = defineConfig({
  testDir: "./tests/integration",
  testMatch: "*.spec.js",
  timeout: 30000,
  fullyParallel: false,
  workers: 1,
  forbidOnly: true,
  retries: 0,
  reporter: "line",
  outputDir: ".test-results/magento-integration",
  use: {
    browserName: "chromium",
    headless: true,
    serviceWorkers: "block",
    ignoreHTTPSErrors: true
  }
});

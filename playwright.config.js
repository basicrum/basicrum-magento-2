const { defineConfig } = require("@playwright/test");

module.exports = defineConfig({
  testDir: "./tests/js",
  testMatch: "**/*.spec.js",
  globalSetup: require.resolve("./tests/js/render-footer-fixtures.js"),
  timeout: 15000,
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  failOnFlakyTests: Boolean(process.env.CI),
  reporter: "line",
  outputDir: ".test-results/playwright",
  use: {
    browserName: "chromium",
    headless: true
  }
});

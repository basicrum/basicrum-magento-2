const { defineConfig } = require("@playwright/test");

module.exports = defineConfig({
  testDir: "./tests/js",
  testMatch: "**/*.spec.js",
  timeout: 15000,
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: "line",
  outputDir: ".test-results/playwright",
  use: {
    browserName: "chromium",
    headless: true
  }
});

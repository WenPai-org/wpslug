const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: 'tests/E2E',
  timeout: 60 * 1000,
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://127.0.0.1:8888',
    headless: true,
    ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
      ? { launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } }
      : {}),
  },
});

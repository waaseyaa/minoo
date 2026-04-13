import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  timeout: 30000,
  expect: {
    toHaveScreenshot: {
      maxDiffPixelRatio: 0.01,
    },
  },
  use: {
    baseURL: 'http://localhost:8081',
    headless: true,
  },
  webServer: {
    command: 'php -S localhost:8081 -t public',
    port: 8081,
    reuseExistingServer: true,
    timeout: 10000,
    env: {
      ...process.env,
      APP_ENV: 'testing',
      WAASEYAA_DEV_FALLBACK_ACCOUNT: 'false',
    },
  },
});

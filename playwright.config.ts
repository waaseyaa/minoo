import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  timeout: 30000,
  use: {
    baseURL: 'http://localhost:8081',
    headless: true,
  },
  webServer: {
    command: 'php -S localhost:8081 -t public',
    port: 8081,
    reuseExistingServer: true,
    timeout: 10000,
  },
});

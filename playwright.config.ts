import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  globalSetup: require.resolve('./tests/playwright/global-setup'),
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
    // Router script is required because public/admin/ and public/newsletter/
    // directories exist — without it, PHP's built-in server 404s those paths
    // instead of falling through to index.php. See #674.
    // Bind 0.0.0.0 so the port is reachable from Windows browsers via localhost (WSL2 port forward).
    // `localhost` only listens on 127.0.0.1 inside Linux and often shows "connection refused" from the host.
    command: 'php -S 0.0.0.0:8081 -t public public/index.php',
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

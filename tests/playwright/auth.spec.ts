import { test, expect } from '@playwright/test';

// These tests assert the EXPECTED redirect behavior for unauthenticated users.
// Currently both dashboard routes return HTTP 403 JSON instead of redirecting.
// Tracked as minoo#149 (v0.13 blocker). Tests are marked test.fail() until fixed.

test.describe('Auth redirects', () => {
  test.fail(
    true,
    'Known blocker #149: dashboards return 403 JSON instead of redirecting to /login',
  );

  test('coordinator dashboard redirects unauthenticated users to /login', async ({ page }) => {
    await page.goto('/dashboard/coordinator');
    await expect(page).toHaveURL(/\/login/);
  });

  test('volunteer dashboard redirects unauthenticated users to /login', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page).toHaveURL(/\/login/);
  });
});

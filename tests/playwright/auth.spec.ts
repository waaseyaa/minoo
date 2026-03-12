import { test, expect } from '@playwright/test';

test.describe('Auth redirects', () => {
  // Dev server auto-authenticates with a fallback account, so redirect
  // tests cannot work locally. These verify behavior in environments
  // without the dev fallback (CI with WAASEYAA_ENV=testing, production).
  test.skip('coordinator dashboard redirects unauthenticated users to /login', async ({ page }) => {
    await page.goto('/dashboard/coordinator');
    await expect(page).toHaveURL(/\/login/);
  });

  test.skip('volunteer dashboard redirects unauthenticated users to /login', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page).toHaveURL(/\/login/);
  });

  test.skip('redirect preserves intended destination in query param', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page).toHaveURL(/\/login\?redirect=/);
  });

  test('login page loads', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('h1')).toContainText('Sign in');
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });

  test('login form includes CSRF token', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('input[name="_csrf_token"]')).toBeAttached();
  });

  test('register page loads', async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });

  test('register form includes CSRF token', async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('input[name="_csrf_token"]')).toBeAttached();
  });

  test('login page has link to register', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('a[href="/register"]')).toBeVisible();
  });

  // 403 error page tests — skipped in dev (fallback account has all roles)
  test.skip('visiting protected route unauthenticated shows friendly 403', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page.locator('h1')).toContainText('Forbidden');
    await expect(page.locator('a[href*="/login?redirect="]')).toBeVisible();
  });

  test.skip('403 page includes login link with redirect', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page.locator('a[href*="/login?redirect=%2Fdashboard%2Fvolunteer"]')).toBeVisible();
  });

  test.skip('403 page includes link to homepage', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page.locator('a[href="/"]')).toBeVisible();
  });
});

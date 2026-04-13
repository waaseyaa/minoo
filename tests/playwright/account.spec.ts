import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.beforeAll(() => {
  execSync('php bin/seed-test-user', { cwd: process.cwd() });
});

test.describe('Account Home — member user', () => {
  test.describe.configure({ mode: 'serial' });

  test('member login redirects to feed then account page works', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'member@minoo.test');
    await page.fill('input[name="password"]', 'MemberPass123!');
    await page.click('.form button[type="submit"]');
    await page.waitForURL('/feed');

    // Navigate to account page
    await page.goto('/account');
    await expect(page.locator('h1')).toContainText('Welcome back');

    // Verify welcoming description
    await expect(page.locator('.text-secondary').first()).toContainText('your home on Minoo');

    // Verify sign out link, no volunteer links
    await expect(page.locator('.account-home a[href="/logout"]')).toBeVisible();
    await expect(page.locator('.account-home a[href="/dashboard/volunteer"]')).not.toBeVisible();
    await expect(page.locator('.account-home a[href="/dashboard/coordinator"]')).not.toBeVisible();
  });
});

test.describe('Account Home — volunteer user', () => {
  test('volunteer login redirects to feed', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@minoo.test');
    await page.fill('input[name="password"]', 'TestPass123!');
    await page.click('.form button[type="submit"]');
    await page.waitForURL('/feed');

    // Navigate to account page and verify volunteer links appear
    await page.goto('/account');
    await expect(page.locator('.account-home a[href="/dashboard/volunteer"]')).toBeVisible();
  });
});

test.describe('Account Home — unauthenticated', () => {
  test('unauthenticated /account access redirects to /login', async ({ page }) => {
    await page.goto('/account');
    await expect(page).toHaveURL(/\/login/);
  });
});

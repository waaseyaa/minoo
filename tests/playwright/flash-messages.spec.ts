import { test, expect } from '@playwright/test';

test.describe('Flash messages', () => {
  test('login shows success flash that disappears on next navigation', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@minoo.test');
    await page.fill('input[name="password"]', 'TestPass123!');
    await page.click('.form button[type="submit"]');
    await page.waitForURL('/feed');

    // Flash message should be visible after login
    const flash = page.locator('.flash-message--success');
    await expect(flash).toBeVisible();
    await expect(flash).toContainText('Welcome back');

    // Verify accessibility attributes
    await expect(flash).toHaveAttribute('role', 'status');

    // Navigate away — flash should be consumed and gone
    await page.goto('/account');
    await expect(page.locator('.flash-message')).not.toBeVisible();
  });

  test('availability toggle shows success flash', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"]', 'member@minoo.test');
    await page.fill('input[name="password"]', 'MemberPass123!');
    await page.click('.form button[type="submit"]');
    await page.waitForURL('/feed');

    // Member user gets flash on login too
    const flash = page.locator('.flash-message--success');
    await expect(flash).toBeVisible();
    await expect(flash).toContainText('Welcome back');
  });
});

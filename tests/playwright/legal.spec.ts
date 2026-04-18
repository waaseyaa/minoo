import { test, expect } from '@playwright/test';

test.describe('Legal Pages', () => {
  test('privacy policy loads', async ({ page }) => {
    await page.goto('/legal/privacy');
    await expect(page.locator('h1')).toContainText('Privacy Policy');
  });

  test('terms of use loads', async ({ page }) => {
    await page.goto('/legal/terms');
    await expect(page.locator('h1')).toContainText('Terms of Use');
  });

  test('accessibility statement loads', async ({ page }) => {
    await page.goto('/legal/accessibility');
    await expect(page.locator('h1')).toContainText('Accessibility');
  });

  test('legal index shows all three links', async ({ page }) => {
    await page.goto('/legal');
    const main = page.locator('#main-content');
    await expect(main.locator('a[href="/legal/privacy"]')).toBeVisible();
    await expect(main.locator('a[href="/legal/terms"]')).toBeVisible();
    await expect(main.locator('a[href="/legal/accessibility"]')).toBeVisible();
  });

  test('footer links to privacy policy', async ({ page }) => {
    // Footer intentionally only links Privacy. Terms & Accessibility live
    // on the /legal index page, not the global footer.
    await page.goto('/');
    await expect(page.locator('.ftr').locator('a[href="/legal/privacy"]')).toBeVisible();
  });
});

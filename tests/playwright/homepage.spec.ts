import { test, expect } from '@playwright/test';

test.describe('Homepage (anonymous)', () => {
  test('shows welcome hero', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.hero')).toBeVisible();
    await expect(page.locator('.hero h1')).toContainText('Anishinaabemowin');
  });

  test('hero CTAs link to the dictionary and games', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.hero__ctas a[href$="/language"]')).toBeVisible();
    await expect(page.locator('.hero__ctas a[href$="/games"]')).toBeVisible();
  });

  test('has skip link', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.skip-link')).toHaveAttribute('href', '#main-content');
  });

  test('explore redirect routes to section pages', async ({ page }) => {
    const response = await page.goto('/explore?type=events');
    expect(response?.url()).toContain('/events');
  });

  test('/feed requires authentication (401 for anonymous)', async ({ page }) => {
    // Post-relaunch, /feed is auth-only (requireAuthentication). Anonymous
    // visitors get a 401 rather than a redirect home.
    const response = await page.goto('/feed');
    expect(response?.status()).toBe(401);
  });
});

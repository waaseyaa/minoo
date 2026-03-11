import { test, expect } from '@playwright/test';

test.describe('Communities', () => {
  test('communities listing page loads', async ({ page }) => {
    await page.goto('/communities');
    await expect(page.locator('h1')).toContainText('Communities');
  });

  test('community detail 404 has no leadership or band office sections', async ({ page }) => {
    await page.goto('/communities/nonexistent-community');
    await expect(page.locator('h1')).toContainText('Community Not Found');
    await expect(page.locator('.community__leadership')).toHaveCount(0);
    await expect(page.locator('.community__band-office')).toHaveCount(0);
  });

  test('communities listing shows filter buttons', async ({ page }) => {
    await page.goto('/communities');
    await expect(page.locator('.communities__filters')).toBeVisible();
    await expect(page.locator('.communities__filters .btn')).toHaveCount(3);
  });
});

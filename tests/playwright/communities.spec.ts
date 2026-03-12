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

  test('community detail page renders when clicking a card', async ({ page }) => {
    await page.goto('/communities');
    const firstCard = page.locator('a.card--community').first();
    // Skip if no community cards rendered (no seeded data in CI)
    const count = await page.locator('a.card--community').count();
    test.skip(count === 0, 'No community cards available — requires seeded data');
    await firstCard.click();
    await expect(page.locator('h1')).toBeVisible();
    expect(page.url()).toMatch(/\/communities\/.+/);
  });

  test('404 for invalid community slug', async ({ page }) => {
    const response = await page.goto('/communities/nonexistent-slug-xyz');
    expect(response?.status()).toBe(404);
  });
});

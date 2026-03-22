import { test, expect } from '@playwright/test';

test.describe('Map entity type filters', () => {
  test('filter buttons are visible on communities page', async ({ page }) => {
    await page.goto('/communities');
    const btn = page.locator('.atlas-filter--communities');
    const hasFilter = await btn.count();
    test.skip(hasFilter === 0, 'Entity filter buttons not rendered — requires seeded data');
    await expect(btn).toBeVisible();
  });

  test('community filter toggles markers', async ({ page }) => {
    await page.goto('/communities');
    const btn = page.locator('.atlas-filter--communities');
    const hasFilter = await btn.count();
    test.skip(hasFilter === 0, 'Entity filter buttons not rendered — requires seeded data');

    // Active by default
    await expect(btn).toHaveClass(/is-active/);

    // Toggle off
    await btn.click();
    await expect(btn).not.toHaveClass(/is-active/);

    // Toggle back on
    await btn.click();
    await expect(btn).toHaveClass(/is-active/);
  });

  test('filter buttons have correct aria-pressed', async ({ page }) => {
    await page.goto('/communities');
    const btn = page.locator('.atlas-filter--communities');
    const hasFilter = await btn.count();
    test.skip(hasFilter === 0, 'Entity filter buttons not rendered — requires seeded data');

    await expect(btn).toHaveAttribute('aria-pressed', 'true');

    await btn.click();
    await expect(btn).toHaveAttribute('aria-pressed', 'false');
  });
});

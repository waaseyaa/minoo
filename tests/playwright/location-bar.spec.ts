import { test, expect } from '@playwright/test';

test.describe('Location Bar', () => {
  test('location bar is present on homepage', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.location-bar')).toBeVisible();
  });

  test('location bar has toggle button', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#location-toggle')).toBeAttached();
  });

  test('location bar dropdown is initially hidden', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#location-dropdown')).toBeHidden();
  });

  test('location bar is present on elders page', async ({ page }) => {
    await page.goto('/elders');
    await expect(page.locator('.location-bar')).toBeVisible();
  });
});

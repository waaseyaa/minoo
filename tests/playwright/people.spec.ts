import { test, expect } from '@playwright/test';

test('people page loads', async ({ page }) => {
  await page.goto('/people');
  await expect(page.locator('h1')).toContainText(/people/i);
});

test('404 for invalid people slug', async ({ page }) => {
  const response = await page.goto('/people/nonexistent-slug-xyz');
  expect(response?.status()).toBe(404);
});

import { test, expect } from '@playwright/test';

test('search page loads', async ({ page }) => {
  await page.goto('/search');
  await expect(page.locator('h1')).toContainText(/search/i);
});

test('search with empty query shows prompt', async ({ page }) => {
  await page.goto('/search');
  await expect(page.getByTestId('search-intro')).toBeVisible();
});

test('search with query returns results or no-results message', async ({ page }) => {
  await page.goto('/search?q=community');
  const hasResults = await page.locator('[class*="result"], [class*="card"]').count();
  const hasNoResults = await page.getByTestId('search-no-results').count();
  expect(hasResults + hasNoResults).toBeGreaterThan(0);
});

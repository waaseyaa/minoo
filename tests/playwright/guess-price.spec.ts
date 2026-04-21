import { test, expect } from '@playwright/test';

test.describe('Guess the Price game', () => {
  test('games hub links to guess-price', async ({ page }) => {
    await page.goto('/games');
    await expect(page.locator('a[href="/games/guess-price"]')).toBeVisible();
  });

  test('guess-price page loads shell', async ({ page }) => {
    await page.goto('/games/guess-price');
    await expect(page.locator('#guess-price-game')).toBeVisible();
    await expect(page.locator('h1')).toContainText('Guess the Price');
  });
});

import { test, expect } from '@playwright/test';

test.describe('Dictionary browse and search', () => {
  test('language page shows dictionary entries', async ({ page }) => {
    await page.goto('/language');
    const heading = page.locator('h1');
    await expect(heading).toBeVisible();
    const cards = page.locator('.card--language');
    await expect(cards.first()).toBeVisible();
  });

  test('language page has pagination', async ({ page }) => {
    await page.goto('/language');
    const pagination = page.locator('.pagination');
    await expect(pagination).toBeVisible();
  });

  test('search form is present on language page', async ({ page }) => {
    await page.goto('/language');
    const searchInput = page.locator('#dict-search');
    await expect(searchInput).toBeVisible();
  });

  test('search returns results for "makwa"', async ({ page }) => {
    await page.goto('/language/search?q=makwa');
    const cards = page.locator('.card--language');
    await expect(cards.first()).toBeVisible({ timeout: 15000 });
  });

  test('search with no results shows message', async ({ page }) => {
    await page.goto('/language/search?q=xyznotaword123');
    const content = page.locator('main');
    await expect(content).toContainText('No results');
  });

  test('dictionary entry detail page works', async ({ page }) => {
    await page.goto('/language');
    const firstLink = page.locator('.card--language .card__title a').first();
    await firstLink.click();
    await expect(page.locator('h1')).toBeVisible();
  });

  test('attribution is visible on entries', async ({ page }) => {
    await page.goto('/language');
    const attribution = page.locator('.card__meta').first();
    await expect(attribution).toBeVisible();
  });
});

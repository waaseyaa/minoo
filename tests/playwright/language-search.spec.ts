import { test, expect } from '@playwright/test';

test.describe('Dictionary search', () => {
  test('search form is present on language page', async ({ page }) => {
    await page.goto('/language');
    const heading = page.locator('h1');
    await expect(heading).toBeVisible();
    const searchInput = page.locator('input[name="q"]');
    await expect(searchInput).toBeAttached();
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
});

test.describe('Dictionary browse (requires synced data)', () => {
  test('language page shows dictionary entries', async ({ page }) => {
    await page.goto('/language');
    const cards = page.locator('.card--language');
    const count = await cards.count();
    test.skip(count === 0, 'No dictionary entries in database — run bin/sync-dictionary first');
    await expect(cards.first()).toBeVisible();
  });

  test('language page has pagination', async ({ page }) => {
    await page.goto('/language');
    const cards = page.locator('.card--language');
    const count = await cards.count();
    test.skip(count === 0, 'No dictionary entries in database');
    const pagination = page.locator('.pagination');
    await expect(pagination).toBeVisible();
  });

  test('dictionary entry detail page works', async ({ page }) => {
    await page.goto('/language');
    const firstLink = page.locator('.card--language .card__title a').first();
    const count = await firstLink.count();
    test.skip(count === 0, 'No dictionary entries in database');
    await firstLink.click();
    await expect(page.locator('h1')).toBeVisible();
  });

  test('attribution is visible on entries', async ({ page }) => {
    await page.goto('/language');
    const attribution = page.locator('.card__meta').first();
    const count = await attribution.count();
    test.skip(count === 0, 'No dictionary entries in database');
    await expect(attribution).toBeVisible();
  });
});

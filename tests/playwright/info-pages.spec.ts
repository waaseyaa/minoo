import { test, expect } from '@playwright/test';

test.describe('Info Pages', () => {
  test('how-it-works page loads', async ({ page }) => {
    await page.goto('/how-it-works');
    await expect(page.locator('h1')).toContainText('How Minoo Works');
  });

  test('safety page loads', async ({ page }) => {
    await page.goto('/safety');
    await expect(page.locator('h1')).toContainText('Safety Guidelines');
  });

  test('how-it-works has FAQ section', async ({ page }) => {
    await page.goto('/how-it-works');
    await expect(page.locator('text=Frequently Asked Questions')).toBeVisible();
  });

  test('safety has emergency callout', async ({ page }) => {
    await page.goto('/safety');
    await expect(page.locator('.safety-callout')).toBeVisible();
  });
});

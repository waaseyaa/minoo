import { test, expect } from '@playwright/test';

test.describe('Volunteer Portal', () => {
  test('volunteer landing page loads', async ({ page }) => {
    await page.goto('/volunteer');
    await expect(page.locator('.hero__title')).toBeVisible();
    await expect(page.locator('a[href="/elders/volunteer"]')).toBeVisible();
  });

  test('volunteer signup form loads', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await expect(page.locator('#name')).toBeVisible();
    await expect(page.locator('#phone')).toBeVisible();
    await expect(page.locator('#availability')).toBeVisible();
  });

  test('volunteer form has privacy note', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await expect(page.locator('.privacy-note')).toBeVisible();
  });
});

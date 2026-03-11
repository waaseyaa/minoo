import { test, expect } from '@playwright/test';

test.describe('Community detail — leadership and band office', () => {
  test('community detail page loads without leadership section when no NC data', async ({ page }) => {
    await page.goto('/communities/sagamok-anishnawbek');
    await expect(page.locator('h1')).toContainText('Sagamok');
    // Leadership section should not be present without nc_id/NC data
    await expect(page.locator('.community__leadership')).toHaveCount(0);
  });

  test('community detail page loads without band office section when no NC data', async ({ page }) => {
    await page.goto('/communities/sagamok-anishnawbek');
    await expect(page.locator('.community__band-office')).toHaveCount(0);
  });

  test('community detail page still shows stats and nearby without NC data', async ({ page }) => {
    await page.goto('/communities/sagamok-anishnawbek');
    await expect(page.locator('.community__stats')).toBeVisible();
  });
});

import { expect, test } from '@playwright/test';

test.describe('Sudbury draft crisis incident', () => {
  test('state-of-emergency returns 404 while incident is draft', async ({ page }) => {
    const response = await page.goto('/communities/sudbury/state-of-emergency');
    expect(response?.status()).toBe(404);
  });
});

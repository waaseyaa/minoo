import { expect, test } from '@playwright/test';

test.describe('Sudbury crisis incident', () => {
  test('state-of-emergency returns 200 when incident is published', async ({ page }) => {
    const response = await page.goto('/communities/sudbury/state-of-emergency');
    if (response?.status() === 404) {
      test.skip(true, 'Sudbury community not seeded in this environment');
    }
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle(/Municipal emergency status — Greater Sudbury — Minoo/);
  });
});

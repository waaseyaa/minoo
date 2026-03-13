import { test, expect } from '@playwright/test';

test.describe('Location Bar', () => {
  test('location bar is present on homepage', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.location-bar')).toBeVisible();
  });

  test('location bar has toggle button', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#location-toggle')).toBeAttached();
  });

  test('location bar dropdown is initially hidden', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#location-dropdown')).toBeHidden();
  });

  test('location bar is present on elders page', async ({ page }) => {
    await page.goto('/elders');
    await expect(page.locator('.location-bar')).toBeVisible();
  });

  test('shows "Set your location" when no cookie and geolocation unavailable', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/');

    const locationText = page.locator('#location-text');
    await expect(locationText).toHaveText('Set your location', { timeout: 15000 });
  });

  test('renders community name from cookie on page load', async ({ page }) => {
    // Set cookie via route handler interception to avoid server 500 on fake community ID.
    // The JS readCookie() + render() runs on DOMContentLoaded, so we intercept the
    // response and inject the cookie header before the browser processes the page.
    await page.route('/', async (route) => {
      const response = await route.fetch();
      await route.fulfill({
        response,
        headers: {
          ...response.headers(),
          'set-cookie': `minoo_location=${encodeURIComponent(JSON.stringify({
            communityName: 'Thunder Bay',
            communityId: 'test-id',
            latitude: 48.38,
            longitude: -89.25,
            source: 'manual'
          }))}; path=/; max-age=2592000`
        }
      });
    });
    await page.goto('/');

    const locationText = page.locator('#location-text');
    await expect(locationText).toHaveText('Near Thunder Bay');
  });

  test('"Set your location" text opens dropdown when clicked', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/');

    const locationText = page.locator('#location-text');
    await expect(locationText).toHaveText('Set your location', { timeout: 15000 });

    await locationText.click();
    await expect(page.locator('#location-dropdown')).toBeVisible();
    await expect(page.locator('#location-search')).toBeFocused();
  });
});

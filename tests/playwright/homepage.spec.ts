import { test, expect } from '@playwright/test';

test.describe('Homepage (anonymous)', () => {
  test('shows welcome hero', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.hero')).toBeVisible();
    await expect(page.locator('.hero h1')).toContainText('Indigenous Knowledge');
  });

  test('hero CTAs link to events and teachings', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.hero__ctas a[href$="/events"]')).toBeVisible();
    await expect(page.locator('.hero__ctas a[href$="/teachings"]')).toBeVisible();
  });

  test('has skip link', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.skip-link')).toHaveAttribute('href', '#main-content');
  });

  test('explore redirect routes to section pages', async ({ page }) => {
    const response = await page.goto('/explore?type=events');
    expect(response?.url()).toContain('/events');
  });

  test('/feed redirects anonymous visitors to home', async ({ page }) => {
    await page.goto('/feed');
    await expect(page).toHaveURL('/');
  });
});

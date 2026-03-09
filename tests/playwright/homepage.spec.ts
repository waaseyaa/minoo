import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
  test('shows hero with title and CTAs', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.hero__title')).toBeVisible();
    await expect(page.locator('.hero__actions .btn--primary')).toBeVisible();
    await expect(page.locator('.hero__actions .btn--secondary')).toBeVisible();
  });

  test('hero CTA links are correct', async ({ page }) => {
    await page.goto('/');
    const helpBtn = page.locator('.hero__actions .btn--primary');
    await expect(helpBtn).toHaveAttribute('href', '/elders');
    const volunteerBtn = page.locator('.hero__actions .btn--secondary');
    await expect(volunteerBtn).toHaveAttribute('href', '/volunteer');
  });

  test('has skip link', async ({ page }) => {
    await page.goto('/');
    const skipLink = page.locator('.skip-link');
    await expect(skipLink).toHaveAttribute('href', '#main-content');
  });

  test('has how-it-works section', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'How It Works' })).toBeVisible();
  });

  test('has explore section with cards', async ({ page }) => {
    await page.goto('/');
    const exploreHeading = page.getByRole('heading', { name: 'Explore' });
    await expect(exploreHeading).toBeVisible();
    const exploreSection = exploreHeading.locator('..');
    await expect(exploreSection.locator('.card-grid .card')).toHaveCount(4);
  });
});

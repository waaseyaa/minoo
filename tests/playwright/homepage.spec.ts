import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
  test('shows hero with platform title and CTAs', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.hero__title')).toContainText('Minoo');
    await expect(page.locator('.hero__actions .btn--primary')).toHaveAttribute('href', '/communities');
    await expect(page.locator('.hero__actions .btn--secondary')).toHaveAttribute('href', '/people');
  });

  test('has skip link', async ({ page }) => {
    await page.goto('/');
    const skipLink = page.locator('.skip-link');
    await expect(skipLink).toHaveAttribute('href', '#main-content');
  });

  test('has Explore Minoo section with 5 cards', async ({ page }) => {
    await page.goto('/');
    const heading = page.getByRole('heading', { name: 'Explore Minoo' });
    await expect(heading).toBeVisible();
    const section = heading.locator('..');
    await expect(section.locator('.card-grid .card')).toHaveCount(5);
  });

  test('Explore section includes Elder Support card', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.card-grid a[href="/elders"] .card__title')).toContainText('Elder Support');
  });

  test('navigation has Programs dropdown', async ({ page }) => {
    await page.goto('/');
    const programsBtn = page.locator('.site-nav__dropdown-toggle');
    await expect(programsBtn).toHaveText(/Programs/);
    await programsBtn.click();
    await expect(page.locator('.site-nav__dropdown-menu')).toBeVisible();
    await expect(page.locator('.site-nav__dropdown-menu a[href="/elders"]')).toBeVisible();
    await expect(page.locator('.site-nav__dropdown-menu a[href="/volunteer"]')).toBeVisible();
  });

  test('navigation shows primary items', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.site-nav a[href="/communities"]')).toBeVisible();
    await expect(page.locator('.site-nav a[href="/people"]')).toBeVisible();
    await expect(page.locator('.site-nav a[href="/teachings"]')).toBeVisible();
    await expect(page.locator('.site-nav a[href="/events"]')).toBeVisible();
  });
});

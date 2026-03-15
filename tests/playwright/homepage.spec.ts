import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
  test('shows hero with platform title and search form', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.homepage-hero-tagline')).toContainText('A Living Map of Community');
    await expect(page.locator('.homepage-hero-search')).toBeVisible();
    await expect(page.locator('.homepage-hero-submit')).toBeVisible();
  });

  test('has skip link', async ({ page }) => {
    await page.goto('/');
    const skipLink = page.locator('.skip-link');
    await expect(skipLink).toHaveAttribute('href', '#main-content');
  });

  test('has tab navigation with 4 tabs', async ({ page }) => {
    await page.goto('/');
    const tabs = page.locator('.homepage-tabs .homepage-tab');
    await expect(tabs).toHaveCount(4);
    await expect(tabs.nth(0)).toContainText('Nearby');
    await expect(tabs.nth(1)).toContainText('Events');
    await expect(tabs.nth(2)).toContainText('People');
    await expect(tabs.nth(3)).toContainText('Groups');
  });

  test('tab switching works', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#panel-nearby')).toBeVisible();
    await expect(page.locator('#panel-events')).toBeHidden();

    await page.locator('.homepage-tab[data-tab="events"]').click();
    await expect(page.locator('#panel-events')).toBeVisible();
    await expect(page.locator('#panel-nearby')).toBeHidden();
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

  test('homepage has communities section', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.homepage-communities')).toBeVisible();
    await expect(page.locator('.homepage-pill').first()).toBeVisible();
  });

  test('homepage has What is Minoo section', async ({ page }) => {
    await page.goto('/');
    const about = page.locator('.homepage-about');
    await expect(about).toBeVisible();
  });

  test('explore redirect routes to section pages', async ({ page }) => {
    const response = await page.goto('/explore?type=events');
    expect(response?.url()).toContain('/events');
  });
});

import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
  test('shows three-column feed layout', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.feed-layout')).toBeVisible();
    await expect(page.locator('.feed-sidebar--left')).toBeVisible();
    await expect(page.locator('.feed-container')).toBeVisible();
  });

  test('has skip link', async ({ page }) => {
    await page.goto('/');
    const skipLink = page.locator('.skip-link');
    await expect(skipLink).toHaveAttribute('href', '#main-content');
  });

  test('has feed filter chips', async ({ page }) => {
    await page.goto('/');
    const chips = page.locator('.feed-chips .feed-chip');
    await expect(chips.first()).toBeVisible();
    await expect(page.locator('.feed-chip[data-filter="all"]')).toBeVisible();
    await expect(page.locator('.feed-chip[data-filter="event"]')).toBeVisible();
    await expect(page.locator('.feed-chip[data-filter="business"]')).toBeVisible();
    await expect(page.locator('.feed-chip[data-filter="person"]')).toBeVisible();
  });

  test('feed filter switching works', async ({ page }) => {
    await page.goto('/');
    const allChip = page.locator('.feed-chip[data-filter="all"]');
    await expect(allChip).toBeVisible();
    await allChip.click();
    await expect(page.locator('.feed-container')).toBeVisible();
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

  test('feed shows content cards', async ({ page }) => {
    await page.goto('/');
    const cards = page.locator('.feed-container article');
    await expect(cards.first()).toBeVisible();
  });

  test('explore redirect routes to section pages', async ({ page }) => {
    const response = await page.goto('/explore?type=events');
    expect(response?.url()).toContain('/events');
  });

  test('left sidebar has navigation shortcuts', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.sidebar-nav')).toBeVisible();
  });
});

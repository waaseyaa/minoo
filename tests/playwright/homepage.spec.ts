import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
  test('shows feed layout with sidebar', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.feed-layout')).toBeVisible();
    await expect(page.locator('.app-sidebar')).toBeVisible();
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

  test('sidebar has Programs section with elder and volunteer links', async ({ page }) => {
    await page.goto('/');
    const sidebar = page.locator('.sidebar-nav');
    await expect(sidebar.locator('a[href="/elders/request"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/elders/volunteer"]')).toBeVisible();
  });

  test('sidebar shows primary navigation items', async ({ page }) => {
    await page.goto('/');
    const sidebar = page.locator('.sidebar-nav');
    await expect(sidebar.locator('a[href="/communities"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/people"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/teachings"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/events"]')).toBeVisible();
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

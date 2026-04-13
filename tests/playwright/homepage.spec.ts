import { test, expect } from '@playwright/test';

test.describe('Homepage (anonymous)', () => {
  test('shows homepage hero for anonymous visitors', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.home-hero')).toBeVisible();
    await expect(page.locator('.home-hero__title')).toContainText('Welcome to Minoo');
  });

  test('has skip link', async ({ page }) => {
    await page.goto('/');
    const skipLink = page.locator('.skip-link');
    await expect(skipLink).toHaveAttribute('href', '#main-content');
  });

  test('homepage has call-to-action section', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.home-cta')).toBeVisible();
    await expect(page.locator('.home-cta__heading')).toContainText('Join the Conversation');
  });

  test('homepage has navigation links to key sections', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.home-hero__actions a[href="/teachings"]')).toBeVisible();
    await expect(page.locator('.home-hero__actions a[href="/events"]')).toBeVisible();
  });

  test('sidebar shows primary navigation items', async ({ page }) => {
    await page.goto('/');
    const sidebar = page.locator('.sidebar-nav');
    await expect(sidebar.locator('a[href="/communities"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/people"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/teachings"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/events"]')).toBeVisible();
  });

  test('sidebar does not show Feed link for anonymous users', async ({ page }) => {
    await page.goto('/');
    const sidebar = page.locator('.sidebar-nav');
    await expect(sidebar.locator('a[href="/feed"]')).not.toBeVisible();
  });

  test('explore redirect routes to section pages', async ({ page }) => {
    const response = await page.goto('/explore?type=events');
    expect(response?.url()).toContain('/events');
  });
});

test.describe('Feed page (/feed)', () => {
  test('feed page loads with feed layout', async ({ page }) => {
    await page.goto('/feed');
    await expect(page.locator('.feed-layout')).toBeVisible();
    await expect(page.locator('.feed-container')).toBeVisible();
  });

  test('feed page has filter chips', async ({ page }) => {
    await page.goto('/feed');
    const chips = page.locator('.feed-chips .feed-chip');
    await expect(chips.first()).toBeVisible();
    await expect(page.locator('.feed-chip[data-filter="all"]')).toBeVisible();
    await expect(page.locator('.feed-chip[data-filter="event"]')).toBeVisible();
  });

  test('feed filter switching works', async ({ page }) => {
    await page.goto('/feed');
    const allChip = page.locator('.feed-chip[data-filter="all"]');
    await expect(allChip).toBeVisible();
    await allChip.click();
    await expect(page.locator('.feed-container')).toBeVisible();
  });

  test('sidebar has Programs section with elder and volunteer links', async ({ page }) => {
    await page.goto('/feed');
    const sidebar = page.locator('.sidebar-nav');
    await expect(sidebar.locator('a[href="/elders/request"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/elders/volunteer"]')).toBeVisible();
  });
});

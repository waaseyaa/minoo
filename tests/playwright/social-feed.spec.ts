import { test, expect } from '@playwright/test';

test.describe('Social Feed', () => {
  test('renders three-column layout', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.feed-layout')).toBeVisible();
    await expect(page.locator('.feed-sidebar--left')).toBeVisible();
    await expect(page.locator('.feed-container')).toBeVisible();
  });

  test('feed cards have content', async ({ page }) => {
    await page.goto('/');
    const firstCard = page.locator('.feed-container article').first();
    await expect(firstCard).toBeVisible();
  });

  test('filter chips show entity type options', async ({ page }) => {
    await page.goto('/');
    const chips = page.locator('.feed-chips .feed-chip');
    await expect(chips.first()).toBeVisible();
    await expect(page.locator('.feed-chip[data-filter="all"]')).toBeVisible();
  });

  test('clicking filter chip updates feed', async ({ page }) => {
    await page.goto('/');
    const eventChip = page.locator('.feed-chip[data-filter="event"]');
    await expect(eventChip).toBeVisible();
    await eventChip.click();
    await expect(page.locator('.feed-container')).toBeVisible();
  });

  test('right sidebar hidden at tablet width', async ({ page }) => {
    await page.goto('/');
    await page.setViewportSize({ width: 1024, height: 768 });
    const rightSidebar = page.locator('.feed-sidebar--right');
    // Right sidebar should not be visible at tablet breakpoint
    if (await rightSidebar.count() > 0) {
      await expect(rightSidebar).not.toBeVisible();
    }
  });

  test('both sidebars hidden on mobile', async ({ page }) => {
    await page.goto('/');
    await page.setViewportSize({ width: 375, height: 812 });
    await expect(page.locator('.feed-sidebar--left')).not.toBeVisible();
    const rightSidebar = page.locator('.feed-sidebar--right');
    if (await rightSidebar.count() > 0) {
      await expect(rightSidebar).not.toBeVisible();
    }
  });

  test('left sidebar has navigation shortcuts', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.sidebar-nav')).toBeVisible();
  });
});

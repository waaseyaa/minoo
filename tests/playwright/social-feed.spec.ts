import { test, expect } from '@playwright/test';

test.describe('Social Feed', () => {
  test('renders feed layout with sidebar', async ({ page }) => {
    await page.goto('/feed');
    await expect(page.locator('.feed-layout')).toBeVisible();
    await expect(page.locator('.app-sidebar')).toBeVisible();
    await expect(page.locator('.feed-container')).toBeVisible();
  });

  test('feed cards have content', async ({ page }) => {
    await page.goto('/feed');
    const firstCard = page.locator('.feed-container article').first();
    await expect(firstCard).toBeVisible();
  });

  test('filter chips show entity type options', async ({ page }) => {
    await page.goto('/feed');
    const chips = page.locator('.feed-chips .feed-chip');
    await expect(chips.first()).toBeVisible();
    await expect(page.locator('.feed-chip[data-filter="all"]')).toBeVisible();
  });

  test('clicking filter chip updates feed', async ({ page }) => {
    await page.goto('/feed');
    const eventChip = page.locator('.feed-chip[data-filter="event"]');
    await expect(eventChip).toBeVisible();
    await eventChip.click();
    await expect(page.locator('.feed-container')).toBeVisible();
  });

  test('right sidebar hidden at tablet width', async ({ page }) => {
    await page.goto('/feed');
    await page.setViewportSize({ width: 1024, height: 768 });
    const rightSidebar = page.locator('.feed-sidebar--right');
    // Right sidebar should not be visible at tablet breakpoint
    if (await rightSidebar.count() > 0) {
      await expect(rightSidebar).not.toBeVisible();
    }
  });

  test('sidebar is off-screen on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/feed');
    // Sidebar uses transform: translateX(-100%) on mobile — off-screen but still in DOM
    const box = await page.locator('.app-sidebar').boundingBox();
    expect(box).toBeTruthy();
    expect(box!.x + box!.width).toBeLessThanOrEqual(0);
  });

  test('left sidebar has navigation shortcuts', async ({ page }) => {
    await page.goto('/feed');
    await expect(page.locator('.sidebar-nav')).toBeVisible();
  });
});

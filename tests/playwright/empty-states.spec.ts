import { test, expect, Page } from '@playwright/test';

async function assertCardsOrEmptyState(page: Page) {
  // /events and /teachings use `.ds-grid`; other pages use `.card-grid`.
  const cards = page.locator('.card-grid, .ds-grid').first();
  const emptyState = page.locator('.empty-state').first();
  // Wait for either to be attached; whichever resolves first tells us the mode.
  await expect(cards.or(emptyState)).toBeVisible();

  if (await emptyState.count() > 0) {
    // Empty-state markup must include at minimum a heading + body. Actions
    // are optional (not every empty state needs a CTA).
    await expect(emptyState.locator('.empty-state__heading')).toBeVisible();
    await expect(emptyState.locator('.empty-state__body')).toBeVisible();
  }
}

test.describe('Empty state component', () => {
  const paths = ['/events', '/groups', '/teachings', '/language', '/people'];

  for (const path of paths) {
    test(`${path} renders empty state or card grid`, async ({ page }) => {
      await page.goto(path);
      await assertCardsOrEmptyState(page);
    });
  }

  test('empty state has correct visual styling', async ({ page }) => {
    await page.goto('/events');
    const emptyState = page.locator('.empty-state');

    if (await emptyState.count() > 0) {
      const bgColor = await emptyState.evaluate(el => getComputedStyle(el).backgroundColor);
      const borderLeft = await emptyState.evaluate(el => getComputedStyle(el).borderInlineStartWidth);
      expect(bgColor).not.toBe('rgba(0, 0, 0, 0)');
      expect(parseFloat(borderLeft)).toBeGreaterThan(0);
    }
  });
});

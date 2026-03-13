import { test, expect } from '@playwright/test';

test.describe('Empty state component', () => {
  // Test structural rendering on pages that may have no data.
  // In the test environment, not all entity types have seeded data.

  test('events page renders empty state or card grid', async ({ page }) => {
    await page.goto('/events');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    // One or the other must be present
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__body')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

  test('groups page renders empty state or card grid', async ({ page }) => {
    await page.goto('/groups');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__body')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

  test('teachings page renders empty state or card grid', async ({ page }) => {
    await page.goto('/teachings');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__body')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

  test('language page renders empty state or card grid', async ({ page }) => {
    await page.goto('/language');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__body')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

  test('people page renders empty state or card grid', async ({ page }) => {
    await page.goto('/people');
    const hasCards = await page.locator('.card-grid').count() > 0;
    const hasEmptyState = await page.locator('.empty-state').count() > 0;
    expect(hasCards || hasEmptyState).toBeTruthy();

    if (hasEmptyState) {
      await expect(page.locator('.empty-state__heading')).toBeVisible();
      await expect(page.locator('.empty-state__body')).toBeVisible();
      await expect(page.locator('.empty-state__action')).toBeVisible();
    }
  });

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

  test('empty state action links are valid', async ({ page }) => {
    // Check all pages for empty states with working action links
    const pages = ['/events', '/groups', '/teachings', '/language', '/people'];

    for (const pagePath of pages) {
      await page.goto(pagePath);
      const emptyState = page.locator('.empty-state');

      if (await emptyState.count() > 0) {
        const actionLink = emptyState.locator('.empty-state__action');
        const href = await actionLink.getAttribute('href');
        expect(href).toBeTruthy();
        // Verify the link target loads (not a 404)
        const response = await page.goto(href!);
        expect(response?.status()).toBeLessThan(400);
      }
    }
  });
});

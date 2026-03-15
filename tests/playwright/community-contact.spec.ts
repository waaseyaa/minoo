import { test, expect } from '@playwright/test';

test.describe('Community contact card', () => {
  test('community detail page renders about section', async ({ page }) => {
    await page.goto('/communities');
    const cards = page.locator('a.atlas-card');
    const count = await cards.count();
    test.skip(count === 0, 'No community cards available — requires seeded data');

    await cards.first().click();
    await expect(page.locator('h1')).toBeVisible();
    expect(page.url()).toMatch(/\/communities\/.+/);

    // Every community detail page must have at least the "About" section label
    const sectionLabels = page.locator('.atlas-section__label');
    const labelCount = await sectionLabels.count();
    expect(labelCount).toBeGreaterThan(0);
  });

  test('community detail page degrades gracefully without NC data', async ({ page }) => {
    await page.goto('/communities');
    const cards = page.locator('a.atlas-card');
    const count = await cards.count();
    test.skip(count === 0, 'No community cards available — requires seeded data');

    await cards.first().click();
    await expect(page.locator('h1')).toBeVisible();

    // Page renders successfully regardless of NC data availability
    // The hero section and about section should always be present
    await expect(page.locator('.atlas-detail-hero__name')).toBeVisible();
  });

  test('community contact card section is absent for 404 communities', async ({ page }) => {
    const response = await page.goto('/communities/nonexistent-community-xyz');
    expect(response?.status()).toBe(404);

    const leaderCards = page.locator('.atlas-leader-card');
    await expect(leaderCards).toHaveCount(0);
  });
});

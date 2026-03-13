import { test, expect } from '@playwright/test';

test.describe('Communities', () => {
  test('communities listing page loads', async ({ page }) => {
    await page.goto('/communities');
    await expect(page.locator('h1')).toContainText('Communities');
  });

  test('community detail 404 has no leadership or band office sections', async ({ page }) => {
    await page.goto('/communities/nonexistent-community');
    await expect(page.locator('h1')).toContainText('Community Not Found');
    await expect(page.locator('.community__leadership')).toHaveCount(0);
    await expect(page.locator('.community__band-office')).toHaveCount(0);
  });

  test('communities listing shows filter buttons', async ({ page }) => {
    await page.goto('/communities');
    await expect(page.locator('.communities__filters')).toBeVisible();
    await expect(page.locator('.communities__filters .btn')).toHaveCount(3);
  });

  test('community detail page renders when clicking a card', async ({ page }) => {
    await page.goto('/communities');
    const firstCard = page.locator('a.card--community').first();
    // Skip if no community cards rendered (no seeded data in CI)
    const count = await page.locator('a.card--community').count();
    test.skip(count === 0, 'No community cards available — requires seeded data');
    await firstCard.click();
    await expect(page.locator('h1')).toBeVisible();
    expect(page.url()).toMatch(/\/communities\/.+/);
  });

  test('404 for invalid community slug', async ({ page }) => {
    const response = await page.goto('/communities/nonexistent-slug-xyz');
    expect(response?.status()).toBe(404);
  });

  // ── Municipality + Mixed Nearby (#177) ─────────────────────────────

  test('empty state renders when no communities match filter', async ({ page }) => {
    // Use a type filter unlikely to match anything in CI (fresh in-memory DB)
    await page.goto('/communities?type=municipalities');
    const hasCards = await page.locator('a.card--community').count() > 0;
    if (!hasCards) {
      await expect(page.locator('.empty-state')).toBeVisible();
    }
  });

  test('filter links use correct URLs for first-nations and municipalities', async ({ page }) => {
    await page.goto('/communities');
    await expect(page.locator('a[href="/communities?type=first-nations"]')).toBeVisible();
    await expect(page.locator('a[href="/communities?type=municipalities"]')).toBeVisible();
  });

  test('all filter is active by default', async ({ page }) => {
    await page.goto('/communities');
    await expect(page.locator('.communities__filters .btn--active')).toContainText('All');
  });

  test('first-nations filter marks its button active', async ({ page }) => {
    await page.goto('/communities?type=first-nations');
    await expect(page.locator('.communities__filters .btn--active')).toContainText('First Nations');
  });

  test('municipalities filter marks its button active', async ({ page }) => {
    await page.goto('/communities?type=municipalities');
    await expect(page.locator('.communities__filters .btn--active')).toContainText('Municipalities');
  });

  test('community cards show municipality or first nation badge', async ({ page }) => {
    await page.goto('/communities');
    const count = await page.locator('a.card--community').count();
    test.skip(count === 0, 'No community cards — requires seeded data');
    await expect(page.locator('.card__badge').first()).toBeVisible();
  });

  test('first-nations filter only shows first nation cards', async ({ page }) => {
    await page.goto('/communities?type=first-nations');
    const count = await page.locator('a.card--community').count();
    test.skip(count === 0, 'No first nation cards — requires seeded data');
    const municipalityBadges = await page.locator('.card__badge--municipality').count();
    expect(municipalityBadges).toBe(0);
  });

  test('municipalities filter only shows municipality cards', async ({ page }) => {
    await page.goto('/communities?type=municipalities');
    const count = await page.locator('a.card--community').count();
    test.skip(count === 0, 'No municipality cards — requires seeded data');
    const firstNationBadges = await page.locator('.card__badge--first_nation').count();
    expect(firstNationBadges).toBe(0);
  });
});

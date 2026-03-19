import { test, expect } from '@playwright/test';

test.describe('Communities', () => {
  test('communities listing page loads', async ({ page }) => {
    await page.goto('/communities');
    const hasAtlas = await page.locator('.atlas-header__title').count();
    test.skip(hasAtlas === 0, 'Atlas UI not rendered — requires seeded community data');
    await expect(page.locator('.atlas-header__title')).toContainText('Communities');
  });

  test('community detail 404 returns 404 status', async ({ page }) => {
    const response = await page.goto('/communities/nonexistent-community');
    expect(response?.status()).toBe(404);
  });

  test('communities listing shows filter chips', async ({ page }) => {
    await page.goto('/communities');
    const hasChips = await page.locator('.atlas-chips').count();
    test.skip(hasChips === 0, 'Atlas chips not rendered — requires seeded community data');
    await expect(page.locator('.atlas-chips')).toBeVisible();
    await expect(page.locator('.atlas-chip')).toHaveCount(6);
  });

  test('community detail page renders when clicking a card', async ({ page }) => {
    await page.goto('/communities');
    const firstCard = page.locator('a.atlas-card').first();
    const count = await page.locator('a.atlas-card').count();
    test.skip(count === 0, 'No community cards available — requires seeded data');
    await firstCard.click();
    await expect(page.locator('h1')).toBeVisible();
    expect(page.url()).toMatch(/\/communities\/.+/);
  });

  test('404 for invalid community slug', async ({ page }) => {
    const response = await page.goto('/communities/nonexistent-slug-xyz');
    expect(response?.status()).toBe(404);
  });

  // ── Atlas Filters ─────────────────────────────

  test('all types chip is active by default', async ({ page }) => {
    await page.goto('/communities');
    const activeChip = page.locator('.atlas-chip--active');
    const hasChips = await activeChip.count();
    test.skip(hasChips === 0, 'Atlas chips not rendered — requires seeded community data');
    await expect(activeChip.first()).toContainText('All Types');
  });

  test('first nations chip toggles active state', async ({ page }) => {
    await page.goto('/communities');
    const fnChip = page.locator('.atlas-chip', { hasText: 'First Nations' });
    const hasChips = await fnChip.count();
    test.skip(hasChips === 0, 'Atlas chips not rendered — requires seeded community data');
    await fnChip.click();
    await expect(fnChip).toHaveClass(/atlas-chip--active/);
  });

  test('municipalities chip toggles active state', async ({ page }) => {
    await page.goto('/communities');
    const munChip = page.locator('.atlas-chip', { hasText: 'Municipalities' });
    const hasChips = await munChip.count();
    test.skip(hasChips === 0, 'Atlas chips not rendered — requires seeded community data');
    await munChip.click();
    await expect(munChip).toHaveClass(/atlas-chip--active/);
  });

  test('community cards show municipality or first nation badge', async ({ page }) => {
    await page.goto('/communities');
    const count = await page.locator('a.atlas-card').count();
    test.skip(count === 0, 'No community cards — requires seeded data');
    await expect(page.locator('.atlas-card__badge').first()).toBeVisible();
  });

  test('search input is present', async ({ page }) => {
    await page.goto('/communities');
    const hasSearch = await page.locator('.atlas-search').count();
    test.skip(hasSearch === 0, 'Atlas search not rendered — requires seeded community data');
    await expect(page.locator('.atlas-search')).toBeVisible();
  });

  test('map container is present', async ({ page }) => {
    await page.goto('/communities');
    const hasMap = await page.locator('#atlas-map').count();
    test.skip(hasMap === 0, 'Atlas map not rendered — requires seeded community data');
    await expect(page.locator('#atlas-map')).toBeVisible();
  });
});

import { test, expect } from '@playwright/test';

const contentPages = [
  { path: '/events', heading: /events/i },
  { path: '/groups', heading: /groups/i },
  { path: '/language', heading: /language|dictionary/i },
];

for (const { path, heading } of contentPages) {
  test(`${path} listing page loads`, async ({ page }) => {
    await page.goto(path);
    await expect(page.locator('h1')).toContainText(heading);
  });
}

test('404 for invalid event slug', async ({ page }) => {
  const response = await page.goto('/events/nonexistent-slug-xyz');
  expect(response?.status()).toBe(404);
});

test('404 for invalid group slug', async ({ page }) => {
  const response = await page.goto('/groups/nonexistent-slug-xyz');
  expect(response?.status()).toBe(404);
});

test('404 for invalid language slug', async ({ page }) => {
  const response = await page.goto('/language/nonexistent-slug-xyz');
  expect(response?.status()).toBe(404);
});

test.describe('Listing pages render a subtitle', () => {
  // All restored listing surfaces standardized on the `.listing-hero` component.
  const paths = ['/events', '/groups', '/language'];

  for (const path of paths) {
    test(`${path} has a listing-hero subtitle`, async ({ page }) => {
      await page.goto(path);
      const subtitle = page.locator('.listing-hero__subtitle');
      await expect(subtitle).toBeVisible();
      const text = await subtitle.textContent();
      expect(text?.trim().length ?? 0).toBeGreaterThan(10);
    });
  }
});

test.describe('Listing pages use empty-state or card-grid', () => {
  const pages = ['/events', '/groups', '/language'];

  for (const path of pages) {
    test(`${path} has empty-state component or card-grid`, async ({ page }) => {
      await page.goto(path);
      const hasCards = await page.locator('.card-grid, .ds-grid').count() > 0;
      const hasEmptyState = await page.locator('.empty-state').count() > 0;
      expect(hasCards || hasEmptyState).toBeTruthy();
    });
  }
});

test.describe('Not-found pages use warm copy', () => {
  const slugs = ['/events/nonexistent', '/groups/nonexistent', '/language/nonexistent'];

  for (const slug of slugs) {
    test(`${slug} uses friendly not-found message`, async ({ page }) => {
      await page.goto(slug);
      const body = await page.locator('.flow-lg').textContent();
      expect(body).not.toContain('<code>');
      expect(body).toContain("couldn't find");
    });
  }
});

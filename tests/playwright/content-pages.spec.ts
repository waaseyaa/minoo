import { test, expect } from '@playwright/test';

const contentPages = [
  { path: '/teachings', heading: /teachings/i },
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

test('404 for invalid teaching slug', async ({ page }) => {
  const response = await page.goto('/teachings/nonexistent-slug-xyz');
  expect(response?.status()).toBe(404);
});

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

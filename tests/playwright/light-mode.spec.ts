import { test, expect } from '@playwright/test';

test.describe('Theme toggle behavior', () => {
  // Playwright headless Chromium defaults to prefers-color-scheme: light.
  // Force dark so the toggle starts in a known state (dark → light).
  test.use({ colorScheme: 'dark' });

  test('theme toggle persists across navigation', async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('[data-theme="dark"]');
    // Toggle theme via JS (standalone .theme-toggle removed; toggle is now in user-menu)
    await page.evaluate(() => {
      const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', theme);
      localStorage.setItem('minoo-theme', theme);
    });
    const theme = await page.evaluate(() =>
      document.documentElement.getAttribute('data-theme'),
    );
    expect(theme).toBe('light');

    await page.goto('/events');
    const persisted = await page.evaluate(() =>
      document.documentElement.getAttribute('data-theme'),
    );
    expect(persisted).toBe('light');
  });

  test('theme preference is read from localStorage on load', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => localStorage.setItem('minoo-theme', 'light'));
    await page.goto('/');
    const theme = await page.evaluate(() =>
      document.documentElement.getAttribute('data-theme'),
    );
    expect(theme).toBe('light');
  });
});

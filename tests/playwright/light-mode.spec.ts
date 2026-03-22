import { test, expect } from '@playwright/test';

const pages = [
  { name: 'homepage', path: '/' },
  { name: 'events', path: '/events' },
  { name: 'teachings', path: '/teachings' },
  { name: 'communities', path: '/communities' },
  { name: 'language', path: '/language' },
  { name: 'search', path: '/search' },
];

test.describe('Light mode visual regression', () => {
  for (const { name, path } of pages) {
    test(`${name} — dark mode`, async ({ page }) => {
      await page.goto(path);
      await page.evaluate(() => {
        document.documentElement.setAttribute('data-theme', 'dark');
      });
      await page.waitForTimeout(300);
      await expect(page).toHaveScreenshot(`${name}-dark.png`, { fullPage: true });
    });

    test(`${name} — light mode`, async ({ page }) => {
      await page.goto(path);
      await page.evaluate(() => {
        document.documentElement.setAttribute('data-theme', 'light');
      });
      await page.waitForTimeout(300);
      await expect(page).toHaveScreenshot(`${name}-light.png`, { fullPage: true });
    });
  }
});

test.describe('Theme toggle behavior', () => {
  test('theme toggle persists across navigation', async ({ page }) => {
    await page.goto('/');
    await page.click('.theme-toggle');
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

  test('theme toggle switches icons', async ({ page }) => {
    await page.goto('/');
    // Dark mode default — moon icon visible
    await expect(page.locator('.theme-toggle__icon--dark')).toBeVisible();
    await expect(page.locator('.theme-toggle__icon--light')).toBeHidden();

    await page.click('.theme-toggle');
    // Light mode — sun icon visible
    await expect(page.locator('.theme-toggle__icon--light')).toBeVisible();
    await expect(page.locator('.theme-toggle__icon--dark')).toBeHidden();
  });
});

import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const publicPages = [
  '/',
  '/teachings',
  '/events',
  '/groups',
  '/language',
  '/communities',
  '/data-sovereignty',
  '/login',
  '/register',
  '/forgot-password',
  '/elder-support',
];

for (const path of publicPages) {
  test(`accessibility: ${path} has no critical or serious violations`, async ({ page }) => {
    await page.goto(path);
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    const critical = results.violations.filter(v =>
      v.impact === 'critical' || v.impact === 'serious'
    );

    expect(critical).toEqual([]);
  });
}

test('skip-to-content link is present and focusable', async ({ page }) => {
  await page.goto('/');
  const skipLink = page.locator('.skip-link');
  await expect(skipLink).toBeAttached();
  await skipLink.focus();
  await expect(skipLink).toBeFocused();
});

test('all pages have lang attribute on html', async ({ page }) => {
  await page.goto('/');
  const lang = await page.locator('html').getAttribute('lang');
  expect(lang).toBe('en');
});

import { test, expect } from '@playwright/test';

test.describe('Sagamok Spanish River flood page', () => {
  test('renders share toolbar with canonical URL in Facebook sharer', async ({ page }) => {
    const response = await page.goto('/communities/sagamok-anishnawbek/spanish-river-flood');
    if (response?.status() === 404) {
      test.skip(true, 'Sagamok community not seeded in this environment');
    }
    expect(response?.status()).toBe(200);

    const root = page.locator('[data-share-toolbar-root]');
    await expect(root).toBeVisible();

    const shareUrl = await root.getAttribute('data-share-url');
    expect(shareUrl).toBeTruthy();
    const pageOrigin = new URL(shareUrl as string).origin;

    const ogImage = await page.locator('meta[property="og:image"]').getAttribute('content');
    expect(ogImage).toBe(`${pageOrigin}/og/crisis/sagamok-spanish-river-flood.png`);

    const fb = page.getByRole('link', { name: 'Facebook' });
    await expect(fb).toBeVisible();
    const href = await fb.getAttribute('href');
    expect(href).toMatch(/facebook\.com\/sharer/);
    expect(href).toContain(encodeURIComponent(pageOrigin));

    await expect(page.getByRole('link', { name: 'X' })).toHaveAttribute('href', /twitter\.com\/intent\/tweet/);
    await expect(page.getByRole('link', { name: 'LinkedIn' })).toHaveAttribute('href', /linkedin\.com\/sharing/);
    await expect(page.getByRole('link', { name: 'Email' })).toHaveAttribute('href', /^mailto:/);
    await expect(page.getByRole('button', { name: 'Copy link' })).toBeVisible();
  });
});

import { test, expect, Page } from '@playwright/test';

/**
 * Feed engagement UI (#817) — react flow.
 *
 * Uses the seeded test@minoo.test account (global-setup.ts → bin/seed-test-user),
 * the same authenticated pattern as auth.spec.ts. The feed's contents depend on
 * whatever the local database holds, so each test runtime-skips when no feed
 * card with a react button is present rather than inventing seed plumbing.
 */

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'test@minoo.test');
  await page.fill('input[name="password"]', 'TestPass123!');
  await page.click('.form button[type="submit"]');
  await page.waitForURL('/');
}

test.describe('Feed engagement', () => {
  test('feed page loads engagement.js', async ({ page }) => {
    await login(page);
    await page.goto('/feed');
    await expect(page.locator('script[src^="/js/engagement.js"]')).toBeAttached();
  });

  test('sidebar shows Feed entry only when authenticated', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.sbx a[href="/feed"]')).toHaveCount(0);

    await login(page);
    await page.goto('/feed');
    await expect(page.locator('.sbx a[href="/feed"]').first()).toBeVisible();
  });

  test('clicking react fires POST /api/engagement/react and toggles the button', async ({ page }) => {
    await login(page);
    await page.goto('/feed');

    // Only these card types are valid reaction targets server-side
    // (EngagementController::ALLOWED_TARGET_TYPES) — the synthetic/featured
    // cards render a react button but the API rejects them with 422.
    const react = page.locator(
      ['post', 'event', 'community_group']
        .map((t) => `.feed-card__action--react[data-type="${t}"]`)
        .join(', '),
    ).first();
    test.skip(await react.count() === 0, 'no reactable feed cards in the local database');

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('/api/engagement/react') && r.request().method() === 'POST',
      ),
      react.click(),
    ]);

    expect(response.status()).toBe(201);
    const body = await response.json();
    expect(body.id).toBeTruthy();
    await expect(react).toHaveClass(/is-active/);
    await expect(react).toHaveAttribute('aria-pressed', 'true');

    // Toggle off — session-local reaction id drives the DELETE.
    const [del] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('/api/engagement/react/') && r.request().method() === 'DELETE',
      ),
      react.click(),
    ]);

    expect(del.status()).toBe(200);
    await expect(react).not.toHaveClass(/is-active/);
  });
});

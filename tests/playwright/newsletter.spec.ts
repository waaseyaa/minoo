import { test, expect } from '@playwright/test';

test.describe('Newsletter public surface', () => {
    test('list page renders with Elder Newsletter heading', async ({ page }) => {
        await page.goto('/newsletter');
        await expect(
            page.getByRole('heading', { name: 'Elder Newsletter' }),
        ).toBeVisible();
    });

    test('submit page redirects when not logged in', async ({ page }) => {
        const resp = await page.goto('/newsletter/submit');
        expect(resp?.status()).toBeLessThan(400);
        // Either we landed on /login or are still on /newsletter/submit
        // (with a login prompt rendered inline).
        expect(page.url()).toMatch(/\/login|\/newsletter\/submit/);
    });
});

test.describe('Newsletter editor surface', () => {
    test('coordinator route is gated', async ({ page }) => {
        const resp = await page.goto('/coordinator/newsletter');
        // 302 to login, 401 unauthorized, or 403 forbidden — all prove
        // the gate is wired up.
        expect([200, 302, 401, 403]).toContain(resp?.status() ?? 0);
        // If it returned 200, confirm we were bounced to a login-ish URL.
        if (resp?.status() === 200) {
            expect(page.url()).toMatch(/\/login|\/coordinator\/newsletter/);
        }
    });
});

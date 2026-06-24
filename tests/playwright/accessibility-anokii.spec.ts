import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// The Anokii Language Workspace is role-gated (admin / elder_coordinator), so
// these axe checks log in as the seeded elder-coordinator first (#879).
const workspacePages = [
  '/admin/anokii',
  '/admin/anokii/ingest',
  '/admin/anokii/transcribe',
  '/admin/anokii/curate',
];

test.describe('Anokii workspace accessibility', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'coordinator@minoo.test');
    await page.fill('input[name="password"]', 'CoordPass123!');
    await page.click('.form button[type="submit"]');
    await page.waitForURL(/\/(feed|admin\/anokii|)?$/);
  });

  for (const path of workspacePages) {
    test(`accessibility: ${path} has no critical or serious violations`, async ({ page }) => {
      await page.goto(path);
      await page.locator('.anokii-app').first().waitFor();
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        // axe-core cannot parse oklch()/color-mix() and computes incorrect contrast.
        // Contrast is manually verified, matching the public accessibility suite.
        .disableRules(['color-contrast'])
        .analyze();

      const critical = results.violations.filter(v =>
        v.impact === 'critical' || v.impact === 'serious'
      );
      expect(critical).toEqual([]);
    });
  }

  test('ingest drop zone is keyboard-operable with a file-picker fallback', async ({ page }) => {
    await page.goto('/admin/anokii/ingest');
    const pick = page.locator('#ig-pick');
    await expect(pick).toBeVisible();
    await pick.focus();
    await expect(pick).toBeFocused();
    // The hidden <input type=file> is the fallback the button drives.
    await expect(page.locator('#ig-file')).toHaveAttribute('type', 'file');
    await expect(page.locator('#ig-drop')).toHaveAttribute('aria-label', /drag and drop/i);
  });
});

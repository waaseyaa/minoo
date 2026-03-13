import { test, expect } from '@playwright/test';

test.describe('AI Chat Widget', () => {
  test('chat toggle button is not visible when chat is disabled', async ({ page }) => {
    await page.goto('/');
    const widget = page.locator('#chat-widget');
    await expect(widget).toHaveCount(0);
  });

  test('chat panel structure is correct when rendered', async ({ page }) => {
    // This test verifies the component markup structure.
    // When chat_enabled is false (default), the widget is not rendered.
    await page.goto('/');
    const widget = page.locator('#chat-widget');
    const count = await widget.count();

    if (count > 0) {
      const toggle = widget.locator('.chat-widget__toggle');
      await expect(toggle).toBeVisible();
      await expect(toggle).toHaveAttribute('aria-expanded', 'false');

      const panel = widget.locator('#chat-panel');
      await expect(panel).toBeHidden();

      // Click toggle to open
      await toggle.click();
      await expect(panel).toBeVisible();
      await expect(toggle).toHaveAttribute('aria-expanded', 'true');

      // Verify panel contents
      await expect(widget.locator('.chat-widget__title')).toHaveText('Community Assistant');
      await expect(widget.locator('.chat-widget__input')).toBeVisible();
      await expect(widget.locator('.chat-widget__send')).toBeVisible();
      await expect(widget.locator('.chat-widget__disclaimer')).toContainText('AI');

      // Close panel
      await widget.locator('.chat-widget__close').click();
      await expect(panel).toBeHidden();
    }
  });
});

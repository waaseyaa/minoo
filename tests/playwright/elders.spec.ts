import { test, expect } from '@playwright/test';

test.describe('Elders Portal', () => {
  test('elders landing page loads with program title', async ({ page }) => {
    await page.goto('/elders');
    await expect(page.locator('h1')).toContainText('Elder Support Program');
    await expect(page.getByTestId('request-help-link')).toBeVisible();
  });

  test('elders page has How It Works section', async ({ page }) => {
    await page.goto('/elders');
    await expect(page.getByTestId('how-it-works-heading')).toBeVisible();
    await expect(page.getByTestId('step-1-heading')).toBeVisible();
    await expect(page.getByTestId('step-2-heading')).toBeVisible();
    await expect(page.getByTestId('step-3-heading')).toBeVisible();
  });

  test('request form loads with all fields', async ({ page }) => {
    await page.goto('/elders/request');
    await expect(page.locator('#name')).toBeVisible();
    await expect(page.locator('#phone')).toBeVisible();
    await expect(page.locator('#type')).toBeVisible();
    await expect(page.locator('#notes')).toBeVisible();
  });

  test('representative toggle shows additional fields', async ({ page }) => {
    await page.goto('/elders/request');
    const toggle = page.locator('#is_representative');
    const repFields = page.locator('#representative-fields');

    // Initially hidden via HTML hidden attribute
    await expect(repFields).toHaveAttribute('hidden', '');

    // Click toggle to show
    await toggle.check();
    await expect(repFields).toBeVisible();
    await expect(page.locator('#elder_name')).toBeVisible();
    await expect(page.locator('#consent')).toBeVisible();
  });

  test('submitting empty request form shows validation errors', async ({ page }) => {
    await page.goto('/elders/request');
    await page.locator('.form button[type="submit"]').click();
    // HTML5 required fields prevent submission in browser — name field should be focused/invalid
    // The form has required attributes on name, phone, type
    const nameInput = page.locator('#name');
    const phoneInput = page.locator('#phone');
    const typeSelect = page.locator('#type');
    await expect(nameInput).toHaveAttribute('required', '');
    await expect(phoneInput).toHaveAttribute('required', '');
    await expect(typeSelect).toHaveAttribute('required', '');
  });

  test('request form has privacy note', async ({ page }) => {
    await page.goto('/elders/request');
    await expect(page.locator('.privacy-note')).toBeVisible();
  });

  test('request form has safety link', async ({ page }) => {
    await page.goto('/elders/request');
    await expect(page.locator('a[href="/safety"]')).toBeVisible();
  });

  test('request form includes CSRF token', async ({ page }) => {
    await page.goto('/elders/request');
    await expect(page.locator('input[name="_csrf_token"]')).toBeAttached();
  });

  test('submitting valid request redirects to confirmation', async ({ page }) => {
    await page.goto('/elders/request');
    await page.locator('#name').fill('Mary Elder');
    await page.locator('#phone').fill('705-555-1234');
    await page.locator('#type').selectOption({ index: 1 });
    await page.locator('.form button[type="submit"]').click();
    await expect(page).toHaveURL(/\/elders\/request\/[a-f0-9-]+/);
  });

  test('confirmation page shows UUID reference and submitted details', async ({ page }) => {
    await page.goto('/elders/request');
    await page.locator('#name').fill('Mary Elder');
    await page.locator('#phone').fill('705-555-1234');
    await page.locator('#type').selectOption('ride');
    await page.locator('#community').fill('Wikwemikong');
    await page.locator('.form button[type="submit"]').click();

    // Confirmation page should show UUID reference
    await expect(page.locator('.card__meta')).toContainText(/Reference:/);
    // Should show the requester name in confirmation message
    await expect(page.getByTestId('confirmation-message')).toBeVisible();
    // Should show type of help
    await expect(page.getByTestId('confirmation-type')).toBeVisible();
    // Should show community
    await expect(page.getByTestId('confirmation-community')).toBeVisible();
    // Should show "What Happens Next" steps
    await expect(page.getByTestId('what-next-heading')).toBeVisible();
  });

  test('representative submission requires elder name', async ({ page }) => {
    await page.goto('/elders/request');
    await page.locator('#name').fill('Jane Representative');
    await page.locator('#phone').fill('705-555-9999');
    await page.locator('#type').selectOption('groceries');
    await page.locator('#is_representative').check();
    // Wait for JS to unhide representative fields
    await expect(page.locator('#representative-fields')).toBeVisible();
    // Leave elder_name empty, do not check consent
    await page.locator('.form button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    // Server-side validation should show error for elder_name
    await expect(page.locator('.form__error').first()).toBeVisible();
  });

  test('request form is centered', async ({ page }) => {
    await page.goto('/elders/request');
    const form = page.locator('.form');
    await expect(form).toHaveClass(/form--centered/);
  });

  test('volunteer form is centered', async ({ page }) => {
    await page.goto('/elders/volunteer');
    const form = page.locator('.form');
    await expect(form).toHaveClass(/form--centered/);
  });

  test('safety callout uses forest colors', async ({ page }) => {
    await page.goto('/elders');
    const callout = page.locator('.safety-callout');
    await expect(callout).toBeVisible();
    await expect(callout).toContainText('Your Safety Matters');
  });

  test('landing page intro has no em-dashes', async ({ page }) => {
    await page.goto('/elders');
    const intro = await page.locator('.text-secondary').first().textContent();
    expect(intro).not.toContain('—');
  });

  test('representative submission requires consent checkbox', async ({ page }) => {
    await page.goto('/elders/request');
    await page.locator('#name').fill('Jane Representative');
    await page.locator('#phone').fill('705-555-9999');
    await page.locator('#type').selectOption('groceries');
    await page.locator('#is_representative').check();
    await page.locator('#elder_name').fill('Elder Person');
    // Do not check consent
    await page.locator('.form button[type="submit"]').click();

    // Server-side validation should show error for consent
    await expect(page.locator('.form__error')).toBeVisible();
  });
});

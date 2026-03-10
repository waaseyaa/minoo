import { test, expect } from '@playwright/test';

test.describe('Elders Portal', () => {
  test('elders landing page loads with program title', async ({ page }) => {
    await page.goto('/elders');
    await expect(page.locator('h1')).toContainText('Elder Support Program');
    await expect(page.getByRole('link', { name: 'Request Help' })).toBeVisible();
  });

  test('elders page has How It Works section', async ({ page }) => {
    await page.goto('/elders');
    await expect(page.getByRole('heading', { name: 'How It Works' })).toBeVisible();
    await expect(page.getByRole('heading', { name: '1. Request Help' })).toBeVisible();
    await expect(page.getByRole('heading', { name: '2. Get Matched' })).toBeVisible();
    await expect(page.getByRole('heading', { name: '3. Receive Support' })).toBeVisible();
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
    await page.locator('button[type="submit"]').click();
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
});

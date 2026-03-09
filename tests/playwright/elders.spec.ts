import { test, expect } from '@playwright/test';

test.describe('Elders Portal', () => {
  test('elders landing page loads', async ({ page }) => {
    await page.goto('/elders');
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('text=Request Help')).toBeVisible();
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

  test('request form has privacy note', async ({ page }) => {
    await page.goto('/elders/request');
    await expect(page.locator('.privacy-note')).toBeVisible();
  });

  test('request form has safety link', async ({ page }) => {
    await page.goto('/elders/request');
    await expect(page.locator('a[href="/safety"]')).toBeVisible();
  });
});

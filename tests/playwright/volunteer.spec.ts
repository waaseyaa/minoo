import { test, expect } from '@playwright/test';

test.describe('Volunteer Portal', () => {
  test('volunteer landing page loads', async ({ page }) => {
    await page.goto('/volunteer');
    await expect(page.locator('.hero__title')).toBeVisible();
    await expect(page.locator('a[href="/elders/volunteer"]')).toBeVisible();
  });

  test('volunteer signup form loads', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await expect(page.locator('#name')).toBeVisible();
    await expect(page.locator('#phone')).toBeVisible();
    await expect(page.locator('#availability')).toBeVisible();
  });

  test('volunteer form has privacy note', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await expect(page.locator('.privacy-note')).toBeVisible();
  });

  test('volunteer signup form renders with heading and submit button', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('skill checkboxes are present', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await expect(page.locator('input[type="checkbox"][name="skills[]"]').first()).toBeVisible();
  });

  test('travel km field is present', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await expect(page.locator('input[name="max_travel_km"]')).toBeVisible();
  });

  test('signup form includes CSRF token', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await expect(page.locator('input[name="_csrf_token"]')).toBeAttached();
  });

  test('submitting valid signup redirects to confirmation', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await page.locator('#name').fill('John Volunteer');
    await page.locator('#phone').fill('705-555-5678');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/elders\/volunteer\/[a-f0-9-]+/);
  });

  test('submitting signup without name shows validation', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await page.locator('#phone').fill('705-555-5678');
    await page.locator('button[type="submit"]').click();
    // HTML5 required attribute prevents submission
    await expect(page.locator('#name')).toHaveAttribute('required', '');
  });

  test('submitting signup without phone shows validation', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await page.locator('#name').fill('John Volunteer');
    await page.locator('button[type="submit"]').click();
    // HTML5 required attribute prevents submission
    await expect(page.locator('#phone')).toHaveAttribute('required', '');
  });

  test('skills selection appears on confirmation page', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await page.locator('#name').fill('Jane Helper');
    await page.locator('#phone').fill('705-555-4321');
    // Check two skill checkboxes
    await page.locator('input[name="skills[]"][value="Rides"]').check();
    await page.locator('input[name="skills[]"][value="Groceries"]').check();
    await page.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/\/elders\/volunteer\/[a-f0-9-]+/);
    // Confirmation should show selected skills as tags
    await expect(page.locator('.card__tag', { hasText: 'Rides' })).toBeVisible();
    await expect(page.locator('.card__tag', { hasText: 'Groceries' })).toBeVisible();
  });

  test('confirmation page shows volunteer details', async ({ page }) => {
    await page.goto('/elders/volunteer');
    await page.locator('#name').fill('John Volunteer');
    // Use a unique phone to avoid duplicate phone validation
    await page.locator('#phone').fill(`705-555-${Date.now().toString().slice(-4)}`);
    await page.locator('#availability').fill('Weekends');
    await page.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/\/elders\/volunteer\/[a-f0-9-]+/);
    // Should show thank-you heading
    await expect(page.getByRole('heading', { name: 'Thank You for Volunteering' })).toBeVisible();
    // Should show the volunteer's name
    await expect(page.getByText('Thank you, John Volunteer')).toBeVisible();
    // Should show availability
    await expect(page.getByText('Weekends')).toBeVisible();
    // Should show "What Happens Next" steps
    await expect(page.getByRole('heading', { name: 'What Happens Next' })).toBeVisible();
  });
});

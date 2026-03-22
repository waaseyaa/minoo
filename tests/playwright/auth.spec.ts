import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.beforeAll(() => {
  execSync('php bin/seed-test-user', { cwd: process.cwd() });
});

test.describe('Auth flows', () => {
  // ── Login ──────────────────────────────────────────────────────────

  test('login form renders with email and password fields', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('h1')).toContainText('Sign In');
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('.form button[type="submit"]')).toBeVisible();
  });

  test('login form includes CSRF token', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('input[name="_csrf_token"]')).toBeAttached();
  });

  test('login validation shows errors for empty form', async ({ page }) => {
    await page.goto('/login');
    // Bypass HTML5 required validation to reach server-side errors
    await page.$eval('form', (form) => form.noValidate = true);
    await page.click('.form button[type="submit"]');
    await expect(page.getByText('Email is required.')).toBeVisible();
    await expect(page.getByText('Password is required.')).toBeVisible();
  });

  test('login failure shows error for invalid credentials', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'nobody@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('.form button[type="submit"]');
    await expect(page.getByText('Invalid email or password.')).toBeVisible();
  });

  test('login success redirects to homepage', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@minoo.test');
    await page.fill('input[name="password"]', 'TestPass123!');
    await page.click('.form button[type="submit"]');
    await page.waitForURL('/');
  });

  // ── Registration ───────────────────────────────────────────────────

  test('registration form renders with name, email, and password fields', async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('.form button[type="submit"]')).toBeVisible();
  });

  test('register form includes CSRF token', async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('input[name="_csrf_token"]')).toBeAttached();
  });

  test('registration validation shows errors for empty form', async ({ page }) => {
    await page.goto('/register');
    // Bypass HTML5 required validation to reach server-side errors
    await page.$eval('form', (form) => form.noValidate = true);
    await page.click('.form button[type="submit"]');
    await expect(page.getByText('Name is required.')).toBeVisible();
    await expect(page.getByText('Email is required.')).toBeVisible();
    await expect(page.getByText('Password is required.')).toBeVisible();
  });

  test('registration password too short shows error', async ({ page }) => {
    await page.goto('/register');
    await page.fill('input[name="name"]', 'Short Pass User');
    await page.fill('input[name="email"]', 'shortpass@example.com');
    await page.fill('input[name="password"]', 'abc');
    // Bypass HTML5 minlength validation to reach server-side errors
    await page.$eval('form', (form) => form.noValidate = true);
    await page.click('.form button[type="submit"]');
    await expect(page.getByText('Password must be at least 8 characters.')).toBeVisible();
  });

  test('registration duplicate email shows check-email page (no enumeration)', async ({ page }) => {
    await page.goto('/register');
    await page.fill('input[name="name"]', 'Duplicate User');
    await page.fill('input[name="email"]', 'test@minoo.test');
    await page.fill('input[name="password"]', 'ValidPass123!');
    await page.click('.form button[type="submit"]');
    await expect(page.getByText('Check Your Email')).toBeVisible();
  });

  test('registration success shows check-email page', async ({ page }) => {
    await page.goto('/register');
    await page.fill('input[name="name"]', 'New Test User');
    await page.fill('input[name="email"]', `newuser-${Date.now()}@minoo.test`);
    await page.fill('input[name="password"]', 'NewUserPass123!');
    await page.click('.form button[type="submit"]');
    await expect(page.getByText('Check Your Email')).toBeVisible();
  });

  // ── Logout ─────────────────────────────────────────────────────────

  test('logout destroys session and redirects to homepage', async ({ page }) => {
    // First log in
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@minoo.test');
    await page.fill('input[name="password"]', 'TestPass123!');
    await page.click('.form button[type="submit"]');
    await page.waitForURL('/');

    // Then log out
    await page.goto('/logout');
    await page.waitForURL('/');
  });

  // ── Auth redirects (skipped in dev — fallback account) ────────────

  test.skip('coordinator dashboard redirects unauthenticated users to /login', async ({ page }) => {
    await page.goto('/dashboard/coordinator');
    await expect(page).toHaveURL(/\/login/);
  });

  test.skip('volunteer dashboard redirects unauthenticated users to /login', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page).toHaveURL(/\/login/);
  });

  test.skip('redirect preserves intended destination in query param', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page).toHaveURL(/\/login\?redirect=/);
  });

  test('login form is centered with welcoming copy', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('.form')).toHaveClass(/form--centered/);
    await expect(page.locator('.text-secondary')).toContainText('Welcome back');
  });

  test('register form is centered with welcoming copy', async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('.form')).toHaveClass(/form--centered/);
    await expect(page.locator('.text-secondary')).toContainText('Join Minoo');
  });

  test('forgot-password form is centered', async ({ page }) => {
    await page.goto('/forgot-password');
    await expect(page.locator('.form')).toHaveClass(/form--centered/);
  });

  test('login page has link to register', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('a[href="/register"]')).toBeVisible();
  });

  // ── 403 error page tests (skipped in dev — fallback account) ──────

  test.skip('visiting protected route unauthenticated shows friendly 403', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page.locator('h1')).toContainText('Forbidden');
    await expect(page.locator('a[href*="/login?redirect="]')).toBeVisible();
  });

  test.skip('403 page includes login link with redirect', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page.locator('a[href*="/login?redirect=%2Fdashboard%2Fvolunteer"]')).toBeVisible();
  });

  test.skip('403 page includes link to homepage', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page.locator('a[href="/"]')).toBeVisible();
  });
});

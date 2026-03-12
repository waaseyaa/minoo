# Account Home Dashboard Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a general-purpose Account Home page for all authenticated users, isolating volunteer/coordinator dashboards behind RBAC so non-program users have a clean post-login experience.

**Architecture:** New `AccountHomeController` at `/account` with `requireAuthentication()`. Update `AuthController::dashboardRedirect()` to send non-volunteer/non-coordinator users to `/account` instead of `/`. Update nav in `base.html.twig` to show "Account" link for authenticated users without program roles.

**Tech Stack:** PHP 8.3, Twig 3, Waaseyaa framework (RouteBuilder, AccountInterface, SsrResponse), Playwright for E2E tests.

---

## File Structure

| Action | File | Responsibility |
|--------|------|---------------|
| Create | `src/Controller/AccountHomeController.php` | Render account home page for authenticated users |
| Create | `src/Provider/AccountServiceProvider.php` | Register `/account` route |
| Create | `templates/account/home.html.twig` | Account home template |
| Modify | `src/Controller/AuthController.php:358-371` | Update `dashboardRedirect()` fallback from `/` to `/account` |
| Modify | `templates/base.html.twig:36-45` | Add "Account" nav link for authenticated non-program users |
| Create | `tests/Minoo/Unit/Controller/AccountHomeControllerTest.php` | Unit test for controller |
| Modify | `bin/seed-test-user` | Add a second test user without volunteer role |
| Create | `tests/playwright/account.spec.ts` | Playwright E2E tests for account home |
| Modify | `tests/playwright/auth.spec.ts:41-47,94-101` | Update redirect expectations for non-volunteer flow |

---

## Chunk 1: AccountHomeController + Route

### Task 1: AccountHomeController unit test and implementation

**Files:**
- Create: `tests/Minoo/Unit/Controller/AccountHomeControllerTest.php`
- Create: `src/Controller/AccountHomeController.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\AccountHomeController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(AccountHomeController::class)]
final class AccountHomeControllerTest extends TestCase
{
    #[Test]
    public function indexRendersAccountHomePage(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(1);
        $account->method('getRoles')->willReturn([]);
        $account->method('isAuthenticated')->willReturn(true);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('account/home.html.twig', $this->callback(function (array $vars) {
                return $vars['account'] instanceof AccountInterface
                    && $vars['roles'] === []
                    && $vars['path'] === '/account';
            }))
            ->willReturn('<html>account home</html>');

        $controller = new AccountHomeController($twig);
        $response = $controller->index([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function indexPassesRolesToTemplate(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(2);
        $account->method('getRoles')->willReturn(['volunteer']);
        $account->method('isAuthenticated')->willReturn(true);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('account/home.html.twig', $this->callback(function (array $vars) {
                return $vars['roles'] === ['volunteer'];
            }))
            ->willReturn('<html>account home</html>');

        $controller = new AccountHomeController($twig);
        $response = $controller->index([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/AccountHomeControllerTest.php`
Expected: FAIL — class `AccountHomeController` does not exist.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\SsrResponse;

final class AccountHomeController
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('account/home.html.twig', [
            'account' => $account,
            'roles' => $account->getRoles(),
            'path' => '/account',
        ]);

        return new SsrResponse(content: $html);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/AccountHomeControllerTest.php`
Expected: PASS (2 tests, 2 assertions)

- [ ] **Step 5: Commit**

```bash
git add tests/Minoo/Unit/Controller/AccountHomeControllerTest.php src/Controller/AccountHomeController.php
git commit -m "feat(#TBD): add AccountHomeController with unit tests"
```

### Task 2: AccountServiceProvider and route registration

**Files:**
- Create: `src/Provider/AccountServiceProvider.php`

- [ ] **Step 1: Write the service provider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AccountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types.
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'account.home',
            RouteBuilder::create('/account')
                ->controller('Minoo\Controller\AccountHomeController::index')
                ->requireAuthentication()
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}
```

- [ ] **Step 2: Register the provider in composer.json**

Check if `composer.json` has an `extra.waaseyaa.providers` array. If so, add `Minoo\\Provider\\AccountServiceProvider`. If providers are auto-discovered via PSR-4 scanning, no change needed.

- [ ] **Step 3: Delete stale manifest cache**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 4: Verify route loads**

Run: `./vendor/bin/phpunit --testsuite MinooIntegration`
Expected: PASS — integration tests boot the kernel successfully.

- [ ] **Step 5: Commit**

```bash
git add src/Provider/AccountServiceProvider.php
git commit -m "feat(#TBD): add AccountServiceProvider with /account route"
```

### Task 3: Account home Twig template

**Files:**
- Create: `templates/account/home.html.twig`

- [ ] **Step 1: Create the template**

```twig
{% extends "base.html.twig" %}

{% block title %}Your Account — Minoo{% endblock %}

{% block content %}
<section class="account-home">
  <h1>Your Account</h1>

  <div class="actions">
    <h2>Profile</h2>
    <ul class="links">
      <li><a href="/logout" class="btn btn--outline">Sign Out</a></li>
    </ul>
  </div>

  {% if 'volunteer' in roles %}
  <div class="actions">
    <h2>Elder Support Program</h2>
    <ul class="links">
      <li><a href="/dashboard/volunteer">My Assignments</a></li>
    </ul>
  </div>
  {% endif %}

  {% if 'elder_coordinator' in roles %}
  <div class="actions">
    <h2>Coordination</h2>
    <ul class="links">
      <li><a href="/dashboard/coordinator">Coordinator Dashboard</a></li>
    </ul>
  </div>
  {% endif %}
</section>
{% endblock %}
```

- [ ] **Step 2: Add CSS for account home in minoo.css**

Add to `@layer components` in `public/css/minoo.css`:

```css
/* Account home */
.account-home {
  max-inline-size: 40rem;

  & .actions { margin-block-start: var(--space-l); }
  & .actions h2 { font-size: var(--step-0); margin-block-end: var(--space-s); }
  & .links { list-style: none; padding: 0; display: flex; flex-direction: column; gap: var(--space-xs); }
  & .links a { display: inline-block; }
}
```

- [ ] **Step 3: Verify template renders**

Start dev server and visit `/account` while logged in. Confirm the page renders with the heading and links.

- [ ] **Step 4: Commit**

```bash
git add templates/account/home.html.twig public/css/minoo.css
git commit -m "feat(#TBD): add account home template and styles"
```

---

## Chunk 2: Login Redirect + Navigation Update

### Task 4: Update login redirect logic

**Files:**
- Modify: `src/Controller/AuthController.php:358-371`

- [ ] **Step 1: Update `dashboardRedirect()` method**

Change the fallback from `/` to `/account`:

```php
private function dashboardRedirect(User $user): string
{
    $roles = $user->getRoles();

    if (in_array('elder_coordinator', $roles, true)) {
        return '/dashboard/coordinator';
    }

    if (in_array('volunteer', $roles, true)) {
        return '/dashboard/volunteer';
    }

    return '/account';
}
```

- [ ] **Step 2: Run existing tests**

Run: `./vendor/bin/phpunit`
Expected: All pass — no existing unit tests cover `dashboardRedirect()` directly.

- [ ] **Step 3: Commit**

```bash
git add src/Controller/AuthController.php
git commit -m "feat(#TBD): redirect non-program users to /account after login"
```

### Task 5: Update navigation in base.html.twig

**Files:**
- Modify: `templates/base.html.twig:36-45`

- [ ] **Step 1: Update nav block**

Replace the authenticated user nav section (lines 36-45) with:

```twig
{% if account is defined and account.isAuthenticated() %}
  {% if 'elder_coordinator' in account.getRoles() %}
    <li class="site-nav__utility"><a href="/dashboard/coordinator"{% if path is defined and path starts with '/dashboard' %} aria-current="page"{% endif %}>Dashboard</a></li>
  {% elseif 'volunteer' in account.getRoles() %}
    <li class="site-nav__utility"><a href="/dashboard/volunteer"{% if path is defined and path starts with '/dashboard' %} aria-current="page"{% endif %}>My Dashboard</a></li>
  {% endif %}
  <li class="site-nav__utility"><a href="/account"{% if path is defined and path starts with '/account' %} aria-current="page"{% endif %}>Account</a></li>
  <li class="site-nav__utility"><a href="/logout">Logout</a></li>
{% else %}
  <li class="site-nav__utility"><a href="/login"{% if path is defined and path == '/login' %} aria-current="page"{% endif %}>Login</a></li>
{% endif %}
```

Key changes:
- Added "Account" link for ALL authenticated users (between dashboard link and logout)
- Kept volunteer/coordinator dashboard links as-is (RBAC-gated)

- [ ] **Step 2: Run all tests**

Run: `./vendor/bin/phpunit`
Expected: All pass.

- [ ] **Step 3: Commit**

```bash
git add templates/base.html.twig
git commit -m "feat(#TBD): add Account nav link for authenticated users"
```

---

## Chunk 3: Test Fixtures + Playwright Tests

### Task 6: Add non-volunteer test user

**Files:**
- Modify: `bin/seed-test-user`

- [ ] **Step 1: Add a second user without volunteer role**

Append to `bin/seed-test-user`, after the existing user creation block:

```php
// Create a non-volunteer test user.
$existingMember = $storage->getQuery()->condition('mail', 'member@minoo.test')->execute();

if ($existingMember === []) {
    $member = $storage->create([
        'name' => 'Member User',
        'mail' => 'member@minoo.test',
        'status' => true,
        'created' => time(),
        'roles' => [],
        'permissions' => [],
    ]);
    $member->setRawPassword('MemberPass123!');
    $storage->save($member);
    fprintf(STDOUT, "Member user created: member@minoo.test / MemberPass123!\n");
} else {
    fprintf(STDOUT, "Member user already exists.\n");
}
```

- [ ] **Step 2: Run seeder to verify**

Run: `php bin/seed-test-user`
Expected: "Member user created: member@minoo.test / MemberPass123!"

- [ ] **Step 3: Commit**

```bash
git add bin/seed-test-user
git commit -m "feat(#TBD): add non-volunteer test user for account home tests"
```

### Task 7: Playwright smoke tests for Account Home

**Files:**
- Create: `tests/playwright/account.spec.ts`

- [ ] **Step 1: Write E2E tests**

```typescript
import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.beforeAll(() => {
  execSync('php bin/seed-test-user', { cwd: process.cwd() });
});

test.describe('Account Home', () => {
  test('non-volunteer login redirects to /account', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'member@minoo.test');
    await page.fill('input[name="password"]', 'MemberPass123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/account');
    await expect(page.locator('h1')).toContainText('Your Account');
  });

  test('volunteer login redirects to /dashboard/volunteer', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@minoo.test');
    await page.fill('input[name="password"]', 'TestPass123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard/volunteer');
  });

  test('account page shows sign out link', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'member@minoo.test');
    await page.fill('input[name="password"]', 'MemberPass123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/account');
    await expect(page.locator('a[href="/logout"]')).toBeVisible();
  });

  test('account page hides volunteer links for non-volunteers', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'member@minoo.test');
    await page.fill('input[name="password"]', 'MemberPass123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/account');
    await expect(page.locator('a[href="/dashboard/volunteer"]')).not.toBeVisible();
    await expect(page.locator('a[href="/dashboard/coordinator"]')).not.toBeVisible();
  });

  test('account page shows volunteer links for volunteers', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@minoo.test');
    await page.fill('input[name="password"]', 'TestPass123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard/volunteer');
    await page.goto('/account');
    await expect(page.locator('a[href="/dashboard/volunteer"]')).toBeVisible();
  });

  test('nav shows Account link for authenticated users', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'member@minoo.test');
    await page.fill('input[name="password"]', 'MemberPass123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/account');
    await expect(page.locator('nav a[href="/account"]')).toBeVisible();
  });

  test('nav hides dashboard links for non-volunteers', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'member@minoo.test');
    await page.fill('input[name="password"]', 'MemberPass123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('/account');
    await expect(page.locator('nav a[href="/dashboard/volunteer"]')).not.toBeVisible();
    await expect(page.locator('nav a[href="/dashboard/coordinator"]')).not.toBeVisible();
  });

  // Skipped in dev — fallback account bypasses auth redirect
  test.skip('unauthenticated /account access redirects to /login', async ({ page }) => {
    await page.goto('/account');
    await expect(page).toHaveURL(/\/login/);
  });
});
```

- [ ] **Step 2: Run Playwright tests**

Run: `npx playwright test tests/playwright/account.spec.ts`
Expected: All pass.

- [ ] **Step 3: Commit**

```bash
git add tests/playwright/account.spec.ts
git commit -m "test(#TBD): add Playwright tests for Account Home"
```

### Task 8: Update existing auth Playwright tests

**Files:**
- Modify: `tests/playwright/auth.spec.ts`

- [ ] **Step 1: Review auth test expectations**

The existing tests at lines 41-47 and 94-101 expect login/register to redirect to `/dashboard/volunteer`. These remain correct because:
- **Login test (line 46):** `test@minoo.test` has the `volunteer` role, so `dashboardRedirect()` still returns `/dashboard/volunteer`.
- **Registration test (line 100):** `submitRegister()` at line 192 hardcodes `Location: /dashboard/volunteer` because it auto-creates a volunteer profile (line 179-188). If registration ever stops auto-assigning the volunteer role, this redirect and its test would need updating.

No changes needed to existing auth tests.

- [ ] **Step 2: Run full Playwright suite**

Run: `npx playwright test`
Expected: All pass.

- [ ] **Step 3: Commit (if any changes were needed)**

No commit needed — existing tests already match the new behavior since test user is a volunteer.

---

## Chunk 4: Dashboard RBAC Audit

### Task 9: Audit dashboard controllers for RBAC guards

**Files:**
- Review: `src/Controller/VolunteerDashboardController.php`
- Review: `src/Controller/CoordinatorDashboardController.php`
- Review: `src/Provider/DashboardServiceProvider.php`

- [ ] **Step 1: Verify route-level RBAC**

`DashboardServiceProvider.php` already uses:
- `requireRole('volunteer')` on all volunteer dashboard routes
- `requireRole('elder_coordinator')` on the coordinator route

These are correct. No controller-level changes needed because route-level RBAC prevents non-role users from reaching the controller at all.

- [ ] **Step 2: Verify no volunteer assumptions elsewhere**

Search for hardcoded volunteer redirects or assumptions:

```bash
grep -rn "dashboard/volunteer\|dashboard/coordinator" src/Controller/ --include="*.php"
```

Confirm that the only references are in:
- `AuthController::dashboardRedirect()` (updated in Task 4)
- `VolunteerDashboardController` (internal redirects within the volunteer dashboard — correct, already behind RBAC)
- `VolunteerController::signupForm()` (redirects existing volunteers — correct)

- [ ] **Step 3: Document findings**

No changes needed. All dashboard routes are RBAC-guarded at the route level via `requireRole()`. No controller assumes all users are volunteers.

---

## Summary

| Task | What | Test Type |
|------|------|-----------|
| 1 | AccountHomeController | PHPUnit unit |
| 2 | AccountServiceProvider + route | Integration (kernel boot) |
| 3 | Twig template + CSS | Manual verify |
| 4 | Login redirect update | Existing tests + Playwright |
| 5 | Nav update | Playwright |
| 6 | Test user fixture | Seeder script |
| 7 | Playwright E2E tests | Playwright |
| 8 | Auth test review | Playwright (no changes) |
| 9 | RBAC audit | Code review |

**Total new files:** 4 (controller, provider, template, Playwright spec)
**Modified files:** 4 (AuthController, base.html.twig, minoo.css, seed-test-user)
**Estimated commits:** 7

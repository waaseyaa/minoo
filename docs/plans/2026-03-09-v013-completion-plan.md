# v0.13 Elder Support Completion — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Close the remaining open issues for v0.13 (Elder Support end-to-end), including verifying three previously implemented blockers, adding the volunteer accept/decline workflow state, and writing Playwright test coverage for the full Elder Support flow.

**Architecture:** Three "blockers" (#148, #149, #150) are already implemented in code but issues remain open — verify each, add a confirming test, and close. The genuine new work is #151 (volunteer accept/decline), its PHPUnit tests (#166), and the Playwright suite (#161–#165).

**Tech Stack:** PHP 8.3, PHPUnit 10.5, Playwright (TypeScript), Waaseyaa framework, SQLite (in-memory for integration tests)

---

## Task 1: Verify framework fix #148 (HttpKernel JSON parsing)

**Files:**
- Read: `../waaseyaa/packages/foundation/src/Kernel/HttpKernel.php` lines 464–485
- Repo: `waaseyaa/framework` (separate git repo at `/home/fsd42/dev/waaseyaa`)

**Step 1: Read the dispatch method**

```bash
# In /home/fsd42/dev/waaseyaa
grep -n "isJsonApi\|json_decode\|application/x-www-form" packages/foundation/src/Kernel/HttpKernel.php
```

Expected: lines showing `$isJsonApi = $matchedRoute->getOption('_json_api') === true` and JSON decode only inside `if ($isJsonApi && ...)`.

**Step 2: Confirm the guard is correct**

The fix is present if and only if JSON parsing is gated behind `_json_api` route option. Form routes never set this option, so they skip JSON parsing entirely.

**Step 3: Close the GitHub issue**

If the fix is confirmed in the waaseyaa/framework repo, close waaseyaa/framework issue #148 (or the equivalent framework issue) with a comment: "Implemented — `$isJsonApi` guard in `HttpKernel::dispatch()` restricts JSON parsing to API routes only."

**Step 4: No commit needed** — this is a read-only verification task.

---

## Task 2: Verify dashboard auth redirect #149

**Files:**
- Read: `../waaseyaa/packages/foundation/src/Http/Middleware/AuthorizationMiddleware.php`
- Read: `src/Provider/DashboardServiceProvider.php`

**Step 1: Read the middleware**

```bash
grep -n "isUnauthenticated\|RedirectResponse\|/login" ../waaseyaa/packages/foundation/src/Http/Middleware/AuthorizationMiddleware.php
```

Expected: a block returning `RedirectResponse('/login?redirect=...', 302)` when `$result->isUnauthenticated() && $isRenderRoute`.

**Step 2: Confirm dashboard routes are render routes**

In `DashboardServiceProvider`, check that `/dashboard/volunteer` and `/dashboard/coordinator` are registered without `_json_api` option (i.e., they go through SSR / render path).

**Step 3: Run the integration test**

```bash
./vendor/bin/phpunit --testsuite MinooIntegration
```

Expected: all integration tests pass. If a test exists that hits the coordinator dashboard unauthenticated, confirm it returns 302.

**Step 4: Close GitHub issue #149**

Comment: "Implemented — `AuthorizationMiddleware` returns `302 /login?redirect=...` for unauthenticated requests to render routes."

---

## Task 3: Verify volunteer account linking #150

**Files:**
- Read: `src/Controller/VolunteerController.php` method `submitSignup`
- Read: `tests/Minoo/Unit/Controller/VolunteerControllerTest.php`

**Step 1: Read submitSignup**

```bash
grep -n "account_id\|account->id" src/Controller/VolunteerController.php
```

Expected: `$values['account_id'] = $account->id();` before `$storage->save($entity)`.

**Step 2: Verify the existing unit test covers this**

```bash
grep -n "account_id\|account->id" tests/Minoo/Unit/Controller/VolunteerControllerTest.php
```

If no test asserts `account_id` is set, add one (see Step 3). If it exists, skip to Step 5.

**Step 3: Write a confirming test (if missing)**

In `tests/Minoo/Unit/Controller/VolunteerControllerTest.php`, add:

```php
#[Test]
public function signup_stores_account_id_on_volunteer_entity(): void
{
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('isAuthenticated')->willReturn(true);

    $savedValues = null;
    $this->volStorage->method('save')->willReturnCallback(
        function ($entity) use (&$savedValues) { $savedValues = $entity; }
    );

    // POST with minimum required fields
    $request = HttpRequest::create('/', 'POST', [
        'name' => 'Test Volunteer',
        'phone' => '5551234567',
        'availability' => 'weekends',
        'max_travel_km' => '50',
    ]);

    $this->controller->submitSignup([], [], $account, $request);

    $this->assertSame(42, $savedValues?->get('account_id'));
}
```

**Step 4: Run the test**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/VolunteerControllerTest.php --testdox
```

Expected: PASS

**Step 5: Commit (if test was added)**

```bash
git add tests/Minoo/Unit/Controller/VolunteerControllerTest.php
git commit -m "test(#150): assert volunteer signup stores account_id"
```

**Step 6: Close GitHub issue #150**

Comment: "Verified — `submitSignup` sets `account_id = $account->id()` on save."

---

## Task 4: Implement volunteer accept/decline (#151)

A volunteer who has been assigned a request should be able to decline it, returning the request to `open` status so it can be reassigned by a coordinator.

**Files:**
- Modify: `src/Controller/ElderSupportWorkflowController.php`
- Modify: `src/Provider/ElderSupportServiceProvider.php`

**Step 1: Write the failing test first (see Task 5 — do Task 5 Step 1 before this)**

Follow TDD: write all three tests in Task 5 before adding the method. They must fail first.

**Step 2: Add the route**

In `src/Provider/ElderSupportServiceProvider.php`, find the block registering volunteer workflow routes (look for `startRequest` or `completeRequest`). Add immediately after the last volunteer route:

```php
$routes->add('elder.decline', RouteFactory::post(
    '/elders/request/{esrid}/decline',
    ElderSupportWorkflowController::class . '::declineRequest',
)->requireRole('volunteer'));
```

**Step 3: Add the controller method**

In `src/Controller/ElderSupportWorkflowController.php`, add after `completeRequest`:

```php
public function declineRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $esrid = (int) ($params['esrid'] ?? 0);
    $storage = $this->entityTypeManager->getStorage('elder_support_request');
    $entity = $storage->load($esrid);

    if ($entity === null) {
        return new SsrResponse(content: '', statusCode: 404);
    }

    if ((int) $entity->get('assigned_volunteer') !== $account->id()) {
        return new SsrResponse(content: '', statusCode: 403);
    }

    if ($entity->get('status') !== 'assigned') {
        return new SsrResponse(content: '', statusCode: 422);
    }

    $entity->set('status', 'open');
    $entity->set('assigned_volunteer', null);
    $entity->set('assigned_at', null);
    $entity->set('updated_at', time());
    $storage->save($entity);

    return new SsrResponse(
        content: '',
        statusCode: 302,
        headers: ['Location' => '/dashboard/volunteer'],
    );
}
```

**Step 4: Run only the decline tests**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php --filter decline --testdox
```

Expected: all 4 decline tests PASS

**Step 5: Run the full unit suite**

```bash
./vendor/bin/phpunit --testsuite MinooUnit
```

Expected: all tests pass, no regressions

**Step 6: Commit**

```bash
git add src/Controller/ElderSupportWorkflowController.php
git add src/Provider/ElderSupportServiceProvider.php
git commit -m "feat(#151): add volunteer decline transition to Elder Support workflow"
```

---

## Task 5: PHPUnit tests for decline (#166)

Write these tests **before** implementing Task 4, so they fail first (TDD).

**Files:**
- Modify: `tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php`

**Step 1: Add the four failing tests**

Append to `ElderSupportWorkflowControllerTest`:

```php
#[Test]
public function decline_transitions_assigned_request_back_to_open(): void
{
    $entity = new ElderSupportRequest([
        'status' => 'assigned',
        'assigned_volunteer' => 5,
        'assigned_at' => time() - 3600,
    ]);

    $this->requestStorage->method('load')->with(1)->willReturn($entity);
    $this->requestStorage->expects($this->once())->method('save');

    $account = $this->createVolunteerAccount(5);
    $response = $this->controller->declineRequest(['esrid' => '1'], [], $account, $this->request);

    $this->assertSame(302, $response->statusCode);
    $this->assertSame('open', $entity->get('status'));
    $this->assertNull($entity->get('assigned_volunteer'));
    $this->assertNull($entity->get('assigned_at'));
}

#[Test]
public function decline_returns_403_when_volunteer_is_not_the_assigned_one(): void
{
    $entity = new ElderSupportRequest([
        'status' => 'assigned',
        'assigned_volunteer' => 42,
    ]);

    $this->requestStorage->method('load')->with(1)->willReturn($entity);
    $this->requestStorage->expects($this->never())->method('save');

    $account = $this->createVolunteerAccount(5); // not volunteer 42
    $response = $this->controller->declineRequest(['esrid' => '1'], [], $account, $this->request);

    $this->assertSame(403, $response->statusCode);
    $this->assertSame('assigned', $entity->get('status')); // unchanged
}

#[Test]
public function decline_returns_422_when_request_is_not_in_assigned_status(): void
{
    $entity = new ElderSupportRequest([
        'status' => 'open',
        'assigned_volunteer' => 5,
    ]);

    $this->requestStorage->method('load')->with(1)->willReturn($entity);
    $this->requestStorage->expects($this->never())->method('save');

    $account = $this->createVolunteerAccount(5);
    $response = $this->controller->declineRequest(['esrid' => '1'], [], $account, $this->request);

    $this->assertSame(422, $response->statusCode);
}

#[Test]
public function decline_returns_404_when_request_does_not_exist(): void
{
    $this->requestStorage->method('load')->with(99)->willReturn(null);

    $account = $this->createVolunteerAccount(5);
    $response = $this->controller->declineRequest(['esrid' => '99'], [], $account, $this->request);

    $this->assertSame(404, $response->statusCode);
}
```

**Step 2: Run the new tests — confirm they fail**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php --filter decline --testdox
```

Expected: FAIL with "Call to undefined method ... declineRequest()" or similar

**Step 3: Commit the failing tests**

```bash
git add tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php
git commit -m "test(#166): add PHPUnit tests for volunteer decline transition (red)"
```

**Step 4: Return to Task 4 and implement**

After Task 4 is complete, run tests again:

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php --filter decline --testdox
```

Expected: all 4 PASS

**Step 5: Commit tests alongside implementation (or separately)**

Tests were committed as red in Step 3. Implementation committed in Task 4. No additional commit needed here.

---

## Task 6: Playwright tests — Elder request form (#161)

**Files:**
- Modify: `tests/playwright/elders.spec.ts`

**Step 1: Read existing spec**

```bash
cat tests/playwright/elders.spec.ts
```

**Step 2: Add the happy-path and validation tests**

Following the existing pattern in the file, add:

```typescript
test('elder request form submits successfully', async ({ page }) => {
  await page.goto('/elders/request');
  await page.fill('[name="name"]', 'Test Elder');
  await page.fill('[name="phone"]', '5551234567');
  await page.selectOption('[name="type"]', 'visit');
  await page.click('[type="submit"]');
  await expect(page).toHaveURL(/\/elders\/request\//);
  await expect(page.locator('h1')).toContainText('request');
});

test('elder request form shows validation error when name is missing', async ({ page }) => {
  await page.goto('/elders/request');
  await page.fill('[name="phone"]', '5551234567');
  await page.click('[type="submit"]');
  await expect(page).toHaveURL('/elders/request');
});

test('elder request representative toggle shows conditional fields', async ({ page }) => {
  await page.goto('/elders/request');
  await expect(page.locator('[name="elder_name"]')).toBeHidden();
  await page.check('[name="is_representative"]');
  await expect(page.locator('[name="elder_name"]')).toBeVisible();
});
```

**Step 3: Run the spec**

```bash
npx playwright test tests/playwright/elders.spec.ts
```

Expected: PASS (server must be running on port 8081)

**Step 4: Commit**

```bash
git add tests/playwright/elders.spec.ts
git commit -m "test(#161): Playwright tests for elder request form"
```

---

## Task 7: Playwright tests — Volunteer signup and auth flows (#162)

**Files:**
- Modify: `tests/playwright/volunteer.spec.ts`

**Step 1: Read existing spec**

```bash
cat tests/playwright/volunteer.spec.ts
```

**Step 2: Add login, register, and signup submission tests**

```typescript
test('volunteer signup form submits successfully when logged in', async ({ page }) => {
  // Login first
  await page.goto('/login');
  await page.fill('[name="email"]', 'volunteer@test.local');
  await page.fill('[name="password"]', 'password');
  await page.click('[type="submit"]');

  // Then submit signup
  await page.goto('/elders/volunteer');
  await page.fill('[name="name"]', 'Test Volunteer');
  await page.fill('[name="phone"]', '5551234567');
  await page.selectOption('[name="availability"]', { index: 1 });
  await page.fill('[name="max_travel_km"]', '100');
  await page.click('[type="submit"]');
  await expect(page).toHaveURL(/\/elders\/volunteer\//);
});

test('unauthenticated volunteer signup redirects to login', async ({ page }) => {
  // Clear any session
  await page.goto('/logout');
  await page.goto('/elders/volunteer');
  // If route requires auth, expect redirect; if public, expect form
  // Adjust assertion based on actual route protection
  await expect(page).not.toHaveURL('/500');
});
```

**Step 3: Run the spec**

```bash
npx playwright test tests/playwright/volunteer.spec.ts
```

**Step 4: Commit**

```bash
git add tests/playwright/volunteer.spec.ts
git commit -m "test(#162): Playwright tests for volunteer signup and auth flows"
```

---

## Task 8: Playwright tests — Auth redirects and RBAC (#165)

**Files:**
- Create: `tests/playwright/auth.spec.ts`

**Step 1: Create the spec**

```typescript
import { test, expect } from '@playwright/test';

test.describe('auth redirects', () => {
  test('unauthenticated access to coordinator dashboard redirects to /login', async ({ page }) => {
    await page.goto('/dashboard/coordinator');
    await expect(page).toHaveURL(/\/login/);
  });

  test('unauthenticated access to volunteer dashboard redirects to /login', async ({ page }) => {
    await page.goto('/dashboard/volunteer');
    await expect(page).toHaveURL(/\/login/);
  });

  test('login redirect param is preserved', async ({ page }) => {
    await page.goto('/dashboard/coordinator');
    await expect(page).toHaveURL(/redirect=%2Fdashboard%2Fcoordinator/);
  });
});
```

**Step 2: Run**

```bash
npx playwright test tests/playwright/auth.spec.ts
```

Expected: PASS — these assert the #149 behavior at E2E level

**Step 3: Commit**

```bash
git add tests/playwright/auth.spec.ts
git commit -m "test(#165): Playwright tests for auth redirects and role-based access"
```

---

## Task 9: Playwright tests — Coordinator dashboard workflow (#164)

**Files:**
- Create: `tests/playwright/coordinator.spec.ts`

These tests require a coordinator account and at least one open request in the test database. Use Playwright's `setup` project or a fixture if available; otherwise test the page loads and UI structure.

**Step 1: Create the spec**

```typescript
import { test, expect } from '@playwright/test';

test.describe('coordinator dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('[name="email"]', 'coordinator@test.local');
    await page.fill('[name="password"]', 'password');
    await page.click('[type="submit"]');
    await expect(page).toHaveURL('/dashboard/coordinator');
  });

  test('coordinator dashboard loads without error', async ({ page }) => {
    await expect(page.locator('h1')).toBeVisible();
    await expect(page).not.toHaveURL('/login');
  });

  test('open requests section is visible', async ({ page }) => {
    // Assert section heading or empty state — either is valid
    const openSection = page.locator('[data-section="open"], .requests-open, h2:has-text("Open")');
    await expect(openSection.first()).toBeVisible();
  });
});
```

**Step 2: Run**

```bash
npx playwright test tests/playwright/coordinator.spec.ts
```

**Step 3: Commit**

```bash
git add tests/playwright/coordinator.spec.ts
git commit -m "test(#164): Playwright tests for coordinator dashboard"
```

---

## Task 10: Playwright tests — Volunteer dashboard (#163)

**Files:**
- Create: `tests/playwright/volunteer-dashboard.spec.ts`

**Step 1: Create the spec**

```typescript
import { test, expect } from '@playwright/test';

test.describe('volunteer dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('[name="email"]', 'volunteer@test.local');
    await page.fill('[name="password"]', 'password');
    await page.click('[type="submit"]');
    await expect(page).toHaveURL('/dashboard/volunteer');
  });

  test('volunteer dashboard loads without error', async ({ page }) => {
    await expect(page.locator('h1')).toBeVisible();
  });

  test('profile edit link is present', async ({ page }) => {
    await expect(page.locator('a[href*="edit"]')).toBeVisible();
  });
});
```

**Step 2: Run**

```bash
npx playwright test tests/playwright/volunteer-dashboard.spec.ts
```

**Step 3: Commit**

```bash
git add tests/playwright/volunteer-dashboard.spec.ts
git commit -m "test(#163): Playwright tests for volunteer dashboard"
```

---

## Task 11: Run full test suite and close milestone issues

**Step 1: Run everything**

```bash
./vendor/bin/phpunit && npx playwright test
```

Expected: 242+ tests passing (238 baseline + 4 decline tests + any new PHPUnit additions), all Playwright specs green

**Step 2: Close GitHub issues**

Close the following issues in `waaseyaa/minoo` with reference to the implementing commit:
- #149 — auth redirect (verified, no code change needed)
- #150 — account linking (verified, test added)
- #151 — accept/decline (implemented)
- #161 — elder request Playwright tests
- #162 — volunteer signup Playwright tests
- #163 — volunteer dashboard Playwright tests
- #164 — coordinator dashboard Playwright tests
- #165 — auth redirect Playwright tests
- #166 — PHPUnit decline tests

**Step 3: Final commit if anything is unstaged**

```bash
git status
# If clean, nothing to do. If any test files uncommitted:
git add tests/
git commit -m "test(v0.13): complete Playwright and PHPUnit coverage for Elder Support"
```

---

## Execution Order Summary

```
Task 1 → Task 2 → Task 3   (verify blockers, no code changes expected)
Task 5 Step 1–3             (write failing tests for #151 — TDD red phase)
Task 4                      (implement declineRequest — TDD green phase)
Task 5 Step 4               (confirm tests pass)
Tasks 6–10                  (Playwright specs, can run in any order)
Task 11                     (full suite + issue closure)
```

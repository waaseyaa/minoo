# v0.7 — Volunteer Self-Service & Request Lifecycle: Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Give volunteers control over their profile/availability, add request cancellation, surface request history, add How It Works page, and establish Playwright smoke tests.

**Architecture:** All changes are additive to existing controllers, templates, and entity definitions. One new field (`cancelled_reason`) on `elder_support_request`. New routes registered in existing service providers. New templates follow existing patterns.

**Tech Stack:** PHP 8.3, Waaseyaa framework, Twig 3, vanilla CSS, PHPUnit 10.5, Playwright MCP

---

### Task 1: Volunteer Profile Edit (#89)

**Files:**
- Modify: `src/Controller/VolunteerDashboardController.php`
- Modify: `src/Provider/DashboardServiceProvider.php` (add routes)
- Create: `templates/dashboard/volunteer-edit.html.twig`
- Modify: `templates/dashboard/volunteer.html.twig` (add Edit Profile link)
- Modify: `tests/Minoo/Unit/Controller/VolunteerDashboardControllerTest.php`

**Step 1: Write failing tests for editForm and submitEdit**

Add to `VolunteerDashboardControllerTest.php`:

```php
#[Test]
public function edit_form_returns_200_with_volunteer_data(): void
{
    $volunteer = new Volunteer(['vid' => 10, 'name' => 'John', 'phone' => '555-1234', 'availability' => 'Weekends', 'max_travel_km' => 50]);

    $volQuery = $this->createMock(EntityQueryInterface::class);
    $volQuery->method('condition')->willReturnSelf();
    $volQuery->method('execute')->willReturn([10]);

    $volStorage = $this->createMock(EntityStorageInterface::class);
    $volStorage->method('getQuery')->willReturn($volQuery);
    $volStorage->method('load')->with(10)->willReturn($volunteer);

    $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
    $this->entityTypeManager->method('getStorage')->willReturnCallback(
        fn (string $type) => match ($type) {
            'elder_support_request' => $this->storage,
            'volunteer' => $volStorage,
            default => throw new \RuntimeException("Unexpected: $type"),
        },
    );

    $this->twig = new Environment(new ArrayLoader([
        'dashboard/volunteer-edit.html.twig' => 'edit:{{ volunteer.get("name") }}',
    ]));

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(100);
    $account->method('getRoles')->willReturn(['volunteer']);

    $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
    $response = $controller->editForm([], [], $account, HttpRequest::create('/'));

    $this->assertSame(200, $response->statusCode);
    $this->assertStringContainsString('edit:John', $response->content);
}

#[Test]
public function edit_form_returns_404_when_no_volunteer_linked(): void
{
    $volQuery = $this->createMock(EntityQueryInterface::class);
    $volQuery->method('condition')->willReturnSelf();
    $volQuery->method('execute')->willReturn([]);

    $volStorage = $this->createMock(EntityStorageInterface::class);
    $volStorage->method('getQuery')->willReturn($volQuery);

    $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
    $this->entityTypeManager->method('getStorage')->willReturnCallback(
        fn (string $type) => match ($type) {
            'elder_support_request' => $this->storage,
            'volunteer' => $volStorage,
            default => throw new \RuntimeException("Unexpected: $type"),
        },
    );

    $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
    $response = $controller->editForm([], [], $this->account, HttpRequest::create('/'));

    $this->assertSame(404, $response->statusCode);
}

#[Test]
public function submit_edit_updates_volunteer_and_redirects(): void
{
    $volunteer = new Volunteer(['vid' => 10, 'name' => 'John', 'phone' => '555-1234']);

    $volQuery = $this->createMock(EntityQueryInterface::class);
    $volQuery->method('condition')->willReturnSelf();
    $volQuery->method('execute')->willReturn([10]);

    $volStorage = $this->createMock(EntityStorageInterface::class);
    $volStorage->method('getQuery')->willReturn($volQuery);
    $volStorage->method('load')->with(10)->willReturn($volunteer);
    $volStorage->expects($this->once())->method('save');

    $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
    $this->entityTypeManager->method('getStorage')->willReturnCallback(
        fn (string $type) => match ($type) {
            'elder_support_request' => $this->storage,
            'volunteer' => $volStorage,
            default => throw new \RuntimeException("Unexpected: $type"),
        },
    );

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(100);

    $request = HttpRequest::create('/dashboard/volunteer/edit', 'POST', [
        'phone' => '555-9999',
        'availability' => 'Evenings',
        'max_travel_km' => '75',
        'notes' => 'Updated notes',
    ]);

    $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
    $response = $controller->submitEdit([], [], $account, $request);

    $this->assertSame(302, $response->statusCode);
    $this->assertSame('555-9999', $volunteer->get('phone'));
    $this->assertSame('Evenings', $volunteer->get('availability'));
}
```

**Step 2: Run tests — expect FAIL** (methods don't exist yet)

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/VolunteerDashboardControllerTest.php
```

**Step 3: Implement editForm and submitEdit in VolunteerDashboardController**

The controller needs a helper to find the volunteer linked to the current account. Since registration creates a volunteer, the linkage is by `account.id()` matching against a query. Looking at the existing code, the volunteer dashboard queries by `assigned_volunteer` on requests — but we need to find the volunteer entity itself. The volunteer entity doesn't store an `account_id` field currently.

**Important discovery:** There's no `account_id` field on the volunteer entity. The volunteer dashboard finds requests by `assigned_volunteer` (which is the volunteer entity ID, not the account ID). We need a way to link the authenticated user to their volunteer record.

**Solution:** Add an `account_id` field to the volunteer entity definition (additive). The `VolunteerController::submitSignup` already runs in an authenticated context — it should set `account_id` to `$account->id()`. Then `editForm` can query `volunteer` by `account_id`.

Add to `ElderSupportServiceProvider` volunteer field definitions:
```php
'account_id' => ['type' => 'integer', 'label' => 'Account ID', 'weight' => 35],
```

Update `VolunteerController::submitSignup` to set `account_id`:
```php
$values['account_id'] = $account->id();
```

Then `editForm` queries:
```php
$volStorage = $this->entityTypeManager->getStorage('volunteer');
$ids = $volStorage->getQuery()->condition('account_id', $account->id())->execute();
```

**Step 4: Create volunteer-edit template**

Model it after `elders/volunteer.html.twig` but pre-filled with current values and POST to `/dashboard/volunteer/edit`.

**Step 5: Add routes to DashboardServiceProvider**

```php
$router->addRoute(
    'dashboard.volunteer.edit',
    RouteBuilder::create('/dashboard/volunteer/edit')
        ->controller('Minoo\Controller\VolunteerDashboardController::editForm')
        ->requireRole('volunteer')
        ->render()
        ->methods('GET')
        ->build(),
);

$router->addRoute(
    'dashboard.volunteer.edit.submit',
    RouteBuilder::create('/dashboard/volunteer/edit')
        ->controller('Minoo\Controller\VolunteerDashboardController::submitEdit')
        ->requireRole('volunteer')
        ->methods('POST')
        ->build(),
);
```

**Step 6: Add "Edit Profile" link to volunteer dashboard template**

**Step 7: Run all tests — expect PASS**

```bash
./vendor/bin/phpunit
```

**Step 8: Commit**

```bash
git add -A && git commit -m "feat(#89): volunteer profile edit from dashboard"
```

---

### Task 2: Volunteer Availability Toggle (#90)

**Files:**
- Modify: `src/Controller/VolunteerDashboardController.php` (add toggleAvailability)
- Modify: `src/Provider/DashboardServiceProvider.php` (add route)
- Modify: `templates/dashboard/volunteer.html.twig` (add toggle UI)
- Modify: `src/Controller/CoordinatorDashboardController.php` (already filters by status=active — confirm)
- Modify: `tests/Minoo/Unit/Controller/VolunteerDashboardControllerTest.php`

**Step 1: Write failing test**

```php
#[Test]
public function toggle_availability_switches_active_to_unavailable(): void
{
    $volunteer = new Volunteer(['vid' => 10, 'name' => 'John', 'status' => 'active']);
    // ... setup storage mocks, query by account_id ...
    $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
    $response = $controller->toggleAvailability([], [], $account, $request);

    $this->assertSame(302, $response->statusCode);
    $this->assertSame('unavailable', $volunteer->get('status'));
}

#[Test]
public function toggle_availability_switches_unavailable_to_active(): void
{
    $volunteer = new Volunteer(['vid' => 10, 'name' => 'John', 'status' => 'unavailable']);
    // ...
    $this->assertSame('active', $volunteer->get('status'));
}
```

**Step 2: Run — FAIL**

**Step 3: Implement toggleAvailability**

```php
public function toggleAvailability(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $volunteer = $this->findVolunteerByAccount($account);
    if ($volunteer === null) {
        return new SsrResponse(content: 'Not found', statusCode: 404);
    }

    $newStatus = $volunteer->get('status') === 'active' ? 'unavailable' : 'active';
    $volunteer->set('status', $newStatus);
    $volunteer->set('updated_at', time());

    $storage = $this->entityTypeManager->getStorage('volunteer');
    $storage->save($volunteer);

    return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/volunteer']);
}
```

**Step 4: Add route**

```php
$router->addRoute(
    'dashboard.volunteer.toggle',
    RouteBuilder::create('/dashboard/volunteer/toggle-availability')
        ->controller('Minoo\Controller\VolunteerDashboardController::toggleAvailability')
        ->requireRole('volunteer')
        ->methods('POST')
        ->build(),
);
```

**Step 5: Update volunteer dashboard template** — add availability status indicator and toggle button

**Step 6: Verify coordinator already filters by status=active** (line 48 of CoordinatorDashboardController — confirmed: `->condition('status', 'active')`)

**Step 7: Run all tests — PASS**

**Step 8: Commit**

```bash
git commit -m "feat(#90): volunteer availability toggle"
```

---

### Task 3: Request Cancellation (#91)

**Files:**
- Modify: `src/Provider/ElderSupportServiceProvider.php` (add `cancelled_reason` field, add route)
- Modify: `src/Controller/ElderSupportWorkflowController.php` (add cancelRequest)
- Modify: `src/Controller/CoordinatorDashboardController.php` (add cancelled bucket)
- Modify: `templates/dashboard/coordinator.html.twig` (add cancel UI + cancelled section)
- Modify: `tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php`

**Step 1: Write failing tests**

```php
#[Test]
public function cancel_transitions_open_to_cancelled(): void
{
    $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open']);
    $this->requestStorage->method('load')->with(1)->willReturn($entity);
    $this->requestStorage->expects($this->once())->method('save');

    $account = $this->createCoordinatorAccount();
    $this->request = HttpRequest::create('/elders/request/1/cancel', 'POST', ['reason' => 'Elder no longer needs help']);

    $controller = new ElderSupportWorkflowController($this->entityTypeManager);
    $response = $controller->cancelRequest(['esrid' => '1'], [], $account, $this->request);

    $this->assertSame(302, $response->statusCode);
    $this->assertSame('cancelled', $entity->get('status'));
    $this->assertSame('Elder no longer needs help', $entity->get('cancelled_reason'));
}

#[Test]
public function cancel_rejects_completed_request(): void
{
    $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'completed']);
    $this->requestStorage->method('load')->with(1)->willReturn($entity);

    $account = $this->createCoordinatorAccount();
    $this->request = HttpRequest::create('/elders/request/1/cancel', 'POST', ['reason' => 'test']);

    $controller = new ElderSupportWorkflowController($this->entityTypeManager);
    $response = $controller->cancelRequest(['esrid' => '1'], [], $account, $this->request);

    $this->assertSame(422, $response->statusCode);
}

#[Test]
public function cancel_returns_403_for_non_coordinator(): void
{
    $account = $this->createVolunteerAccount(5);
    $this->request = HttpRequest::create('/elders/request/1/cancel', 'POST', ['reason' => 'test']);

    $controller = new ElderSupportWorkflowController($this->entityTypeManager);
    $response = $controller->cancelRequest(['esrid' => '1'], [], $account, $this->request);

    $this->assertSame(403, $response->statusCode);
}
```

**Step 2: Run — FAIL**

**Step 3: Add `cancelled_reason` field to ElderSupportServiceProvider**

```php
'cancelled_reason' => ['type' => 'text_long', 'label' => 'Cancellation Reason', 'weight' => 30],
```

**Step 4: Implement cancelRequest**

```php
public function cancelRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    if (!in_array('elder_coordinator', $account->getRoles(), true) && !$account->hasPermission('administer content')) {
        return new SsrResponse(content: 'Forbidden', statusCode: 403);
    }

    $esrid = (int) ($params['esrid'] ?? 0);
    $storage = $this->entityTypeManager->getStorage('elder_support_request');
    $entity = $esrid > 0 ? $storage->load($esrid) : null;

    if ($entity === null) {
        return new SsrResponse(content: 'Not found', statusCode: 404);
    }

    $status = $entity->get('status');
    if (!in_array($status, ['open', 'assigned'], true)) {
        return new SsrResponse(content: 'Invalid status transition', statusCode: 422);
    }

    $reason = trim((string) $request->request->get('reason', ''));

    $entity->set('status', 'cancelled');
    $entity->set('cancelled_reason', $reason);
    $entity->set('updated_at', time());
    $storage->save($entity);

    return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator']);
}
```

**Step 5: Add route to ElderSupportServiceProvider**

```php
$router->addRoute(
    'elder.cancel',
    RouteBuilder::create('/elders/request/{esrid}/cancel')
        ->controller('Minoo\Controller\ElderSupportWorkflowController::cancelRequest')
        ->requireRole('elder_coordinator')
        ->methods('POST')
        ->build(),
);
```

**Step 6: Update CoordinatorDashboardController** — add `$cancelled` bucket in the match statement

**Step 7: Update coordinator template** — add cancel button on open/assigned cards, add "Cancelled" section

**Step 8: Run all tests — PASS**

**Step 9: Run schema:check** for the new field

```bash
bin/waaseyaa schema:check
```

**Step 10: Commit**

```bash
git commit -m "feat(#91): request cancellation by coordinator"
```

---

### Task 4: Request History in Dashboards (#92)

**Files:**
- Modify: `templates/dashboard/volunteer.html.twig` (add History section for confirmed)
- Modify: `templates/dashboard/coordinator.html.twig` (add History section for confirmed + cancelled)
- Modify: `public/css/minoo.css` (history section styling)

**Step 1: Update volunteer dashboard template**

Add after the completed section:
```twig
{% if grouped.confirmed is not empty %}
<h2>History</h2>
<div class="dashboard__cards">
  {% for req in grouped.confirmed %}
  <article class="card card--dashboard card--history">
    <span class="card__badge card__badge--confirmed">Confirmed</span>
    <h3 class="card__title">{{ req.get('name') }}</h3>
    <dl class="card__meta">
      <dt>Type</dt><dd>{{ req.get('type') }}</dd>
    </dl>
  </article>
  {% endfor %}
</div>
{% endif %}
```

**Step 2: Update coordinator dashboard template**

Replace the empty confirmed section with a full History section showing both confirmed and cancelled requests.

**Step 3: Add CSS for history cards** (muted styling)

**Step 4: Run all tests — PASS**

**Step 5: Commit**

```bash
git commit -m "enhance(#92): request history in volunteer and coordinator dashboards"
```

---

### Task 5: Elder Support "How It Works" Page (#93)

**Files:**
- Create: `templates/elders/how-it-works.html.twig`
- Modify: `templates/base.html.twig` (nav link → `/elders`)
- Modify: `src/Provider/ElderSupportServiceProvider.php` (add route for `/elders`)

**Step 1: Create the how-it-works template**

Path-based template rendering maps `/elders` to `elders.html.twig`, but since we have an `elders/` directory, we need an explicit route. Register a GET route for `/elders` pointing to a simple render.

Actually — the framework's `tryRenderPathTemplate()` matches single segments. `/elders` would look for `elders.html.twig`. We can create that file and it auto-serves.

Create `templates/elders.html.twig`:
```twig
{% extends "base.html.twig" %}

{% block title %}Grandparent Connection — How It Works — Minoo{% endblock %}

{% block content %}
<section class="content-section flow-lg">
  <h1>Grandparent Connection</h1>
  <p class="text-secondary">Connecting Elders with volunteers for everyday support.</p>
  ...
  <div class="how-it-works__actions">
    <a href="/elders/request" class="form__submit">Request Help</a>
    <a href="/elders/volunteer" class="form__submit form__submit--secondary">Volunteer</a>
  </div>
</section>
{% endblock %}
```

**Step 2: Update nav link in base.html.twig**

Change `/elders/request` → `/elders`

**Step 3: Run all tests — PASS**

**Step 4: Commit**

```bash
git commit -m "feat(#93): Elder Support How It Works public page"
```

---

### Task 6: Playwright Smoke Tests (#94)

**Files:**
- No PHP files — Playwright MCP browser tests run interactively

**Step 1: Start dev server**

```bash
php -S localhost:8081 -t public &
```

**Step 2: Run smoke tests via Playwright MCP**

Test each flow:
1. Navigate to `/` — verify title contains "Minoo"
2. Navigate to `/events` — verify heading
3. Navigate to `/elders` — verify How It Works page loads
4. Navigate to `/elders/request` — verify form fields present
5. Navigate to `/elders/volunteer` — verify form fields present
6. Navigate to `/people` — verify directory loads
7. Navigate to `/communities` — verify page loads
8. Navigate to `/login` — verify login form
9. Navigate to `/register` — verify register form
10. Navigate to `/dashboard/volunteer` — verify redirect (not authenticated)
11. Check all nav links resolve (no 404s)

**Step 3: Fix any failures**

**Step 4: Document results**

**Step 5: Commit any fixes**

---

## Execution Order

1. Task 1 (Volunteer Profile Edit) — requires `account_id` field addition
2. Task 2 (Availability Toggle) — builds on Task 1's `findVolunteerByAccount` helper
3. Task 3 (Request Cancellation) — independent but best after Task 2
4. Task 4 (Request History) — depends on Task 3 for cancelled status
5. Task 5 (How It Works page) — independent, template-only
6. Task 6 (Playwright Smoke Tests) — runs last, validates everything

Tasks 3 and 5 are independent and can be parallelized.

## Schema Changes Summary

| Entity | Field | Type | Migration |
|--------|-------|------|-----------|
| `volunteer` | `account_id` | integer | Additive — `ALTER TABLE volunteer ADD COLUMN account_id INTEGER` |
| `elder_support_request` | `cancelled_reason` | text_long | Additive — `ALTER TABLE elder_support_request ADD COLUMN cancelled_reason TEXT` |

Both are nullable, backwards-compatible. Run `bin/waaseyaa schema:check` after each to detect drift.

# Volunteer Application & Approval Workflow

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an approval gate so volunteer signups become pending applications that coordinators review before volunteers gain active status and the `volunteer` role.

**Architecture:** Change volunteer entity default status from `active` to `pending`. Add approve/deny endpoints to coordinator dashboard. On approval, set status to `active` and grant `volunteer` role to the linked user account (if any).

**Tech Stack:** PHP 8.4, PHPUnit 10.5 (Pest-free), Twig 3, Waaseyaa framework entity system

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Modify | `src/Entity/Volunteer.php` | Default status → `pending` |
| Modify | `src/Controller/VolunteerController.php` | Set `status: 'pending'` on signup, update confirmation messaging |
| Modify | `src/Controller/CoordinatorDashboardController.php` | Add `applications()`, `approveApplication()`, `denyApplication()` methods |
| Modify | `src/Provider/DashboardServiceProvider.php` | Register 3 new coordinator routes |
| Modify | `templates/elders/volunteer-confirmation.html.twig` | Update copy for "application submitted" vs "you're active" |
| Modify | `templates/dashboard/coordinator.html.twig` | Add pending applications count/link |
| Create | `templates/dashboard/coordinator-applications.html.twig` | Applications list with approve/deny buttons |
| Modify | `tests/Minoo/Unit/Entity/VolunteerTest.php` | Assert default status is `pending` |
| Modify | `tests/Minoo/Unit/Controller/VolunteerControllerTest.php` | Assert signup creates `pending` volunteer |
| Create | `tests/Minoo/Unit/Controller/CoordinatorApplicationsTest.php` | Test approve/deny logic |

---

### Task 1: Volunteer entity defaults to `pending`

**Files:**
- Modify: `src/Entity/Volunteer.php:22`
- Modify: `tests/Minoo/Unit/Entity/VolunteerTest.php`

- [ ] **Step 1: Update existing test and add new failing test**

In `tests/Minoo/Unit/Entity/VolunteerTest.php`:
- Change line 22 from `$this->assertSame('active', $volunteer->get('status'))` to `$this->assertSame('pending', $volunteer->get('status'))`
- Add new test:

```php
#[Test]
public function default_status_is_pending(): void
{
    $volunteer = new Volunteer(['name' => 'Test']);
    $this->assertSame('pending', $volunteer->get('status'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter default_status_is_pending`
Expected: FAIL — status is `active`

- [ ] **Step 3: Update Volunteer entity default**

In `src/Entity/Volunteer.php`, change:
```php
if (!array_key_exists('status', $values)) {
    $values['status'] = 'pending';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter VolunteerTest`
Expected: All pass

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Volunteer.php tests/Minoo/Unit/Entity/VolunteerTest.php
git commit -m "feat(#460): volunteer entity defaults to pending status"
```

---

### Task 2: Signup creates `pending` volunteer

**Files:**
- Modify: `src/Controller/VolunteerController.php:95`
- Modify: `tests/Minoo/Unit/Controller/VolunteerControllerTest.php`

- [ ] **Step 1: Write failing test — signup sets status to `pending`**

In `VolunteerControllerTest.php`, add a test that asserts the `create()` call receives `'status' => 'pending'`. Use the existing mock pattern from `submit_persists_max_travel_km`:

```php
#[Test]
public function submit_creates_volunteer_with_pending_status(): void
{
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('uuid')->willReturn('test-uuid');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
        ->method('create')
        ->with($this->callback(function (array $values): bool {
            return $values['status'] === 'pending';
        }))
        ->willReturn($entity);
    $storage->method('save');

    $query = $this->createMock(EntityQueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $storage->method('getQuery')->willReturn($query);

    $this->entityTypeManager->method('getStorage')
        ->with('volunteer')
        ->willReturn($storage);

    $request = HttpRequest::create('/elders/volunteer', 'POST', [
        'name' => 'Jane',
        'phone' => '705-555-1234',
    ]);

    $controller = new VolunteerController($this->entityTypeManager, $this->twig);
    $response = $controller->submitSignup([], [], $this->account, $request);

    $this->assertSame(302, $response->statusCode);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter submit_creates_volunteer_with_pending_status`
Expected: FAIL — status is `active`

- [ ] **Step 3: Change status in VolunteerController::submitSignup()**

In `src/Controller/VolunteerController.php` line 95, change:
```php
'status' => 'pending',
```

- [ ] **Step 4: Run all volunteer controller tests**

Run: `./vendor/bin/phpunit --filter VolunteerControllerTest`
Expected: All pass. If existing tests assert `status => 'active'`, update them to `'pending'`.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/VolunteerController.php tests/Minoo/Unit/Controller/VolunteerControllerTest.php
git commit -m "feat(#460): signup creates volunteer with pending status"
```

---

### Task 3: Coordinator approval and denial endpoints

**Files:**
- Modify: `src/Controller/CoordinatorDashboardController.php`
- Create: `tests/Minoo/Unit/Controller/CoordinatorApplicationsTest.php`

- [ ] **Step 1: Write failing test — approve sets status to `active` and grants role**

Create `tests/Minoo/Unit/Controller/CoordinatorApplicationsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\CoordinatorDashboardController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\User;

#[CoversClass(CoordinatorDashboardController::class)]
final class CoordinatorApplicationsTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->twig = new Environment(new ArrayLoader([
            'dashboard/coordinator-applications.html.twig' => 'apps',
            'dashboard/coordinator.html.twig' => 'dash',
        ]));
        $this->account = $this->createMock(AccountInterface::class);
    }

    #[Test]
    public function approve_sets_status_active_and_grants_volunteer_role(): void
    {
        // Volunteer entity with account_id
        $volunteer = $this->createMock(EntityInterface::class);
        $volunteer->method('get')->willReturnMap([
            ['status', 'pending'],
            ['account_id', 42],
        ]);
        $volunteer->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $field, mixed $value) {
                match ($field) {
                    'status' => $this->assertSame('active', $value),
                    'updated_at' => $this->assertIsInt($value),
                    default => $this->fail("Unexpected set('$field')"),
                };
            });

        $volStorage = $this->createMock(EntityStorageInterface::class);
        $volQuery = $this->createMock(EntityQueryInterface::class);
        $volQuery->method('condition')->willReturnSelf();
        $volQuery->method('execute')->willReturn([1]);
        $volStorage->method('getQuery')->willReturn($volQuery);
        $volStorage->method('load')->with(1)->willReturn($volunteer);
        $volStorage->expects($this->once())->method('save')->with($volunteer);

        // User entity for role grant
        $user = new User([
            'uid' => 42,
            'name' => 'Jane',
            'mail' => 'jane@example.com',
            'roles' => [],
            'status' => 1,
        ]);

        $userStorage = $this->createMock(EntityStorageInterface::class);
        $userStorage->method('load')->with(42)->willReturn($user);
        $userStorage->expects($this->once())->method('save')->with($user);

        $this->entityTypeManager->method('getStorage')
            ->willReturnMap([
                ['volunteer', $volStorage],
                ['user', $userStorage],
            ]);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/dashboard/coordinator/applications/test-uuid/approve', 'POST');
        $response = $controller->approveApplication(['uuid' => 'test-uuid'], [], $this->account, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertContains('volunteer', $user->getRoles());
    }

    #[Test]
    public function deny_sets_status_denied(): void
    {
        $volunteer = $this->createMock(EntityInterface::class);
        $volunteer->method('get')->willReturnMap([
            ['status', 'pending'],
        ]);
        $volunteer->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $field, mixed $value) {
                match ($field) {
                    'status' => $this->assertSame('denied', $value),
                    'updated_at' => $this->assertIsInt($value),
                    default => $this->fail("Unexpected set('$field')"),
                };
            });

        $storage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([1]);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('load')->with(1)->willReturn($volunteer);
        $storage->expects($this->once())->method('save');

        $this->entityTypeManager->method('getStorage')
            ->with('volunteer')
            ->willReturn($storage);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/dashboard/coordinator/applications/test-uuid/deny', 'POST');
        $response = $controller->denyApplication(['uuid' => 'test-uuid'], [], $this->account, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function applications_lists_pending_volunteers(): void
    {
        $vol1 = $this->createMock(EntityInterface::class);

        $storage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('sort')->willReturnSelf();
        $query->method('execute')->willReturn([1]);
        $storage->method('getQuery')->willReturn($query);
        $storage->method('loadMultiple')->with([1])->willReturn([$vol1]);

        $this->entityTypeManager->method('getStorage')
            ->with('volunteer')
            ->willReturn($storage);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $request = HttpRequest::create('/dashboard/coordinator/applications');
        $response = $controller->applications([], [], $this->account, $request);

        $this->assertSame(200, $response->statusCode);
    }
}
```

Also add edge case tests to the same file:

```php
#[Test]
public function approve_already_processed_returns_redirect_without_save(): void
{
    $volunteer = $this->createMock(EntityInterface::class);
    $volunteer->method('get')->willReturnMap([
        ['status', 'active'],
    ]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $query = $this->createMock(EntityQueryInterface::class);
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([1]);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('load')->with(1)->willReturn($volunteer);
    $storage->expects($this->never())->method('save');

    $this->entityTypeManager->method('getStorage')
        ->with('volunteer')
        ->willReturn($storage);

    $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
    $request = HttpRequest::create('/dashboard/coordinator/applications/test-uuid/approve', 'POST');
    $response = $controller->approveApplication(['uuid' => 'test-uuid'], [], $this->account, $request);

    $this->assertSame(302, $response->statusCode);
}

#[Test]
public function approve_without_account_id_skips_role_grant(): void
{
    $volunteer = $this->createMock(EntityInterface::class);
    $volunteer->method('get')->willReturnMap([
        ['status', 'pending'],
        ['account_id', null],
    ]);
    $volunteer->method('set')->willReturnSelf();

    $volStorage = $this->createMock(EntityStorageInterface::class);
    $volQuery = $this->createMock(EntityQueryInterface::class);
    $volQuery->method('condition')->willReturnSelf();
    $volQuery->method('execute')->willReturn([1]);
    $volStorage->method('getQuery')->willReturn($volQuery);
    $volStorage->method('load')->with(1)->willReturn($volunteer);
    $volStorage->expects($this->once())->method('save');

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects($this->never())->method('load');
    $userStorage->expects($this->never())->method('save');

    $this->entityTypeManager->method('getStorage')
        ->willReturnMap([
            ['volunteer', $volStorage],
            ['user', $userStorage],
        ]);

    $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
    $request = HttpRequest::create('/dashboard/coordinator/applications/test-uuid/approve', 'POST');
    $response = $controller->approveApplication(['uuid' => 'test-uuid'], [], $this->account, $request);

    $this->assertSame(302, $response->statusCode);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit --filter CoordinatorApplicationsTest`
Expected: FAIL — methods don't exist

- [ ] **Step 3: Implement `applications()`, `approveApplication()`, `denyApplication()`**

Add to `src/Controller/CoordinatorDashboardController.php`:

```php
public function applications(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $storage = $this->entityTypeManager->getStorage('volunteer');
    $ids = $storage->getQuery()
        ->condition('status', 'pending')
        ->sort('created_at', 'DESC')
        ->execute();

    $applications = $ids !== [] ? $storage->loadMultiple($ids) : [];

    $html = $this->twig->render('dashboard/coordinator-applications.html.twig', [
        'applications' => $applications,
    ]);

    return new SsrResponse(content: $html);
}

public function approveApplication(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $volunteer = $this->loadVolunteerByUuid($params['uuid'] ?? '');

    if ($volunteer === null || $volunteer->get('status') !== 'pending') {
        Flash::error('Application not found or already processed.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator/applications']);
    }

    $volunteer->set('status', 'active');
    $volunteer->set('updated_at', time());
    $this->entityTypeManager->getStorage('volunteer')->save($volunteer);

    // Grant volunteer role to linked user account
    $accountId = $volunteer->get('account_id');
    if ($accountId !== null && $accountId !== '' && is_numeric($accountId)) {
        $userStorage = $this->entityTypeManager->getStorage('user');
        /** @var \Waaseyaa\User\User|null $user */
        $user = $userStorage->load((int) $accountId);
        if ($user !== null) {
            $user->addRole('volunteer');
            $userStorage->save($user);
        }
    }

    Flash::success('Volunteer application approved.');
    return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator/applications']);
}

public function denyApplication(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $volunteer = $this->loadVolunteerByUuid($params['uuid'] ?? '');

    if ($volunteer === null || $volunteer->get('status') !== 'pending') {
        Flash::error('Application not found or already processed.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator/applications']);
    }

    $volunteer->set('status', 'denied');
    $volunteer->set('updated_at', time());
    $this->entityTypeManager->getStorage('volunteer')->save($volunteer);

    Flash::info('Volunteer application denied.');
    return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator/applications']);
}

private function loadVolunteerByUuid(string $uuid): ?EntityInterface
{
    if ($uuid === '') {
        return null;
    }

    $storage = $this->entityTypeManager->getStorage('volunteer');
    $ids = $storage->getQuery()->condition('uuid', $uuid)->execute();

    if ($ids === []) {
        return null;
    }

    return $storage->load(reset($ids));
}
```

Also add imports at top:
```php
use Minoo\Support\Flash;
use Waaseyaa\Entity\EntityInterface;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit --filter CoordinatorApplicationsTest`
Expected: All 3 pass

- [ ] **Step 5: Run existing coordinator dashboard test**

Run: `./vendor/bin/phpunit --filter CoordinatorDashboardControllerTest`
Expected: Still passes (existing functionality unchanged)

- [ ] **Step 6: Commit**

```bash
git add src/Controller/CoordinatorDashboardController.php tests/Minoo/Unit/Controller/CoordinatorApplicationsTest.php
git commit -m "feat(#460): coordinator approve/deny volunteer applications"
```

---

### Task 4: Register coordinator application routes

**Files:**
- Modify: `src/Provider/DashboardServiceProvider.php`

- [ ] **Step 1: Add 3 routes to DashboardServiceProvider::routes()**

After the existing `dashboard.coordinator` route, add:

```php
$router->addRoute(
    'dashboard.coordinator.applications',
    RouteBuilder::create('/dashboard/coordinator/applications')
        ->controller('Minoo\Controller\CoordinatorDashboardController::applications')
        ->requireRole('elder_coordinator')
        ->render()
        ->methods('GET')
        ->build(),
);

$router->addRoute(
    'dashboard.coordinator.applications.approve',
    RouteBuilder::create('/dashboard/coordinator/applications/{uuid}/approve')
        ->controller('Minoo\Controller\CoordinatorDashboardController::approveApplication')
        ->requireRole('elder_coordinator')
        ->methods('POST')
        ->build(),
);

$router->addRoute(
    'dashboard.coordinator.applications.deny',
    RouteBuilder::create('/dashboard/coordinator/applications/{uuid}/deny')
        ->controller('Minoo\Controller\CoordinatorDashboardController::denyApplication')
        ->requireRole('elder_coordinator')
        ->methods('POST')
        ->build(),
);
```

- [ ] **Step 2: Delete stale manifest cache**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 3: Run full test suite to verify no regressions**

Run: `./vendor/bin/phpunit`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add src/Provider/DashboardServiceProvider.php
git commit -m "feat(#460): register coordinator application routes"
```

---

### Task 5: TDD — pending count in coordinator dashboard index

**Files:**
- Modify: `tests/Minoo/Unit/Controller/CoordinatorDashboardControllerTest.php`
- Modify: `src/Controller/CoordinatorDashboardController.php`

- [ ] **Step 1: Read the existing coordinator dashboard test**

Read `tests/Minoo/Unit/Controller/CoordinatorDashboardControllerTest.php` to understand setup.

- [ ] **Step 2: Write failing test — update mock to expect pending query**

The `index()` method will make an additional query for `status = 'pending'`. Update the volunteer storage mock to handle both the existing `condition('status', 'active')` query and the new `condition('status', 'pending')` query. This may require returning a fresh query mock for each `getQuery()` call.

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter CoordinatorDashboardControllerTest`
Expected: FAIL — template receives unexpected `pending_application_count` variable, or mock expects call that doesn't happen yet.

- [ ] **Step 4: Update CoordinatorDashboardController::index() to pass pending count**

Add to the `index()` method, after loading active volunteers:
```php
$pendingIds = $volunteerStorage->getQuery()
    ->condition('status', 'pending')
    ->execute();
$pendingCount = count($pendingIds);
```

Pass `'pending_application_count' => $pendingCount` to the template.

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter CoordinatorDashboardControllerTest`
Expected: All pass

- [ ] **Step 6: Commit**

```bash
git add tests/Minoo/Unit/Controller/CoordinatorDashboardControllerTest.php src/Controller/CoordinatorDashboardController.php
git commit -m "feat(#460): pass pending application count to coordinator dashboard"
```

---

### Task 6: Templates — confirmation page, applications list, dashboard badge

**Files:**
- Modify: `templates/elders/volunteer-confirmation.html.twig`
- Modify: `templates/dashboard/coordinator.html.twig`
- Create: `templates/dashboard/coordinator-applications.html.twig`

- [ ] **Step 1: Read current templates**

Read `templates/elders/volunteer-confirmation.html.twig` and `templates/dashboard/coordinator.html.twig` to understand structure.

- [ ] **Step 2: Update volunteer confirmation page**

Change copy from "you're registered" to "your application has been submitted" — a coordinator will review it. Keep the same layout, just update the messaging to reflect the pending state.

- [ ] **Step 3: Create coordinator-applications.html.twig**

Template extending `base.html.twig` that:
- Shows count of pending applications
- Lists each application with: name, phone, community, skills, availability, submitted date
- Each row has Approve and Deny buttons (POST forms to the respective endpoints)
- Back link to coordinator dashboard

Follow existing Minoo CSS patterns from `minoo.css` — use existing card/table/button classes.

- [ ] **Step 4: Add pending applications badge to coordinator dashboard**

In `templates/dashboard/coordinator.html.twig`, add a link/badge near the top showing the count of pending applications with a link to `/dashboard/coordinator/applications`. Use `pending_application_count` variable (already passed from Task 5).

- [ ] **Step 5: Visual check with dev server**

Run: `php -S localhost:8081 -t public`
Visit `/dashboard/coordinator/applications` as a coordinator user. Verify the page renders.

- [ ] **Step 6: Commit**

```bash
git add templates/elders/volunteer-confirmation.html.twig templates/dashboard/coordinator.html.twig templates/dashboard/coordinator-applications.html.twig
git commit -m "feat(#460): application templates and coordinator pending badge"
```

---

### Task 7: Full regression check

- [ ] **Step 1: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass (639+ tests)

- [ ] **Step 2: Delete stale manifest cache and verify**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

- [ ] **Step 3: Check PHPStan baseline if available**

Run: `./vendor/bin/phpstan analyse` (if configured)
If new `EntityInterface::get()` calls were added, regenerate baseline.

- [ ] **Step 4: Final commit if any cleanup needed**

Only if changes were required during regression check.

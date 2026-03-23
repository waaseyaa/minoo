# Role Management UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let coordinators and admins view users and manage their roles (Volunteer, Elder, Coordinator) from dedicated UI pages.

**Architecture:** New `RoleManagementController` with three endpoints (coordinator list, admin list, shared POST role-change). New `RoleManagementServiceProvider` for routes. Two Twig templates following the existing dashboard card pattern. Permission matrix enforced in-controller.

**Tech Stack:** PHP 8.4, Waaseyaa framework (EntityTypeManager, RouteBuilder, User entity), Twig 3, PHPUnit 10.5

**Spec:** `docs/superpowers/specs/2026-03-23-role-management-ui-design.md`

**Issue:** #499

**Prerequisite:** Framework PR #625 (isElder/setElder on User) must be merged, or vendor copy patched.

---

### Task 1: Translation strings

**Files:**
- Modify: `resources/lang/en.php`

- [ ] **Step 1: Add role management translation keys**

Add after the `account.*` keys:

```php
'coordinator.users_title' => 'Community Members',
'coordinator.users_subtitle' => 'Manage volunteer and Elder roles for community members.',
'admin.users_title' => 'Manage Users',
'admin.users_subtitle' => 'View and manage user roles across the community.',
'roles.grant_volunteer' => 'Grant Volunteer',
'roles.revoke_volunteer' => 'Revoke Volunteer',
'roles.grant_elder' => 'Grant Elder',
'roles.revoke_elder' => 'Revoke Elder',
'roles.grant_coordinator' => 'Grant Coordinator',
'roles.revoke_coordinator' => 'Revoke Coordinator',
'roles.badge_volunteer' => 'Volunteer',
'roles.badge_elder' => 'Elder',
'roles.badge_coordinator' => 'Coordinator',
'roles.badge_admin' => 'Admin',
'roles.no_users' => 'No users found.',
'roles.manage_members' => 'Manage Members',
```

- [ ] **Step 2: Commit**

```bash
git add resources/lang/en.php
git commit -m "feat(#499): add role management translation strings"
```

---

### Task 2: RoleManagementController — changeRole endpoint

**Files:**
- Create: `src/Controller/RoleManagementController.php`
- Create: `tests/Minoo/Unit/Controller/RoleManagementControllerTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Minoo/Unit/Controller/RoleManagementControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\RoleManagementController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\User;

#[CoversClass(RoleManagementController::class)]
final class RoleManagementControllerTest extends TestCase
{
    private function buildController(?EntityTypeManager $etm = null): RoleManagementController
    {
        return new RoleManagementController(
            $etm ?? $this->createMock(EntityTypeManager::class),
            $this->createMock(Environment::class),
        );
    }

    private function buildPostRequest(string $action, string $role): HttpRequest
    {
        return HttpRequest::create('/api/users/2/roles', 'POST', [
            'action' => $action,
            'role' => $role,
        ]);
    }

    private function mockStorage(User $targetUser): EntityStorageInterface
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('load')->willReturn($targetUser);
        $storage->expects($this->once())->method('save')->with($targetUser);
        return $storage;
    }

    #[Test]
    public function changeRole_grants_volunteer_role(): void
    {
        $target = new User(['uid' => 2, 'name' => 'Target']);
        $storage = $this->mockStorage($target);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('user')->willReturn($storage);

        $actor = new User(['uid' => 1, 'roles' => ['elder_coordinator']]);
        $request = $this->buildPostRequest('grant', 'volunteer');

        $_SESSION = [];
        $response = $this->buildController($etm)->changeRole(['uid' => '2'], [], $actor, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertContains('volunteer', $target->getRoles());
    }

    #[Test]
    public function changeRole_revokes_volunteer_role(): void
    {
        $target = new User(['uid' => 2, 'name' => 'Target', 'roles' => ['volunteer']]);
        $storage = $this->mockStorage($target);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('user')->willReturn($storage);

        $actor = new User(['uid' => 1, 'roles' => ['elder_coordinator']]);
        $request = $this->buildPostRequest('revoke', 'volunteer');

        $_SESSION = [];
        $response = $this->buildController($etm)->changeRole(['uid' => '2'], [], $actor, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertNotContains('volunteer', $target->getRoles());
    }

    #[Test]
    public function changeRole_grants_elder_sets_field(): void
    {
        $target = new User(['uid' => 2, 'name' => 'Target']);
        $storage = $this->mockStorage($target);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('user')->willReturn($storage);

        $actor = new User(['uid' => 1, 'roles' => ['elder_coordinator']]);
        $request = $this->buildPostRequest('grant', 'elder');

        $_SESSION = [];
        $this->buildController($etm)->changeRole(['uid' => '2'], [], $actor, $request);

        $this->assertTrue($target->isElder());
    }

    #[Test]
    public function changeRole_revokes_elder_unsets_field(): void
    {
        $target = new User(['uid' => 2, 'name' => 'Target', 'is_elder' => 1]);
        $storage = $this->mockStorage($target);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('user')->willReturn($storage);

        $actor = new User(['uid' => 1, 'roles' => ['elder_coordinator']]);
        $request = $this->buildPostRequest('revoke', 'elder');

        $_SESSION = [];
        $this->buildController($etm)->changeRole(['uid' => '2'], [], $actor, $request);

        $this->assertFalse($target->isElder());
    }

    #[Test]
    public function changeRole_rejects_self_modification(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $actor = new User(['uid' => 1, 'roles' => ['elder_coordinator']]);
        $request = $this->buildPostRequest('grant', 'volunteer');

        $_SESSION = [];
        $response = $this->buildController($etm)->changeRole(['uid' => '1'], [], $actor, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_rejects_coordinator_grant_by_non_admin(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $actor = new User(['uid' => 1, 'roles' => ['elder_coordinator']]);
        $request = $this->buildPostRequest('grant', 'coordinator');

        $_SESSION = [];
        $response = $this->buildController($etm)->changeRole(['uid' => '2'], [], $actor, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_allows_coordinator_grant_by_admin(): void
    {
        $target = new User(['uid' => 2, 'name' => 'Target']);
        $storage = $this->mockStorage($target);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('user')->willReturn($storage);

        $actor = new User(['uid' => 1, 'roles' => ['admin']]);
        $request = $this->buildPostRequest('grant', 'coordinator');

        $_SESSION = [];
        $this->buildController($etm)->changeRole(['uid' => '2'], [], $actor, $request);

        $this->assertContains('coordinator', $target->getRoles());
    }

    #[Test]
    public function changeRole_rejects_admin_modification(): void
    {
        $target = new User(['uid' => 2, 'name' => 'Admin', 'roles' => ['admin']]);
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('load')->willReturn($target);
        $storage->expects($this->never())->method('save');
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('user')->willReturn($storage);

        $actor = new User(['uid' => 1, 'roles' => ['admin']]);
        $request = $this->buildPostRequest('revoke', 'admin');

        $_SESSION = [];
        $response = $this->buildController($etm)->changeRole(['uid' => '2'], [], $actor, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_rejects_invalid_action(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $actor = new User(['uid' => 1, 'roles' => ['admin']]);
        $request = $this->buildPostRequest('promote', 'volunteer');

        $_SESSION = [];
        $response = $this->buildController($etm)->changeRole(['uid' => '2'], [], $actor, $request);

        $this->assertSame(302, $response->statusCode);
    }

    #[Test]
    public function changeRole_rejects_unprivileged_user(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $actor = new User(['uid' => 1, 'roles' => []]);
        $request = $this->buildPostRequest('grant', 'volunteer');

        $_SESSION = [];
        $response = $this->buildController($etm)->changeRole(['uid' => '2'], [], $actor, $request);

        $this->assertSame(403, $response->statusCode);
    }

    #[Test]
    public function coordinatorList_renders_user_list(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn(new class {
            public function condition(...$args): static { return $this; }
            public function sort(...$args): static { return $this; }
            public function execute(): array { return [1, 2]; }
        });
        $storage->method('loadMultiple')->willReturn([
            new User(['uid' => 1, 'name' => 'Actor']),
            new User(['uid' => 2, 'name' => 'Other']),
        ]);
        $etm->method('getStorage')->with('user')->willReturn($storage);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('dashboard/coordinator-users.html.twig', $this->callback(function (array $vars) {
                return $vars['can_manage_coordinator'] === false
                    && count($vars['users']) === 1
                    && $vars['users'][0]['name'] === 'Other';
            }))
            ->willReturn('<html></html>');

        $controller = new RoleManagementController($etm, $twig);
        $actor = new User(['uid' => 1, 'roles' => ['elder_coordinator']]);

        $response = $controller->coordinatorList([], [], $actor, HttpRequest::create('/dashboard/coordinator/users'));
        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function adminList_renders_with_coordinator_management(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn(new class {
            public function condition(...$args): static { return $this; }
            public function sort(...$args): static { return $this; }
            public function execute(): array { return [1, 2]; }
        });
        $storage->method('loadMultiple')->willReturn([
            new User(['uid' => 1, 'name' => 'Admin']),
            new User(['uid' => 2, 'name' => 'Other']),
        ]);
        $etm->method('getStorage')->with('user')->willReturn($storage);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('admin/users.html.twig', $this->callback(function (array $vars) {
                return $vars['can_manage_coordinator'] === true
                    && count($vars['users']) === 1;
            }))
            ->willReturn('<html></html>');

        $controller = new RoleManagementController($etm, $twig);
        $actor = new User(['uid' => 1, 'roles' => ['admin']]);

        $response = $controller->adminList([], [], $actor, HttpRequest::create('/admin/users'));
        $this->assertSame(200, $response->statusCode);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/RoleManagementControllerTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Create RoleManagementController**

Create `src/Controller/RoleManagementController.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\Flash;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;
use Waaseyaa\User\User;

final class RoleManagementController
{
    private const ALLOWED_ACTIONS = ['grant', 'revoke'];
    private const ALLOWED_ROLES = ['volunteer', 'elder', 'coordinator'];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function changeRole(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $targetUid = (int) $params['uid'];
        $action = $request->request->get('action', '');
        $role = $request->request->get('role', '');
        $referrer = $request->headers->get('Referer', '/account');

        // Validate input
        if (!in_array($action, self::ALLOWED_ACTIONS, true)
            || !in_array($role, self::ALLOWED_ROLES, true)) {
            Flash::error('Invalid request.');
            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        // Prevent self-modification
        if ($targetUid === $account->id()) {
            Flash::error('You cannot modify your own roles.');
            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        // Check actor permission
        $actorRoles = $account->getRoles();
        $isCoordinator = in_array('elder_coordinator', $actorRoles, true);
        $isAdmin = in_array('admin', $actorRoles, true);

        if (!$isCoordinator && !$isAdmin) {
            return new SsrResponse(content: '', statusCode: 403);
        }

        // Coordinators cannot manage coordinators
        if ($role === 'coordinator' && !$isAdmin) {
            Flash::error('Only admins can manage coordinator roles.');
            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        // Load target user
        $storage = $this->entityTypeManager->getStorage('user');
        $user = $storage->load($targetUid);

        if ($user === null) {
            Flash::error('User not found.');
            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        // Cannot modify admins
        if (in_array('admin', $user->getRoles(), true)) {
            Flash::error('Admin accounts cannot be modified.');
            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        // Apply change
        if ($role === 'elder') {
            $user->setElder($action === 'grant');
        } else {
            if ($action === 'grant') {
                $user->addRole($role);
            } else {
                $user->removeRole($role);
            }
        }
        $storage->save($user);

        $verb = $action === 'grant' ? 'granted to' : 'revoked from';
        Flash::success(ucfirst($role) . " role {$verb} " . $user->getName() . '.');

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
    }

    public function coordinatorList(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $users = $this->loadUserRows($account);

        $html = $this->twig->render('dashboard/coordinator-users.html.twig', [
            'users' => $users,
            'account' => $account,
            'can_manage_coordinator' => false,
            'path' => '/dashboard/coordinator/users',
        ]);

        return new SsrResponse(content: $html);
    }

    public function adminList(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $users = $this->loadUserRows($account);

        $html = $this->twig->render('admin/users.html.twig', [
            'users' => $users,
            'account' => $account,
            'can_manage_coordinator' => true,
            'path' => '/admin/users',
        ]);

        return new SsrResponse(content: $html);
    }

    /**
     * @return list<array{uid: int, name: string, email: string, roles: string[], is_elder: bool}>
     */
    private function loadUserRows(AccountInterface $account): array
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC')
            ->execute();

        if ($ids === []) {
            return [];
        }

        $users = array_values($storage->loadMultiple($ids));
        $rows = [];

        foreach ($users as $user) {
            if ($user->id() === $account->id()) {
                continue; // Exclude self
            }

            $rows[] = [
                'uid' => $user->id(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'is_elder' => $user->isElder(),
            ];
        }

        return $rows;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/RoleManagementControllerTest.php`
Expected: All 10 tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Controller/RoleManagementController.php tests/Minoo/Unit/Controller/RoleManagementControllerTest.php
git commit -m "feat(#499): add RoleManagementController with changeRole endpoint

Permission matrix: coordinators manage volunteer/elder, admins also
manage coordinator. No one modifies admins or themselves."
```

---

### Task 3: RoleManagementServiceProvider + routes

**Files:**
- Create: `src/Provider/RoleManagementServiceProvider.php`
- Modify: `composer.json`

- [ ] **Step 1: Create provider**

Create `src/Provider/RoleManagementServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class RoleManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types.
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'dashboard.coordinator.users',
            RouteBuilder::create('/dashboard/coordinator/users')
                ->controller('Minoo\Controller\RoleManagementController::coordinatorList')
                ->requireRole('elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.users',
            RouteBuilder::create('/admin/users')
                ->controller('Minoo\Controller\RoleManagementController::adminList')
                ->requireRole('admin')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.users.roles',
            RouteBuilder::create('/api/users/{uid}/roles')
                ->controller('Minoo\Controller\RoleManagementController::changeRole')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );
    }
}
```

- [ ] **Step 2: Register provider in composer.json**

Add `"Minoo\\Provider\\RoleManagementServiceProvider"` to the `extra.waaseyaa.providers` array, **before** `AdminServiceProvider` (to ensure `/admin/users` is registered before the admin SPA catch-all):

```json
"Minoo\\Provider\\AccountServiceProvider",
"Minoo\\Provider\\FlashServiceProvider",
"Minoo\\Provider\\ChatServiceProvider",
"Minoo\\Provider\\FeaturedItemServiceProvider",
"Minoo\\Provider\\RoleManagementServiceProvider",
"Minoo\\Provider\\AdminServiceProvider",
```

- [ ] **Step 3: Delete stale manifest**

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 4: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Provider/RoleManagementServiceProvider.php composer.json
git commit -m "feat(#499): add RoleManagementServiceProvider with routes

Routes: GET /dashboard/coordinator/users, GET /admin/users,
POST /api/users/{uid}/roles. Registered before AdminServiceProvider
to avoid SPA catch-all conflict."
```

---

### Task 4: User role card partial + coordinator users template

**Files:**
- Create: `templates/components/user-role-card.html.twig`
- Create: `templates/dashboard/coordinator-users.html.twig`

- [ ] **Step 1: Create shared partial**

Create `templates/components/user-role-card.html.twig`:

```twig
{# templates/components/user-role-card.html.twig — shared by coordinator + admin views #}
<div class="card card--dashboard">
  <h3 class="card__title">{{ user.name }}</h3>
  {% if user.email %}<p class="card__meta">{{ user.email }}</p>{% endif %}
  <div class="card__meta">
    {% if 'volunteer' in user.roles %}<span class="card__badge">{{ trans('roles.badge_volunteer') }}</span>{% endif %}
    {% if user.is_elder %}<span class="card__badge">{{ trans('roles.badge_elder') }}</span>{% endif %}
    {% if 'elder_coordinator' in user.roles %}<span class="card__badge">{{ trans('roles.badge_coordinator') }}</span>{% endif %}
    {% if 'admin' in user.roles %}<span class="card__badge">{{ trans('roles.badge_admin') }}</span>{% endif %}
  </div>

  {% if 'admin' not in user.roles %}
  <div class="card__actions">
    {% if 'volunteer' not in user.roles %}
      <form method="post" action="/api/users/{{ user.uid }}/roles" style="display:inline">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <input type="hidden" name="action" value="grant">
        <input type="hidden" name="role" value="volunteer">
        <button type="submit" class="btn btn--sm btn--primary">{{ trans('roles.grant_volunteer') }}</button>
      </form>
    {% else %}
      <form method="post" action="/api/users/{{ user.uid }}/roles" style="display:inline">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <input type="hidden" name="action" value="revoke">
        <input type="hidden" name="role" value="volunteer">
        <button type="submit" class="btn btn--sm btn--outline">{{ trans('roles.revoke_volunteer') }}</button>
      </form>
    {% endif %}

    {% if not user.is_elder %}
      <form method="post" action="/api/users/{{ user.uid }}/roles" style="display:inline">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <input type="hidden" name="action" value="grant">
        <input type="hidden" name="role" value="elder">
        <button type="submit" class="btn btn--sm btn--primary">{{ trans('roles.grant_elder') }}</button>
      </form>
    {% else %}
      <form method="post" action="/api/users/{{ user.uid }}/roles" style="display:inline">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <input type="hidden" name="action" value="revoke">
        <input type="hidden" name="role" value="elder">
        <button type="submit" class="btn btn--sm btn--outline">{{ trans('roles.revoke_elder') }}</button>
      </form>
    {% endif %}

    {% if can_manage_coordinator|default(false) %}
      {% if 'coordinator' not in user.roles %}
        <form method="post" action="/api/users/{{ user.uid }}/roles" style="display:inline">
          <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
          <input type="hidden" name="action" value="grant">
          <input type="hidden" name="role" value="coordinator">
          <button type="submit" class="btn btn--sm btn--primary">{{ trans('roles.grant_coordinator') }}</button>
        </form>
      {% else %}
        <form method="post" action="/api/users/{{ user.uid }}/roles" style="display:inline">
          <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
          <input type="hidden" name="action" value="revoke">
          <input type="hidden" name="role" value="coordinator">
          <button type="submit" class="btn btn--sm btn--outline">{{ trans('roles.revoke_coordinator') }}</button>
        </form>
      {% endif %}
    {% endif %}
  </div>
  {% endif %}
</div>
```

- [ ] **Step 2: Create coordinator users template**

Create `templates/dashboard/coordinator-users.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}{{ trans('coordinator.users_title') }} — Minoo{% endblock %}

{% block content %}
<section class="account-home flow-lg">
  <div>
    <h1>{{ trans('coordinator.users_title') }}</h1>
    <p class="text-secondary">{{ trans('coordinator.users_subtitle') }}</p>
  </div>

  {% if users|length == 0 %}
    <p class="text-secondary">{{ trans('roles.no_users') }}</p>
  {% endif %}

  {% for user in users %}
    {% include "components/user-role-card.html.twig" with { user: user, can_manage_coordinator: can_manage_coordinator } %}
  {% endfor %}
</section>
{% endblock %}
```

- [ ] **Step 3: Commit**

```bash
git add templates/components/user-role-card.html.twig templates/dashboard/coordinator-users.html.twig
git commit -m "feat(#499): add user role card partial and coordinator users template"
```

---

### Task 5: Admin users template

**Files:**
- Create: `templates/admin/users.html.twig`

- [ ] **Step 1: Create template**

Create `templates/admin/users.html.twig` — reuses the shared `user-role-card` partial with `can_manage_coordinator: true`:

```twig
{% extends "base.html.twig" %}

{% block title %}{{ trans('admin.users_title') }} — Minoo{% endblock %}

{% block content %}
<section class="account-home flow-lg">
  <div>
    <h1>{{ trans('admin.users_title') }}</h1>
    <p class="text-secondary">{{ trans('admin.users_subtitle') }}</p>
  </div>

  {% if users|length == 0 %}
    <p class="text-secondary">{{ trans('roles.no_users') }}</p>
  {% endif %}

  {% for user in users %}
    {% include "components/user-role-card.html.twig" with { user: user, can_manage_coordinator: can_manage_coordinator } %}
  {% endfor %}
</section>
{% endblock %}
```

- [ ] **Step 2: Commit**

```bash
git add templates/admin/users.html.twig
git commit -m "feat(#499): add admin users template with coordinator management"
```

---

### Task 6: Navigation links

**Files:**
- Modify: `templates/dashboard/coordinator.html.twig`
- Modify: `templates/account/home.html.twig`

- [ ] **Step 1: Add "Manage Members" link to coordinator dashboard**

Add a link near the top of the coordinator dashboard (after the heading, before the requests section):

```twig
<a href="{{ lang_url('/dashboard/coordinator/users') }}" class="btn btn--outline">{{ trans('roles.manage_members') }}</a>
```

- [ ] **Step 2: Add admin section to account home**

Add an admin section to `templates/account/home.html.twig` after the coordinator block:

```twig
{% if 'admin' in roles %}
<div class="actions">
  <h2>{{ trans('admin.users_title') }}</h2>
  <p class="text-secondary">{{ trans('admin.users_subtitle') }}</p>
  <ul class="links">
    <li><a href="{{ lang_url('/admin/users') }}">{{ trans('admin.users_title') }}</a></li>
  </ul>
</div>
{% endif %}
```

- [ ] **Step 3: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS

- [ ] **Step 4: Commit**

```bash
git add templates/dashboard/coordinator.html.twig templates/account/home.html.twig
git commit -m "feat(#499): add navigation links for role management

Coordinator dashboard gets 'Manage Members' link.
Account page gets admin section with 'Manage Users' link."
```

---

### Task 7: Final verification

- [ ] **Step 1: Delete stale manifest**

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 2: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS

- [ ] **Step 3: Manual smoke test (if dev server available)**

1. Start dev server: `php -S localhost:8081 -t public`
2. Log in as coordinator → navigate to `/dashboard/coordinator` → see "Manage Members" link
3. Click → see user list with Grant/Revoke buttons for Volunteer and Elder
4. Grant Volunteer to a user → flash message → badge appears
5. Revoke → badge removed
6. Log in as admin → navigate to `/admin/users` → see user list with Coordinator buttons too
7. Verify admin users cannot be modified (no action buttons shown)

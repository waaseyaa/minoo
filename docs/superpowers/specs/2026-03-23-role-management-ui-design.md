# Role Management UI Design

**Issue:** #499
**Parent:** #462 (coordinator and admin account management)
**Milestone:** Role Hierarchy & Account Management (#44)
**Date:** 2026-03-23

## Problem

Coordinators and admins need the ability to view users and manage their roles. Currently, the only role-granting mechanism is the volunteer application approval flow on the coordinator dashboard. There is no general-purpose role management UI.

## Architecture

Two surfaces share one underlying role-change endpoint:

- **Coordinator Dashboard** (`/dashboard/coordinator/users`) — scoped to Volunteer and Elder management
- **Admin Users** (`/admin/users`) — full role management including Coordinator promotion

Route guards enforce minimum access. Controller enforces fine-grained permission per the role hierarchy matrix.

## Endpoints

| Route | Method | Controller | Guard |
|---|---|---|---|
| `/dashboard/coordinator/users` | GET | `RoleManagementController::coordinatorList` | `requireRole('elder_coordinator')` |
| `/admin/users` | GET | `RoleManagementController::adminList` | `requireRole('admin')` |
| `/api/users/{uid}/roles` | POST | `RoleManagementController::changeRole` | `requireAuthentication()` |

## Role Change Endpoint

**POST** `/api/users/{uid}/roles`

Request body (form POST):
- `action` — `grant` or `revoke`
- `role` — `volunteer`, `elder`, or `coordinator`
- `_csrf_token` — handled by CsrfMiddleware

### Controller logic

```php
public function changeRole(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $targetUid = (int) $params['uid'];
    $action = $request->request->get('action'); // grant|revoke
    $role = $request->request->get('role');     // volunteer|elder|coordinator
    $referrer = $request->headers->get('Referer', '/account');

    // 1. Validate input
    if (!in_array($action, ['grant', 'revoke'], true)
        || !in_array($role, ['volunteer', 'elder', 'coordinator'], true)) {
        Flash::error('Invalid request.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
    }

    // 2. Prevent self-modification
    if ($targetUid === $account->id()) {
        Flash::error('You cannot modify your own roles.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
    }

    // 3. Check actor permission
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

    // 4. Load target user
    $storage = $this->entityTypeManager->getStorage('user');
    $user = $storage->load($targetUid);
    if ($user === null) {
        Flash::error('User not found.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
    }

    // 5. Cannot modify admins
    if (in_array('admin', $user->getRoles(), true)) {
        Flash::error('Admin accounts cannot be modified.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
    }

    // 6. Apply change
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
```

Note: `isElder()`/`setElder()` methods are added to User in framework PR #625 (part of #461). These must be merged before this feature lands.

## Permission Matrix

| Actor | Volunteer | Elder | Coordinator | Admin |
|-------|-----------|-------|-------------|-------|
| Coordinator (`elder_coordinator`) | Grant/Revoke | Grant/Revoke | No | No |
| Admin (`admin`) | Grant/Revoke | Grant/Revoke | Grant/Revoke | No |

**Invariant:** No one can modify Admin accounts. No one can modify their own roles.

## User Listing

### Coordinator view

```php
public function coordinatorList(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $storage = $this->entityTypeManager->getStorage('user');
    $ids = $storage->getQuery()->condition('status', 1)->sort('name', 'ASC')->execute();
    $users = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

    // Build view data: name, email, roles, is_elder
    $userRows = array_map(fn($u) => [
        'uid' => $u->id(),
        'name' => $u->getName(),
        'email' => $u->getEmail(),
        'roles' => $u->getRoles(),
        'is_elder' => $u->isElder(),
    ], $users);

    // Filter out self
    $userRows = array_filter($userRows, fn($r) => $r['uid'] !== $account->id());

    $html = $this->twig->render('dashboard/coordinator-users.html.twig', [
        'users' => array_values($userRows),
        'account' => $account,
        'path' => '/dashboard/coordinator/users',
    ]);

    return new SsrResponse(content: $html);
}
```

### Admin view

Same pattern but renders `admin/users.html.twig` with `can_manage_coordinator: true`.

## Templates

### `templates/dashboard/coordinator-users.html.twig`

Extends `base.html.twig`. Shows user cards with:
- User name and email
- Current roles as badges (Volunteer, Elder, Coordinator)
- Action buttons: Grant/Revoke per role (within coordinator's permission scope)
- Each button is a POST form to `/api/users/{uid}/roles`

Uses existing BEM classes from the dashboard card pattern (`card__title`, `card__meta`, `card__badge`). Role action buttons use inline forms.

```twig
{% for user in users %}
<div class="card card--dashboard">
  <h3 class="card__title">{{ user.name }}</h3>
  {% if user.email %}<p class="card__meta">{{ user.email }}</p>{% endif %}
  <div class="card__meta">
    {% if 'volunteer' in user.roles %}<span class="card__badge">{{ trans('roles.badge_volunteer') }}</span>{% endif %}
    {% if user.is_elder %}<span class="card__badge">{{ trans('roles.badge_elder') }}</span>{% endif %}
    {% if 'elder_coordinator' in user.roles %}<span class="card__badge">{{ trans('roles.badge_coordinator') }}</span>{% endif %}
  </div>
  <div class="card__meta">
    {% if 'volunteer' not in user.roles %}
      <form method="post" action="/api/users/{{ user.uid }}/roles">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <input type="hidden" name="action" value="grant">
        <input type="hidden" name="role" value="volunteer">
        <button type="submit" class="btn btn--sm btn--primary">Grant Volunteer</button>
      </form>
    {% else %}
      <form method="post" action="/api/users/{{ user.uid }}/roles">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <input type="hidden" name="action" value="revoke">
        <input type="hidden" name="role" value="volunteer">
        <button type="submit" class="btn btn--sm btn--outline">Revoke Volunteer</button>
      </form>
    {% endif %}

    {% if not user.is_elder %}
      <form method="post" action="/api/users/{{ user.uid }}/roles">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <input type="hidden" name="action" value="grant">
        <input type="hidden" name="role" value="elder">
        <button type="submit" class="btn btn--sm btn--primary">Grant Elder</button>
      </form>
    {% else %}
      <form method="post" action="/api/users/{{ user.uid }}/roles">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
        <input type="hidden" name="action" value="revoke">
        <input type="hidden" name="role" value="elder">
        <button type="submit" class="btn btn--sm btn--outline">Revoke Elder</button>
      </form>
    {% endif %}
  </div>
</div>
{% endfor %}
```

### `templates/admin/users.html.twig`

Same structure as coordinator-users but adds Coordinator grant/revoke buttons and hides Admin users from modification.

## Route Registration

All three routes live in a new `RoleManagementServiceProvider`. This avoids conflicts with `AdminServiceProvider`'s `/admin/{path}` SPA catch-all (which would absorb `/admin/users` if registered first).

```php
// RoleManagementServiceProvider::routes()

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
```

Register `RoleManagementServiceProvider` in `composer.json`'s `extra.waaseyaa.providers` array.

## Translation Strings

```php
'admin.users_title' => 'Manage Users',
'admin.users_subtitle' => 'View and manage user roles across the community.',
'coordinator.users_title' => 'Community Members',
'coordinator.users_subtitle' => 'Manage volunteer and Elder roles for community members.',
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
```

## Navigation

Add a "Manage Members" link to:
- `templates/dashboard/coordinator.html.twig` — link to `/dashboard/coordinator/users`
- `templates/account/home.html.twig` — admin section with link to `/admin/users` (if user has `admin` role)

## What This Does NOT Do

- No account creation (that's #500)
- No user search/filter (future enhancement)
- No pagination (user count is small enough)
- No self-modification (enforced in controller)
- No access policy changes (that's #463)
- No email notifications on role changes

## Testing

### Unit tests (`RoleManagementControllerTest`)

- `changeRole` grants volunteer role and redirects
- `changeRole` revokes volunteer role
- `changeRole` grants elder (sets `is_elder` field)
- `changeRole` revokes elder (unsets `is_elder` field)
- `changeRole` rejects self-modification
- `changeRole` rejects coordinator management by non-admin
- `changeRole` rejects admin modification by anyone
- `changeRole` rejects invalid action/role
- `coordinatorList` renders user list for coordinators
- `adminList` renders user list for admins

### Integration

- Coordinator can see `/dashboard/coordinator/users`
- Admin can see `/admin/users`
- Grant/revoke buttons work end-to-end
- Flash messages appear after role changes

## Scope Boundaries

- New controller: `RoleManagementController` (Minoo app layer)
- New provider: `RoleManagementServiceProvider` (routes for all 3 endpoints)
- New templates: `dashboard/coordinator-users.html.twig`, `admin/users.html.twig`
- Modified templates: `dashboard/coordinator.html.twig` (nav link), `account/home.html.twig` (admin link)
- No framework changes needed
- No North Cloud involvement

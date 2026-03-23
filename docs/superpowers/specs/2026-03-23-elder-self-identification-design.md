# Elder Self-Identification Design

**Issue:** #461
**Milestone:** Role Hierarchy & Account Management (#44)
**Date:** 2026-03-23

## Problem

Any Minoo user should be able to self-identify as an Elder. No approval gate, no workflow, no permission escalation. The system currently has no mechanism for this.

## Design Principle

**Roles represent capabilities. Fields represent identity.**

Elder status is a self-declared identity attribute, not an operational role. It must not be stored in the `roles[]` array. It gets its own field on the User entity.

## Data Layer

### User entity field

- **Field:** `is_elder` (integer, 0 or 1, default 0)
- **Storage:** `_data` JSON blob + dedicated SQLite column
- **Migration:** `20260323_110000_add_is_elder_to_user.php`

```sql
ALTER TABLE user ADD COLUMN is_elder INTEGER DEFAULT 0
```

### User class changes (framework: `waaseyaa/packages/user/src/User.php`)

Add `'is_elder' => 0` to the constructor defaults (alongside `status`), and two helper methods following the `isActive()`/`setActive()` pattern:

```php
// In __construct(), add to $values += [...]:
'is_elder' => 0,

// New methods:
public function isElder(): bool
{
    return (int) ($this->get('is_elder') ?? 0) === 1;
}

public function setElder(bool $elder): static
{
    return $this->set('is_elder', $elder ? 1 : 0);
}
```

## Controller

### `AccountHomeController::toggleElder()`

- **Route:** POST `/account/elder-toggle`
- **Auth:** Requires authenticated user (`.requireAuth()` on route)
- **CSRF:** Handled automatically by `CsrfMiddleware` (middleware validates `_csrf_token` on all POST requests before the controller is called — no manual validation needed)
- **Logic:** Load current user, flip `is_elder`, save, flash message, redirect to `/account`

```php
public function toggleElder(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $storage = $this->entityTypeManager->getStorage('user');
    $user = $storage->load($account->id());

    $isElder = $user->isElder();
    $user->setElder(!$isElder);
    $storage->save($user);

    $flashKey = $isElder ? 'account.elder_removed_flash' : 'account.elder_set_flash';
    Flash::success(trans($flashKey));

    return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/account']);
}
```

### Constructor update

Add `EntityTypeManager` to `AccountHomeController` constructor (auto-resolved by `SsrPageHandler`):

```php
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\User\User;

final class AccountHomeController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly EntityTypeManager $entityTypeManager,
    ) {}
}
```

### Route registration

Add POST route in `AccountServiceProvider::routes()` after the existing GET route:

```php
$router->addRoute(
    'account.elder_toggle',
    RouteBuilder::create('/account/elder-toggle')
        ->controller('Minoo\Controller\AccountHomeController::toggleElder')
        ->requireAuthentication()
        ->methods('POST')
        ->build(),
);
```

## Template

### `templates/account/home.html.twig`

Add an Elder Status section after the Profile section, before the volunteer/coordinator blocks:

```twig
<div class="actions">
  <h2>{{ trans('account.elder_status') }}</h2>
  {% if is_elder %}
    <p class="text-secondary">{{ trans('account.elder_identified') }}</p>
    <form method="post" action="/account/elder-toggle">
      <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
      <button type="submit" class="btn btn--outline">{{ trans('account.elder_remove') }}</button>
    </form>
  {% else %}
    <p class="text-secondary">{{ trans('account.elder_question') }}</p>
    <form method="post" action="/account/elder-toggle">
      <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
      <button type="submit" class="btn btn--primary">{{ trans('account.elder_identify') }}</button>
    </form>
  {% endif %}
</div>
```

### Template variable

`AccountHomeController::index()` must pass `is_elder` to the template (requires `use Waaseyaa\User\User` import, already added in constructor update above):

```php
'is_elder' => $account instanceof User && $account->isElder(),
```

## Translation Strings

Add to `resources/lang/en.php`:

| Key | Value |
|-----|-------|
| `account.elder_status` | Elder Status |
| `account.elder_identified` | You have identified as an Elder. |
| `account.elder_question` | Are you an Elder in your community? |
| `account.elder_identify` | I am an Elder |
| `account.elder_remove` | Remove Elder status |
| `account.elder_set_flash` | You have identified as an Elder. Miigwech. |
| `account.elder_removed_flash` | Elder status removed. |

## What This Does NOT Do

- No permission changes -- Elder is not a capability
- No access policy updates -- orthogonal to role hierarchy (#463)
- No workflow or approval gate -- self-determined
- No admin notification -- not a privileged action

## Testing

### Unit tests

- `User::isElder()` returns false by default
- `User::setElder(true)` then `isElder()` returns true
- `User::setElder(false)` then `isElder()` returns false

### Controller tests

- POST `/account/elder-toggle` as authenticated user sets `is_elder = 1`
- POST again toggles back to `is_elder = 0`
- POST without auth returns 302 redirect to login (enforced by `.requireAuth()`)
- CSRF validation handled by middleware (not tested in controller unit tests)

### Integration

- Account page renders "I am an Elder" button when not Elder
- Account page renders "Remove Elder status" when Elder

## Scope Boundaries

- This feature is Minoo-only (app layer)
- The `User` class changes are framework-level (`waaseyaa/packages/user`)
- No North Cloud involvement
- No changes to existing access policies

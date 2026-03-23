# Elder Self-Identification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow any authenticated Minoo user to self-identify as an Elder via a toggle on their account page.

**Architecture:** Add `is_elder` boolean field to the framework User entity (identity attribute, not a role). Add a POST toggle endpoint to AccountHomeController. Update the account template to show the toggle.

**Tech Stack:** PHP 8.4, Waaseyaa framework (User entity, RouteBuilder, CsrfMiddleware), Twig templates, SQLite migration, PHPUnit 10.5

**Spec:** `docs/superpowers/specs/2026-03-23-elder-self-identification-design.md`

**Issue:** #461

---

### Task 1: Add `isElder()`/`setElder()` to framework User class

**Files:**
- Modify: `/home/jones/dev/waaseyaa/packages/user/src/User.php`
- Test: `/home/jones/dev/waaseyaa/packages/user/tests/Unit/UserTest.php`

- [ ] **Step 1: Write failing tests**

Add to `UserTest.php`:

```php
public function testIsElderDefaultsFalse(): void
{
    $user = new User();
    $this->assertFalse($user->isElder());
}

public function testSetElderTrue(): void
{
    $user = new User();
    $user->setElder(true);
    $this->assertTrue($user->isElder());
}

public function testSetElderFalseAfterTrue(): void
{
    $user = new User();
    $user->setElder(true);
    $user->setElder(false);
    $this->assertFalse($user->isElder());
}

public function testSetElderReturnsSelf(): void
{
    $user = new User();
    $result = $user->setElder(true);
    $this->assertSame($user, $result);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/Unit/UserTest.php --filter testIsElder`
Expected: FAIL — `isElder()` method does not exist

- [ ] **Step 3: Add constructor default and helper methods**

In `User.php`, add `'is_elder' => 0` to the `$values +=` array in `__construct()`:

```php
$values += [
    'roles' => [],
    'permissions' => [],
    'status' => 1,
    'is_elder' => 0,
];
```

Add methods after the `setActive()` method (in the "Status" section):

```php
// -----------------------------------------------------------------
// Elder self-identification
// -----------------------------------------------------------------

public function isElder(): bool
{
    return (int) ($this->get('is_elder') ?? 0) === 1;
}

public function setElder(bool $elder): static
{
    return $this->set('is_elder', $elder ? 1 : 0);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/Unit/UserTest.php`
Expected: All tests PASS

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/user/src/User.php packages/user/tests/Unit/UserTest.php
git commit -m "feat(#461): add isElder()/setElder() to User entity

Identity attribute for Elder self-identification. Stored as is_elder
field (integer 0/1), not as a role — roles represent capabilities,
fields represent identity."
```

---

### Task 2: Add SQLite migration in Minoo

**Files:**
- Create: `/home/jones/dev/minoo/migrations/20260323_110000_add_is_elder_to_user.php`

- [ ] **Step 1: Create migration file**

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add is_elder column to user table for Elder self-identification.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement(
            'ALTER TABLE user ADD COLUMN is_elder INTEGER DEFAULT 0',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite < 3.35 doesn't support DROP COLUMN
    }
};
```

- [ ] **Step 2: Commit**

```bash
cd /home/jones/dev/minoo
git add migrations/20260323_110000_add_is_elder_to_user.php
git commit -m "feat(#461): add is_elder migration for user table"
```

---

### Task 3: Add translation strings

**Files:**
- Modify: `/home/jones/dev/minoo/resources/lang/en.php`

- [ ] **Step 1: Add Elder-related translation keys**

Add after the existing `account.*` keys:

```php
'account.elder_status' => 'Elder Status',
'account.elder_identified' => 'You have identified as an Elder.',
'account.elder_question' => 'Are you an Elder in your community?',
'account.elder_identify' => 'I am an Elder',
'account.elder_remove' => 'Remove Elder status',
'account.elder_set_flash' => 'You have identified as an Elder. Miigwech.',
'account.elder_removed_flash' => 'Elder status removed.',
```

- [ ] **Step 2: Commit**

```bash
cd /home/jones/dev/minoo
git add resources/lang/en.php
git commit -m "feat(#461): add Elder self-identification translation strings"
```

---

### Task 4: Update AccountHomeController with toggle endpoint

**Files:**
- Modify: `/home/jones/dev/minoo/src/Controller/AccountHomeController.php`
- Modify: `/home/jones/dev/minoo/src/Provider/AccountServiceProvider.php`
- Test: `/home/jones/dev/minoo/tests/Minoo/Unit/Controller/AccountHomeControllerTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Minoo/Unit/Controller/AccountHomeControllerTest.php`:

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
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\User;

#[CoversClass(AccountHomeController::class)]
final class AccountHomeControllerTest extends TestCase
{
    #[Test]
    public function index_passes_is_elder_false_for_non_elder(): void
    {
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);

        $capturedContext = null;
        $twig->expects($this->once())
            ->method('render')
            ->with('account/home.html.twig', $this->callback(function (array $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;
                return true;
            }))
            ->willReturn('<html></html>');

        $controller = new AccountHomeController($twig, $etm);
        $account = new User(['uid' => 1, 'name' => 'Test']);
        $request = HttpRequest::create('/account');

        $controller->index([], [], $account, $request);

        $this->assertArrayHasKey('is_elder', $capturedContext);
        $this->assertFalse($capturedContext['is_elder']);
    }

    #[Test]
    public function index_passes_is_elder_true_for_elder(): void
    {
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);

        $capturedContext = null;
        $twig->expects($this->once())
            ->method('render')
            ->with('account/home.html.twig', $this->callback(function (array $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;
                return true;
            }))
            ->willReturn('<html></html>');

        $controller = new AccountHomeController($twig, $etm);
        $account = new User(['uid' => 1, 'name' => 'Test', 'is_elder' => 1]);
        $request = HttpRequest::create('/account');

        $controller->index([], [], $account, $request);

        $this->assertTrue($capturedContext['is_elder']);
    }

    #[Test]
    public function toggle_elder_sets_elder_and_redirects(): void
    {
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(EntityStorageInterface::class);

        $user = new User(['uid' => 1, 'name' => 'Test']);
        $this->assertFalse($user->isElder());

        $etm->method('getStorage')->with('user')->willReturn($storage);
        $storage->method('load')->with(1)->willReturn($user);
        $storage->expects($this->once())->method('save')->with($user);

        $controller = new AccountHomeController($twig, $etm);
        $account = new User(['uid' => 1, 'name' => 'Test']);
        $request = HttpRequest::create('/account/elder-toggle', 'POST');

        $_SESSION = [];
        $response = $controller->toggleElder([], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertSame('/account', $response->headers['Location']);
        $this->assertTrue($user->isElder());
    }

    #[Test]
    public function toggle_elder_unsets_elder_when_already_elder(): void
    {
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(EntityStorageInterface::class);

        $user = new User(['uid' => 1, 'name' => 'Test', 'is_elder' => 1]);
        $this->assertTrue($user->isElder());

        $etm->method('getStorage')->with('user')->willReturn($storage);
        $storage->method('load')->with(1)->willReturn($user);
        $storage->expects($this->once())->method('save')->with($user);

        $controller = new AccountHomeController($twig, $etm);
        $account = new User(['uid' => 1, 'name' => 'Test', 'is_elder' => 1]);
        $request = HttpRequest::create('/account/elder-toggle', 'POST');

        $_SESSION = [];
        $response = $controller->toggleElder([], [], $account, $request);

        $this->assertSame(302, $response->statusCode);
        $this->assertFalse($user->isElder());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/AccountHomeControllerTest.php`
Expected: FAIL — `toggleElder()` method does not exist, constructor signature mismatch

- [ ] **Step 3: Update AccountHomeController**

Replace `AccountHomeController.php` with:

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

final class AccountHomeController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('account/home.html.twig', [
            'account' => $account,
            'roles' => $account->getRoles(),
            'is_elder' => $account instanceof User && $account->isElder(),
            'path' => '/account',
        ]);

        return new SsrResponse(content: $html);
    }

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
}
```

- [ ] **Step 4: Add route in AccountServiceProvider**

Add the POST route after the existing GET route in `AccountServiceProvider::routes()`:

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

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/AccountHomeControllerTest.php`
Expected: All 4 tests PASS

- [ ] **Step 6: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS (check no existing AccountHomeController tests broke)

- [ ] **Step 7: Commit**

```bash
git add src/Controller/AccountHomeController.php src/Provider/AccountServiceProvider.php tests/Minoo/Unit/Controller/AccountHomeControllerTest.php
git commit -m "feat(#461): add Elder self-identification toggle endpoint

POST /account/elder-toggle flips is_elder on User entity.
Flash message confirms the change. CSRF handled by middleware."
```

---

### Task 5: Update account template

**Files:**
- Modify: `/home/jones/dev/minoo/templates/account/home.html.twig`

- [ ] **Step 1: Add Elder Status section**

After the Profile `<div class="actions">` block (line 17), before the volunteer block, add:

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

- [ ] **Step 2: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS

- [ ] **Step 3: Commit**

```bash
git add templates/account/home.html.twig
git commit -m "feat(#461): add Elder self-identification toggle to account page

Shows 'I am an Elder' button for non-Elders, 'Remove Elder status'
for Elders. Low friction, immediately reversible."
```

---

### Task 6: Delete stale manifest cache and verify end-to-end

- [ ] **Step 1: Delete stale manifest if present**

```bash
rm -f storage/framework/packages.php
```

- [ ] **Step 2: Run full Minoo test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS

- [ ] **Step 3: Run framework test suite**

Run: `cd /home/jones/dev/waaseyaa && ./vendor/bin/phpunit packages/user/tests/`
Expected: All tests PASS

- [ ] **Step 4: Manual smoke test (if dev server available)**

1. Start dev server: `php -S localhost:8081 -t public`
2. Log in as a test user
3. Navigate to `/account`
4. Verify "Elder Status" section appears with "I am an Elder" button
5. Click the button — verify flash message "You have identified as an Elder. Miigwech."
6. Verify page now shows "Remove Elder status" button
7. Click remove — verify flash message "Elder status removed."

- [ ] **Step 5: Final commit if any cleanup needed**

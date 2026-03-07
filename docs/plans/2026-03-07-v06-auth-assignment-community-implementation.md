# v0.6 Auth, Assignment, Community Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Activate authentication, add coordinator-assigned elder support workflow, and introduce community content entity.

**Architecture:** Three pillars built on existing waaseyaa auth infrastructure. Community entity is a new content entity type. Auth adds login/register/logout controllers using framework's User entity and SessionMiddleware. Elder Support v0.2 adds assignment fields, status transitions, and role-gated dashboards.

**Tech Stack:** PHP 8.3, waaseyaa framework (User entity, SessionMiddleware, AccessChecker, RouteBuilder), Twig 3, SQLite, PHPUnit 10.5

**Design doc:** `docs/plans/2026-03-07-v06-auth-assignment-community-design.md`

---

## Task 1: Community Entity

**Files:**
- Create: `src/Entity/Community.php`
- Test: `tests/Minoo/Unit/Entity/CommunityTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\Community;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Community::class)]
final class CommunityTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $community = new Community([
            'name' => 'Sagamok Anishnawbek',
            'community_type' => 'first_nation',
        ]);

        $this->assertSame('Sagamok Anishnawbek', $community->get('name'));
        $this->assertSame('first_nation', $community->get('community_type'));
        $this->assertSame('community', $community->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_timestamps_to_zero(): void
    {
        $community = new Community(['name' => 'Test', 'community_type' => 'town']);

        $this->assertSame(0, $community->get('created_at'));
        $this->assertSame(0, $community->get('updated_at'));
    }

    #[Test]
    public function it_supports_optional_geo_fields(): void
    {
        $community = new Community([
            'name' => 'Sagamok Anishnawbek',
            'community_type' => 'first_nation',
            'latitude' => 46.15,
            'longitude' => -81.95,
            'population' => 3200,
        ]);

        $this->assertSame(46.15, $community->get('latitude'));
        $this->assertSame(-81.95, $community->get('longitude'));
        $this->assertSame(3200, $community->get('population'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/CommunityTest.php`
Expected: FAIL — class `Minoo\Entity\Community` not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Community extends ContentEntityBase
{
    protected string $entityTypeId = 'community';

    protected array $entityKeys = [
        'id' => 'cid',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    public function __construct(array $values = [])
    {
        if (!array_key_exists('created_at', $values)) {
            $values['created_at'] = 0;
        }
        if (!array_key_exists('updated_at', $values)) {
            $values['updated_at'] = 0;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/CommunityTest.php`
Expected: 3 tests, all PASS

**Step 5: Commit**

```bash
git add src/Entity/Community.php tests/Minoo/Unit/Entity/CommunityTest.php
git commit -m "feat(#TBD): add Community content entity with unit tests"
```

---

## Task 2: Community Seeder

**Files:**
- Create: `src/Seed/CommunitySeeder.php`
- Test: `tests/Minoo/Unit/Seed/CommunitySeederTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Seed;

use Minoo\Seed\CommunitySeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommunitySeeder::class)]
final class CommunitySeederTest extends TestCase
{
    #[Test]
    public function it_provides_north_shore_communities(): void
    {
        $communities = CommunitySeeder::northShoreCommunities();

        $this->assertNotEmpty($communities);
        $this->assertArrayHasKey('name', $communities[0]);
        $this->assertArrayHasKey('community_type', $communities[0]);
    }

    #[Test]
    public function it_includes_first_nations_and_towns(): void
    {
        $communities = CommunitySeeder::northShoreCommunities();
        $types = array_unique(array_column($communities, 'community_type'));

        $this->assertContains('first_nation', $types);
        $this->assertContains('town', $types);
        $this->assertContains('region', $types);
    }

    #[Test]
    public function it_includes_sagamok(): void
    {
        $communities = CommunitySeeder::northShoreCommunities();
        $names = array_column($communities, 'name');

        $this->assertContains('Sagamok Anishnawbek', $names);
    }

    #[Test]
    public function it_provides_ten_communities(): void
    {
        $communities = CommunitySeeder::northShoreCommunities();

        $this->assertCount(10, $communities);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Seed/CommunitySeederTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Seed;

final class CommunitySeeder
{
    /** @return list<array{name: string, community_type: string}> */
    public static function northShoreCommunities(): array
    {
        return [
            ['name' => 'Sagamok Anishnawbek', 'community_type' => 'first_nation'],
            ['name' => 'Serpent River First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Mississauga First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Thessalon First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Garden River First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Batchewana First Nation', 'community_type' => 'first_nation'],
            ['name' => 'Elliot Lake', 'community_type' => 'town'],
            ['name' => 'Blind River', 'community_type' => 'town'],
            ['name' => 'Thessalon', 'community_type' => 'town'],
            ['name' => 'North Shore', 'community_type' => 'region'],
        ];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Seed/CommunitySeederTest.php`
Expected: 4 tests, all PASS

**Step 5: Commit**

```bash
git add src/Seed/CommunitySeeder.php tests/Minoo/Unit/Seed/CommunitySeederTest.php
git commit -m "feat(#TBD): add CommunitySeeder with North Shore communities"
```

---

## Task 3: Community Access Policy

**Files:**
- Create: `src/Access/CommunityAccessPolicy.php`
- Test: `tests/Minoo/Unit/Access/CommunityAccessPolicyTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Access;

use Minoo\Access\CommunityAccessPolicy;
use Minoo\Entity\Community;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommunityAccessPolicy::class)]
final class CommunityAccessPolicyTest extends TestCase
{
    #[Test]
    public function anyone_can_view_a_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $entity = new Community(['name' => 'Sagamok', 'community_type' => 'first_nation']);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($entity, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_create_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $account = $this->createAnonymousAccount();

        $result = $policy->createAccess('community', '', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_create_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $account = $this->createAdminAccount();

        $result = $policy->createAccess('community', '', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function anonymous_cannot_update_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $entity = new Community(['name' => 'Sagamok', 'community_type' => 'first_nation']);
        $account = $this->createAnonymousAccount();

        $result = $policy->access($entity, 'update', $account);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function admin_can_update_community(): void
    {
        $policy = new CommunityAccessPolicy();
        $entity = new Community(['name' => 'Sagamok', 'community_type' => 'first_nation']);
        $account = $this->createAdminAccount();

        $result = $policy->access($entity, 'update', $account);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function it_applies_to_community_type(): void
    {
        $policy = new CommunityAccessPolicy();

        $this->assertTrue($policy->appliesTo('community'));
        $this->assertFalse($policy->appliesTo('event'));
    }

    private function createAnonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 0; }
            public function hasPermission(string $permission): bool { return false; }
            public function getRoles(): array { return ['anonymous']; }
            public function isAuthenticated(): bool { return false; }
        };
    }

    private function createAdminAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 1; }
            public function hasPermission(string $permission): bool { return true; }
            public function getRoles(): array { return ['administrator']; }
            public function isAuthenticated(): bool { return true; }
        };
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/CommunityAccessPolicyTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'community')]
final class CommunityAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'community';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin permission.');
        }

        return match ($operation) {
            'view' => AccessResult::allowed('Communities are publicly viewable.'),
            default => AccessResult::neutral('Only admins can modify communities.'),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer content')) {
            return AccessResult::allowed('Admin can create communities.');
        }

        return AccessResult::neutral('Only admins can create communities.');
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Access/CommunityAccessPolicyTest.php`
Expected: 6 tests, all PASS

**Step 5: Commit**

```bash
git add src/Access/CommunityAccessPolicy.php tests/Minoo/Unit/Access/CommunityAccessPolicyTest.php
git commit -m "feat(#TBD): add CommunityAccessPolicy — public view, admin write"
```

---

## Task 4: Community Service Provider

**Files:**
- Create: `src/Provider/CommunityServiceProvider.php`
- Modify: `composer.json` — add provider to waaseyaa.providers array

**Step 1: Write the provider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Community;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class CommunityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'community',
            label: 'Community',
            class: Community::class,
            keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'community',
            fieldDefinitions: [
                'community_type' => [
                    'type' => 'string',
                    'label' => 'Community Type',
                    'description' => 'first_nation, town, or region.',
                    'weight' => 1,
                ],
                'latitude' => [
                    'type' => 'float',
                    'label' => 'Latitude',
                    'weight' => 10,
                ],
                'longitude' => [
                    'type' => 'float',
                    'label' => 'Longitude',
                    'weight' => 11,
                ],
                'population' => [
                    'type' => 'integer',
                    'label' => 'Population',
                    'weight' => 15,
                ],
                'external_ids' => [
                    'type' => 'json',
                    'label' => 'External IDs',
                    'weight' => 20,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'community.autocomplete',
            RouteBuilder::create('/api/communities/autocomplete')
                ->controller('Minoo\Controller\CommunityController::autocomplete')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}
```

**Step 2: Add provider to composer.json**

Add `"Minoo\\Provider\\CommunityServiceProvider"` to the `extra.waaseyaa.providers` array in `composer.json`.

**Step 3: Delete stale manifest**

Run: `rm -f storage/framework/packages.php`

**Step 4: Run full test suite to verify nothing breaks**

Run: `./vendor/bin/phpunit`
Expected: All existing tests PASS (community entity type now registered but no integration test yet)

**Step 5: Commit**

```bash
git add src/Provider/CommunityServiceProvider.php composer.json
git commit -m "feat(#TBD): add CommunityServiceProvider with entity type and autocomplete route"
```

---

## Task 5: Community Autocomplete Controller

**Files:**
- Create: `src/Controller/CommunityController.php`
- Test: `tests/Minoo/Unit/Controller/CommunityControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\CommunityController;
use Minoo\Entity\Community;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(CommunityController::class)]
final class CommunityControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private EntityStorageInterface $storage;
    private EntityQueryInterface $query;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();
        $this->query->method('sort')->willReturnSelf();
        $this->query->method('range')->willReturnSelf();

        $this->storage = $this->createMock(EntityStorageInterface::class);
        $this->storage->method('getQuery')->willReturn($this->query);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->with('community')
            ->willReturn($this->storage);

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function autocomplete_returns_json_array(): void
    {
        $sagamok = new Community(['cid' => 1, 'name' => 'Sagamok Anishnawbek', 'community_type' => 'first_nation']);
        $serpent = new Community(['cid' => 2, 'name' => 'Serpent River First Nation', 'community_type' => 'first_nation']);

        $this->query->method('execute')->willReturn([1, 2]);
        $this->storage->method('loadMultiple')
            ->with([1, 2])
            ->willReturn([1 => $sagamok, 2 => $serpent]);

        $this->request = HttpRequest::create('/?q=Sa');
        $controller = new CommunityController($this->entityTypeManager);
        $response = $controller->autocomplete([], ['q' => 'Sa'], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $data = json_decode($response->content, true);
        $this->assertCount(2, $data);
        $this->assertSame(1, $data[0]['id']);
        $this->assertSame('Sagamok Anishnawbek', $data[0]['name']);
    }

    #[Test]
    public function autocomplete_returns_empty_array_when_no_matches(): void
    {
        $this->query->method('execute')->willReturn([]);

        $this->request = HttpRequest::create('/?q=zzz');
        $controller = new CommunityController($this->entityTypeManager);
        $response = $controller->autocomplete([], ['q' => 'zzz'], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $data = json_decode($response->content, true);
        $this->assertSame([], $data);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CommunityControllerTest.php`
Expected: FAIL — class not found

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class CommunityController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function autocomplete(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        $storage = $this->entityTypeManager->getStorage('community');

        $queryBuilder = $storage->getQuery()
            ->sort('name', 'ASC')
            ->range(0, 10);

        if ($q !== '') {
            $queryBuilder->condition('name', $q . '%', 'LIKE');
        }

        $ids = $queryBuilder->execute();
        $communities = $ids !== [] ? $storage->loadMultiple($ids) : [];

        $results = [];
        foreach ($communities as $community) {
            $results[] = [
                'id' => $community->id(),
                'name' => $community->get('name'),
            ];
        }

        return new SsrResponse(
            content: json_encode($results, \JSON_THROW_ON_ERROR),
            statusCode: 200,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CommunityControllerTest.php`
Expected: 2 tests, all PASS

Note: The `condition('name', $q . '%', 'LIKE')` operator depends on the framework query builder supporting LIKE. If the mock tests pass but integration fails, check `EntityQueryInterface` for the correct operator syntax. The mock tests use `willReturnSelf()` on `condition()` so they won't catch operator issues. The integration test in Task 15 will validate this.

**Step 5: Commit**

```bash
git add src/Controller/CommunityController.php tests/Minoo/Unit/Controller/CommunityControllerTest.php
git commit -m "feat(#TBD): add community autocomplete controller"
```

---

## Task 6: Auth Service Provider

**Files:**
- Create: `src/Provider/AuthServiceProvider.php`
- Modify: `composer.json` — add provider

**Step 1: Write the provider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types — uses framework's User entity.
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'auth.login_form',
            RouteBuilder::create('/login')
                ->controller('Minoo\Controller\AuthController::loginForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.login_submit',
            RouteBuilder::create('/login')
                ->controller('Minoo\Controller\AuthController::submitLogin')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.register_form',
            RouteBuilder::create('/register')
                ->controller('Minoo\Controller\AuthController::registerForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.register_submit',
            RouteBuilder::create('/register')
                ->controller('Minoo\Controller\AuthController::submitRegister')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.logout',
            RouteBuilder::create('/logout')
                ->controller('Minoo\Controller\AuthController::logout')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}
```

**Step 2: Add provider to composer.json**

Add `"Minoo\\Provider\\AuthServiceProvider"` to `extra.waaseyaa.providers`.

**Step 3: Delete stale manifest and run tests**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All existing tests PASS

**Step 4: Commit**

```bash
git add src/Provider/AuthServiceProvider.php composer.json
git commit -m "feat(#TBD): add AuthServiceProvider with login/register/logout routes"
```

---

## Task 7: Auth Controller

**Files:**
- Create: `src/Controller/AuthController.php`
- Test: `tests/Minoo/Unit/Controller/AuthControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\AuthController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\User;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(AuthController::class)]
final class AuthControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private EntityStorageInterface $userStorage;
    private EntityQueryInterface $query;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();
        $this->query->method('sort')->willReturnSelf();
        $this->query->method('range')->willReturnSelf();

        $this->userStorage = $this->createMock(EntityStorageInterface::class);
        $this->userStorage->method('getQuery')->willReturn($this->query);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->with('user')
            ->willReturn($this->userStorage);

        $this->twig = new Environment(new ArrayLoader([
            'auth/login.html.twig' => '{{ errors.email|default("") }}',
            'auth/register.html.twig' => '{{ errors.name|default("") }}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function login_form_returns_200(): void
    {
        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->loginForm([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function register_form_returns_200(): void
    {
        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->registerForm([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function submit_login_with_empty_email_shows_error(): void
    {
        $this->request = HttpRequest::create('/login', 'POST', ['email' => '', 'password' => '']);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitLogin([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Email is required', $response->content);
    }

    #[Test]
    public function submit_login_with_unknown_email_shows_error(): void
    {
        $this->query->method('execute')->willReturn([]);
        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'nobody@example.com',
            'password' => 'secret123',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitLogin([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Invalid email or password', $response->content);
    }

    #[Test]
    public function submit_register_with_empty_fields_shows_errors(): void
    {
        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => '',
            'email' => '',
            'password' => '',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitRegister([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Name is required', $response->content);
    }

    #[Test]
    public function submit_register_with_short_password_shows_error(): void
    {
        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => 'Mary',
            'email' => 'mary@example.com',
            'password' => 'short',
        ]);
        $this->query->method('execute')->willReturn([]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitRegister([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('at least 8 characters', $response->content);
    }

    #[Test]
    public function submit_register_with_duplicate_email_shows_error(): void
    {
        $this->query->method('execute')->willReturn([1]);
        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => 'Mary',
            'email' => 'mary@example.com',
            'password' => 'password123',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitRegister([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('already registered', $response->content);
    }

    #[Test]
    public function logout_redirects_to_home(): void
    {
        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->logout([], [], $this->account, $this->request);

        $this->assertSame(302, $response->statusCode);
        $this->assertSame('/', $response->headers['Location']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/AuthControllerTest.php`
Expected: FAIL — class not found

**Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;
use Waaseyaa\User\User;

final class AuthController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function loginForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('auth/login.html.twig', [
            'errors' => [],
            'values' => [],
        ]);

        return new SsrResponse(content: $html);
    }

    public function submitLogin(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');

        $errors = [];
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if ($errors !== []) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => $errors,
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $ids = $storage->getQuery()
            ->condition('mail', $email)
            ->execute();

        if ($ids === []) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'Invalid email or password.'],
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        /** @var User|null $user */
        $user = $storage->load(reset($ids));

        if ($user === null || !$user->checkPassword($password)) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'Invalid email or password.'],
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        if (!$user->isActive()) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'This account has been deactivated.'],
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        $_SESSION['waaseyaa_uid'] = $user->id();

        $redirect = $this->dashboardRedirect($user);

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $redirect]);
    }

    public function registerForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('auth/register.html.twig', [
            'errors' => [],
            'values' => [],
        ]);

        return new SsrResponse(content: $html);
    }

    public function submitRegister(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        $phone = trim((string) $request->request->get('phone', ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($errors !== []) {
            $html = $this->twig->render('auth/register.html.twig', [
                'errors' => $errors,
                'values' => compact('name', 'email', 'phone'),
            ]);
            return new SsrResponse(content: $html);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $existing = $storage->getQuery()
            ->condition('mail', $email)
            ->execute();

        if ($existing !== []) {
            $html = $this->twig->render('auth/register.html.twig', [
                'errors' => ['email' => 'This email is already registered.'],
                'values' => compact('name', 'email', 'phone'),
            ]);
            return new SsrResponse(content: $html);
        }

        /** @var User $user */
        $user = $storage->create([
            'name' => $name,
            'mail' => $email,
            'status' => true,
            'created' => time(),
            'roles' => ['volunteer'],
            'permissions' => ['view own assignments', 'update assignment status'],
        ]);
        $user->setRawPassword($password);

        if ($phone !== '') {
            $user->set('phone', $phone);
        }

        $storage->save($user);

        $_SESSION['waaseyaa_uid'] = $user->id();

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/volunteer']);
    }

    public function logout(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/']);
    }

    private function dashboardRedirect(User $user): string
    {
        $roles = $user->getRoles();

        if (in_array('elder_coordinator', $roles, true)) {
            return '/dashboard/coordinator';
        }

        if (in_array('volunteer', $roles, true)) {
            return '/dashboard/volunteer';
        }

        return '/';
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/AuthControllerTest.php`
Expected: 8 tests, all PASS

**Step 5: Commit**

```bash
git add src/Controller/AuthController.php tests/Minoo/Unit/Controller/AuthControllerTest.php
git commit -m "feat(#TBD): add AuthController — login, register, logout"
```

---

## Task 8: Auth Templates

**Files:**
- Create: `templates/auth/login.html.twig`
- Create: `templates/auth/register.html.twig`

**Step 1: Create login template**

```twig
{% extends "base.html.twig" %}

{% block title %}Login — Minoo{% endblock %}

{% block content %}
<section class="content-section">
  <h1>Login</h1>

  <form class="form" method="post" action="/login">
    <div class="form__field">
      <label class="form__label" for="email">Email</label>
      <input
        class="form__input{% if errors.email is defined %} form__input--error{% endif %}"
        type="email" id="email" name="email"
        value="{{ values.email|default('') }}"
        required>
      {% if errors.email is defined %}
        <p class="form__error" role="alert">{{ errors.email }}</p>
      {% endif %}
    </div>

    <div class="form__field">
      <label class="form__label" for="password">Password</label>
      <input
        class="form__input{% if errors.password is defined %} form__input--error{% endif %}"
        type="password" id="password" name="password"
        required>
      {% if errors.password is defined %}
        <p class="form__error" role="alert">{{ errors.password }}</p>
      {% endif %}
    </div>

    <div class="form__actions">
      <button class="form__submit" type="submit">Login</button>
    </div>
  </form>

  <p class="form__link">Don't have an account? <a href="/register">Register as a volunteer</a></p>
</section>
{% endblock %}
```

**Step 2: Create register template**

```twig
{% extends "base.html.twig" %}

{% block title %}Register — Minoo{% endblock %}

{% block content %}
<section class="content-section">
  <h1>Volunteer Registration</h1>

  <form class="form" method="post" action="/register">
    <div class="form__field">
      <label class="form__label" for="name">Name</label>
      <input
        class="form__input{% if errors.name is defined %} form__input--error{% endif %}"
        type="text" id="name" name="name"
        value="{{ values.name|default('') }}"
        required>
      {% if errors.name is defined %}
        <p class="form__error" role="alert">{{ errors.name }}</p>
      {% endif %}
    </div>

    <div class="form__field">
      <label class="form__label" for="email">Email</label>
      <input
        class="form__input{% if errors.email is defined %} form__input--error{% endif %}"
        type="email" id="email" name="email"
        value="{{ values.email|default('') }}"
        required>
      {% if errors.email is defined %}
        <p class="form__error" role="alert">{{ errors.email }}</p>
      {% endif %}
    </div>

    <div class="form__field">
      <label class="form__label" for="password">Password</label>
      <input
        class="form__input{% if errors.password is defined %} form__input--error{% endif %}"
        type="password" id="password" name="password"
        minlength="8"
        required>
      {% if errors.password is defined %}
        <p class="form__error" role="alert">{{ errors.password }}</p>
      {% endif %}
    </div>

    <div class="form__field">
      <label class="form__label" for="phone">Phone <span class="form__label-optional">(optional)</span></label>
      <input
        class="form__input"
        type="tel" id="phone" name="phone"
        value="{{ values.phone|default('') }}">
    </div>

    <div class="form__actions">
      <button class="form__submit" type="submit">Create Account</button>
    </div>
  </form>

  <p class="form__link">Already have an account? <a href="/login">Login</a></p>
</section>
{% endblock %}
```

**Step 3: Run full tests to verify templates don't break anything**

Run: `./vendor/bin/phpunit`
Expected: All PASS

**Step 4: Commit**

```bash
git add templates/auth/login.html.twig templates/auth/register.html.twig
git commit -m "feat(#TBD): add login and register templates"
```

---

## Task 9: Elder Support Assignment Fields

**Files:**
- Modify: `src/Entity/ElderSupportRequest.php` — add assignment defaults
- Modify: `src/Provider/ElderSupportServiceProvider.php` — add assignment fields
- Modify: `tests/Minoo/Unit/Entity/ElderSupportRequestTest.php` — add assignment tests

**Step 1: Add test for assignment defaults**

Add to `ElderSupportRequestTest.php`:

```php
#[Test]
public function it_defaults_assignment_fields_to_null(): void
{
    $request = new ElderSupportRequest(['name' => 'Test', 'phone' => '555', 'type' => 'ride']);

    $this->assertNull($request->get('assigned_volunteer'));
    $this->assertNull($request->get('assigned_at'));
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Entity/ElderSupportRequestTest.php`
Expected: FAIL — `assigned_volunteer` and `assigned_at` won't be null by default (they'll likely be undefined/null already, so this might pass — if it does, the test is still valuable for documenting the contract)

**Step 3: Update ElderSupportRequest entity**

Add to `__construct()` in `src/Entity/ElderSupportRequest.php`:

```php
if (!array_key_exists('assigned_volunteer', $values)) {
    $values['assigned_volunteer'] = null;
}
if (!array_key_exists('assigned_at', $values)) {
    $values['assigned_at'] = null;
}
```

**Step 4: Update ElderSupportServiceProvider field definitions**

Add these fields to the `elder_support_request` entity type's `fieldDefinitions` array in `src/Provider/ElderSupportServiceProvider.php`:

```php
'assigned_volunteer' => [
    'type' => 'integer',
    'label' => 'Assigned Volunteer',
    'description' => 'ID of the assigned volunteer entity.',
    'weight' => 25,
],
'assigned_at' => [
    'type' => 'timestamp',
    'label' => 'Assigned At',
    'weight' => 26,
],
```

**Step 5: Run tests**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All PASS

**Step 6: Commit**

```bash
git add src/Entity/ElderSupportRequest.php src/Provider/ElderSupportServiceProvider.php tests/Minoo/Unit/Entity/ElderSupportRequestTest.php
git commit -m "feat(#TBD): add assignment fields to ElderSupportRequest"
```

---

## Task 10: Status Transition Controller

**Files:**
- Create: `src/Controller/ElderSupportWorkflowController.php`
- Test: `tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\ElderSupportWorkflowController;
use Minoo\Entity\ElderSupportRequest;
use Minoo\Entity\Volunteer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(ElderSupportWorkflowController::class)]
final class ElderSupportWorkflowControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private EntityStorageInterface $requestStorage;
    private EntityStorageInterface $volunteerStorage;
    private EntityQueryInterface $query;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();

        $this->requestStorage = $this->createMock(EntityStorageInterface::class);
        $this->requestStorage->method('getQuery')->willReturn($this->query);

        $this->volunteerStorage = $this->createMock(EntityStorageInterface::class);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->requestStorage,
                'volunteer' => $this->volunteerStorage,
                default => throw new \RuntimeException("Unexpected type: $type"),
            },
        );

        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function assign_sets_status_and_volunteer(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open']);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $volunteer = new Volunteer(['vid' => 5, 'name' => 'John']);
        $this->volunteerStorage->method('load')->with(5)->willReturn($volunteer);

        $account = $this->createCoordinatorAccount();
        $this->request = HttpRequest::create('/elders/request/1/assign', 'POST', ['volunteer_id' => '5']);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->assignVolunteer(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->statusCode);
        $this->assertSame('assigned', $entity->get('status'));
        $this->assertSame(5, $entity->get('assigned_volunteer'));
    }

    #[Test]
    public function start_transitions_assigned_to_in_progress(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'assigned', 'assigned_volunteer' => 5]);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $account = $this->createVolunteerAccount(5);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->startRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->statusCode);
        $this->assertSame('in_progress', $entity->get('status'));
    }

    #[Test]
    public function complete_transitions_in_progress_to_completed(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'in_progress', 'assigned_volunteer' => 5]);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $account = $this->createVolunteerAccount(5);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->completeRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->statusCode);
        $this->assertSame('completed', $entity->get('status'));
    }

    #[Test]
    public function confirm_transitions_completed_to_confirmed(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'completed']);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);
        $this->requestStorage->expects($this->once())->method('save');

        $account = $this->createCoordinatorAccount();

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->confirmRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(302, $response->statusCode);
        $this->assertSame('confirmed', $entity->get('status'));
    }

    #[Test]
    public function assign_returns_403_for_non_coordinator(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open']);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);

        $account = $this->createVolunteerAccount(5);
        $this->request = HttpRequest::create('/elders/request/1/assign', 'POST', ['volunteer_id' => '5']);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->assignVolunteer(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(403, $response->statusCode);
    }

    #[Test]
    public function start_returns_403_for_wrong_volunteer(): void
    {
        $entity = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'assigned', 'assigned_volunteer' => 5]);
        $this->requestStorage->method('load')->with(1)->willReturn($entity);

        $account = $this->createVolunteerAccount(99);

        $controller = new ElderSupportWorkflowController($this->entityTypeManager);
        $response = $controller->startRequest(['esrid' => '1'], [], $account, $this->request);

        $this->assertSame(403, $response->statusCode);
    }

    private function createCoordinatorAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int { return 1; }
            public function hasPermission(string $permission): bool { return true; }
            public function getRoles(): array { return ['elder_coordinator']; }
            public function isAuthenticated(): bool { return true; }
        };
    }

    private function createVolunteerAccount(int $uid): AccountInterface
    {
        return new class($uid) implements AccountInterface {
            public function __construct(private readonly int $uid) {}
            public function id(): int { return $this->uid; }
            public function hasPermission(string $permission): bool
            {
                return in_array($permission, ['view own assignments', 'update assignment status'], true);
            }
            public function getRoles(): array { return ['volunteer']; }
            public function isAuthenticated(): bool { return true; }
        };
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php`
Expected: FAIL — class not found

**Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class ElderSupportWorkflowController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function assignVolunteer(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        if (!in_array('elder_coordinator', $account->getRoles(), true) && !$account->hasPermission('administer content')) {
            return new SsrResponse(content: 'Forbidden', statusCode: 403);
        }

        $esrid = (int) ($params['esrid'] ?? 0);
        $volunteerId = (int) $request->request->get('volunteer_id', 0);

        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $esrid > 0 ? $storage->load($esrid) : null;

        if ($entity === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        $volunteerStorage = $this->entityTypeManager->getStorage('volunteer');
        $volunteer = $volunteerId > 0 ? $volunteerStorage->load($volunteerId) : null;

        if ($volunteer === null) {
            return new SsrResponse(content: 'Volunteer not found', statusCode: 404);
        }

        $entity->set('assigned_volunteer', $volunteerId);
        $entity->set('assigned_at', time());
        $entity->set('status', 'assigned');
        $entity->set('updated_at', time());
        $storage->save($entity);

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator']);
    }

    public function startRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->volunteerTransition($params, $account, 'assigned', 'in_progress');
    }

    public function completeRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->volunteerTransition($params, $account, 'in_progress', 'completed');
    }

    public function confirmRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        if ($entity->get('status') !== 'completed') {
            return new SsrResponse(content: 'Invalid status transition', statusCode: 422);
        }

        $entity->set('status', 'confirmed');
        $entity->set('updated_at', time());
        $storage->save($entity);

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator']);
    }

    public function reassignVolunteer(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        if (!in_array('elder_coordinator', $account->getRoles(), true) && !$account->hasPermission('administer content')) {
            return new SsrResponse(content: 'Forbidden', statusCode: 403);
        }

        $esrid = (int) ($params['esrid'] ?? 0);
        $volunteerId = (int) $request->request->get('volunteer_id', 0);

        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $esrid > 0 ? $storage->load($esrid) : null;

        if ($entity === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        $volunteerStorage = $this->entityTypeManager->getStorage('volunteer');
        $volunteer = $volunteerId > 0 ? $volunteerStorage->load($volunteerId) : null;

        if ($volunteer === null) {
            return new SsrResponse(content: 'Volunteer not found', statusCode: 404);
        }

        $entity->set('assigned_volunteer', $volunteerId);
        $entity->set('assigned_at', time());
        $entity->set('status', 'assigned');
        $entity->set('updated_at', time());
        $storage->save($entity);

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator']);
    }

    private function volunteerTransition(array $params, AccountInterface $account, string $fromStatus, string $toStatus): SsrResponse
    {
        $esrid = (int) ($params['esrid'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $esrid > 0 ? $storage->load($esrid) : null;

        if ($entity === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        if ($entity->get('assigned_volunteer') !== $account->id()) {
            return new SsrResponse(content: 'Forbidden', statusCode: 403);
        }

        if ($entity->get('status') !== $fromStatus) {
            return new SsrResponse(content: 'Invalid status transition', statusCode: 422);
        }

        $entity->set('status', $toStatus);
        $entity->set('updated_at', time());
        $storage->save($entity);

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/volunteer']);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php`
Expected: 6 tests, all PASS

**Step 5: Commit**

```bash
git add src/Controller/ElderSupportWorkflowController.php tests/Minoo/Unit/Controller/ElderSupportWorkflowControllerTest.php
git commit -m "feat(#TBD): add ElderSupportWorkflowController with status transitions"
```

---

## Task 11: Workflow Routes in ElderSupportServiceProvider

**Files:**
- Modify: `src/Provider/ElderSupportServiceProvider.php` — add workflow routes

**Step 1: Add routes to `routes()` method**

Add these routes after the existing routes in `ElderSupportServiceProvider::routes()`:

```php
$router->addRoute(
    'elder.assign',
    RouteBuilder::create('/elders/request/{esrid}/assign')
        ->controller('Minoo\Controller\ElderSupportWorkflowController::assignVolunteer')
        ->requireRole('elder_coordinator')
        ->methods('POST')
        ->build(),
);

$router->addRoute(
    'elder.start',
    RouteBuilder::create('/elders/request/{esrid}/start')
        ->controller('Minoo\Controller\ElderSupportWorkflowController::startRequest')
        ->requireRole('volunteer')
        ->methods('POST')
        ->build(),
);

$router->addRoute(
    'elder.complete',
    RouteBuilder::create('/elders/request/{esrid}/complete')
        ->controller('Minoo\Controller\ElderSupportWorkflowController::completeRequest')
        ->requireRole('volunteer')
        ->methods('POST')
        ->build(),
);

$router->addRoute(
    'elder.confirm',
    RouteBuilder::create('/elders/request/{esrid}/confirm')
        ->controller('Minoo\Controller\ElderSupportWorkflowController::confirmRequest')
        ->requireRole('elder_coordinator')
        ->methods('POST')
        ->build(),
);

$router->addRoute(
    'elder.reassign',
    RouteBuilder::create('/elders/request/{esrid}/reassign')
        ->controller('Minoo\Controller\ElderSupportWorkflowController::reassignVolunteer')
        ->requireRole('elder_coordinator')
        ->methods('POST')
        ->build(),
);
```

**Step 2: Run tests**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All PASS

**Step 3: Commit**

```bash
git add src/Provider/ElderSupportServiceProvider.php
git commit -m "feat(#TBD): add workflow routes to ElderSupportServiceProvider"
```

---

## Task 12: Dashboard Service Provider

**Files:**
- Create: `src/Provider/DashboardServiceProvider.php`
- Modify: `composer.json` — add provider

**Step 1: Write the provider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types.
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'dashboard.volunteer',
            RouteBuilder::create('/dashboard/volunteer')
                ->controller('Minoo\Controller\VolunteerDashboardController::index')
                ->requireRole('volunteer')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator',
            RouteBuilder::create('/dashboard/coordinator')
                ->controller('Minoo\Controller\CoordinatorDashboardController::index')
                ->requireRole('elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}
```

**Step 2: Add provider to composer.json**

Add `"Minoo\\Provider\\DashboardServiceProvider"` to `extra.waaseyaa.providers`.

**Step 3: Delete stale manifest and run tests**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All PASS

**Step 4: Commit**

```bash
git add src/Provider/DashboardServiceProvider.php composer.json
git commit -m "feat(#TBD): add DashboardServiceProvider with volunteer and coordinator routes"
```

---

## Task 13: Volunteer Dashboard Controller

**Files:**
- Create: `src/Controller/VolunteerDashboardController.php`
- Test: `tests/Minoo/Unit/Controller/VolunteerDashboardControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\VolunteerDashboardController;
use Minoo\Entity\ElderSupportRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(VolunteerDashboardController::class)]
final class VolunteerDashboardControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private EntityStorageInterface $storage;
    private EntityQueryInterface $query;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();
        $this->query->method('sort')->willReturnSelf();

        $this->storage = $this->createMock(EntityStorageInterface::class);
        $this->storage->method('getQuery')->willReturn($this->query);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->with('elder_support_request')
            ->willReturn($this->storage);

        $this->twig = new Environment(new ArrayLoader([
            'dashboard/volunteer.html.twig' => '{% for r in requests %}|{{ r.get("name") }}{% endfor %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->account->method('id')->willReturn(5);
        $this->account->method('getRoles')->willReturn(['volunteer']);

        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function index_returns_200_with_assigned_requests(): void
    {
        $req1 = new ElderSupportRequest(['esrid' => 1, 'name' => 'Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'assigned', 'assigned_volunteer' => 5]);

        $this->query->method('execute')->willReturn([1]);
        $this->storage->method('loadMultiple')
            ->with([1])
            ->willReturn([1 => $req1]);

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Mary', $response->content);
    }

    #[Test]
    public function index_returns_200_when_no_assignments(): void
    {
        $this->query->method('execute')->willReturn([]);

        $controller = new VolunteerDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/VolunteerDashboardControllerTest.php`
Expected: FAIL — class not found

**Step 3: Write implementation**

Note on volunteer-to-user linkage: The `assigned_volunteer` field on `ElderSupportRequest` stores a **volunteer entity ID** (vid), not a user ID (uid). For v0.6, coordinators assign volunteer entities directly. The dashboard queries requests by `assigned_volunteer` matching the volunteer entity that corresponds to the logged-in user. For the MVP, we'll query by user ID — this means we need to decide: does `assigned_volunteer` store a `vid` or a `uid`?

**Decision:** Store `uid` (user ID) in `assigned_volunteer`. This simplifies dashboard queries — the logged-in user's `$account->id()` directly matches. Volunteer entities remain for the public signup data, but assignment links to user accounts.

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class VolunteerDashboardController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('elder_support_request');

        $ids = $storage->getQuery()
            ->condition('assigned_volunteer', $account->id())
            ->sort('updated_at', 'DESC')
            ->execute();

        $requests = $ids !== [] ? $storage->loadMultiple($ids) : [];

        $grouped = ['assigned' => [], 'in_progress' => [], 'completed' => [], 'confirmed' => []];
        foreach ($requests as $req) {
            $status = $req->get('status');
            if (isset($grouped[$status])) {
                $grouped[$status][] = $req;
            }
        }

        $html = $this->twig->render('dashboard/volunteer.html.twig', [
            'requests' => $requests,
            'grouped' => $grouped,
        ]);

        return new SsrResponse(content: $html);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/VolunteerDashboardControllerTest.php`
Expected: 2 tests, all PASS

**Step 5: Commit**

```bash
git add src/Controller/VolunteerDashboardController.php tests/Minoo/Unit/Controller/VolunteerDashboardControllerTest.php
git commit -m "feat(#TBD): add VolunteerDashboardController"
```

---

## Task 14: Coordinator Dashboard Controller

**Files:**
- Create: `src/Controller/CoordinatorDashboardController.php`
- Test: `tests/Minoo/Unit/Controller/CoordinatorDashboardControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\CoordinatorDashboardController;
use Minoo\Entity\ElderSupportRequest;
use Minoo\Entity\Volunteer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(CoordinatorDashboardController::class)]
final class CoordinatorDashboardControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private EntityStorageInterface $requestStorage;
    private EntityStorageInterface $volunteerStorage;
    private EntityQueryInterface $requestQuery;
    private EntityQueryInterface $volunteerQuery;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->requestQuery = $this->createMock(EntityQueryInterface::class);
        $this->requestQuery->method('condition')->willReturnSelf();
        $this->requestQuery->method('sort')->willReturnSelf();

        $this->volunteerQuery = $this->createMock(EntityQueryInterface::class);
        $this->volunteerQuery->method('condition')->willReturnSelf();
        $this->volunteerQuery->method('sort')->willReturnSelf();

        $this->requestStorage = $this->createMock(EntityStorageInterface::class);
        $this->requestStorage->method('getQuery')->willReturn($this->requestQuery);

        $this->volunteerStorage = $this->createMock(EntityStorageInterface::class);
        $this->volunteerStorage->method('getQuery')->willReturn($this->volunteerQuery);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnCallback(
            fn (string $type) => match ($type) {
                'elder_support_request' => $this->requestStorage,
                'volunteer' => $this->volunteerStorage,
                default => throw new \RuntimeException("Unexpected: $type"),
            },
        );

        $this->twig = new Environment(new ArrayLoader([
            'dashboard/coordinator.html.twig' => '{% for r in open_requests %}|{{ r.get("name") }}{% endfor %}{% for v in volunteers %}|{{ v.get("name") }}{% endfor %}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->account->method('getRoles')->willReturn(['elder_coordinator']);

        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function index_returns_200_with_requests_and_volunteers(): void
    {
        $req = new ElderSupportRequest(['esrid' => 1, 'name' => 'Elder Mary', 'phone' => '555', 'type' => 'ride', 'status' => 'open']);
        $vol = new Volunteer(['vid' => 1, 'name' => 'John Helper', 'status' => 'active']);

        $this->requestQuery->method('execute')->willReturn([1]);
        $this->requestStorage->method('loadMultiple')
            ->with([1])
            ->willReturn([1 => $req]);

        $this->volunteerQuery->method('execute')->willReturn([1]);
        $this->volunteerStorage->method('loadMultiple')
            ->with([1])
            ->willReturn([1 => $vol]);

        $controller = new CoordinatorDashboardController($this->entityTypeManager, $this->twig);
        $response = $controller->index([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Elder Mary', $response->content);
        $this->assertStringContainsString('John Helper', $response->content);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CoordinatorDashboardControllerTest.php`
Expected: FAIL — class not found

**Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class CoordinatorDashboardController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $requestStorage = $this->entityTypeManager->getStorage('elder_support_request');

        $allIds = $requestStorage->getQuery()
            ->sort('created_at', 'DESC')
            ->execute();

        $allRequests = $allIds !== [] ? $requestStorage->loadMultiple($allIds) : [];

        $open = [];
        $assigned = [];
        $pendingConfirmation = [];
        $confirmed = [];

        foreach ($allRequests as $req) {
            match ($req->get('status')) {
                'open' => $open[] = $req,
                'assigned', 'in_progress' => $assigned[] = $req,
                'completed' => $pendingConfirmation[] = $req,
                'confirmed' => $confirmed[] = $req,
                default => null,
            };
        }

        $volunteerStorage = $this->entityTypeManager->getStorage('volunteer');
        $volunteerIds = $volunteerStorage->getQuery()
            ->condition('status', 'active')
            ->sort('name', 'ASC')
            ->execute();

        $volunteers = $volunteerIds !== [] ? $volunteerStorage->loadMultiple($volunteerIds) : [];

        $html = $this->twig->render('dashboard/coordinator.html.twig', [
            'open_requests' => $open,
            'assigned_requests' => $assigned,
            'pending_confirmation' => $pendingConfirmation,
            'confirmed_requests' => $confirmed,
            'volunteers' => $volunteers,
        ]);

        return new SsrResponse(content: $html);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/CoordinatorDashboardControllerTest.php`
Expected: 1 test, PASS

**Step 5: Commit**

```bash
git add src/Controller/CoordinatorDashboardController.php tests/Minoo/Unit/Controller/CoordinatorDashboardControllerTest.php
git commit -m "feat(#TBD): add CoordinatorDashboardController"
```

---

## Task 15: Dashboard Templates

**Files:**
- Create: `templates/dashboard/volunteer.html.twig`
- Create: `templates/dashboard/coordinator.html.twig`

**Step 1: Create volunteer dashboard template**

```twig
{% extends "base.html.twig" %}

{% block title %}My Dashboard — Minoo{% endblock %}

{% block content %}
<section class="content-section">
  <h1>My Assignments</h1>

  {% if requests is empty %}
    <p class="dashboard__empty">No assignments yet. A coordinator will assign requests to you.</p>
  {% else %}

    {% if grouped.assigned is not empty %}
    <h2>Assigned to You</h2>
    <div class="dashboard__cards">
      {% for req in grouped.assigned %}
      <article class="card card--dashboard">
        <span class="card__badge card__badge--assigned">Assigned</span>
        <h3 class="card__title">{{ req.get('name') }}</h3>
        <dl class="card__meta">
          <dt>Type</dt><dd>{{ req.get('type') }}</dd>
          {% if req.get('community') %}<dt>Community</dt><dd>{{ req.get('community') }}</dd>{% endif %}
        </dl>
        <form method="post" action="/elders/request/{{ req.id() }}/start">
          <button class="form__submit" type="submit">Mark In Progress</button>
        </form>
      </article>
      {% endfor %}
    </div>
    {% endif %}

    {% if grouped.in_progress is not empty %}
    <h2>In Progress</h2>
    <div class="dashboard__cards">
      {% for req in grouped.in_progress %}
      <article class="card card--dashboard">
        <span class="card__badge card__badge--in-progress">In Progress</span>
        <h3 class="card__title">{{ req.get('name') }}</h3>
        <dl class="card__meta">
          <dt>Type</dt><dd>{{ req.get('type') }}</dd>
          {% if req.get('community') %}<dt>Community</dt><dd>{{ req.get('community') }}</dd>{% endif %}
        </dl>
        <form method="post" action="/elders/request/{{ req.id() }}/complete">
          <button class="form__submit" type="submit">Mark Complete</button>
        </form>
      </article>
      {% endfor %}
    </div>
    {% endif %}

    {% if grouped.completed is not empty %}
    <h2>Completed (Awaiting Confirmation)</h2>
    <div class="dashboard__cards">
      {% for req in grouped.completed %}
      <article class="card card--dashboard">
        <span class="card__badge card__badge--completed">Completed</span>
        <h3 class="card__title">{{ req.get('name') }}</h3>
        <dl class="card__meta">
          <dt>Type</dt><dd>{{ req.get('type') }}</dd>
        </dl>
        <p class="card__note">Waiting for coordinator confirmation.</p>
      </article>
      {% endfor %}
    </div>
    {% endif %}

  {% endif %}
</section>
{% endblock %}
```

**Step 2: Create coordinator dashboard template**

```twig
{% extends "base.html.twig" %}

{% block title %}Coordinator Dashboard — Minoo{% endblock %}

{% block content %}
<section class="content-section">
  <h1>Elder Support Dashboard</h1>

  <h2>Open Requests ({{ open_requests|length }})</h2>
  {% if open_requests is empty %}
    <p class="dashboard__empty">No open requests.</p>
  {% else %}
  <div class="dashboard__cards">
    {% for req in open_requests %}
    <article class="card card--dashboard">
      <span class="card__badge card__badge--open">Open</span>
      <h3 class="card__title">{{ req.get('name') }}</h3>
      <dl class="card__meta">
        <dt>Type</dt><dd>{{ req.get('type') }}</dd>
        <dt>Phone</dt><dd>{{ req.get('phone') }}</dd>
        {% if req.get('community') %}<dt>Community</dt><dd>{{ req.get('community') }}</dd>{% endif %}
        {% if req.get('notes') %}<dt>Notes</dt><dd>{{ req.get('notes') }}</dd>{% endif %}
      </dl>
      <form class="dashboard__assign-form" method="post" action="/elders/request/{{ req.id() }}/assign">
        <label class="form__label" for="volunteer-{{ req.id() }}">Assign to</label>
        <select class="form__select" name="volunteer_id" id="volunteer-{{ req.id() }}" required>
          <option value="">Select volunteer...</option>
          {% for vol in volunteers %}
          <option value="{{ vol.id() }}">{{ vol.get('name') }}{% if vol.get('community') %} ({{ vol.get('community') }}){% endif %}</option>
          {% endfor %}
        </select>
        <button class="form__submit" type="submit">Assign</button>
      </form>
    </article>
    {% endfor %}
  </div>
  {% endif %}

  <h2>Assigned / In Progress ({{ assigned_requests|length }})</h2>
  {% if assigned_requests is empty %}
    <p class="dashboard__empty">No assigned requests.</p>
  {% else %}
  <div class="dashboard__cards">
    {% for req in assigned_requests %}
    <article class="card card--dashboard">
      <span class="card__badge card__badge--{{ req.get('status') }}">{{ req.get('status')|replace({'_': ' '})|title }}</span>
      <h3 class="card__title">{{ req.get('name') }}</h3>
      <dl class="card__meta">
        <dt>Type</dt><dd>{{ req.get('type') }}</dd>
        {% if req.get('community') %}<dt>Community</dt><dd>{{ req.get('community') }}</dd>{% endif %}
      </dl>
    </article>
    {% endfor %}
  </div>
  {% endif %}

  <h2>Pending Confirmation ({{ pending_confirmation|length }})</h2>
  {% if pending_confirmation is empty %}
    <p class="dashboard__empty">Nothing to confirm.</p>
  {% else %}
  <div class="dashboard__cards">
    {% for req in pending_confirmation %}
    <article class="card card--dashboard">
      <span class="card__badge card__badge--completed">Completed</span>
      <h3 class="card__title">{{ req.get('name') }}</h3>
      <dl class="card__meta">
        <dt>Type</dt><dd>{{ req.get('type') }}</dd>
      </dl>
      <form method="post" action="/elders/request/{{ req.id() }}/confirm">
        <button class="form__submit" type="submit">Confirm Completion</button>
      </form>
    </article>
    {% endfor %}
  </div>
  {% endif %}

  <h2>Volunteer Pool ({{ volunteers|length }})</h2>
  {% if volunteers is empty %}
    <p class="dashboard__empty">No active volunteers.</p>
  {% else %}
  <div class="dashboard__cards">
    {% for vol in volunteers %}
    <article class="card card--dashboard">
      <h3 class="card__title">{{ vol.get('name') }}</h3>
      <dl class="card__meta">
        <dt>Phone</dt><dd>{{ vol.get('phone') }}</dd>
        {% if vol.get('community') %}<dt>Community</dt><dd>{{ vol.get('community') }}</dd>{% endif %}
        {% if vol.get('availability') %}<dt>Availability</dt><dd>{{ vol.get('availability') }}</dd>{% endif %}
      </dl>
    </article>
    {% endfor %}
  </div>
  {% endif %}
</section>
{% endblock %}
```

**Step 3: Run tests**

Run: `./vendor/bin/phpunit`
Expected: All PASS

**Step 4: Commit**

```bash
git add templates/dashboard/volunteer.html.twig templates/dashboard/coordinator.html.twig
git commit -m "feat(#TBD): add volunteer and coordinator dashboard templates"
```

---

## Task 16: Navigation and CSS Updates

**Files:**
- Modify: `templates/base.html.twig` — add auth-aware nav
- Modify: `public/css/minoo.css` — add dashboard component styles

**Step 1: Update base.html.twig nav**

The nav section in `base.html.twig` (around lines 18-23) currently has a static list. Replace the nav items list with auth-aware items. The `account` variable needs to be available in templates. Check how the framework passes the account to Twig — look for Twig globals or template variables set by the render pipeline.

**Important:** The framework's SSR render pipeline may or may not pass `account` to Twig. If it doesn't, this may require a framework-level change to add `account` as a Twig global. Check `HttpKernel` or the render controller for how Twig context is built. If `account` is not available, an alternative is to pass `is_authenticated`, `user_roles`, and `user_name` from each controller that renders a template.

For the MVP approach, add account info to the template context via a shared pattern. Each controller already receives `$account` — pass it through to Twig:

After the existing nav items, add conditional auth links:

```twig
{% if account is defined and account.isAuthenticated() %}
  {% if 'elder_coordinator' in account.getRoles() %}
    <li><a href="/dashboard/coordinator"{% if path is defined and path starts with '/dashboard' %} aria-current="page"{% endif %}>Dashboard</a></li>
  {% elseif 'volunteer' in account.getRoles() %}
    <li><a href="/dashboard/volunteer"{% if path is defined and path starts with '/dashboard' %} aria-current="page"{% endif %}>My Dashboard</a></li>
  {% endif %}
  <li><a href="/logout">Logout</a></li>
{% else %}
  <li><a href="/login"{% if path is defined and path == '/login' %} aria-current="page"{% endif %}>Login</a></li>
{% endif %}
```

**Note:** Getting `account` into all Twig renders is a cross-cutting concern. Options:
1. Add `account` to every controller's render context (tedious but works)
2. Add a Twig global in the kernel (framework change)
3. Add a Twig extension that resolves the current account

For v0.6 MVP, option 1 is simplest: add `'account' => $account` to every controller that renders templates via `base.html.twig`. This includes: AuthController (loginForm, registerForm), VolunteerDashboardController, CoordinatorDashboardController, PeopleController, ElderSupportController, VolunteerController, and the framework's RenderController for path-based templates.

**The cleanest approach is a framework Twig global.** This requires adding `$twig->addGlobal('account', $account)` in the HttpKernel render pipeline. This is a small framework change. If not feasible, use option 1 for now.

**Step 2: Add dashboard CSS to minoo.css**

Add in `@layer components` section, after the existing form styles:

```css
  /* -- Dashboards ---------------------------------------- */
  .dashboard__cards {
    display: grid;
    gap: var(--space-sm);
    grid-template-columns: repeat(auto-fill, minmax(min(100%, var(--width-card)), 1fr));
    margin-block-end: var(--space-lg);
  }

  .dashboard__empty {
    color: var(--text-secondary);
    padding-block: var(--space-sm);
  }

  .dashboard__assign-form {
    display: flex;
    gap: var(--space-2xs);
    align-items: end;
    margin-block-start: var(--space-2xs);
  }

  .card--dashboard {
    container-type: inline-size;
    background: var(--surface-raised);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: var(--space-sm);
    box-shadow: var(--shadow-sm);
    display: grid;
    gap: var(--space-2xs);
  }

  .card__badge--open {
    background-color: var(--color-sun-500);
    color: var(--color-earth-900);
  }

  .card__badge--assigned {
    background-color: var(--color-water-600);
    color: white;
  }

  .card__badge--in-progress {
    background-color: var(--accent);
    color: white;
  }

  .card__badge--completed {
    background-color: var(--color-forest-700);
    color: white;
  }

  .card__badge--confirmed {
    background-color: var(--color-earth-700);
    color: white;
  }

  .card__note {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    font-style: italic;
  }

  .form__link {
    margin-block-start: var(--space-sm);
    font-size: var(--text-sm);
  }
```

**Step 3: Run tests**

Run: `./vendor/bin/phpunit`
Expected: All PASS

**Step 4: Commit**

```bash
git add templates/base.html.twig public/css/minoo.css
git commit -m "feat(#TBD): add auth-aware nav and dashboard CSS components"
```

---

## Task 17: Integration Test Updates

**Files:**
- Modify: `tests/Minoo/Integration/BootTest.php` — add community entity type check

**Step 1: Update BootTest to include new entity types**

In `kernel_boots_with_all_minoo_entity_types()`, add `'community'` to the `$minooTypes` array.

The current count is 13 types + `resource_person` + `elder_support_request` + `volunteer` = 16 types. Adding `community` = 17.

Update the `$minooTypes` array:

```php
$minooTypes = [
    'event', 'event_type',
    'group', 'group_type',
    'cultural_group',
    'teaching', 'teaching_type',
    'cultural_collection',
    'dictionary_entry', 'example_sentence', 'word_part', 'speaker',
    'ingest_log',
    'resource_person',
    'elder_support_request', 'volunteer',
    'community',
];
```

**Step 2: Run integration tests**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit --testsuite MinooIntegration`
Expected: All PASS, community entity type registered

**Step 3: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: All PASS

**Step 4: Commit**

```bash
git add tests/Minoo/Integration/BootTest.php
git commit -m "test(#TBD): add community to integration test entity type list"
```

---

## Task 18: Final Verification

**Step 1: Delete stale manifest**

Run: `rm -f storage/framework/packages.php`

**Step 2: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: All tests PASS (previous count was 138 tests, 356 assertions; new count should be approximately 165+ tests)

**Step 3: Start dev server and smoke test**

Run: `php -S localhost:8081 -t public`

Manual checks:
- `GET /login` — login form renders
- `GET /register` — registration form renders
- `POST /register` with valid data — creates account, redirects to `/dashboard/volunteer`
- `GET /dashboard/volunteer` — empty dashboard renders
- `GET /elders/request` — elder support form still works
- `GET /api/communities/autocomplete?q=Sag` — returns JSON

**Step 4: Commit any fixes from smoke testing**

---

## Summary

| Task | Component | New files | Tests |
|------|-----------|-----------|-------|
| 1 | Community entity | 1 entity, 1 test | 3 |
| 2 | Community seeder | 1 seeder, 1 test | 4 |
| 3 | Community access policy | 1 policy, 1 test | 6 |
| 4 | Community provider | 1 provider | 0 |
| 5 | Community autocomplete | 1 controller, 1 test | 2 |
| 6 | Auth provider | 1 provider | 0 |
| 7 | Auth controller | 1 controller, 1 test | 8 |
| 8 | Auth templates | 2 templates | 0 |
| 9 | Assignment fields | 0 (modify) | 1 |
| 10 | Workflow controller | 1 controller, 1 test | 6 |
| 11 | Workflow routes | 0 (modify) | 0 |
| 12 | Dashboard provider | 1 provider | 0 |
| 13 | Volunteer dashboard | 1 controller, 1 test | 2 |
| 14 | Coordinator dashboard | 1 controller, 1 test | 1 |
| 15 | Dashboard templates | 2 templates | 0 |
| 16 | Nav + CSS | 0 (modify) | 0 |
| 17 | Integration tests | 0 (modify) | 0 |
| 18 | Final verification | 0 | 0 |

**Totals:** ~13 new files, ~7 modified files, ~33 new tests

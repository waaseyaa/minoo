# App Route Registration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add app-level route registration to the Waaseyaa framework, then use it to wire Minoo's `/people` page to real entity storage via a `PeopleController`.

**Architecture:** Add `routes(WaaseyaaRouter $router)` to the service provider lifecycle. HttpKernel calls provider routes after framework routes but before the SSR catchall. A new `dispatchAppController()` method handles `Class::method` controllers. Minoo's `PeopleController` becomes the first SSR listing controller.

**Tech Stack:** PHP 8.3, PHPUnit 10.5, Twig 3, Symfony Routing, SQLite

---

## Phase 1: Framework — App Route Registration (waaseyaa repo)

Work in `/home/fsd42/dev/waaseyaa`. Branch from main.

### Task 1: Add `routes()` to ServiceProviderInterface

**Files:**
- Modify: `packages/foundation/src/ServiceProvider/ServiceProviderInterface.php`
- Modify: `packages/foundation/src/ServiceProvider/ServiceProvider.php`
- Modify: `packages/foundation/tests/Unit/ServiceProvider/ServiceProviderTest.php`

**Step 1: Write the failing test**

Add to `ServiceProviderTest.php`:

```php
#[Test]
public function routes_is_callable_by_default(): void
{
    $provider = new class extends ServiceProvider {
        public function register(): void {}
    };

    $router = new \Waaseyaa\Routing\WaaseyaaRouter();
    $provider->routes($router);

    // Default no-op should not add any routes
    $this->assertSame(0, $router->getRouteCollection()->count());
}
```

Add the import at the top of the test file if not present:

```php
use Waaseyaa\Routing\WaaseyaaRouter;
```

**Step 2: Run test to verify it fails**

```bash
cd /home/fsd42/dev/waaseyaa
./vendor/bin/phpunit packages/foundation/tests/Unit/ServiceProvider/ServiceProviderTest.php --filter routes_is_callable_by_default
```

Expected: FAIL — `routes()` method does not exist on `ServiceProvider`.

**Step 3: Add `routes()` to the interface and base class**

In `packages/foundation/src/ServiceProvider/ServiceProviderInterface.php`, add after `public function boot(): void;`:

```php
public function routes(\Waaseyaa\Routing\WaaseyaaRouter $router): void;
```

In `packages/foundation/src/ServiceProvider/ServiceProvider.php`, add after the `boot()` method:

```php
public function routes(\Waaseyaa\Routing\WaaseyaaRouter $router): void {}
```

**Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit packages/foundation/tests/Unit/ServiceProvider/ServiceProviderTest.php --filter routes_is_callable_by_default
```

Expected: PASS

**Step 5: Run full framework test suite**

```bash
./vendor/bin/phpunit --configuration phpunit.xml.dist
```

Expected: All tests pass.

**Step 6: Commit**

```bash
git add packages/foundation/src/ServiceProvider/ServiceProviderInterface.php packages/foundation/src/ServiceProvider/ServiceProvider.php packages/foundation/tests/Unit/ServiceProvider/ServiceProviderTest.php
git commit -m "feat: add routes() to ServiceProvider lifecycle"
```

---

### Task 2: Call provider routes in HttpKernel::registerRoutes()

**Files:**
- Modify: `packages/foundation/src/Kernel/HttpKernel.php`

**Step 1: Add provider route registration before the SSR catchall**

In `HttpKernel::registerRoutes()`, find the block that registers `public.home` (around line 395). Insert BEFORE it:

```php
// App routes — registered before SSR catchall so they take priority.
foreach ($this->providers as $provider) {
    $provider->routes($router);
}
```

So the end of `registerRoutes()` becomes:

```php
        // ... (mcp.endpoint route above)

        // App routes — registered before SSR catchall so they take priority.
        foreach ($this->providers as $provider) {
            $provider->routes($router);
        }

        $router->addRoute(
            'public.home',
            // ... existing code
        );

        $router->addRoute(
            'public.page',
            // ... existing code
        );
    }
```

**Step 2: Run framework tests**

```bash
./vendor/bin/phpunit --configuration phpunit.xml.dist
```

Expected: All tests pass (no providers register routes yet, so behavior is unchanged).

**Step 3: Commit**

```bash
git add packages/foundation/src/Kernel/HttpKernel.php
git commit -m "feat: call provider routes() before SSR catchall in HttpKernel"
```

---

### Task 3: Add app controller dispatch

**Files:**
- Modify: `packages/foundation/src/Kernel/HttpKernel.php`

**Step 1: Add the `dispatchAppController()` method**

Add this private method to `HttpKernel`, after `handleRenderPage()`:

```php
/**
 * Dispatch an app-level controller registered via ServiceProvider::routes().
 *
 * Controllers use Class::method format. The class receives EntityTypeManager
 * and Twig Environment via constructor; the method receives route params,
 * query params, and account.
 */
private function dispatchAppController(
    string $controller,
    array $params,
    array $query,
    AccountInterface $account,
    HttpRequest $httpRequest,
): never {
    [$class, $method] = explode('::', $controller, 2);

    $twig = SsrServiceProvider::getTwigEnvironment();
    if ($twig === null) {
        $twig = SsrServiceProvider::createTwigEnvironment($this->projectRoot, $this->config);
    }

    $instance = new $class($this->entityTypeManager, $twig);
    $response = $instance->{$method}($params, $query, $account);

    $cacheMaxAge = $this->resolveRenderCacheMaxAge();
    $headers = $response->headers;
    $headers['Cache-Control'] = $this->cacheControlHeaderForRender($account, $cacheMaxAge);
    $this->sendHtml($response->statusCode, $response->content, $headers);
}
```

**Step 2: Wire it into the dispatch match block**

In the `dispatch()` method, find the `default` arm of the `match(true)` block (around line 873). Add a new arm BEFORE `default`:

```php
str_contains($controller, '::') => $this->dispatchAppController(
    $controller, $params, $query, $account, $httpRequest
),
```

The end of the match block should look like:

```php
                // ... (render.page arm above)

                str_contains($controller, '::') => $this->dispatchAppController(
                    $controller, $params, $query, $account, $httpRequest
                ),

                default => (function () use ($controller): never {
                    error_log(sprintf('[Waaseyaa] Unknown controller: %s', $controller));
                    // ...
                })(),
            };
```

**Step 3: Run framework tests**

```bash
./vendor/bin/phpunit --configuration phpunit.xml.dist
```

Expected: All tests pass (no controllers using `::` format are registered yet).

**Step 4: Commit**

```bash
git add packages/foundation/src/Kernel/HttpKernel.php
git commit -m "feat: add dispatchAppController for Class::method controllers"
```

---

### Task 4: Push framework changes and create PR

**Step 1: Push the branch**

```bash
git push -u origin feat/app-route-registration
```

**Step 2: Create PR**

```bash
gh pr create --title "feat: app-level route registration via ServiceProvider::routes()" --body "$(cat <<'EOF'
## Summary

- Add `routes(WaaseyaaRouter $router)` to `ServiceProviderInterface` and `ServiceProvider`
- Call provider routes in `HttpKernel::registerRoutes()` before SSR catchall
- Add `dispatchAppController()` for `Class::method` controller dispatch

This enables Minoo (and any Waaseyaa app) to register SSR controllers
via service providers, unlocking entity-backed listing pages.

## Test plan

- [x] ServiceProvider routes() test
- [x] Full framework test suite passes
- [x] No behavioral change for existing routes (app routes are additive)

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

**Step 3: Merge the PR**

```bash
gh pr merge --squash --delete-branch
```

---

## Phase 2: Minoo — PeopleController + Entity-Backed /people

Work in `/home/fsd42/dev/minoo`. Branch from main.

**Prerequisite:** Framework PR from Phase 1 must be merged. Run `composer update` if needed to pick up new `ServiceProvider::routes()`.

### Task 5: Create PeopleController

**Files:**
- Create: `src/Controller/PeopleController.php`
- Create: `tests/Minoo/Unit/Controller/PeopleControllerTest.php`

**Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\PeopleController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(PeopleController::class)]
final class PeopleControllerTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $entityTypeManager = $this->createMock(EntityTypeManager::class);
        $twig = new Environment(new ArrayLoader([]));

        $controller = new PeopleController($entityTypeManager, $twig);

        $this->assertInstanceOf(PeopleController::class, $controller);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/PeopleControllerTest.php
```

Expected: Error — class `PeopleController` not found.

**Step 3: Write the controller**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class PeopleController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $queryBuilder = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC');

        $ids = $queryBuilder->execute();
        $people = $ids !== [] ? $storage->loadMultiple($ids) : [];

        $html = $this->twig->render('people.html.twig', [
            'path' => '/people',
            'people' => array_values($people),
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();

        $person = $ids !== [] ? $storage->load(reset($ids)) : null;

        $html = $this->twig->render('people.html.twig', [
            'path' => '/people/' . $slug,
            'person' => $person,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $person !== null ? 200 : 404,
        );
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Controller/PeopleControllerTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Controller/PeopleController.php tests/Minoo/Unit/Controller/PeopleControllerTest.php
git commit -m "feat(#53): add PeopleController with list and show actions"
```

---

### Task 6: Register routes in PeopleServiceProvider

**Files:**
- Modify: `src/Provider/PeopleServiceProvider.php`

**Step 1: Add the routes() method**

Add the `routes()` method and required imports to `PeopleServiceProvider.php`:

```php
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
```

Add after the `register()` method:

```php
public function routes(WaaseyaaRouter $router): void
{
    $router->addRoute(
        'people.list',
        RouteBuilder::create('/people')
            ->controller('Minoo\Controller\PeopleController::list')
            ->allowAll()
            ->render()
            ->methods('GET')
            ->build(),
    );

    $router->addRoute(
        'people.show',
        RouteBuilder::create('/people/{slug}')
            ->controller('Minoo\Controller\PeopleController::show')
            ->allowAll()
            ->render()
            ->methods('GET')
            ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
            ->build(),
    );
}
```

**Step 2: Run full test suite**

```bash
rm -f storage/framework/packages.php && ./vendor/bin/phpunit
```

Expected: All tests pass.

**Step 3: Commit**

```bash
git add src/Provider/PeopleServiceProvider.php
git commit -m "feat(#53): register /people routes in PeopleServiceProvider"
```

---

### Task 7: Update templates for entity data

**Files:**
- Modify: `templates/people.html.twig`
- Modify: `templates/components/resource-person-card.html.twig`

**Step 1: Rewrite `people.html.twig` to use entity data**

Replace the entire content block. The template now receives:
- `people` (array of ResourcePerson entities) on listing
- `person` (single entity or null) on detail

```twig
{% extends "base.html.twig" %}

{% block title %}
  {%- if path == '/people' -%}
    People — Minoo
  {%- elseif person is defined and person -%}
    {{ person.get('name') }} — Minoo
  {%- else -%}
    Person Not Found — Minoo
  {%- endif -%}
{% endblock %}

{% block content %}
  {% if path == '/people' %}
    <div class="flow-lg">
      <h1>People</h1>
      <p>Community members, knowledge keepers, and service providers.</p>

      {% if people is defined and people|length > 0 %}
        <div class="card-grid">
          {% for p in people %}
            {% include "components/resource-person-card.html.twig" with {
              name: p.get('name'),
              role: p.get('roles') is iterable ? p.get('roles')|first : '',
              community: p.get('community'),
              offerings: p.get('offerings') is iterable ? p.get('offerings') : [],
              excerpt: p.get('bio') ? p.get('bio')|striptags|slice(0, 160) ~ '…' : '',
              url: "/people/" ~ p.get('slug')
            } %}
          {% endfor %}
        </div>
      {% else %}
        <p>No resource people found.</p>
      {% endif %}
    </div>
  {% elseif person is defined and person %}
    <div class="flow-lg detail">
      <a href="/people" class="detail__back">People</a>
      <div class="detail__header">
        {% if person.get('roles') is iterable %}
          {% for r in person.get('roles') %}
            <span class="card__badge card__badge--person">{{ r }}</span>
          {% endfor %}
        {% endif %}
        <h1>{{ person.get('name') }}</h1>
        <div class="detail__meta">
          {% if person.get('community') %}
            <span>{{ person.get('community') }}</span>
          {% endif %}
          {% if person.get('business_name') %}
            <span>{{ person.get('business_name') }}</span>
          {% endif %}
        </div>
      </div>

      {% if person.get('offerings') is iterable and person.get('offerings')|length > 0 %}
        <div class="card__tags">
          {% for offering in person.get('offerings') %}
            <span class="card__tag">{{ offering }}</span>
          {% endfor %}
        </div>
      {% endif %}

      {% if person.get('bio') %}
        <div class="detail__body flow">
          {% for paragraph in person.get('bio')|split("\n\n") %}
            <p>{{ paragraph }}</p>
          {% endfor %}
        </div>
      {% endif %}

      {% if person.get('email') or person.get('phone') %}
        <div class="detail__contact">
          {% if person.get('email') %}
            <a href="mailto:{{ person.get('email') }}">{{ person.get('email') }}</a>
          {% endif %}
          {% if person.get('phone') %}
            <span>{{ person.get('phone') }}</span>
          {% endif %}
        </div>
      {% endif %}
    </div>
  {% else %}
    <div class="flow-lg">
      <h1>Person Not Found</h1>
      <p>The person at <code>{{ path }}</code> could not be found.</p>
      <p><a href="/people">Browse all people</a></p>
    </div>
  {% endif %}
{% endblock %}
```

**Step 2: Update the card component**

The card component doesn't need changes — it already receives simple values (`name`, `role`, `community`, `offerings`, `excerpt`, `url`) via `{% include ... with {} %}`. The controller-backed template passes these same variables.

**Step 3: Commit**

```bash
git add templates/people.html.twig
git commit -m "feat(#53): update people template for entity-backed data"
```

---

### Task 8: Add seed data for development

**Files:**
- Create: `src/Seed/PeopleSeeder.php`
- Create: `tests/Minoo/Unit/Seed/PeopleSeederTest.php`

**Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Seed;

use Minoo\Seed\PeopleSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PeopleSeeder::class)]
final class PeopleSeederTest extends TestCase
{
    #[Test]
    public function it_provides_sample_people(): void
    {
        $people = PeopleSeeder::samplePeople();

        $this->assertCount(4, $people);
        $this->assertSame('Mary Trudeau', $people[0]['name']);
        $this->assertSame('mary-trudeau', $people[0]['slug']);
        $this->assertArrayHasKey('bio', $people[0]);
        $this->assertArrayHasKey('community', $people[0]);
        $this->assertArrayHasKey('email', $people[0]);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Seed/PeopleSeederTest.php
```

Expected: Error — class `PeopleSeeder` not found.

**Step 3: Write the seeder**

```php
<?php

declare(strict_types=1);

namespace Minoo\Seed;

final class PeopleSeeder
{
    /** @return list<array<string, mixed>> */
    public static function samplePeople(): array
    {
        return [
            [
                'name' => 'Mary Trudeau',
                'slug' => 'mary-trudeau',
                'bio' => "Mary has been catering community events in Sagamok for over fifteen years. She specializes in traditional dishes including bannock, wild rice soup, and smoked fish, as well as contemporary meals for large gatherings.\n\nHer business, Mary's Bannock & Catering, serves events ranging from small family celebrations to community-wide feasts. She is passionate about keeping traditional food practices alive while making them accessible for modern events.",
                'community' => 'Sagamok Anishnawbek',
                'roles' => ['Caterer', 'Small Business Owner'],
                'offerings' => ['Food'],
                'business_name' => "Mary's Bannock & Catering",
                'email' => 'mary@example.com',
                'phone' => '705-555-0101',
                'status' => 1,
            ],
            [
                'name' => 'John Beaucage',
                'slug' => 'john-beaucage',
                'bio' => "John is a respected Elder in the Sagamok community with deep knowledge of Anishinaabe governance traditions and treaty history. He is frequently called upon to provide opening prayers and cultural guidance for community events and government meetings.\n\nJohn is available for teachings on treaty rights, traditional governance, and land-based education. He has mentored many young leaders in the community over the past three decades.",
                'community' => 'Sagamok Anishnawbek',
                'roles' => ['Elder', 'Knowledge Keeper'],
                'offerings' => ['Teachings', 'Cultural Services'],
                'business_name' => '',
                'email' => 'john@example.com',
                'phone' => '705-555-0102',
                'status' => 1,
            ],
            [
                'name' => 'Sarah Owl',
                'slug' => 'sarah-owl',
                'bio' => "Sarah is a skilled regalia maker and beadwork artist from Garden River First Nation. She creates custom jingle dresses, fancy shawl regalia, and beadwork pieces for powwow dancers and community members across the region.\n\nThrough her business Owl Designs, Sarah also runs regular workshops teaching beadwork fundamentals, ribbon skirt making, and regalia construction. She believes in passing traditional crafting skills to the next generation.",
                'community' => 'Garden River First Nation',
                'roles' => ['Regalia Maker', 'Crafter', 'Workshop Facilitator'],
                'offerings' => ['Regalia', 'Beadwork', 'Workshops'],
                'business_name' => 'Owl Designs',
                'email' => 'sarah@example.com',
                'phone' => '705-555-0103',
                'status' => 1,
            ],
            [
                'name' => 'Mike Abitong',
                'slug' => 'mike-abitong',
                'bio' => "Mike leads land-based youth programs in Atikameksheng, teaching young people traditional harvesting practices including cedar picking, medicine gathering, and seasonal land stewardship.\n\nHe provides cedar bundles and other harvested materials for community ceremonies and events. Mike also runs week-long summer camps focused on reconnecting youth with the land through hands-on traditional activities.",
                'community' => 'Atikameksheng Anishnawbek',
                'roles' => ['Cedar Harvester', 'Youth Worker'],
                'offerings' => ['Cedar Products', 'Workshops', 'Cultural Services'],
                'business_name' => '',
                'email' => 'mike@example.com',
                'phone' => '705-555-0104',
                'status' => 1,
            ],
        ];
    }
}
```

**Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Minoo/Unit/Seed/PeopleSeederTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add src/Seed/PeopleSeeder.php tests/Minoo/Unit/Seed/PeopleSeederTest.php
git commit -m "feat(#53): add PeopleSeeder with 4 sample resource people"
```

---

### Task 9: Full test suite + e2e verification

**Step 1: Run full test suite**

```bash
rm -f storage/framework/packages.php && ./vendor/bin/phpunit
```

Expected: All tests pass.

**Step 2: Run integration tests**

```bash
./vendor/bin/phpunit --testsuite MinooIntegration
```

Expected: Integration test passes (kernel boots with updated PeopleServiceProvider).

**Step 3: E2E smoke test**

To test the full controller flow with seeded data, the dev server must have people in the database. If the database is empty, `/people` will show "No resource people found." This is correct behavior — it proves the controller works against real storage.

```bash
php -S localhost:8081 -t public &
SERVER_PID=$!
sleep 2
curl -s -o /dev/null -w "%{http_code}" http://localhost:8081/people
# Expect: 200 (listing page, may be empty without seeded DB data)
curl -s http://localhost:8081/people | grep -c 'People'
# Expect: at least 1 (the heading)
kill $SERVER_PID
```

---

### Task 10: Push and create PR

**Step 1: Push branch**

```bash
git push -u origin feat/people-controller
```

**Step 2: Create PR**

```bash
gh pr create --title "feat(#53): wire /people to entity storage via PeopleController" --body "$(cat <<'EOF'
Closes #53

## Summary

- Add `PeopleController` with `list()` and `show()` actions
- Register `/people` and `/people/{slug}` routes via `PeopleServiceProvider::routes()`
- Update `people.html.twig` to render entity data instead of hardcoded arrays
- Add `PeopleSeeder` with 4 sample resource people
- Requires framework PR: app-level route registration

## Test plan

- [x] PeopleController unit test
- [x] PeopleSeeder unit test
- [x] Full test suite passes
- [x] Integration test passes
- [x] E2E: `/people` returns 200

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

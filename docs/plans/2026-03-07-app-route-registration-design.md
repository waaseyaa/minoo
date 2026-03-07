# App Route Registration — Design

**Date:** 2026-03-07
**Scope:** Waaseyaa framework (foundation, routing) + Minoo (PeopleController)
**Milestone:** Waaseyaa framework enhancement, prerequisite for Minoo v0.5

## Problem

The framework's SSR layer is template-only. All five Minoo listing pages (`/events`, `/groups`, `/teachings`, `/language`, `/people`) use hardcoded Twig arrays because there is no mechanism for app-level code to register controllers that query entity storage and pass real data to templates.

The `HttpKernel::registerRoutes()` method is private and registers only framework routes (JSON:API, schema, broadcast, etc.) followed by an SSR catchall (`/{path}` → `render.page`). App service providers have no way to insert routes before this catchall.

## Solution

Add `routes(WaaseyaaRouter $router)` to the service provider lifecycle. App providers register named routes with `Class::method` controllers. The kernel calls provider routes after framework routes but before the SSR catchall. A new dispatch path instantiates app controllers and sends their responses.

## Design

### Provider lifecycle change

Add `routes()` to `ServiceProviderInterface` and `ServiceProvider`:

```php
// ServiceProviderInterface.php
interface ServiceProviderInterface
{
    public function register(): void;
    public function boot(): void;
    public function routes(WaaseyaaRouter $router): void;
    public function provides(): array;
    public function isDeferred(): bool;
}

// ServiceProvider.php — default no-op
public function routes(WaaseyaaRouter $router): void {}
```

Lifecycle order: `register()` → entity type collection → `boot()` → `routes()` (during request handling).

### Route registration order

```
1. Framework API routes (JsonApi, schema, broadcast, media, search, discovery, mcp)
2. App routes (from providers, in discovery order)
3. SSR catchall (public.home, public.page)
```

In `HttpKernel::registerRoutes()`:

```php
// After framework routes, before catchall:
foreach ($this->providers as $provider) {
    $provider->routes($router);
}
```

### Controller convention

App controllers use `Class::method` string format:

```php
$router->addRoute('people.list',
    RouteBuilder::create('/people')
        ->controller('Minoo\Controller\PeopleController::list')
        ->allowAll()->render()->methods('GET')->build()
);
```

### Dispatch

Add one arm to the `match(true)` block in `dispatch()`, before `default`:

```php
str_contains($controller, '::') => $this->dispatchAppController(
    $controller, $params, $query, $account, $httpRequest
),
```

The `dispatchAppController` method:

1. Splits `Class::method` string
2. Instantiates the controller, injecting `EntityTypeManager` and `Twig\Environment`
3. Calls the method with route params, query params, and account
4. Expects an `SsrResponse` return value
5. Sends HTML with cache headers

```php
private function dispatchAppController(
    string $controller,
    array $params,
    array $query,
    AccountInterface $account,
    HttpRequest $httpRequest,
): never {
    [$class, $method] = explode('::', $controller, 2);
    $twig = SsrServiceProvider::getTwigEnvironment()
        ?? SsrServiceProvider::createTwigEnvironment($this->projectRoot, $this->config);

    $instance = new $class($this->entityTypeManager, $twig);
    $response = $instance->$method($params, $query, $account);

    $cacheMaxAge = $this->resolveRenderCacheMaxAge();
    $headers = $response->headers;
    $headers['Cache-Control'] = $this->cacheControlHeaderForRender($account, $cacheMaxAge);
    $this->sendHtml($response->statusCode, $response->content, $headers);
}
```

### Controller interface

Controllers receive `EntityTypeManager` and `Twig\Environment` via constructor, route/query params and account via method arguments:

```php
final class PeopleController
{
    public function __construct(
        private EntityTypeManager $entityTypeManager,
        private Environment $twig,
    ) {}

    public function list(array $params, array $query, AccountInterface $account): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC')
            ->execute();
        $people = $storage->loadMultiple($ids);

        $html = $this->twig->render('people.html.twig', [
            'path' => '/people',
            'people' => $people,
        ]);

        return new SsrResponse(200, $html);
    }

    public function show(array $params, array $query, AccountInterface $account): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();

        if ($ids === []) {
            $html = $this->twig->render('people.html.twig', [
                'path' => '/people/' . $slug,
                'person' => null,
            ]);
            return new SsrResponse(404, $html);
        }

        $person = $storage->load(reset($ids));
        $html = $this->twig->render('people.html.twig', [
            'path' => '/people/' . $slug,
            'person' => $person,
        ]);

        return new SsrResponse(200, $html);
    }
}
```

### Template changes

`people.html.twig` changes from hardcoded arrays to receiving entity objects:

- Listing: iterates `people` (array of `ResourcePerson` entities), calls `person.get('name')`, etc.
- Detail: receives `person` (single entity or null)
- Removes all `{% set people = [...] %}` static data

### Filtering (v0.5 enhancement)

With a controller, filtering becomes query parameter handling:

```
/people?role=elder           → condition on roles field
/people?offering=food        → condition on offerings field
/people?search=cedar         → CONTAINS condition on name/bio
```

The controller translates query params into `SqlEntityQuery` conditions.

## Files changed

### Framework (waaseyaa)

| File | Change |
|------|--------|
| `packages/foundation/src/ServiceProvider/ServiceProviderInterface.php` | Add `routes()` method |
| `packages/foundation/src/ServiceProvider/ServiceProvider.php` | Add default `routes()` no-op |
| `packages/foundation/src/Kernel/HttpKernel.php` | Call provider routes before catchall; add `dispatchAppController()` |

### Minoo

| File | Change |
|------|--------|
| `src/Controller/PeopleController.php` | New — list + show methods |
| `src/Provider/PeopleServiceProvider.php` | Add `routes()` method |
| `templates/people.html.twig` | Replace hardcoded arrays with entity data |
| `templates/components/resource-person-card.html.twig` | Adapt to entity `get()` calls |

## Scope

- Framework: ~50 lines across 3 files
- Minoo: 1 new controller, 1 modified provider, 2 modified templates
- No new dependencies, no new packages, no configuration changes

## What this unlocks

Once the pattern exists, all SSR listing pages can migrate from hardcoded arrays to entity-backed controllers. The pattern is: provider registers routes → controller queries storage → template renders entities.

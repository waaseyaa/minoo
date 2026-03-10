---
name: minoo:controllers
description: Use when working on Minoo HTTP controllers, routing, or request handling in src/Controller/ or route definitions in src/Provider/
---

# Minoo Controllers & Routing Specialist

## Scope

Files: `src/Controller/`, route definitions in `src/Provider/`
Tests: `tests/Minoo/Unit/Controller/`
Entry point: `public/index.php`

## Controller Signature

All SSR controllers follow this pattern:
```php
public function method(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
```

- `$params` — route parameters (e.g. `{slug}`, `{uuid}`, `{esrid}`)
- `$query` — query string parameters
- `$account` — current user (anonymous or authenticated)
- `$request` — full HTTP request (for POST body, headers)

JSON API controllers return `JsonResponse` instead.

## Controller Map

| Controller | Routes | Access | Purpose |
|-----------|--------|--------|---------|
| `HomeController` | `GET /` | allowAll | Home page with events + communities |
| `CommunityController` | `GET /communities`, `/communities/{slug}`, `/api/communities/autocomplete` | allowAll | Community list, detail, autocomplete API |
| `PeopleController` | `GET /people`, `/people/{slug}` | allowAll | Resource people list + detail |
| `AuthController` | `GET/POST /login`, `/register`, `GET /logout` | allowAll | Authentication forms |
| `ElderSupportController` | `GET/POST /elders/request`, `GET /elders/request/{uuid}` | allowAll | Elder support request form + confirmation |
| `VolunteerController` | `GET/POST /elders/volunteer`, `GET /elders/volunteer/{uuid}` | allowAll | Volunteer signup form + confirmation |
| `VolunteerDashboardController` | `GET/POST /dashboard/volunteer/*` | requireRole: volunteer | Volunteer dashboard, profile edit, toggle availability |
| `CoordinatorDashboardController` | `GET /dashboard/coordinator` | requireRole: elder_coordinator | Coordinator dashboard with volunteer ranking |
| `ElderSupportWorkflowController` | `POST /elders/request/{esrid}/*` | requireRole: volunteer or elder_coordinator | State machine transitions |
| `LocationController` | `GET/POST /api/location/*` | allowAll | JSON API for geolocation |

## Route Registration

Routes defined in ServiceProvider `routes()` method:
```php
public function routes(WaaseyaaRouter $router): void
{
    $router->addRoute('community.list',
        RouteBuilder::create('/communities')
            ->controller('Minoo\\Controller\\CommunityController::list')
            ->allowAll()
            ->render()        // marks as SSR route
            ->methods('GET')
            ->build(),
    );
    $router->addRoute('community.show',
        RouteBuilder::create('/communities/{slug}')
            ->controller('Minoo\\Controller\\CommunityController::show')
            ->allowAll()
            ->render()
            ->methods('GET')
            ->requirement('slug', '[a-z0-9-]+')
            ->build(),
    );
}
```

**Access control options:**
- `allowAll()` — public, no auth required
- `requireRole('volunteer')` — role-based gate

## Response Patterns

**SSR page render:**
```php
$html = $this->twig->render('template.html.twig', [
    'path' => '/current/path',
    'account' => $account,
    'items' => $entities,
    'location' => $location,
]);
return new SsrResponse(content: $html, statusCode: 200);
```

**Redirect (after form POST):**
```php
return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/target']);
```

**404 (entity not found):**
```php
$html = $this->twig->render('404.html.twig', ['path' => $path, 'account' => $account]);
return new SsrResponse(content: $html, statusCode: 404);
```

**Form validation error (422):**
```php
return new SsrResponse(content: $this->twig->render('form.html.twig', [
    'errors' => $errors, 'values' => $values, 'path' => $path, 'account' => $account,
]), statusCode: 422);
```

**JSON API:**
```php
return new JsonResponse(['data' => $result], 200);
return new JsonResponse(['error' => 'Not found'], 404);
```

## Entity Loading Pattern

```php
$storage = $this->entityTypeManager->getStorage('entity_type');

// Query by field
$ids = $storage->getQuery()
    ->condition('slug', $slug)
    ->condition('status', 1)
    ->sort('created_at', 'DESC')
    ->execute();
$entities = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

// Single load
$entity = $storage->load($id);

// Create + save
$entity = $storage->create([...]);
$storage->save($entity);
```

## Form GET/POST Pattern

Most forms follow this flow:
1. **GET** — render form with empty `errors`/`values`
2. **POST** — validate `$request->getParsedBody()`, on error re-render with 422, on success create entity + redirect 302

```php
public function submitForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
{
    $body = $request->getParsedBody();
    $errors = [];
    if (empty($body['name'])) { $errors['name'] = 'Name is required.'; }
    if ($errors !== []) {
        return new SsrResponse(content: $this->twig->render('form.html.twig', [
            'errors' => $errors, 'values' => $body, 'path' => '/form', 'account' => $account,
        ]), statusCode: 422);
    }
    $storage = $this->entityTypeManager->getStorage('entity_type');
    $entity = $storage->create([...]);
    $entity->enforceIsNew();
    $storage->save($entity);
    return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/success/' . $entity->uuid()]);
}
```

## State Machine Pattern (ElderSupportWorkflowController)

Workflow transitions validate current status before modifying:
```php
public function assignVolunteer(...): SsrResponse
{
    $request = $storage->load($params['esrid']);
    if ($request->get('status') !== 'open') {
        return new SsrResponse(content: 'Invalid state', statusCode: 422);
    }
    $request->set('status', 'assigned');
    $request->set('assigned_volunteer_id', $body['volunteer_id']);
    $storage->save($request);
    return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator']);
}
```

Status flow: `open` → `assigned` → `in_progress` → `completed` → `confirmed` | any → `cancelled`

## Testing Patterns

Mock dependencies — EntityTypeManager, Twig Environment:
```php
$storage = $this->createMock(EntityStorageInterface::class);
$etm = $this->createMock(EntityTypeManager::class);
$etm->method('getStorage')->willReturn($storage);

$twig = new Environment(new ArrayLoader(['template.html.twig' => 'ok']));
$controller = new MyController($etm, $twig);
```

Assert response:
```php
$response = $controller->method($params, $query, $account, $request);
$this->assertSame(200, $response->getStatusCode());
$this->assertSame(302, $response->getStatusCode());
$this->assertSame('/target', $response->getHeader('Location'));
```

## Common Mistakes

- **Missing `path` in template context**: Every SSR render must pass `path` — base template uses it for nav active states
- **Missing `account` in template context**: Base template renders auth-dependent UI (dashboard links, login/logout)
- **Using `$request->getContent()` for forms**: POST form data uses `$request->getParsedBody()`, not `getContent()` (which is for JSON)
- **Forgetting `enforceIsNew()`**: When creating entities with pre-set IDs, call this before `save()`
- **Wrong status code for validation**: Use 422 for form errors, not 400
- **Redirect without empty content**: `SsrResponse` for redirects should have `content: ''`
- **Loading entity by slug without status check**: Public pages should filter by `status: 1` (published)
- **Role check in controller vs route**: Prefer `requireRole()` in RouteBuilder; only use in-method checks for complex conditional logic

## Related Specs

- `docs/specs/frontend-ssr.md` — template conventions, CSS patterns
- `docs/specs/entity-model.md` — entity types, field definitions
- Framework: `waaseyaa_get_spec api-layer` — RouteBuilder, SsrResponse, JsonResponse
- Framework: `waaseyaa_get_spec access-control` — route access options, AccessChecker

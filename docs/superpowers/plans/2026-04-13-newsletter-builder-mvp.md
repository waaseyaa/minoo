# Newsletter Builder MVP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin SPA for assembling newsletter editions from real entities, previewing the print layout, generating PDFs, and sending to the printer.

**Architecture:** Lightweight Vue 3 SPA (Vite, no Nuxt) served at `/admin/newsletter/`. Backend JSON API controller reuses existing newsletter services (`EditionLifecycle`, `NewsletterRenderer`, `NewsletterDispatcher`). Admin-surface generic CRUD handles entity storage; custom endpoints handle workflow actions (preview, generate, send) and the grouped-by-section item view.

**Tech Stack:** Vue 3 + Vite (frontend), PHP 8.4 (backend API), Playwright (PDF rendering), PHPUnit (backend tests), Playwright (e2e tests)

**Prerequisite:** Milestone 1 (Waaseyaa alpha.140 upgrade) must be complete.

---

## File Structure

### Backend (PHP)

| Action | File | Purpose |
|--------|------|---------|
| Create | `src/Controller/NewsletterAdminApiController.php` | JSON API for newsletter builder — edition CRUD, item management, workflow actions |
| Create | `tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php` | Unit tests for API controller |
| Modify | `src/Provider/AppServiceProvider.php` | Register admin API routes |
| Modify | `config/newsletter.php` | Add `printer_email` key if not present |

### Frontend (Vue 3)

| Action | File | Purpose |
|--------|------|---------|
| Create | `resources/newsletter-admin/package.json` | Node dependencies (vue, vite, vue-router) |
| Create | `resources/newsletter-admin/vite.config.js` | Build config — output to `public/admin/newsletter/` |
| Create | `resources/newsletter-admin/index.html` | SPA entry point |
| Create | `resources/newsletter-admin/src/main.js` | Vue app bootstrap |
| Create | `resources/newsletter-admin/src/App.vue` | Root component with nav |
| Create | `resources/newsletter-admin/src/router.js` | Vue Router — list + detail routes |
| Create | `resources/newsletter-admin/src/api.js` | Fetch wrapper for newsletter API |
| Create | `resources/newsletter-admin/src/pages/EditionList.vue` | Edition list + create form |
| Create | `resources/newsletter-admin/src/pages/EditionDetail.vue` | Section editor, item management, preview, actions |
| Create | `resources/newsletter-admin/src/components/SectionPanel.vue` | Collapsible section with item list |
| Create | `resources/newsletter-admin/src/components/ItemCard.vue` | Single item display with actions |
| — | `EntityPicker` + `AddItemModal` | Embedded inline in `EditionDetail.vue` for MVP simplicity |

### Serving

| Action | File | Purpose |
|--------|------|---------|
| Modify | `src/Provider/AppServiceProvider.php` | Add catch-all route for `/admin/newsletter/*` SPA fallback |
| Create | `public/admin/newsletter/.gitkeep` | Placeholder for built assets |

---

### Task 1: Newsletter Admin API Controller — Edition Endpoints

**Files:**
- Create: `src/Controller/NewsletterAdminApiController.php`
- Create: `tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`
- Modify: `src/Provider/AppServiceProvider.php` (routes)

- [ ] **Step 1: Write failing test for listEditions**

Create `tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\NewsletterAdminApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Twig\Environment;

#[CoversClass(NewsletterAdminApiController::class)]
final class NewsletterAdminApiControllerTest extends TestCase
{
    private NewsletterAdminApiController $controller;
    private EntityTypeManager $etm;
    private EntityStorageInterface $editionStorage;
    private EntityStorageInterface $itemStorage;

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->editionStorage = $this->createMock(EntityStorageInterface::class);
        $this->itemStorage = $this->createMock(EntityStorageInterface::class);

        $this->etm->method('getStorage')
            ->willReturnCallback(fn (string $type) => match ($type) {
                'newsletter_edition' => $this->editionStorage,
                'newsletter_item' => $this->itemStorage,
                default => throw new \RuntimeException("Unexpected type: $type"),
            });

        $twig = $this->createMock(Environment::class);

        $this->controller = new NewsletterAdminApiController($this->etm, $twig);
    }

    #[Test]
    public function list_editions_returns_json(): void
    {
        $this->editionStorage->method('loadMultiple')->willReturn([]);

        $response = $this->controller->listEditions(
            [],
            [],
            $this->createMock(\Waaseyaa\User\AccountInterface::class),
            HttpRequest::create('/'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('editions', $data);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`
Expected: FAIL — class `NewsletterAdminApiController` not found

- [ ] **Step 3: Create the controller with listEditions**

Create `src/Controller/NewsletterAdminApiController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\User\AccountInterface;
use Twig\Environment;

final class NewsletterAdminApiController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function listEditions(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $editions = $storage->loadMultiple();

        $result = array_map(fn ($e) => [
            'id' => $e->id(),
            'headline' => $e->get('headline'),
            'volume' => $e->get('volume'),
            'issue_number' => $e->get('issue_number'),
            'community_id' => $e->get('community_id'),
            'status' => $e->get('status'),
            'created_at' => $e->get('created_at'),
        ], $editions);

        return new Response(
            json_encode(['editions' => array_values($result)]),
            200,
            ['Content-Type' => 'application/json'],
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`
Expected: PASS

- [ ] **Step 5: Add test for createEdition**

Add to the test class:

```php
#[Test]
public function create_edition_returns_created_entity(): void
{
    $mockEdition = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockEdition->method('id')->willReturn(1);
    $mockEdition->method('get')->willReturnCallback(fn (string $field) => match ($field) {
        'headline' => 'Test Edition',
        'volume' => 1,
        'issue_number' => 1,
        'community_id' => 'test-community',
        'status' => 'draft',
        default => null,
    });

    $this->editionStorage->expects($this->once())
        ->method('create')
        ->willReturn($mockEdition);
    $this->editionStorage->expects($this->once())
        ->method('save');

    $request = HttpRequest::create('/', 'POST', [], [], [], [], json_encode([
        'headline' => 'Test Edition',
        'volume' => 1,
        'issue_number' => 1,
        'community_id' => 'test-community',
    ]));

    $response = $this->controller->createEdition(
        [],
        [],
        $this->createMock(AccountInterface::class),
        $request,
    );

    $this->assertSame(201, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertSame('Test Edition', $data['edition']['headline']);
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php --filter=create_edition`
Expected: FAIL — method `createEdition` not found

- [ ] **Step 7: Implement createEdition**

Add to `NewsletterAdminApiController`:

```php
public function createEdition(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $body = json_decode($request->getContent(), true);

    $storage = $this->entityTypeManager->getStorage('newsletter_edition');
    $edition = $storage->create([
        'headline' => $body['headline'] ?? '',
        'volume' => (int) ($body['volume'] ?? 1),
        'issue_number' => (int) ($body['issue_number'] ?? 1),
        'community_id' => $body['community_id'] ?? '',
        'status' => 'draft',
        'created_by' => $account->id(),
    ]);
    $storage->save($edition);

    return new Response(
        json_encode(['edition' => [
            'id' => $edition->id(),
            'headline' => $edition->get('headline'),
            'volume' => $edition->get('volume'),
            'issue_number' => $edition->get('issue_number'),
            'community_id' => $edition->get('community_id'),
            'status' => $edition->get('status'),
        ]]),
        201,
        ['Content-Type' => 'application/json'],
    );
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`
Expected: PASS (both tests)

- [ ] **Step 9: Add test for getEdition**

Add to the test class:

```php
#[Test]
public function get_edition_returns_edition_with_items_by_section(): void
{
    $mockEdition = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockEdition->method('id')->willReturn(1);
    $mockEdition->method('get')->willReturnCallback(fn (string $field) => match ($field) {
        'headline' => 'Test',
        'volume' => 1,
        'issue_number' => 1,
        'community_id' => 'test',
        'status' => 'draft',
        default => null,
    });

    $this->editionStorage->method('load')->with(1)->willReturn($mockEdition);

    $mockItem = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockItem->method('id')->willReturn(10);
    $mockItem->method('get')->willReturnCallback(fn (string $field) => match ($field) {
        'edition_id' => 1,
        'section' => 'news',
        'position' => 1,
        'source_type' => 'inline',
        'source_id' => 0,
        'inline_title' => 'A News Item',
        'inline_body' => '<p>Body</p>',
        'editor_blurb' => 'News blurb',
        'included' => 1,
        default => null,
    });

    $mockQuery = $this->createMock(\Waaseyaa\Entity\Query\EntityQueryInterface::class);
    $mockQuery->method('condition')->willReturnSelf();
    $mockQuery->method('execute')->willReturn([$mockItem]);
    $this->itemStorage->method('getQuery')->willReturn($mockQuery);

    $response = $this->controller->getEdition(
        ['id' => '1'],
        [],
        $this->createMock(AccountInterface::class),
        HttpRequest::create('/'),
    );

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertArrayHasKey('edition', $data);
    $this->assertArrayHasKey('items_by_section', $data);
    $this->assertArrayHasKey('news', $data['items_by_section']);
}
```

- [ ] **Step 10: Run test to verify it fails**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php --filter=get_edition`
Expected: FAIL

- [ ] **Step 11: Implement getEdition**

Add to `NewsletterAdminApiController`:

```php
public function getEdition(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $id = (int) $params['id'];
    $storage = $this->entityTypeManager->getStorage('newsletter_edition');
    $edition = $storage->load($id);

    if ($edition === null) {
        return new Response(
            json_encode(['error' => 'Edition not found']),
            404,
            ['Content-Type' => 'application/json'],
        );
    }

    $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
    $items = $itemStorage->getQuery()
        ->condition('edition_id', $id)
        ->execute();

    $sections = $this->config()['sections'] ?? [];
    $inlineSections = $this->config()['inline_sections'] ?? [];
    $allSections = array_merge(array_keys($inlineSections), array_keys($sections));

    $itemsBySection = [];
    foreach ($allSections as $section) {
        $itemsBySection[$section] = [];
    }

    foreach ($items as $item) {
        $section = $item->get('section');
        $itemsBySection[$section][] = [
            'id' => $item->id(),
            'position' => $item->get('position'),
            'section' => $section,
            'source_type' => $item->get('source_type'),
            'source_id' => $item->get('source_id'),
            'inline_title' => $item->get('inline_title'),
            'inline_body' => $item->get('inline_body'),
            'editor_blurb' => $item->get('editor_blurb'),
            'included' => $item->get('included'),
        ];
    }

    // Sort each section by position
    foreach ($itemsBySection as &$sectionItems) {
        usort($sectionItems, fn ($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
    }

    return new Response(
        json_encode([
            'edition' => [
                'id' => $edition->id(),
                'headline' => $edition->get('headline'),
                'volume' => $edition->get('volume'),
                'issue_number' => $edition->get('issue_number'),
                'community_id' => $edition->get('community_id'),
                'status' => $edition->get('status'),
                'pdf_path' => $edition->get('pdf_path'),
                'pdf_hash' => $edition->get('pdf_hash'),
                'sent_at' => $edition->get('sent_at'),
            ],
            'items_by_section' => $itemsBySection,
            'section_order' => $allSections,
        ]),
        200,
        ['Content-Type' => 'application/json'],
    );
}

private function config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__, 2) . '/config/newsletter.php';
    }
    return $config;
}
```

- [ ] **Step 12: Run test to verify it passes**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`
Expected: PASS (all 3 tests)

- [ ] **Step 13: Commit**

```bash
git add src/Controller/NewsletterAdminApiController.php tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php
git commit -m "feat: newsletter admin API — edition list, create, get with items by section"
```

---

### Task 2: Newsletter Admin API Controller — Item Management

**Files:**
- Modify: `src/Controller/NewsletterAdminApiController.php`
- Modify: `tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`

- [ ] **Step 1: Write failing test for addItem**

Add to test class:

```php
#[Test]
public function add_item_creates_newsletter_item(): void
{
    $mockEdition = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockEdition->method('id')->willReturn(1);
    $this->editionStorage->method('load')->with(1)->willReturn($mockEdition);

    $mockItem = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockItem->method('id')->willReturn(10);
    $mockItem->method('get')->willReturnCallback(fn (string $field) => match ($field) {
        'edition_id' => 1,
        'section' => 'news',
        'position' => 1,
        'source_type' => 'inline',
        'source_id' => 0,
        'inline_title' => 'New Item',
        'inline_body' => '<p>Content</p>',
        'editor_blurb' => 'A blurb',
        'included' => 1,
        default => null,
    });

    $this->itemStorage->expects($this->once())->method('create')->willReturn($mockItem);
    $this->itemStorage->expects($this->once())->method('save');

    // Mock the count query for position calculation
    $mockQuery = $this->createMock(\Waaseyaa\Entity\Query\EntityQueryInterface::class);
    $mockQuery->method('condition')->willReturnSelf();
    $mockQuery->method('count')->willReturnSelf();
    $mockQuery->method('execute')->willReturn([0]);
    $this->itemStorage->method('getQuery')->willReturn($mockQuery);

    $request = HttpRequest::create('/', 'POST', [], [], [], [], json_encode([
        'section' => 'news',
        'source_type' => 'inline',
        'inline_title' => 'New Item',
        'inline_body' => '<p>Content</p>',
        'editor_blurb' => 'A blurb',
    ]));

    $response = $this->controller->addItem(
        ['id' => '1'],
        [],
        $this->createMock(AccountInterface::class),
        $request,
    );

    $this->assertSame(201, $response->getStatusCode());
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php --filter=add_item`
Expected: FAIL

- [ ] **Step 3: Implement addItem**

Add to `NewsletterAdminApiController`:

```php
public function addItem(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $editionId = (int) $params['id'];
    $editionStorage = $this->entityTypeManager->getStorage('newsletter_edition');
    $edition = $editionStorage->load($editionId);

    if ($edition === null) {
        return new Response(
            json_encode(['error' => 'Edition not found']),
            404,
            ['Content-Type' => 'application/json'],
        );
    }

    $body = json_decode($request->getContent(), true);
    $section = $body['section'] ?? '';
    $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');

    // Auto-assign position as next in section
    $countResult = $itemStorage->getQuery()
        ->condition('edition_id', $editionId)
        ->condition('section', $section)
        ->count()
        ->execute();
    $nextPosition = ($countResult[0] ?? 0) + 1;

    $item = $itemStorage->create([
        'edition_id' => $editionId,
        'section' => $section,
        'position' => $nextPosition,
        'source_type' => $body['source_type'] ?? 'inline',
        'source_id' => (int) ($body['source_id'] ?? 0),
        'inline_title' => $body['inline_title'] ?? '',
        'inline_body' => $body['inline_body'] ?? '',
        'editor_blurb' => $body['editor_blurb'] ?? '',
        'included' => 1,
    ]);
    $itemStorage->save($item);

    return new Response(
        json_encode(['item' => [
            'id' => $item->id(),
            'section' => $item->get('section'),
            'position' => $item->get('position'),
            'source_type' => $item->get('source_type'),
            'inline_title' => $item->get('inline_title'),
            'editor_blurb' => $item->get('editor_blurb'),
            'included' => $item->get('included'),
        ]]),
        201,
        ['Content-Type' => 'application/json'],
    );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php --filter=add_item`
Expected: PASS

- [ ] **Step 5: Write failing test for removeItem**

Add to test class:

```php
#[Test]
public function remove_item_deletes_newsletter_item(): void
{
    $mockItem = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockItem->method('id')->willReturn(10);
    $mockItem->method('get')->willReturnCallback(fn (string $field) => match ($field) {
        'edition_id' => 1,
        default => null,
    });

    $this->itemStorage->method('load')->with(10)->willReturn($mockItem);
    $this->itemStorage->expects($this->once())->method('delete')->with([$mockItem]);

    $response = $this->controller->removeItem(
        ['id' => '1', 'itemId' => '10'],
        [],
        $this->createMock(AccountInterface::class),
        HttpRequest::create('/', 'DELETE'),
    );

    $this->assertSame(200, $response->getStatusCode());
}
```

- [ ] **Step 6: Implement removeItem**

Add to `NewsletterAdminApiController`:

```php
public function removeItem(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $editionId = (int) $params['id'];
    $itemId = (int) $params['itemId'];
    $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
    $item = $itemStorage->load($itemId);

    if ($item === null || (int) $item->get('edition_id') !== $editionId) {
        return new Response(
            json_encode(['error' => 'Item not found']),
            404,
            ['Content-Type' => 'application/json'],
        );
    }

    $itemStorage->delete([$item]);

    return new Response(
        json_encode(['deleted' => true]),
        200,
        ['Content-Type' => 'application/json'],
    );
}
```

- [ ] **Step 7: Write failing test for reorderItem**

Add to test class:

```php
#[Test]
public function reorder_item_updates_position(): void
{
    $mockItem = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockItem->method('id')->willReturn(10);
    $mockItem->method('get')->willReturnCallback(fn (string $field) => match ($field) {
        'edition_id' => 1,
        'section' => 'news',
        default => null,
    });
    $mockItem->expects($this->once())->method('set')->with('position', 3)->willReturn($mockItem);

    $this->itemStorage->method('load')->with(10)->willReturn($mockItem);
    $this->itemStorage->expects($this->once())->method('save');

    $request = HttpRequest::create('/', 'POST', [], [], [], [], json_encode([
        'position' => 3,
    ]));

    $response = $this->controller->reorderItem(
        ['id' => '1', 'itemId' => '10'],
        [],
        $this->createMock(AccountInterface::class),
        $request,
    );

    $this->assertSame(200, $response->getStatusCode());
}
```

- [ ] **Step 8: Implement reorderItem**

Add to `NewsletterAdminApiController`:

```php
public function reorderItem(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $editionId = (int) $params['id'];
    $itemId = (int) $params['itemId'];
    $body = json_decode($request->getContent(), true);
    $newPosition = (int) ($body['position'] ?? 1);

    $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
    $item = $itemStorage->load($itemId);

    if ($item === null || (int) $item->get('edition_id') !== $editionId) {
        return new Response(
            json_encode(['error' => 'Item not found']),
            404,
            ['Content-Type' => 'application/json'],
        );
    }

    $item->set('position', $newPosition);
    $itemStorage->save($item);

    return new Response(
        json_encode(['item' => ['id' => $item->id(), 'position' => $newPosition]]),
        200,
        ['Content-Type' => 'application/json'],
    );
}
```

- [ ] **Step 9: Run all tests**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`
Expected: PASS (all 6 tests)

- [ ] **Step 10: Commit**

```bash
git add src/Controller/NewsletterAdminApiController.php tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php
git commit -m "feat: newsletter admin API — add, remove, reorder items"
```

---

### Task 3: Newsletter Admin API — Entity Search

**Files:**
- Modify: `src/Controller/NewsletterAdminApiController.php`
- Modify: `tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`

- [ ] **Step 1: Write failing test for entitySearch**

Add to test class:

```php
#[Test]
public function entity_search_returns_matching_entities(): void
{
    $mockTeaching = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockTeaching->method('id')->willReturn(5);
    $mockTeaching->method('label')->willReturn('Seven Grandfather Teachings');

    $teachingStorage = $this->createMock(EntityStorageInterface::class);
    $mockQuery = $this->createMock(\Waaseyaa\Entity\Query\EntityQueryInterface::class);
    $mockQuery->method('condition')->willReturnSelf();
    $mockQuery->method('range')->willReturnSelf();
    $mockQuery->method('execute')->willReturn([$mockTeaching]);
    $teachingStorage->method('getQuery')->willReturn($mockQuery);

    $this->etm->method('getStorage')
        ->willReturnCallback(fn (string $type) => match ($type) {
            'newsletter_edition' => $this->editionStorage,
            'newsletter_item' => $this->itemStorage,
            'teaching' => $teachingStorage,
            default => $teachingStorage,
        });

    $request = HttpRequest::create('/?q=seven&types=teaching');

    $response = $this->controller->entitySearch(
        [],
        ['q' => 'seven', 'types' => 'teaching'],
        $this->createMock(AccountInterface::class),
        $request,
    );

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertArrayHasKey('results', $data);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php --filter=entity_search`
Expected: FAIL

- [ ] **Step 3: Implement entitySearch**

Add to `NewsletterAdminApiController`:

```php
private const SEARCHABLE_TYPES = ['post', 'event', 'teaching', 'dictionary_entry'];

public function entitySearch(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $q = $query['q'] ?? $request->query->get('q', '');
    $typesParam = $query['types'] ?? $request->query->get('types', '');
    $types = $typesParam !== '' ? explode(',', $typesParam) : self::SEARCHABLE_TYPES;
    $types = array_intersect($types, self::SEARCHABLE_TYPES);

    $results = [];
    foreach ($types as $type) {
        try {
            $storage = $this->entityTypeManager->getStorage($type);
        } catch (\Exception) {
            continue;
        }

        $entities = $storage->getQuery()
            ->condition('label', "%{$q}%", 'LIKE')
            ->range(0, 10)
            ->execute();

        foreach ($entities as $entity) {
            $results[] = [
                'entity_type' => $type,
                'entity_id' => $entity->id(),
                'label' => $entity->label(),
            ];
        }
    }

    return new Response(
        json_encode(['results' => $results]),
        200,
        ['Content-Type' => 'application/json'],
    );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php --filter=entity_search`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Controller/NewsletterAdminApiController.php tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php
git commit -m "feat: newsletter admin API — entity type-ahead search"
```

---

### Task 4: Newsletter Admin API — Preview, Generate, Download, Send

**Files:**
- Modify: `src/Controller/NewsletterAdminApiController.php`
- Modify: `tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`

- [ ] **Step 1: Add service dependencies to constructor**

Update the constructor to accept the newsletter services. The existing `NewsletterEditorController` injects `EditionLifecycle`, `NewsletterRenderer`, and `NewsletterDispatcher`. Mirror that pattern.

Update `src/Controller/NewsletterAdminApiController.php` constructor:

```php
use App\Support\Newsletter\EditionLifecycle;
use App\Support\Newsletter\NewsletterRenderer;
use App\Support\Newsletter\NewsletterDispatcher;
use App\Support\Newsletter\RenderTokenStore;

final class NewsletterAdminApiController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly EditionLifecycle $lifecycle,
        private readonly NewsletterRenderer $renderer,
        private readonly NewsletterDispatcher $dispatcher,
        private readonly RenderTokenStore $tokenStore,
    ) {}
```

Update `setUp()` in the test to mock the new dependencies:

```php
protected function setUp(): void
{
    $this->etm = $this->createMock(EntityTypeManager::class);
    $this->editionStorage = $this->createMock(EntityStorageInterface::class);
    $this->itemStorage = $this->createMock(EntityStorageInterface::class);

    $this->etm->method('getStorage')
        ->willReturnCallback(fn (string $type) => match ($type) {
            'newsletter_edition' => $this->editionStorage,
            'newsletter_item' => $this->itemStorage,
            default => $this->createMock(EntityStorageInterface::class),
        });

    $twig = $this->createMock(Environment::class);
    $this->lifecycle = $this->createMock(EditionLifecycle::class);
    $this->renderer = $this->createMock(NewsletterRenderer::class);
    $this->dispatcher = $this->createMock(NewsletterDispatcher::class);
    $this->tokenStore = $this->createMock(RenderTokenStore::class);

    $this->controller = new NewsletterAdminApiController(
        $this->etm,
        $twig,
        $this->lifecycle,
        $this->renderer,
        $this->dispatcher,
        $this->tokenStore,
    );
}
```

Add the new mock properties to the test class:

```php
private EditionLifecycle $lifecycle;
private NewsletterRenderer $renderer;
private NewsletterDispatcher $dispatcher;
private RenderTokenStore $tokenStore;
```

- [ ] **Step 2: Run existing tests to verify they still pass**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`
Expected: PASS (all existing tests)

- [ ] **Step 3: Write test for previewToken**

```php
#[Test]
public function preview_token_returns_url(): void
{
    $mockEdition = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockEdition->method('id')->willReturn(1);
    $this->editionStorage->method('load')->with(1)->willReturn($mockEdition);

    $this->tokenStore->expects($this->once())
        ->method('create')
        ->with(1)
        ->willReturn('abc123token');

    $response = $this->controller->previewToken(
        ['id' => '1'],
        [],
        $this->createMock(AccountInterface::class),
        HttpRequest::create('/'),
    );

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertArrayHasKey('preview_url', $data);
    $this->assertStringContainsString('abc123token', $data['preview_url']);
}
```

- [ ] **Step 4: Implement previewToken**

```php
public function previewToken(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $id = (int) $params['id'];
    $storage = $this->entityTypeManager->getStorage('newsletter_edition');
    $edition = $storage->load($id);

    if ($edition === null) {
        return new Response(
            json_encode(['error' => 'Edition not found']),
            404,
            ['Content-Type' => 'application/json'],
        );
    }

    $token = $this->tokenStore->create($id);
    $previewUrl = "/newsletter/_internal/{$id}/print?token={$token}";

    return new Response(
        json_encode(['preview_url' => $previewUrl]),
        200,
        ['Content-Type' => 'application/json'],
    );
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php --filter=preview_token`
Expected: PASS

- [ ] **Step 6: Write test for generate**

```php
#[Test]
public function generate_triggers_renderer_and_returns_metadata(): void
{
    $mockEdition = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
    $mockEdition->method('id')->willReturn(1);
    $mockEdition->method('get')->willReturn('draft');
    $this->editionStorage->method('load')->with(1)->willReturn($mockEdition);

    $artifact = new class {
        public string $path = 'storage/newsletter/regional/1-1.pdf';
        public string $sha256 = 'abc123';
        public int $bytes = 85000;
    };

    $this->renderer->expects($this->once())
        ->method('render')
        ->with($mockEdition)
        ->willReturn($artifact);

    $this->lifecycle->expects($this->once())
        ->method('markGenerated');

    $response = $this->controller->generate(
        ['id' => '1'],
        [],
        $this->createMock(AccountInterface::class),
        HttpRequest::create('/', 'POST'),
    );

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertSame(85000, $data['bytes']);
}
```

- [ ] **Step 7: Implement generate**

```php
public function generate(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $id = (int) $params['id'];
    $storage = $this->entityTypeManager->getStorage('newsletter_edition');
    $edition = $storage->load($id);

    if ($edition === null) {
        return new Response(
            json_encode(['error' => 'Edition not found']),
            404,
            ['Content-Type' => 'application/json'],
        );
    }

    try {
        $artifact = $this->renderer->render($edition);
        $this->lifecycle->markGenerated($edition, $artifact);

        return new Response(
            json_encode([
                'path' => $artifact->path,
                'sha256' => $artifact->sha256,
                'bytes' => $artifact->bytes,
            ]),
            200,
            ['Content-Type' => 'application/json'],
        );
    } catch (\Exception $e) {
        return new Response(
            json_encode(['error' => 'PDF generation failed: ' . $e->getMessage()]),
            500,
            ['Content-Type' => 'application/json'],
        );
    }
}
```

- [ ] **Step 8: Implement download**

```php
public function download(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $id = (int) $params['id'];
    $storage = $this->entityTypeManager->getStorage('newsletter_edition');
    $edition = $storage->load($id);

    if ($edition === null) {
        return new Response(
            json_encode(['error' => 'Edition not found']),
            404,
            ['Content-Type' => 'application/json'],
        );
    }

    $pdfPath = $edition->get('pdf_path');
    if ($pdfPath === null || !file_exists($pdfPath)) {
        return new Response(
            json_encode(['error' => 'PDF not generated yet']),
            404,
            ['Content-Type' => 'application/json'],
        );
    }

    $filename = sprintf(
        'newsletter-%d-%d.pdf',
        $edition->get('volume'),
        $edition->get('issue_number'),
    );

    return new Response(
        file_get_contents($pdfPath),
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ],
    );
}
```

- [ ] **Step 9: Implement send**

```php
public function send(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $id = (int) $params['id'];
    $storage = $this->entityTypeManager->getStorage('newsletter_edition');
    $edition = $storage->load($id);

    if ($edition === null) {
        return new Response(
            json_encode(['error' => 'Edition not found']),
            404,
            ['Content-Type' => 'application/json'],
        );
    }

    $config = $this->config();
    $printerEmail = $config['printer_email'] ?? '';

    if ($printerEmail === '') {
        return new Response(
            json_encode(['error' => 'Printer email not configured']),
            400,
            ['Content-Type' => 'application/json'],
        );
    }

    try {
        $this->dispatcher->dispatch($edition, $printerEmail);
        $this->lifecycle->markSent($edition);

        return new Response(
            json_encode(['sent' => true, 'recipient' => $printerEmail]),
            200,
            ['Content-Type' => 'application/json'],
        );
    } catch (\Exception $e) {
        return new Response(
            json_encode(['error' => 'Send failed: ' . $e->getMessage()]),
            500,
            ['Content-Type' => 'application/json'],
        );
    }
}
```

- [ ] **Step 10: Run all tests**

Run: `cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php`
Expected: PASS (all tests)

- [ ] **Step 11: Commit**

```bash
git add src/Controller/NewsletterAdminApiController.php tests/App/Unit/Controller/NewsletterAdminApiControllerTest.php
git commit -m "feat: newsletter admin API — preview, generate, download, send"
```

---

### Task 5: Register Admin API Routes

**Files:**
- Modify: `src/Provider/AppServiceProvider.php`

- [ ] **Step 1: Add newsletter admin API routes to AppServiceProvider**

Find the newsletter route registration block in `AppServiceProvider.php` (near the existing `newsletter.editor.*` routes) and add the admin API routes after them:

```php
// Newsletter Admin API (JSON)
$router->get('/admin/api/newsletter', 'newsletter.admin.list')
    ->controller(NewsletterAdminApiController::class, 'listEditions')
    ->middleware(['auth', 'admin']);
$router->post('/admin/api/newsletter', 'newsletter.admin.create')
    ->controller(NewsletterAdminApiController::class, 'createEdition')
    ->middleware(['auth', 'admin']);
$router->get('/admin/api/newsletter/entity-search', 'newsletter.admin.entity_search')
    ->controller(NewsletterAdminApiController::class, 'entitySearch')
    ->middleware(['auth', 'admin']);
$router->get('/admin/api/newsletter/{id}', 'newsletter.admin.get')
    ->controller(NewsletterAdminApiController::class, 'getEdition')
    ->middleware(['auth', 'admin']);
$router->post('/admin/api/newsletter/{id}/items', 'newsletter.admin.add_item')
    ->controller(NewsletterAdminApiController::class, 'addItem')
    ->middleware(['auth', 'admin']);
$router->delete('/admin/api/newsletter/{id}/items/{itemId}', 'newsletter.admin.remove_item')
    ->controller(NewsletterAdminApiController::class, 'removeItem')
    ->middleware(['auth', 'admin']);
$router->post('/admin/api/newsletter/{id}/items/{itemId}/reorder', 'newsletter.admin.reorder_item')
    ->controller(NewsletterAdminApiController::class, 'reorderItem')
    ->middleware(['auth', 'admin']);
$router->get('/admin/api/newsletter/{id}/preview-token', 'newsletter.admin.preview_token')
    ->controller(NewsletterAdminApiController::class, 'previewToken')
    ->middleware(['auth', 'admin']);
$router->post('/admin/api/newsletter/{id}/generate', 'newsletter.admin.generate')
    ->controller(NewsletterAdminApiController::class, 'generate')
    ->middleware(['auth', 'admin']);
$router->get('/admin/api/newsletter/{id}/download', 'newsletter.admin.download')
    ->controller(NewsletterAdminApiController::class, 'download')
    ->middleware(['auth', 'admin']);
$router->post('/admin/api/newsletter/{id}/send', 'newsletter.admin.send')
    ->controller(NewsletterAdminApiController::class, 'send')
    ->middleware(['auth', 'admin']);
```

Add the use statement at the top of the file:

```php
use App\Controller\NewsletterAdminApiController;
```

Note: The `entity-search` route must come BEFORE the `{id}` route to avoid `entity-search` being captured as an ID.

- [ ] **Step 2: Clear manifest and run tests**

```bash
rm -f storage/framework/packages.php
cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit
```

Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add src/Provider/AppServiceProvider.php
git commit -m "feat: register newsletter admin API routes"
```

---

### Task 6: Vue 3 SPA Scaffold

**Files:**
- Create: `resources/newsletter-admin/package.json`
- Create: `resources/newsletter-admin/vite.config.js`
- Create: `resources/newsletter-admin/index.html`
- Create: `resources/newsletter-admin/src/main.js`
- Create: `resources/newsletter-admin/src/App.vue`
- Create: `resources/newsletter-admin/src/router.js`
- Create: `resources/newsletter-admin/src/api.js`

- [ ] **Step 1: Create package.json**

Create `resources/newsletter-admin/package.json`:

```json
{
  "name": "minoo-newsletter-admin",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "vue": "^3.5",
    "vue-router": "^4.5"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.2",
    "vite": "^6.0"
  }
}
```

- [ ] **Step 2: Create vite.config.js**

Create `resources/newsletter-admin/vite.config.js`:

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/admin/newsletter/',
  build: {
    outDir: '../../public/admin/newsletter',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
    proxy: {
      '/admin/api': 'http://localhost:8080',
      '/newsletter/_internal': 'http://localhost:8080',
    },
  },
})
```

- [ ] **Step 3: Create index.html**

Create `resources/newsletter-admin/index.html`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Newsletter Builder — Minoo Admin</title>
</head>
<body>
  <div id="app"></div>
  <script type="module" src="/src/main.js"></script>
</body>
</html>
```

- [ ] **Step 4: Create main.js**

Create `resources/newsletter-admin/src/main.js`:

```js
import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router.js'

createApp(App).use(router).mount('#app')
```

- [ ] **Step 5: Create router.js**

Create `resources/newsletter-admin/src/router.js`:

```js
import { createRouter, createWebHistory } from 'vue-router'
import EditionList from './pages/EditionList.vue'
import EditionDetail from './pages/EditionDetail.vue'

export const router = createRouter({
  history: createWebHistory('/admin/newsletter/'),
  routes: [
    { path: '/', component: EditionList },
    { path: '/:id', component: EditionDetail, props: true },
  ],
})
```

- [ ] **Step 6: Create api.js**

Create `resources/newsletter-admin/src/api.js`:

```js
const BASE = '/admin/api/newsletter'

async function request(path, options = {}) {
  const res = await fetch(`${BASE}${path}`, {
    headers: { 'Content-Type': 'application/json', ...options.headers },
    ...options,
  })
  if (!res.ok) {
    const err = await res.json().catch(() => ({ error: res.statusText }))
    throw new Error(err.error || res.statusText)
  }
  return res
}

export const api = {
  async listEditions() {
    const res = await request('')
    return res.json()
  },

  async getEdition(id) {
    const res = await request(`/${id}`)
    return res.json()
  },

  async createEdition(data) {
    const res = await request('', {
      method: 'POST',
      body: JSON.stringify(data),
    })
    return res.json()
  },

  async addItem(editionId, data) {
    const res = await request(`/${editionId}/items`, {
      method: 'POST',
      body: JSON.stringify(data),
    })
    return res.json()
  },

  async removeItem(editionId, itemId) {
    const res = await request(`/${editionId}/items/${itemId}`, {
      method: 'DELETE',
    })
    return res.json()
  },

  async reorderItem(editionId, itemId, position) {
    const res = await request(`/${editionId}/items/${itemId}/reorder`, {
      method: 'POST',
      body: JSON.stringify({ position }),
    })
    return res.json()
  },

  async entitySearch(query, types) {
    const params = new URLSearchParams({ q: query })
    if (types) params.set('types', types)
    const res = await request(`/entity-search?${params}`)
    return res.json()
  },

  async getPreviewToken(id) {
    const res = await request(`/${id}/preview-token`)
    return res.json()
  },

  async generate(id) {
    const res = await request(`/${id}/generate`, { method: 'POST' })
    return res.json()
  },

  downloadUrl(id) {
    return `${BASE}/${id}/download`
  },

  async send(id) {
    const res = await request(`/${id}/send`, { method: 'POST' })
    return res.json()
  },
}
```

- [ ] **Step 7: Create App.vue**

Create `resources/newsletter-admin/src/App.vue`:

```vue
<template>
  <div class="newsletter-admin">
    <header class="admin-header">
      <h1><router-link to="/">Newsletter Builder</router-link></h1>
      <a href="/admin">← Back to Admin</a>
    </header>
    <main>
      <router-view />
    </main>
  </div>
</template>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; color: #1a1a1a; }
.newsletter-admin { max-width: 1400px; margin: 0 auto; padding: 1rem; }
.admin-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 0; border-bottom: 2px solid #e0e0e0; margin-bottom: 1.5rem; }
.admin-header h1 { font-size: 1.25rem; }
.admin-header h1 a { color: inherit; text-decoration: none; }
.admin-header a { color: #555; font-size: 0.875rem; }
</style>
```

- [ ] **Step 8: Install dependencies and verify build**

```bash
cd /home/fsd42/dev/minoo/resources/newsletter-admin && npm install && npm run build
```

Expected: Build succeeds, files appear in `public/admin/newsletter/`

- [ ] **Step 9: Commit**

```bash
cd /home/fsd42/dev/minoo
git add resources/newsletter-admin/ public/admin/newsletter/
git commit -m "feat: vue 3 SPA scaffold for newsletter builder"
```

---

### Task 7: Edition List Page

**Files:**
- Create: `resources/newsletter-admin/src/pages/EditionList.vue`

- [ ] **Step 1: Create EditionList.vue**

Create `resources/newsletter-admin/src/pages/EditionList.vue`:

```vue
<template>
  <div class="edition-list">
    <div class="list-header">
      <h2>Editions</h2>
      <button class="btn-primary" @click="showCreate = !showCreate">
        {{ showCreate ? 'Cancel' : 'New Edition' }}
      </button>
    </div>

    <form v-if="showCreate" class="create-form" @submit.prevent="create">
      <div class="form-row">
        <label>
          Headline
          <input v-model="form.headline" type="text" required>
        </label>
      </div>
      <div class="form-row">
        <label>
          Volume
          <input v-model.number="form.volume" type="number" min="1" required>
        </label>
        <label>
          Issue
          <input v-model.number="form.issue_number" type="number" min="1" required>
        </label>
        <label>
          Community
          <input v-model="form.community_id" type="text" required>
        </label>
      </div>
      <button type="submit" class="btn-primary" :disabled="creating">
        {{ creating ? 'Creating...' : 'Create Edition' }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>

    <p v-if="loading">Loading editions...</p>
    <p v-else-if="editions.length === 0">No editions yet.</p>

    <table v-else class="editions-table">
      <thead>
        <tr>
          <th>Headline</th>
          <th>Vol / Issue</th>
          <th>Community</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="e in editions" :key="e.id" @click="$router.push(`/${e.id}`)" class="clickable">
          <td>{{ e.headline }}</td>
          <td>{{ e.volume }}.{{ e.issue_number }}</td>
          <td>{{ e.community_id }}</td>
          <td><span class="badge" :class="'badge-' + e.status">{{ e.status }}</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'

const router = useRouter()
const editions = ref([])
const loading = ref(true)
const showCreate = ref(false)
const creating = ref(false)
const error = ref('')

const form = ref({
  headline: '',
  volume: 1,
  issue_number: 1,
  community_id: 'manitoulin-regional',
})

onMounted(async () => {
  try {
    const data = await api.listEditions()
    editions.value = data.editions
  } finally {
    loading.value = false
  }
})

async function create() {
  creating.value = true
  error.value = ''
  try {
    const data = await api.createEdition(form.value)
    router.push(`/${data.edition.id}`)
  } catch (e) {
    error.value = e.message
  } finally {
    creating.value = false
  }
}
</script>

<style scoped>
.list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.create-form { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.form-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
.form-row label { flex: 1; display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; }
.form-row input { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; font-size: 0.875rem; }
.editions-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.editions-table th { text-align: left; padding: 0.75rem 1rem; background: #f9f9f9; font-size: 0.8rem; text-transform: uppercase; color: #666; }
.editions-table td { padding: 0.75rem 1rem; border-top: 1px solid #eee; }
.clickable { cursor: pointer; }
.clickable:hover { background: #f5f5ff; }
.badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.badge-draft { background: #e0e0e0; color: #555; }
.badge-curating { background: #fff3cd; color: #856404; }
.badge-generated { background: #d4edda; color: #155724; }
.badge-sent { background: #cce5ff; color: #004085; }
.btn-primary { padding: 0.5rem 1rem; background: #2c5282; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.875rem; }
.btn-primary:hover { background: #2b4c7e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.error { color: #c53030; margin-top: 0.5rem; font-size: 0.875rem; }
</style>
```

- [ ] **Step 2: Build and verify**

```bash
cd /home/fsd42/dev/minoo/resources/newsletter-admin && npm run build
```

- [ ] **Step 3: Commit**

```bash
cd /home/fsd42/dev/minoo
git add resources/newsletter-admin/src/pages/EditionList.vue public/admin/newsletter/
git commit -m "feat: newsletter builder — edition list + create page"
```

---

### Task 8: Edition Detail Page — Section/Item Management

**Files:**
- Create: `resources/newsletter-admin/src/pages/EditionDetail.vue`
- Create: `resources/newsletter-admin/src/components/SectionPanel.vue`
- Create: `resources/newsletter-admin/src/components/ItemCard.vue`

- [ ] **Step 1: Create ItemCard.vue**

Create `resources/newsletter-admin/src/components/ItemCard.vue`:

```vue
<template>
  <div class="item-card">
    <div class="item-info">
      <span class="item-position">#{{ item.position }}</span>
      <span class="item-type badge" :class="'badge-' + item.source_type">{{ item.source_type }}</span>
      <strong>{{ item.inline_title || item.editor_blurb || '(untitled)' }}</strong>
    </div>
    <div class="item-actions">
      <button class="btn-sm" @click="$emit('move-up')" :disabled="isFirst" title="Move up">↑</button>
      <button class="btn-sm" @click="$emit('move-down')" :disabled="isLast" title="Move down">↓</button>
      <button class="btn-sm btn-danger" @click="$emit('remove')" title="Remove">×</button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  item: { type: Object, required: true },
  isFirst: { type: Boolean, default: false },
  isLast: { type: Boolean, default: false },
})
defineEmits(['move-up', 'move-down', 'remove'])
</script>

<style scoped>
.item-card { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: white; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 0.25rem; }
.item-info { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; }
.item-position { color: #999; font-size: 0.75rem; min-width: 1.5rem; }
.item-actions { display: flex; gap: 0.25rem; }
.btn-sm { padding: 0.2rem 0.4rem; border: 1px solid #ccc; border-radius: 3px; background: white; cursor: pointer; font-size: 0.75rem; line-height: 1; }
.btn-sm:hover { background: #f0f0f0; }
.btn-sm:disabled { opacity: 0.3; cursor: not-allowed; }
.btn-danger { color: #c53030; border-color: #fed7d7; }
.btn-danger:hover { background: #fff5f5; }
.badge { padding: 0.1rem 0.4rem; border-radius: 3px; font-size: 0.7rem; font-weight: 600; }
.badge-inline { background: #e0e0e0; color: #555; }
.badge-post, .badge-event, .badge-teaching, .badge-dictionary_entry { background: #ebf8ff; color: #2b6cb0; }
</style>
```

- [ ] **Step 2: Create SectionPanel.vue**

Create `resources/newsletter-admin/src/components/SectionPanel.vue`:

```vue
<template>
  <div class="section-panel">
    <div class="section-header" @click="open = !open">
      <span class="toggle">{{ open ? '▾' : '▸' }}</span>
      <h3>{{ label }}</h3>
      <span class="item-count">{{ items.length }} items</span>
    </div>
    <div v-if="open" class="section-body">
      <ItemCard
        v-for="(item, i) in items"
        :key="item.id"
        :item="item"
        :is-first="i === 0"
        :is-last="i === items.length - 1"
        @move-up="$emit('reorder', item.id, item.position - 1)"
        @move-down="$emit('reorder', item.id, item.position + 1)"
        @remove="$emit('remove', item.id)"
      />
      <p v-if="items.length === 0" class="empty">No items in this section.</p>
      <button class="btn-add" @click="$emit('add')">+ Add Item</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ItemCard from './ItemCard.vue'

defineProps({
  label: { type: String, required: true },
  items: { type: Array, required: true },
})
defineEmits(['add', 'remove', 'reorder'])

const open = ref(true)
</script>

<style scoped>
.section-panel { margin-bottom: 0.75rem; }
.section-header { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; }
.section-header:hover { background: #f0f0f0; }
.section-header h3 { font-size: 0.875rem; flex: 1; text-transform: capitalize; }
.toggle { font-size: 0.75rem; color: #999; }
.item-count { font-size: 0.75rem; color: #999; }
.section-body { padding: 0.5rem 0 0.5rem 1.5rem; }
.empty { font-size: 0.8rem; color: #999; padding: 0.5rem 0; }
.btn-add { padding: 0.3rem 0.6rem; border: 1px dashed #ccc; border-radius: 4px; background: transparent; cursor: pointer; font-size: 0.8rem; color: #666; margin-top: 0.25rem; }
.btn-add:hover { background: #f5f5f5; border-color: #999; }
</style>
```

- [ ] **Step 3: Create EditionDetail.vue**

Create `resources/newsletter-admin/src/pages/EditionDetail.vue`:

```vue
<template>
  <div v-if="loading" class="loading">Loading edition...</div>
  <div v-else-if="!edition" class="error">Edition not found.</div>
  <div v-else class="edition-detail">
    <div class="detail-header">
      <div>
        <router-link to="/" class="back">← Editions</router-link>
        <h2>{{ edition.headline }}</h2>
        <p class="meta">
          Vol. {{ edition.volume }}, Issue {{ edition.issue_number }} ·
          {{ edition.community_id }} ·
          <span class="badge" :class="'badge-' + edition.status">{{ edition.status }}</span>
        </p>
      </div>
      <div class="action-bar">
        <button class="btn" @click="refreshPreview">Refresh Preview</button>
        <button class="btn-primary" @click="generatePdf" :disabled="generating">
          {{ generating ? 'Generating...' : 'Generate PDF' }}
        </button>
        <a
          v-if="edition.pdf_path"
          :href="downloadUrl"
          class="btn"
          download
        >Download PDF</a>
        <button
          v-if="edition.pdf_path"
          class="btn-primary"
          @click="sendToPrinter"
          :disabled="sending"
        >
          {{ sending ? 'Sending...' : 'Send to Printer' }}
        </button>
      </div>
    </div>

    <div class="editor-layout">
      <div class="sections-panel">
        <SectionPanel
          v-for="section in sectionOrder"
          :key="section"
          :label="sectionLabel(section)"
          :items="itemsBySection[section] || []"
          @add="openAddModal(section)"
          @remove="removeItem"
          @reorder="reorderItem"
        />
      </div>
      <div class="preview-panel">
        <iframe
          v-if="previewUrl"
          :src="previewUrl"
          class="preview-iframe"
        ></iframe>
        <p v-else class="preview-placeholder">Click "Refresh Preview" to load</p>
      </div>
    </div>

    <!-- Add Item Modal -->
    <div v-if="addModal.open" class="modal-overlay" @click.self="addModal.open = false">
      <div class="modal">
        <h3>Add Item to "{{ sectionLabel(addModal.section) }}"</h3>

        <div class="tabs">
          <button :class="{ active: addModal.tab === 'inline' }" @click="addModal.tab = 'inline'">Inline Content</button>
          <button :class="{ active: addModal.tab === 'entity' }" @click="addModal.tab = 'entity'">From Entity</button>
        </div>

        <form v-if="addModal.tab === 'inline'" @submit.prevent="addInlineItem">
          <label>
            Title
            <input v-model="addModal.inline_title" type="text" required>
          </label>
          <label>
            Body (HTML)
            <textarea v-model="addModal.inline_body" rows="6" required></textarea>
          </label>
          <label>
            Blurb (for TOC)
            <input v-model="addModal.editor_blurb" type="text">
          </label>
          <button type="submit" class="btn-primary" :disabled="addModal.saving">
            {{ addModal.saving ? 'Adding...' : 'Add Item' }}
          </button>
        </form>

        <div v-else>
          <label>
            Search
            <input v-model="addModal.search" type="text" @input="searchEntities" placeholder="Type to search...">
          </label>
          <ul v-if="addModal.results.length" class="search-results">
            <li v-for="r in addModal.results" :key="r.entity_type + '-' + r.entity_id" @click="addEntityItem(r)">
              <span class="badge" :class="'badge-' + r.entity_type">{{ r.entity_type }}</span>
              {{ r.label }}
            </li>
          </ul>
          <p v-else-if="addModal.search.length > 1" class="empty">No results.</p>
        </div>

        <button class="btn-cancel" @click="addModal.open = false">Cancel</button>
        <p v-if="addModal.error" class="error">{{ addModal.error }}</p>
      </div>
    </div>

    <p v-if="message" class="flash" :class="messageType">{{ message }}</p>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { api } from '../api.js'
import SectionPanel from '../components/SectionPanel.vue'

const props = defineProps({ id: { type: String, required: true } })

const loading = ref(true)
const edition = ref(null)
const itemsBySection = ref({})
const sectionOrder = ref([])
const previewUrl = ref('')
const generating = ref(false)
const sending = ref(false)
const message = ref('')
const messageType = ref('success')

const downloadUrl = computed(() => api.downloadUrl(props.id))

const SECTION_LABELS = {
  cover: 'Cover',
  editors_note: "Editor's Note",
  news: 'News',
  events: 'Events',
  teachings: 'Teachings',
  language: 'Language',
  community: 'Community',
  language_corner: 'Language Corner',
  jokes: 'Jokes',
  puzzles: 'Puzzles',
  horoscope: 'Horoscope',
  elder_spotlight: 'Elder Spotlight',
  back_page: 'Back Page',
}

function sectionLabel(key) {
  return SECTION_LABELS[key] || key
}

function flash(msg, type = 'success') {
  message.value = msg
  messageType.value = type
  setTimeout(() => { message.value = '' }, 4000)
}

async function loadEdition() {
  const data = await api.getEdition(props.id)
  edition.value = data.edition
  itemsBySection.value = data.items_by_section
  sectionOrder.value = data.section_order
}

onMounted(async () => {
  try { await loadEdition() }
  finally { loading.value = false }
})

async function refreshPreview() {
  const data = await api.getPreviewToken(props.id)
  previewUrl.value = data.preview_url
}

async function generatePdf() {
  generating.value = true
  try {
    const data = await api.generate(props.id)
    flash(`PDF generated — ${Math.round(data.bytes / 1024)} KB`)
    await loadEdition()
  } catch (e) {
    flash(e.message, 'error')
  } finally {
    generating.value = false
  }
}

async function sendToPrinter() {
  if (!confirm('Send this newsletter to the printer?')) return
  sending.value = true
  try {
    const data = await api.send(props.id)
    flash(`Sent to ${data.recipient}`)
    await loadEdition()
  } catch (e) {
    flash(e.message, 'error')
  } finally {
    sending.value = false
  }
}

async function removeItem(itemId) {
  if (!confirm('Remove this item?')) return
  await api.removeItem(props.id, itemId)
  await loadEdition()
}

async function reorderItem(itemId, newPosition) {
  if (newPosition < 1) return
  await api.reorderItem(props.id, itemId, newPosition)
  await loadEdition()
}

// Add item modal
const addModal = reactive({
  open: false,
  section: '',
  tab: 'inline',
  inline_title: '',
  inline_body: '',
  editor_blurb: '',
  search: '',
  results: [],
  saving: false,
  error: '',
})

function openAddModal(section) {
  addModal.open = true
  addModal.section = section
  addModal.tab = 'inline'
  addModal.inline_title = ''
  addModal.inline_body = ''
  addModal.editor_blurb = ''
  addModal.search = ''
  addModal.results = []
  addModal.saving = false
  addModal.error = ''
}

async function addInlineItem() {
  addModal.saving = true
  addModal.error = ''
  try {
    await api.addItem(props.id, {
      section: addModal.section,
      source_type: 'inline',
      inline_title: addModal.inline_title,
      inline_body: addModal.inline_body,
      editor_blurb: addModal.editor_blurb || addModal.inline_title,
    })
    addModal.open = false
    await loadEdition()
  } catch (e) {
    addModal.error = e.message
  } finally {
    addModal.saving = false
  }
}

let searchTimer = null
function searchEntities() {
  clearTimeout(searchTimer)
  if (addModal.search.length < 2) { addModal.results = []; return }
  searchTimer = setTimeout(async () => {
    const data = await api.entitySearch(addModal.search)
    addModal.results = data.results
  }, 300)
}

async function addEntityItem(entity) {
  addModal.saving = true
  addModal.error = ''
  try {
    await api.addItem(props.id, {
      section: addModal.section,
      source_type: entity.entity_type,
      source_id: entity.entity_id,
      editor_blurb: entity.label,
    })
    addModal.open = false
    await loadEdition()
  } catch (e) {
    addModal.error = e.message
  } finally {
    addModal.saving = false
  }
}
</script>

<style scoped>
.back { font-size: 0.8rem; color: #666; text-decoration: none; }
.back:hover { color: #333; }
.detail-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.meta { font-size: 0.875rem; color: #666; margin-top: 0.25rem; }
.action-bar { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.editor-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.sections-panel { overflow-y: auto; max-height: 80vh; }
.preview-panel { position: sticky; top: 1rem; }
.preview-iframe { width: 100%; height: 80vh; border: 1px solid #e0e0e0; border-radius: 4px; background: white; }
.preview-placeholder { text-align: center; padding: 3rem 1rem; color: #999; background: white; border: 1px dashed #ccc; border-radius: 4px; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal { background: white; padding: 1.5rem; border-radius: 8px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto; }
.modal h3 { margin-bottom: 1rem; }
.modal label { display: block; margin-bottom: 0.75rem; font-size: 0.875rem; font-weight: 500; }
.modal input, .modal textarea { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; font-size: 0.875rem; margin-top: 0.25rem; font-family: inherit; }
.tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.tabs button { padding: 0.4rem 0.8rem; border: 1px solid #ccc; border-radius: 4px; background: white; cursor: pointer; font-size: 0.8rem; }
.tabs button.active { background: #2c5282; color: white; border-color: #2c5282; }
.search-results { list-style: none; max-height: 200px; overflow-y: auto; border: 1px solid #eee; border-radius: 4px; }
.search-results li { padding: 0.5rem 0.75rem; cursor: pointer; font-size: 0.875rem; display: flex; gap: 0.5rem; align-items: center; }
.search-results li:hover { background: #f5f5ff; }
.btn-cancel { margin-top: 1rem; padding: 0.4rem 0.8rem; border: 1px solid #ccc; border-radius: 4px; background: white; cursor: pointer; }

.btn { padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 4px; background: white; cursor: pointer; font-size: 0.875rem; text-decoration: none; color: inherit; }
.btn:hover { background: #f0f0f0; }
.btn-primary { padding: 0.5rem 1rem; background: #2c5282; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.875rem; }
.btn-primary:hover { background: #2b4c7e; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.badge-draft { background: #e0e0e0; color: #555; }
.badge-curating { background: #fff3cd; color: #856404; }
.badge-generated { background: #d4edda; color: #155724; }
.badge-sent { background: #cce5ff; color: #004085; }

.flash { position: fixed; bottom: 1rem; right: 1rem; padding: 0.75rem 1.25rem; border-radius: 4px; font-size: 0.875rem; z-index: 200; }
.success { background: #d4edda; color: #155724; }
.error { color: #c53030; font-size: 0.875rem; }
.empty { font-size: 0.8rem; color: #999; padding: 0.5rem 0; }
.loading { text-align: center; padding: 3rem; color: #666; }
</style>
```

- [ ] **Step 4: Build and verify**

```bash
cd /home/fsd42/dev/minoo/resources/newsletter-admin && npm run build
```

- [ ] **Step 5: Commit**

```bash
cd /home/fsd42/dev/minoo
git add resources/newsletter-admin/src/ public/admin/newsletter/
git commit -m "feat: newsletter builder — edition detail with section/item management, entity picker, preview, actions"
```

---

### Task 9: SPA Fallback Route

**Files:**
- Modify: `src/Provider/AppServiceProvider.php`
- Modify: `src/Controller/NewsletterAdminApiController.php`

The admin newsletter SPA needs a catch-all route so Vue Router handles client-side routing.

- [ ] **Step 1: Add SPA fallback method to controller**

Add to `NewsletterAdminApiController`:

```php
public function spaFallback(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $indexPath = dirname(__DIR__, 2) . '/public/admin/newsletter/index.html';

    if (!file_exists($indexPath)) {
        return new Response('Newsletter admin not built. Run: cd resources/newsletter-admin && npm run build', 404);
    }

    return new Response(
        file_get_contents($indexPath),
        200,
        ['Content-Type' => 'text/html'],
    );
}
```

- [ ] **Step 2: Register the SPA fallback route**

Add to `AppServiceProvider.php` AFTER the newsletter admin API routes but BEFORE any wildcard routes:

```php
// Newsletter Admin SPA fallback (must be after API routes)
$router->get('/admin/newsletter', 'newsletter.admin.spa')
    ->controller(NewsletterAdminApiController::class, 'spaFallback')
    ->middleware(['auth', 'admin']);
$router->get('/admin/newsletter/{path}', 'newsletter.admin.spa_fallback')
    ->controller(NewsletterAdminApiController::class, 'spaFallback')
    ->middleware(['auth', 'admin'])
    ->where('path', '.*');
```

Note: These routes must come AFTER the `/admin/api/newsletter/*` routes so API calls aren't caught by the fallback.

- [ ] **Step 3: Clear manifest and run tests**

```bash
rm -f storage/framework/packages.php
cd /home/fsd42/dev/minoo && ./vendor/bin/phpunit
```

Expected: All tests pass

- [ ] **Step 4: Manual verification**

Start dev server and navigate to `http://localhost:8080/admin/newsletter`:
- SPA should load
- Navigating to `http://localhost:8080/admin/newsletter/1` should also load the SPA (not 404)
- API calls to `/admin/api/newsletter` should return JSON (not the SPA HTML)

- [ ] **Step 5: Commit**

```bash
git add src/Controller/NewsletterAdminApiController.php src/Provider/AppServiceProvider.php
git commit -m "feat: newsletter admin SPA serving with fallback route"
```

---

### Task 10: Add printer_email to config

**Files:**
- Modify: `config/newsletter.php`

- [ ] **Step 1: Check if printer_email exists**

Read `config/newsletter.php` and look for `printer_email`. If it already exists, skip this task.

- [ ] **Step 2: Add printer_email key**

Add to the newsletter config array:

```php
'printer_email' => $_ENV['NEWSLETTER_PRINTER_EMAIL'] ?? 'sales@ojgraphix.com',
```

- [ ] **Step 3: Commit**

```bash
git add config/newsletter.php
git commit -m "feat: add printer_email to newsletter config"
```

---

### Task 11: Port Issue #1 Through the Builder — End-to-End Validation

**Files:**
- No code changes — this is a workflow validation task

- [ ] **Step 1: Start both servers**

Terminal 1 (PHP):
```bash
cd /home/fsd42/dev/minoo && php -S localhost:8080 -t public public/index.php
```

Terminal 2 (Vite dev, optional — can use built assets):
```bash
cd /home/fsd42/dev/minoo/resources/newsletter-admin && npm run dev
```

- [ ] **Step 2: Navigate to newsletter builder**

Open `http://localhost:8080/admin/newsletter` (or `http://localhost:5173` if using Vite dev server).

- [ ] **Step 3: Create Vol. 1, Issue 1 edition**

Use the "New Edition" form:
- Headline: "Manitoulin Regional Elder Newsletter"
- Volume: 1
- Issue: 1
- Community: manitoulin-regional

- [ ] **Step 4: Add all ~25 items**

Refer to `scripts/backport-edition-1-content.php` for the content. Add each item via the "Add Item" modal for the correct section:
- Cover (1 item)
- Editor's Note (1 item)
- News (3 items)
- Events (5 items)
- Teachings (2 items)
- Language (1 item)
- Community (4 items)
- Language Corner (1 item)
- Jokes (1 item)
- Puzzles (1 item)
- Horoscope (1 item)
- Elder Spotlight (1 item)
- Back Page (1 item)

All items are inline content — paste the HTML from the backport script.

- [ ] **Step 5: Preview the edition**

Click "Refresh Preview" — verify the iframe shows the formatted newsletter matching the print template layout.

- [ ] **Step 6: Generate PDF**

Click "Generate PDF" — wait for completion. Verify:
- Success message with file size (~83 KB)
- Edition status changes to "generated"
- "Download PDF" button appears

- [ ] **Step 7: Compare output**

Download the generated PDF. Compare side-by-side with `storage/newsletter/regional/1-1.pdf` (the script-generated version):
- Same page count (12 pages)
- Same layout (cover, two-column events, horoscope grid, etc.)
- Same content in each section

- [ ] **Step 8: Document discrepancies**

If any differences found, create follow-up issues for CSS fine-tuning. The content and structure should match exactly since both use the same Twig template and PDF renderer.

- [ ] **Step 9: Test send-to-printer**

Click "Send to Printer" — verify:
- Confirmation dialog appears
- Success message shows printer email
- Edition status changes to "sent"

Note: This requires SendGrid to be configured. On local dev without SendGrid, verify the API call returns the expected error ("AuthMailer not configured") rather than crashing.

- [ ] **Step 10: Commit completion marker**

```bash
git commit --allow-empty -m "chore: milestone 2 complete — newsletter builder MVP validated with Issue #1"
```

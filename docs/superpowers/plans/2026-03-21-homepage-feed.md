# Homepage Feed Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the tab-based homepage with a single-column, infinite-scroll feed that interleaves events, groups, businesses, people, and featured items.

**Architecture:** Thin `FeedController` delegates to a 6-stage `FeedAssembler` pipeline (gather → transform → inject → filter → sort → paginate). All items — entity-backed and synthetic — flow through a unified `FeedItem` contract. Cursor-based pagination powers infinite scroll via a JSON API endpoint.

**Tech Stack:** PHP 8.4+, Twig 3, vanilla CSS (@layer architecture), vanilla JS (Intersection Observer, fetch, AbortController), SQLite (Waaseyaa entity storage)

**Spec:** `docs/superpowers/specs/2026-03-21-homepage-feed-design.md`

---

## File Structure

| File | Responsibility |
|---|---|
| `src/Feed/FeedContext.php` | Immutable value object — all inputs for a feed request |
| `src/Feed/FeedItem.php` | Unified feed item (entity-backed + synthetic) |
| `src/Feed/FeedResponse.php` | Assembler output (items + cursor + filter) |
| `src/Feed/FeedCursor.php` | Cursor encode/decode (base64 JSON) |
| `src/Feed/FeedItemFactory.php` | Entity → FeedItem transform + synthetic item creation |
| `src/Feed/EntityLoaderService.php` | Extracted entity loading (events, groups, businesses, people, featured) |
| `src/Feed/FeedAssemblerInterface.php` | Interface for DI |
| `src/Feed/FeedAssembler.php` | 6-stage pipeline implementing the interface |
| `src/Controller/FeedController.php` | Thin controller (index + api + explore) |
| `src/Provider/FeedServiceProvider.php` | Registers all feed services + routes |
| `templates/feed.html.twig` | New homepage template |
| `templates/components/feed-card.html.twig` | Unified card component |
| `templates/about.html.twig` | Dedicated about page |
| `public/css/minoo.css` | Feed component styles added to @layer components |
| `tests/Minoo/Unit/Feed/FeedContextTest.php` | FeedContext construction + defaults |
| `tests/Minoo/Unit/Feed/FeedCursorTest.php` | Cursor encode/decode roundtrip |
| `tests/Minoo/Unit/Feed/FeedItemFactoryTest.php` | Entity → FeedItem mapping per type |
| `tests/Minoo/Unit/Feed/FeedAssemblerTest.php` | Full pipeline + golden file sort |
| `tests/Minoo/Unit/Feed/EntityLoaderServiceTest.php` | Entity loading queries |
| `tests/Minoo/Unit/Controller/FeedControllerTest.php` | SSR + API responses |

---

### Task 1: FeedContext Value Object

**Files:**
- Create: `src/Feed/FeedContext.php`
- Test: `tests/Minoo/Unit/Feed/FeedContextTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Feed\FeedContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedContext::class)]
final class FeedContextTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $ctx = FeedContext::defaults();

        $this->assertNull($ctx->latitude);
        $this->assertNull($ctx->longitude);
        $this->assertSame('all', $ctx->activeFilter);
        $this->assertSame([], $ctx->requestedTypes);
        $this->assertNull($ctx->cursor);
        $this->assertSame(20, $ctx->limit);
        $this->assertFalse($ctx->isFirstVisit);
        $this->assertFalse($ctx->isAuthenticated);
    }

    #[Test]
    public function it_creates_with_location(): void
    {
        $ctx = new FeedContext(
            latitude: 46.5,
            longitude: -81.2,
            activeFilter: 'event',
            requestedTypes: ['event', 'group'],
            cursor: 'abc123',
            limit: 10,
            isFirstVisit: true,
            isAuthenticated: false,
        );

        $this->assertSame(46.5, $ctx->latitude);
        $this->assertSame(-81.2, $ctx->longitude);
        $this->assertSame('event', $ctx->activeFilter);
        $this->assertSame(['event', 'group'], $ctx->requestedTypes);
        $this->assertSame('abc123', $ctx->cursor);
        $this->assertSame(10, $ctx->limit);
        $this->assertTrue($ctx->isFirstVisit);
        $this->assertFalse($ctx->isAuthenticated);
    }

    #[Test]
    public function it_reports_has_location(): void
    {
        $with = new FeedContext(latitude: 46.5, longitude: -81.2);
        $without = FeedContext::defaults();

        $this->assertTrue($with->hasLocation());
        $this->assertFalse($without->hasLocation());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedContextTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed;

final readonly class FeedContext
{
    /**
     * @param string[] $requestedTypes
     */
    public function __construct(
        public ?float $latitude = null,
        public ?float $longitude = null,
        public string $activeFilter = 'all',
        public array $requestedTypes = [],
        public ?string $cursor = null,
        public int $limit = 20,
        public bool $isFirstVisit = false,
        public bool $isAuthenticated = false,
    ) {}

    public static function defaults(): self
    {
        return new self();
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedContextTest.php -v`
Expected: 3 tests, 3 assertions, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/FeedContext.php tests/Minoo/Unit/Feed/FeedContextTest.php
git commit -m "feat(feed): add FeedContext value object"
```

---

### Task 2: FeedCursor Encode/Decode

**Files:**
- Create: `src/Feed/FeedCursor.php`
- Test: `tests/Minoo/Unit/Feed/FeedCursorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Feed\FeedCursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedCursor::class)]
final class FeedCursorTest extends TestCase
{
    #[Test]
    public function it_encodes_and_decodes_roundtrip(): void
    {
        $sortKey = '9999:0000000002.30:01:09223372036854775707:event:42';
        $cursor = FeedCursor::encode($sortKey, 'event', 'event:42');
        $decoded = FeedCursor::decode($cursor);

        $this->assertSame($sortKey, $decoded['lastSortKey']);
        $this->assertSame('event', $decoded['lastType']);
        $this->assertSame('event:42', $decoded['lastId']);
    }

    #[Test]
    public function it_returns_null_for_invalid_cursor(): void
    {
        $this->assertNull(FeedCursor::decode('not-valid-base64-json'));
        $this->assertNull(FeedCursor::decode(''));
    }

    #[Test]
    public function it_returns_null_for_missing_fields(): void
    {
        $partial = base64_encode(json_encode(['lastSortKey' => 'x']));
        $this->assertNull(FeedCursor::decode($partial));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedCursorTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed;

final class FeedCursor
{
    public static function encode(string $lastSortKey, string $lastType, string $lastId): string
    {
        return base64_encode(json_encode([
            'lastSortKey' => $lastSortKey,
            'lastType' => $lastType,
            'lastId' => $lastId,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{lastSortKey: string, lastType: string, lastId: string}|null
     */
    public static function decode(string $cursor): ?array
    {
        if ($cursor === '') {
            return null;
        }

        $json = base64_decode($cursor, true);
        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (
            !is_array($data)
            || !isset($data['lastSortKey'], $data['lastType'], $data['lastId'])
            || !is_string($data['lastSortKey'])
            || !is_string($data['lastType'])
            || !is_string($data['lastId'])
        ) {
            return null;
        }

        return [
            'lastSortKey' => $data['lastSortKey'],
            'lastType' => $data['lastType'],
            'lastId' => $data['lastId'],
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedCursorTest.php -v`
Expected: 3 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/FeedCursor.php tests/Minoo/Unit/Feed/FeedCursorTest.php
git commit -m "feat(feed): add FeedCursor encode/decode"
```

---

### Task 3: FeedItem and FeedResponse

**Files:**
- Create: `src/Feed/FeedItem.php`
- Create: `src/Feed/FeedResponse.php`
- Test: `tests/Minoo/Unit/Feed/FeedItemTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Feed\FeedItem;
use Minoo\Feed\FeedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedItem::class)]
#[CoversClass(FeedResponse::class)]
final class FeedItemTest extends TestCase
{
    #[Test]
    public function it_creates_entity_backed_item(): void
    {
        $item = new FeedItem(
            id: 'event:42',
            type: 'event',
            title: 'Language Circle',
            url: '/events/language-circle',
            badge: 'Event',
            weight: 0,
            createdAt: new \DateTimeImmutable('2026-03-21'),
            sortKey: '9999:0000000002.30:01:09223372036854775707:event:42',
        );

        $this->assertSame('event:42', $item->id);
        $this->assertSame('event', $item->type);
        $this->assertSame('/events/language-circle', $item->url);
        $this->assertNull($item->entity);
        $this->assertSame([], $item->payload);
    }

    #[Test]
    public function it_creates_synthetic_item(): void
    {
        $item = new FeedItem(
            id: 'welcome:global',
            type: 'welcome',
            title: 'Welcome to Minoo',
            url: '/about',
            badge: 'Welcome',
            weight: 999,
            createdAt: new \DateTimeImmutable('2026-03-21'),
            sortKey: '9000:0099999.99:00:09223372036854775707:welcome:global',
        );

        $this->assertSame('welcome:global', $item->id);
        $this->assertSame(999, $item->weight);
        $this->assertTrue($item->isSynthetic());
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $item = new FeedItem(
            id: 'event:42',
            type: 'event',
            title: 'Language Circle',
            url: '/events/language-circle',
            badge: 'Event',
            weight: 0,
            createdAt: new \DateTimeImmutable('2026-03-21'),
            sortKey: 'key',
            subtitle: 'Tomorrow at 6 PM',
            distance: 2.3,
            communityName: 'Sagamok',
            meta: 'Community Centre',
            date: '2026-03-22T18:00:00',
        );

        $json = $item->toArray();

        $this->assertSame('event:42', $json['id']);
        $this->assertSame('Event', $json['badge']);
        $this->assertSame(2.3, $json['distance']);
        $this->assertArrayNotHasKey('weight', $json);
        $this->assertArrayNotHasKey('sortKey', $json);
    }

    #[Test]
    public function it_creates_feed_response(): void
    {
        $items = [
            new FeedItem(
                id: 'event:1', type: 'event', title: 'Test',
                url: '/events/test', badge: 'Event', weight: 0,
                createdAt: new \DateTimeImmutable(), sortKey: 'key',
            ),
        ];
        $response = new FeedResponse($items, 'cursor123', 'all');

        $this->assertCount(1, $response->items);
        $this->assertSame('cursor123', $response->nextCursor);
        $this->assertSame('all', $response->activeFilter);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedItemTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Write FeedItem**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Waaseyaa\Entity\ContentEntityBase;

final readonly class FeedItem
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $url,
        public string $badge,
        public int $weight,
        public \DateTimeImmutable $createdAt,
        public string $sortKey,
        public ?ContentEntityBase $entity = null,
        public ?string $subtitle = null,
        public ?string $date = null,
        public ?float $distance = null,
        public ?string $communityName = null,
        public ?string $meta = null,
        public array $payload = [],
    ) {}

    public function isSynthetic(): bool
    {
        return in_array($this->type, ['welcome', 'communities'], true);
    }

    /**
     * JSON-safe array for API responses. Excludes internal sort fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'badge' => $this->badge,
            'title' => $this->title,
            'url' => $this->url,
        ];

        if ($this->subtitle !== null) {
            $data['subtitle'] = $this->subtitle;
        }
        if ($this->distance !== null) {
            $data['distance'] = $this->distance;
        }
        if ($this->communityName !== null) {
            $data['communityName'] = $this->communityName;
        }
        if ($this->meta !== null) {
            $data['meta'] = $this->meta;
        }
        if ($this->date !== null) {
            $data['date'] = $this->date;
        }
        if ($this->payload !== []) {
            $data['payload'] = $this->payload;
        }

        return $data;
    }
}
```

- [ ] **Step 4: Write FeedResponse**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed;

final readonly class FeedResponse
{
    /**
     * @param list<FeedItem> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public string $activeFilter,
        public ?int $totalHint = null,
    ) {}
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedItemTest.php -v`
Expected: 4 tests, all PASS

- [ ] **Step 6: Commit**

```bash
git add src/Feed/FeedItem.php src/Feed/FeedResponse.php tests/Minoo/Unit/Feed/FeedItemTest.php
git commit -m "feat(feed): add FeedItem and FeedResponse value objects"
```

---

### Task 4: FeedItemFactory

**Files:**
- Create: `src/Feed/FeedItemFactory.php`
- Test: `tests/Minoo/Unit/Feed/FeedItemFactoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Entity\Event;
use Minoo\Entity\Group;
use Minoo\Entity\ResourcePerson;
use Minoo\Feed\FeedItem;
use Minoo\Feed\FeedItemFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedItemFactory::class)]
final class FeedItemFactoryTest extends TestCase
{
    private FeedItemFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new FeedItemFactory();
    }

    #[Test]
    public function it_creates_event_item(): void
    {
        $entity = new Event([
            'eid' => 42,
            'title' => 'Language Circle',
            'slug' => 'language-circle',
            'starts_at' => '2026-03-22T18:00:00',
            'location' => 'Community Centre',
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('event', $entity, typeSlot: 1);

        $this->assertSame('event:42', $item->id);
        $this->assertSame('event', $item->type);
        $this->assertSame('Language Circle', $item->title);
        $this->assertSame('/events/language-circle', $item->url);
        $this->assertSame('Event', $item->badge);
        $this->assertSame(0, $item->weight);
        $this->assertSame('Community Centre', $item->meta);
        $this->assertNotEmpty($item->sortKey);
    }

    #[Test]
    public function it_creates_group_item(): void
    {
        $entity = new Group([
            'gid' => 7,
            'name' => 'Youth Council',
            'slug' => 'youth-council',
            'type' => 'organization',
            'description' => 'Community leadership for youth voices in Sagamok',
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('group', $entity, typeSlot: 2);

        $this->assertSame('group:7', $item->id);
        $this->assertSame('group', $item->type);
        $this->assertSame('/groups/youth-council', $item->url);
        $this->assertSame('Group', $item->badge);
    }

    #[Test]
    public function it_creates_business_item(): void
    {
        $entity = new Group([
            'gid' => 10,
            'name' => 'Eagle Feather Crafts',
            'slug' => 'eagle-feather-crafts',
            'type' => 'business',
            'description' => 'Traditional crafts & gifts',
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('business', $entity, typeSlot: 3);

        $this->assertSame('business:10', $item->id);
        $this->assertSame('business', $item->type);
        $this->assertSame('/businesses/eagle-feather-crafts', $item->url);
        $this->assertSame('Business', $item->badge);
    }

    #[Test]
    public function it_creates_person_item(): void
    {
        $entity = new ResourcePerson([
            'rpid' => 5,
            'name' => 'Mary Toulouse',
            'slug' => 'mary-toulouse',
            'role' => 'Knowledge Keeper',
            'community' => 'Sagamok Anishnawbek',
            'consent_public' => 1,
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('person', $entity, typeSlot: 4);

        $this->assertSame('person:5', $item->id);
        $this->assertSame('person', $item->type);
        $this->assertSame('/people/mary-toulouse', $item->url);
        $this->assertSame('Person', $item->badge);
        $this->assertSame('Sagamok Anishnawbek', $item->communityName);
        $this->assertSame('Knowledge Keeper', $item->meta);
    }

    #[Test]
    public function it_computes_distance_when_location_provided(): void
    {
        $entity = new Event([
            'eid' => 1,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('event', $entity, typeSlot: 0, lat: 46.5, lon: -81.2, entityLat: 46.6, entityLon: -81.3);

        $this->assertNotNull($item->distance);
        $this->assertGreaterThan(0.0, $item->distance);
    }

    #[Test]
    public function it_creates_welcome_synthetic(): void
    {
        $item = $this->factory->createWelcome();

        $this->assertSame('welcome:global', $item->id);
        $this->assertSame('welcome', $item->type);
        $this->assertSame(999, $item->weight);
        $this->assertSame('/about', $item->url);
        $this->assertTrue($item->isSynthetic());
    }

    #[Test]
    public function it_creates_communities_synthetic(): void
    {
        $communities = [
            ['name' => 'Sagamok', 'slug' => 'sagamok-anishnawbek'],
            ['name' => 'Espanola', 'slug' => 'espanola'],
        ];

        $item = $this->factory->createCommunities($communities);

        $this->assertSame('communities:global', $item->id);
        $this->assertSame('communities', $item->type);
        $this->assertSame(500, $item->weight);
        $this->assertSame($communities, $item->payload['communities']);
    }

    #[Test]
    public function it_truncates_long_descriptions(): void
    {
        $longDesc = str_repeat('A', 100);
        $entity = new Group([
            'gid' => 1,
            'name' => 'Test',
            'slug' => 'test',
            'type' => 'organization',
            'description' => $longDesc,
            'status' => 1,
            'created_at' => time(),
        ]);

        $item = $this->factory->fromEntity('group', $entity, typeSlot: 0);

        $this->assertLessThanOrEqual(63, mb_strlen($item->meta ?? ''));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedItemFactoryTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Minoo\Support\GeoDistance;
use Waaseyaa\Entity\ContentEntityBase;

final class FeedItemFactory
{
    private const int MAX_META_LENGTH = 60;

    public function fromEntity(
        string $type,
        ContentEntityBase $entity,
        int $typeSlot,
        ?float $lat = null,
        ?float $lon = null,
        ?float $entityLat = null,
        ?float $entityLon = null,
    ): FeedItem {
        $distance = ($lat !== null && $lon !== null && $entityLat !== null && $entityLon !== null)
            ? GeoDistance::haversine($lat, $lon, $entityLat, $entityLon)
            : null;

        $createdAt = $this->resolveCreatedAt($entity);

        return match ($type) {
            'event' => $this->buildEvent($entity, $typeSlot, $distance, $createdAt),
            'group' => $this->buildGroup($entity, $typeSlot, $distance, $createdAt),
            'business' => $this->buildBusiness($entity, $typeSlot, $distance, $createdAt),
            'person' => $this->buildPerson($entity, $typeSlot, $distance, $createdAt),
            'featured' => $this->buildFeatured($entity, $typeSlot, $distance, $createdAt),
            default => throw new \InvalidArgumentException("Unknown feed item type: {$type}"),
        };
    }

    public function createWelcome(): FeedItem
    {
        return new FeedItem(
            id: 'welcome:global',
            type: 'welcome',
            title: 'Welcome to Minoo',
            url: '/about',
            badge: 'Welcome',
            weight: 999,
            createdAt: new \DateTimeImmutable(),
            sortKey: $this->buildSortKey(999, null, 0, new \DateTimeImmutable(), 'welcome:global'),
        );
    }

    /**
     * @param list<array{name: string, slug: string}> $communities
     */
    public function createCommunities(array $communities): FeedItem
    {
        return new FeedItem(
            id: 'communities:global',
            type: 'communities',
            title: 'Communities Near You',
            url: '/communities',
            badge: 'Communities',
            weight: 500,
            createdAt: new \DateTimeImmutable(),
            sortKey: $this->buildSortKey(500, null, 0, new \DateTimeImmutable(), 'communities:global'),
            payload: ['communities' => $communities],
        );
    }

    public function buildSortKey(
        int $weight,
        ?float $distance,
        int $typeSlot,
        \DateTimeImmutable $createdAt,
        string $id,
    ): string {
        return sprintf(
            '%04d:%010.2f:%02d:%020d:%s',
            9999 - $weight,
            $distance ?? 99999.99,
            $typeSlot,
            PHP_INT_MAX - $createdAt->getTimestamp(),
            $id,
        );
    }

    private function buildEvent(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'event:' . $entity->id();
        $startsAt = $entity->get('starts_at');

        return new FeedItem(
            id: $id,
            type: 'event',
            title: (string) ($entity->get('title') ?? ''),
            url: '/events/' . $entity->get('slug'),
            badge: 'Event',
            weight: 0,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(0, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            subtitle: $startsAt ? (new \DateTimeImmutable($startsAt))->format('F j, Y \a\t g:i A') : null,
            date: $startsAt,
            distance: $distance,
            meta: $entity->get('location'),
        );
    }

    private function buildGroup(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'group:' . $entity->id();

        return new FeedItem(
            id: $id,
            type: 'group',
            title: (string) ($entity->get('name') ?? ''),
            url: '/groups/' . $entity->get('slug'),
            badge: 'Group',
            weight: 0,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(0, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            distance: $distance,
            meta: $this->truncate($entity->get('description')),
        );
    }

    private function buildBusiness(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'business:' . $entity->id();

        return new FeedItem(
            id: $id,
            type: 'business',
            title: (string) ($entity->get('name') ?? ''),
            url: '/businesses/' . $entity->get('slug'),
            badge: 'Business',
            weight: 0,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(0, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            distance: $distance,
            meta: $this->truncate($entity->get('description')),
        );
    }

    private function buildPerson(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'person:' . $entity->id();

        return new FeedItem(
            id: $id,
            type: 'person',
            title: (string) ($entity->get('name') ?? ''),
            url: '/people/' . $entity->get('slug'),
            badge: 'Person',
            weight: 0,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(0, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            distance: $distance,
            communityName: $entity->get('community'),
            meta: $entity->get('role'),
        );
    }

    private function buildFeatured(ContentEntityBase $entity, int $typeSlot, ?float $distance, \DateTimeImmutable $createdAt): FeedItem
    {
        $id = 'featured:' . $entity->id();

        return new FeedItem(
            id: $id,
            type: 'featured',
            title: (string) ($entity->get('headline') ?? $entity->label()),
            url: '/',
            badge: 'Featured',
            weight: 1000,
            createdAt: $createdAt,
            sortKey: $this->buildSortKey(1000, $distance, $typeSlot, $createdAt, $id),
            entity: $entity,
            subtitle: $entity->get('subheadline'),
            distance: $distance,
        );
    }

    private function resolveCreatedAt(ContentEntityBase $entity): \DateTimeImmutable
    {
        $ts = $entity->get('created_at');
        if ($ts !== null && is_numeric($ts) && (int) $ts > 0) {
            return (new \DateTimeImmutable())->setTimestamp((int) $ts);
        }

        return new \DateTimeImmutable();
    }

    private function truncate(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        if (mb_strlen($text) <= self::MAX_META_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_META_LENGTH) . '…';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedItemFactoryTest.php -v`
Expected: 8 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/FeedItemFactory.php tests/Minoo/Unit/Feed/FeedItemFactoryTest.php
git commit -m "feat(feed): add FeedItemFactory with entity and synthetic item creation"
```

---

### Task 5: EntityLoaderService

**Files:**
- Create: `src/Feed/EntityLoaderService.php`
- Test: `tests/Minoo/Unit/Feed/EntityLoaderServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Feed\EntityLoaderService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(EntityLoaderService::class)]
final class EntityLoaderServiceTest extends TestCase
{
    #[Test]
    public function it_returns_empty_arrays_when_no_entities_exist(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(\Waaseyaa\Entity\EntityStorageInterface::class);
        $query = $this->createMock(\Waaseyaa\Entity\Query\EntityQueryInterface::class);

        $query->method('condition')->willReturnSelf();
        $query->method('sort')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        $query->method('execute')->willReturn([]);
        $storage->method('getQuery')->willReturn($query);
        $etm->method('getStorage')->willReturn($storage);

        $loader = new EntityLoaderService($etm);

        $this->assertSame([], $loader->loadUpcomingEvents(6));
        $this->assertSame([], $loader->loadGroups(6));
        $this->assertSame([], $loader->loadBusinesses(6));
        $this->assertSame([], $loader->loadPublicPeople(6));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/EntityLoaderServiceTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

Extract entity loading logic from `HomeController` into a dedicated service. The queries are identical — just moved to their own class.

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Waaseyaa\Entity\EntityTypeManager;

final class EntityLoaderService
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function loadUpcomingEvents(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('event');
        $now = date('Y-m-d\TH:i:s');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('starts_at', $now, '>=')
            ->sort('starts_at', 'ASC')
            ->range(0, $limit)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $events = array_values($storage->loadMultiple($ids));

        return array_values(array_filter($events, function ($entity) {
            $mediaId = $entity->get('media_id');
            if ($mediaId === null || $mediaId === '') {
                return true;
            }
            $status = $entity->get('copyright_status');
            return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
        }));
    }

    public function loadGroups(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('type', 'business', '!=')
            ->range(0, $limit)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    public function loadBusinesses(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('type', 'business')
            ->range(0, $limit)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    public function loadPublicPeople(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $ids = $storage->getQuery()
            ->condition('consent_public', 1)
            ->condition('status', 1)
            ->range(0, $limit)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    /** @return list<array{featured: mixed, entity: mixed, url: string}> */
    public function loadFeaturedItems(): array
    {
        try {
            $storage = $this->entityTypeManager->getStorage('featured_item');
        } catch (\Throwable) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('starts_at', $now, '<=')
            ->condition('ends_at', $now, '>=')
            ->sort('weight', 'DESC')
            ->execute();

        if ($ids === []) {
            return [];
        }

        $items = [];
        foreach ($storage->loadMultiple($ids) as $featured) {
            $entityType = $featured->get('entity_type');
            $entityId = $featured->get('entity_id');

            if ($entityType === null || $entityId === null) {
                continue;
            }

            try {
                $refStorage = $this->entityTypeManager->getStorage($entityType);
                $entity = $refStorage->load((int) $entityId);
            } catch (\Throwable) {
                continue;
            }

            if ($entity === null) {
                continue;
            }

            $items[] = ['featured' => $featured, 'entity' => $entity];
        }

        return $items;
    }

    public function loadAllCommunities(): array
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->execute();
        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/EntityLoaderServiceTest.php -v`
Expected: 1 test, PASS

- [ ] **Step 5: Commit**

```bash
git add src/Feed/EntityLoaderService.php tests/Minoo/Unit/Feed/EntityLoaderServiceTest.php
git commit -m "feat(feed): extract EntityLoaderService from HomeController"
```

---

### Task 6: FeedAssembler Pipeline

**Files:**
- Create: `src/Feed/FeedAssemblerInterface.php`
- Create: `src/Feed/FeedAssembler.php`
- Test: `tests/Minoo/Unit/Feed/FeedAssemblerTest.php`

- [ ] **Step 1: Write the interface**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed;

interface FeedAssemblerInterface
{
    public function assemble(FeedContext $ctx): FeedResponse;
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed;

use Minoo\Entity\Event;
use Minoo\Entity\Group;
use Minoo\Entity\ResourcePerson;
use Minoo\Feed\EntityLoaderService;
use Minoo\Feed\FeedAssembler;
use Minoo\Feed\FeedContext;
use Minoo\Feed\FeedItemFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedAssembler::class)]
final class FeedAssemblerTest extends TestCase
{
    private FeedAssembler $assembler;
    private EntityLoaderService $loader;

    protected function setUp(): void
    {
        $this->loader = $this->createMock(EntityLoaderService::class);
        $this->assembler = new FeedAssembler($this->loader, new FeedItemFactory());
    }

    #[Test]
    public function it_returns_empty_feed_when_no_content(): void
    {
        $this->loader->method('loadUpcomingEvents')->willReturn([]);
        $this->loader->method('loadGroups')->willReturn([]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = FeedContext::defaults();
        $response = $this->assembler->assemble($ctx);

        $this->assertSame([], $response->items);
        $this->assertNull($response->nextCursor);
        $this->assertSame('all', $response->activeFilter);
    }

    #[Test]
    public function it_interleaves_types_in_feed(): void
    {
        $event = new Event(['eid' => 1, 'title' => 'E1', 'slug' => 'e1', 'status' => 1, 'created_at' => time()]);
        $group = new Group(['gid' => 2, 'name' => 'G1', 'slug' => 'g1', 'type' => 'organization', 'status' => 1, 'created_at' => time()]);
        $person = new ResourcePerson(['rpid' => 3, 'name' => 'P1', 'slug' => 'p1', 'consent_public' => 1, 'status' => 1, 'created_at' => time()]);

        $this->loader->method('loadUpcomingEvents')->willReturn([$event]);
        $this->loader->method('loadGroups')->willReturn([$group]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([$person]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = FeedContext::defaults();
        $response = $this->assembler->assemble($ctx);

        $types = array_map(fn($item) => $item->type, $response->items);

        // Communities synthetic is always injected; entities interleaved by typeSlot
        $this->assertContains('communities', $types);
        $this->assertContains('event', $types);
        $this->assertContains('group', $types);
        $this->assertContains('person', $types);
    }

    #[Test]
    public function it_filters_by_type(): void
    {
        $event = new Event(['eid' => 1, 'title' => 'E1', 'slug' => 'e1', 'status' => 1, 'created_at' => time()]);
        $group = new Group(['gid' => 2, 'name' => 'G1', 'slug' => 'g1', 'type' => 'organization', 'status' => 1, 'created_at' => time()]);

        $this->loader->method('loadUpcomingEvents')->willReturn([$event]);
        $this->loader->method('loadGroups')->willReturn([$group]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = new FeedContext(activeFilter: 'event');
        $response = $this->assembler->assemble($ctx);

        $entityTypes = array_filter(
            array_map(fn($item) => $item->type, $response->items),
            fn($t) => !in_array($t, ['communities', 'welcome'], true),
        );

        foreach ($entityTypes as $t) {
            $this->assertSame('event', $t);
        }
    }

    #[Test]
    public function it_injects_welcome_card_on_first_visit(): void
    {
        $this->loader->method('loadUpcomingEvents')->willReturn([]);
        $this->loader->method('loadGroups')->willReturn([]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = new FeedContext(isFirstVisit: true);
        $response = $this->assembler->assemble($ctx);

        $types = array_map(fn($item) => $item->type, $response->items);
        $this->assertContains('welcome', $types);
    }

    #[Test]
    public function it_paginates_with_cursor(): void
    {
        $events = [];
        for ($i = 1; $i <= 25; $i++) {
            $events[] = new Event([
                'eid' => $i,
                'title' => "Event {$i}",
                'slug' => "event-{$i}",
                'status' => 1,
                'created_at' => time() - $i,
            ]);
        }

        $this->loader->method('loadUpcomingEvents')->willReturn($events);
        $this->loader->method('loadGroups')->willReturn([]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        // First page
        $ctx = new FeedContext(limit: 10);
        $page1 = $this->assembler->assemble($ctx);
        $this->assertNotNull($page1->nextCursor);

        // Second page
        $ctx2 = new FeedContext(cursor: $page1->nextCursor, limit: 10);
        $page2 = $this->assembler->assemble($ctx2);

        // No ID overlap between pages (excluding synthetic items)
        $page1Ids = array_map(fn($i) => $i->id, array_filter($page1->items, fn($i) => !$i->isSynthetic()));
        $page2Ids = array_map(fn($i) => $i->id, array_filter($page2->items, fn($i) => !$i->isSynthetic()));
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    #[Test]
    public function it_sorts_deterministically_golden_file(): void
    {
        // Fixed timestamps for deterministic output
        $base = 1711000000;
        $event = new Event(['eid' => 1, 'title' => 'E1', 'slug' => 'e1', 'status' => 1, 'created_at' => $base]);
        $group = new Group(['gid' => 2, 'name' => 'G1', 'slug' => 'g1', 'type' => 'organization', 'status' => 1, 'created_at' => $base - 100]);
        $person = new ResourcePerson(['rpid' => 3, 'name' => 'P1', 'slug' => 'p1', 'consent_public' => 1, 'status' => 1, 'created_at' => $base - 200]);

        $this->loader->method('loadUpcomingEvents')->willReturn([$event]);
        $this->loader->method('loadGroups')->willReturn([$group]);
        $this->loader->method('loadBusinesses')->willReturn([]);
        $this->loader->method('loadPublicPeople')->willReturn([$person]);
        $this->loader->method('loadFeaturedItems')->willReturn([]);
        $this->loader->method('loadAllCommunities')->willReturn([]);

        $ctx = FeedContext::defaults();
        $response = $this->assembler->assemble($ctx);

        // Communities card (weight 500) sorts before regular items (weight 0)
        $ids = array_map(fn($item) => $item->id, $response->items);
        $commIdx = array_search('communities:global', $ids, true);
        $entityIds = array_filter($ids, fn($id) => !str_starts_with($id, 'communities:'));

        $this->assertSame(0, $commIdx, 'Communities card should be first (weight 500)');
        $this->assertNotEmpty($entityIds);

        // Run twice — same order
        $response2 = $this->assembler->assemble($ctx);
        $ids2 = array_map(fn($item) => $item->id, $response2->items);
        $this->assertSame($ids, $ids2, 'Sort must be deterministic across runs');
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedAssemblerTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 4: Write FeedAssembler implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Minoo\Domain\Geo\Service\CommunityFinder;
use Minoo\Support\GeoDistance;

final class FeedAssembler implements FeedAssemblerInterface
{
    public function __construct(
        private readonly EntityLoaderService $loader,
        private readonly FeedItemFactory $factory,
    ) {}

    public function assemble(FeedContext $ctx): FeedResponse
    {
        // 1. Gather
        $events = $this->loader->loadUpcomingEvents($ctx->limit * 2);
        $groups = $this->loader->loadGroups($ctx->limit * 2);
        $businesses = $this->loader->loadBusinesses($ctx->limit * 2);
        $people = $this->loader->loadPublicPeople($ctx->limit * 2);
        $featuredRaw = $this->loader->loadFeaturedItems();
        $communities = $this->loader->loadAllCommunities();

        // Build community coordinate map for distance calculation
        $communityCoords = $this->buildCommunityCoords($communities);

        // 2. Transform — assign typeSlots cyclically for round-robin
        $items = [];
        $slotCounter = 0;

        foreach ($featuredRaw as $raw) {
            $items[] = $this->factory->fromEntity(
                'featured',
                $raw['featured'],
                typeSlot: $slotCounter++ % 5,
            );
        }

        $sources = [
            ['type' => 'event', 'entities' => $events, 'communityField' => 'community_id'],
            ['type' => 'group', 'entities' => $groups, 'communityField' => 'community_id'],
            ['type' => 'business', 'entities' => $businesses, 'communityField' => 'community_id'],
            ['type' => 'person', 'entities' => $people, 'communityField' => 'community'],
        ];

        foreach ($sources as $sourceIdx => $source) {
            foreach ($source['entities'] as $entity) {
                $coords = $this->resolveEntityCoords($entity, $source['communityField'], $communityCoords);
                $items[] = $this->factory->fromEntity(
                    $source['type'],
                    $entity,
                    typeSlot: $sourceIdx,
                    lat: $ctx->latitude,
                    lon: $ctx->longitude,
                    entityLat: $coords['lat'] ?? null,
                    entityLon: $coords['lon'] ?? null,
                );
            }
        }

        // 3. Inject synthetic items
        $communityData = array_map(fn($c) => [
            'name' => $c->get('name') ?? '',
            'slug' => $c->get('slug') ?? '',
        ], $ctx->hasLocation()
            ? array_slice($this->sortCommunitiesByDistance($communities, $ctx->latitude, $ctx->longitude), 0, 6)
            : array_slice($communities, 0, 6)
        );
        $items[] = $this->factory->createCommunities($communityData);

        if ($ctx->isFirstVisit) {
            $items[] = $this->factory->createWelcome();
        }

        // 4. Filter
        if ($ctx->activeFilter !== 'all') {
            $items = array_values(array_filter($items, function (FeedItem $item) use ($ctx) {
                if ($item->isSynthetic()) {
                    return true;
                }
                return $item->type === $ctx->activeFilter;
            }));
        }

        // 5. Sort
        usort($items, fn(FeedItem $a, FeedItem $b) => strcmp($a->sortKey, $b->sortKey));

        // 6. Paginate
        $startIdx = 0;
        if ($ctx->cursor !== null) {
            $cursorData = FeedCursor::decode($ctx->cursor);
            if ($cursorData !== null) {
                // Find position after cursor
                foreach ($items as $idx => $item) {
                    if ($item->sortKey === $cursorData['lastSortKey'] && $item->id === $cursorData['lastId']) {
                        $startIdx = $idx + 1;
                        break;
                    }
                }
            }
        }

        $pageItems = array_slice($items, $startIdx, $ctx->limit);

        $nextCursor = null;
        if ($pageItems !== [] && ($startIdx + $ctx->limit) < count($items)) {
            $lastItem = end($pageItems);
            $nextCursor = FeedCursor::encode($lastItem->sortKey, $lastItem->type, $lastItem->id);
        }

        return new FeedResponse(
            items: $pageItems,
            nextCursor: $nextCursor,
            activeFilter: $ctx->activeFilter,
        );
    }

    /** @return array<string|int, array{lat: float, lon: float}> */
    private function buildCommunityCoords(array $communities): array
    {
        $coords = [];
        foreach ($communities as $c) {
            $cLat = $c->get('latitude');
            $cLon = $c->get('longitude');
            if ($cLat !== null && $cLon !== null) {
                $coords[(int) $c->id()] = ['lat' => (float) $cLat, 'lon' => (float) $cLon];
                $name = $c->get('name');
                if ($name !== null) {
                    $coords['name:' . $name] = ['lat' => (float) $cLat, 'lon' => (float) $cLon];
                }
            }
        }
        return $coords;
    }

    /** @return array{lat: ?float, lon: ?float} */
    private function resolveEntityCoords(mixed $entity, string $communityField, array $communityCoords): array
    {
        if ($communityField === 'community') {
            $name = $entity->get('community');
            $coords = $name !== null ? ($communityCoords['name:' . $name] ?? null) : null;
        } else {
            $cid = $entity->get($communityField);
            $coords = $cid !== null ? ($communityCoords[(int) $cid] ?? null) : null;
        }

        return $coords ?? ['lat' => null, 'lon' => null];
    }

    private function sortCommunitiesByDistance(array $communities, ?float $lat, ?float $lon): array
    {
        if ($lat === null || $lon === null) {
            return $communities;
        }

        usort($communities, function ($a, $b) use ($lat, $lon) {
            $aLat = $a->get('latitude');
            $aLon = $a->get('longitude');
            $bLat = $b->get('latitude');
            $bLon = $b->get('longitude');

            $distA = ($aLat !== null && $aLon !== null) ? GeoDistance::haversine($lat, $lon, (float) $aLat, (float) $aLon) : PHP_FLOAT_MAX;
            $distB = ($bLat !== null && $bLon !== null) ? GeoDistance::haversine($lat, $lon, (float) $bLat, (float) $bLon) : PHP_FLOAT_MAX;

            return $distA <=> $distB;
        });

        return $communities;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Feed/FeedAssemblerTest.php -v`
Expected: 6 tests, all PASS

- [ ] **Step 6: Commit**

```bash
git add src/Feed/FeedAssemblerInterface.php src/Feed/FeedAssembler.php tests/Minoo/Unit/Feed/FeedAssemblerTest.php
git commit -m "feat(feed): add FeedAssembler 6-stage pipeline"
```

---

### Task 7: FeedController

**Files:**
- Create: `src/Controller/FeedController.php`
- Test: `tests/Minoo/Unit/Controller/FeedControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\FeedController;
use Minoo\Feed\FeedAssemblerInterface;
use Minoo\Feed\FeedItem;
use Minoo\Feed\FeedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(FeedController::class)]
final class FeedControllerTest extends TestCase
{
    #[Test]
    public function index_renders_feed_template(): void
    {
        $twig = $this->createMock(Environment::class);
        $assembler = $this->createMock(FeedAssemblerInterface::class);

        $assembler->method('assemble')->willReturn(
            new FeedResponse([], null, 'all')
        );
        $twig->expects($this->once())
            ->method('render')
            ->with('feed.html.twig', $this->anything())
            ->willReturn('<html>feed</html>');

        $controller = new FeedController($assembler, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/');

        $response = $controller->index([], [], $account, $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function api_returns_json(): void
    {
        $twig = $this->createMock(Environment::class);
        $assembler = $this->createMock(FeedAssemblerInterface::class);

        $item = new FeedItem(
            id: 'event:1', type: 'event', title: 'Test',
            url: '/events/test', badge: 'Event', weight: 0,
            createdAt: new \DateTimeImmutable(), sortKey: 'key',
        );

        $assembler->method('assemble')->willReturn(
            new FeedResponse([$item], 'cursor123', 'all')
        );

        // API needs Twig to render card HTML fragments
        $twig->method('render')->willReturn('<article>card</article>');

        $controller = new FeedController($assembler, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/api/feed?filter=all');

        $response = $controller->api([], [], $account, $request);

        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $json);
        $this->assertArrayHasKey('nextCursor', $json);
        $this->assertArrayHasKey('activeFilter', $json);
        $this->assertSame('cursor123', $json['nextCursor']);
    }

    #[Test]
    public function explore_redirects(): void
    {
        $twig = $this->createMock(Environment::class);
        $assembler = $this->createMock(FeedAssemblerInterface::class);

        $controller = new FeedController($assembler, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/explore?type=events&q=pow+wow');

        $response = $controller->explore([], ['type' => 'events', 'q' => 'pow wow'], $account, $request);

        $this->assertSame(302, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/FeedControllerTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Feed\FeedAssemblerInterface;
use Minoo\Feed\FeedContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\SsrResponse;

final class FeedController
{
    public function __construct(
        private readonly FeedAssemblerInterface $assembler,
        private readonly Environment $twig,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $ctx = $this->buildContext($request, $query);
        $response = $this->assembler->assemble($ctx);

        $html = $this->twig->render('feed.html.twig', [
            'path' => '/',
            'account' => $account,
            'response' => $response,
            'nextCursor' => $response->nextCursor,
            'activeFilter' => $response->activeFilter,
        ]);

        $ssrResponse = new SsrResponse(content: $html);

        if ($ctx->isFirstVisit) {
            $ssrResponse->headers->setCookie(
                new \Symfony\Component\HttpFoundation\Cookie(
                    'minoo_fv', '1', time() + 86400 * 365, '/',
                )
            );
        }

        return $ssrResponse;
    }

    public function api(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $ctx = $this->buildContext($request, $query);
        $response = $this->assembler->assemble($ctx);

        $items = array_map(function ($item) {
            $data = $item->toArray();
            $data['html'] = $this->twig->render('components/feed-card.html.twig', ['item' => $item]);
            return $data;
        }, $response->items);

        $json = json_encode([
            'items' => $items,
            'nextCursor' => $response->nextCursor,
            'activeFilter' => $response->activeFilter,
        ], JSON_THROW_ON_ERROR);

        return new SsrResponse(
            content: $json,
            statusCode: 200,
            headers: ['Content-Type' => 'application/json'],
        );
    }

    public function explore(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $type = $query['type'] ?? 'all';
        $q = trim($query['q'] ?? '');

        $targets = [
            'businesses' => '/groups',
            'people' => '/people',
            'events' => '/events',
            'all' => '/groups',
        ];

        $target = $targets[$type] ?? '/groups';

        if ($q !== '') {
            $target .= '?' . http_build_query(['q' => $q]);
        }

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $target]);
    }

    private function buildContext(HttpRequest $request, array $query): FeedContext
    {
        $locationCookie = $request->cookies->get('minoo_loc');
        $lat = null;
        $lon = null;

        if ($locationCookie !== null) {
            try {
                $loc = json_decode($locationCookie, true, 4, JSON_THROW_ON_ERROR);
                $lat = isset($loc['lat']) ? (float) $loc['lat'] : null;
                $lon = isset($loc['lon']) ? (float) $loc['lon'] : null;
            } catch (\JsonException) {
                // Invalid cookie — ignore
            }
        }

        $isFirstVisit = $request->cookies->get('minoo_fv') === null;

        return new FeedContext(
            latitude: $lat,
            longitude: $lon,
            activeFilter: $query['filter'] ?? 'all',
            cursor: $query['cursor'] ?? null,
            limit: min((int) ($query['limit'] ?? 20), 50),
            isFirstVisit: $isFirstVisit,
            isAuthenticated: false,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/FeedControllerTest.php -v`
Expected: 3 tests, all PASS

- [ ] **Step 5: Commit**

```bash
git add src/Controller/FeedController.php tests/Minoo/Unit/Controller/FeedControllerTest.php
git commit -m "feat(feed): add FeedController with SSR and JSON endpoints"
```

---

### Task 8: FeedServiceProvider + Route Wiring

**Files:**
- Create: `src/Provider/FeedServiceProvider.php`
- Modify: `src/Provider/CommunityServiceProvider.php` — remove `home` and `explore.redirect` routes

- [ ] **Step 1: Write the service provider**

```php
<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class FeedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types — this provider handles feed services and routes only
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'feed.index',
            RouteBuilder::create('/')
                ->controller('Minoo\\Controller\\FeedController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'feed.api',
            RouteBuilder::create('/api/feed')
                ->controller('Minoo\\Controller\\FeedController::api')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explore.redirect',
            RouteBuilder::create('/explore')
                ->controller('Minoo\\Controller\\FeedController::explore')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'home.alias',
            RouteBuilder::create('/home')
                ->controller('Minoo\\Controller\\FeedController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}
```

- [ ] **Step 2: Remove old routes from CommunityServiceProvider**

In `src/Provider/CommunityServiceProvider.php`, remove these two route registrations:
- The `home` route (lines ~52-60 pointing to `HomeController::index`)
- The `explore.redirect` route (lines ~119-126 pointing to `HomeController::explore`)

Keep all community routes intact.

- [ ] **Step 3: Delete stale manifest cache and verify**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 4: Run all tests to verify nothing is broken**

Run: `./vendor/bin/phpunit -v`
Expected: All existing tests pass (some HomeController tests may need updating — see Task 11)

- [ ] **Step 5: Commit**

```bash
git add src/Provider/FeedServiceProvider.php src/Provider/CommunityServiceProvider.php
git commit -m "feat(feed): add FeedServiceProvider, move routes from CommunityServiceProvider"
```

---

### Task 9: Templates

**Files:**
- Create: `templates/feed.html.twig`
- Create: `templates/components/feed-card.html.twig`
- Create: `templates/about.html.twig`

- [ ] **Step 1: Create feed-card.html.twig**

```twig
{# Unified feed card component — all types render through this template #}
{% if item.type == 'welcome' %}
  <article class="feed-card feed-card--welcome" data-id="{{ item.id }}">
    <h3 class="feed-card__title">{{ item.title }}</h3>
    <p class="feed-card__meta">{{ trans('page.about_body') }}</p>
    <a href="{{ lang_url(item.url) }}" class="btn btn--secondary">{{ trans('page.about_cta') }}</a>
  </article>
{% elseif item.type == 'communities' %}
  <article class="feed-card feed-card--communities" data-id="{{ item.id }}">
    <h3 class="feed-card__title">{{ item.title }}</h3>
    <div class="feed-pills">
      {% for community in item.payload.communities|default([]) %}
        <a href="{{ lang_url('/communities/' ~ community.slug) }}" class="feed-pill">{{ community.name }}</a>
      {% endfor %}
    </div>
  </article>
{% elseif item.type == 'featured' %}
  <article class="feed-card feed-card--featured" data-id="{{ item.id }}">
    <span class="feed-badge feed-badge--featured">{{ item.badge }}</span>
    <h3 class="feed-card__title"><a href="{{ lang_url(item.url) }}">{{ item.title }}</a></h3>
    {% if item.subtitle %}<p class="feed-card__subtitle">{{ item.subtitle }}</p>{% endif %}
  </article>
{% else %}
  <article class="feed-card feed-card--{{ item.type }}" data-id="{{ item.id }}">
    <div class="feed-card__header">
      <span class="feed-badge feed-badge--{{ item.type }}">{{ item.badge }}</span>
      {% if item.distance is not null %}<span class="feed-card__distance">{{ item.distance|round(1) }} km</span>{% endif %}
    </div>
    <h3 class="feed-card__title"><a href="{{ lang_url(item.url) }}">{{ item.title }}</a></h3>
    {% if item.subtitle %}<p class="feed-card__subtitle">{{ item.subtitle }}</p>{% endif %}
    {% if item.communityName %}<p class="feed-card__community">{{ item.communityName }}</p>{% endif %}
    {% if item.meta %}<p class="feed-card__meta">{{ item.meta }}</p>{% endif %}
  </article>
{% endif %}
```

- [ ] **Step 2: Create feed.html.twig**

```twig
{% extends "base.html.twig" %}

{% block title %}{{ trans('page.title') }} — Minoo{% endblock %}

{% block content %}
  {# === Compact Sticky Header === #}
  <header class="feed-header">
    <strong class="feed-header__logo">Minoo</strong>
    <form class="feed-header__search" action="{{ lang_url('/explore') }}" method="get" role="search" aria-label="{{ trans('page.search_label') }}">
      <label for="feed-q" class="visually-hidden">{{ trans('page.search_query') }}</label>
      <input id="feed-q" name="q" type="search" class="feed-header__input" placeholder="{{ trans('page.search_placeholder') }}" />
      <button type="submit" class="feed-header__submit">{{ trans('page.search_button') }}</button>
    </form>
  </header>

  {# === Filter Chips === #}
  <nav class="feed-chips" aria-label="Filter feed">
    <button class="feed-chip{{ activeFilter == 'all' ? ' feed-chip--active' : '' }}" data-type="all">{{ trans('page.search_all') }}</button>
    <button class="feed-chip{{ activeFilter == 'event' ? ' feed-chip--active' : '' }}" data-type="event">{{ trans('page.tab_events') }}</button>
    <button class="feed-chip{{ activeFilter == 'group' ? ' feed-chip--active' : '' }}" data-type="group">{{ trans('page.tab_groups') }}</button>
    <button class="feed-chip{{ activeFilter == 'business' ? ' feed-chip--active' : '' }}" data-type="business">{{ trans('page.search_businesses') }}</button>
    <button class="feed-chip{{ activeFilter == 'person' ? ' feed-chip--active' : '' }}" data-type="person">{{ trans('page.tab_people') }}</button>
  </nav>

  {# === Feed Container === #}
  <div class="feed-container" id="feed" data-next-cursor="{{ nextCursor }}" data-active-filter="{{ activeFilter }}">
    {% for item in response.items %}
      {% include "components/feed-card.html.twig" with { item: item } only %}
    {% endfor %}
  </div>

  {# === Loading Sentinel === #}
  {% if nextCursor %}
    <div class="feed-sentinel" id="feed-sentinel">
      <div class="feed-card feed-card--loading" aria-hidden="true">
        <div class="feed-card__skeleton"></div>
      </div>
    </div>
  {% endif %}

  {# === Infinite Scroll (Progressive Enhancement) === #}
  <script>
  (function() {
    const feed = document.getElementById('feed');
    const sentinel = document.getElementById('feed-sentinel');
    if (!feed || !sentinel) return;

    let nextCursor = feed.dataset.nextCursor || null;
    let activeFilter = feed.dataset.activeFilter || 'all';
    let controller = null;
    let fetchToken = 0;
    let loading = false;

    const seenIds = new Set();
    feed.querySelectorAll('[data-id]').forEach(el => seenIds.add(el.dataset.id));

    async function loadMore() {
      if (loading || !nextCursor) return;
      loading = true;
      const token = ++fetchToken;

      if (controller) controller.abort();
      controller = new AbortController();

      try {
        const url = '/api/feed?filter=' + encodeURIComponent(activeFilter)
          + '&cursor=' + encodeURIComponent(nextCursor);
        const res = await fetch(url, { signal: controller.signal });
        if (token !== fetchToken) return;
        const data = await res.json();

        for (const item of data.items) {
          if (seenIds.has(item.id)) continue;
          seenIds.add(item.id);
          feed.insertAdjacentHTML('beforeend', item.html);
        }

        nextCursor = data.nextCursor;
        if (!nextCursor) {
          sentinel.innerHTML = '<p class="feed-end">You\'re all caught up</p>';
        }
      } catch (e) {
        if (e.name !== 'AbortError') console.error('Feed load error:', e);
      } finally {
        loading = false;
      }
    }

    const observer = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) loadMore();
    }, { rootMargin: '200px' });
    observer.observe(sentinel);

    // Filter chips
    document.querySelectorAll('.feed-chip').forEach(chip => {
      chip.addEventListener('click', function() {
        const type = this.dataset.type;
        if (type === activeFilter) return;

        activeFilter = type;
        nextCursor = null;
        feed.dataset.activeFilter = type;
        feed.innerHTML = '';
        seenIds.clear();

        document.querySelectorAll('.feed-chip').forEach(c => c.classList.remove('feed-chip--active'));
        this.classList.add('feed-chip--active');

        // Reset sentinel
        sentinel.innerHTML = '<div class="feed-card feed-card--loading" aria-hidden="true"><div class="feed-card__skeleton"></div></div>';

        // Fetch first page with new filter
        fetchToken++;
        if (controller) controller.abort();
        controller = new AbortController();
        loading = false;

        fetch('/api/feed?filter=' + encodeURIComponent(activeFilter), { signal: controller.signal })
          .then(r => r.json())
          .then(data => {
            for (const item of data.items) {
              if (seenIds.has(item.id)) continue;
              seenIds.add(item.id);
              feed.insertAdjacentHTML('beforeend', item.html);
            }
            nextCursor = data.nextCursor;
            feed.dataset.nextCursor = nextCursor || '';
            if (!nextCursor) {
              sentinel.innerHTML = '<p class="feed-end">You\'re all caught up</p>';
            }
          })
          .catch(e => { if (e.name !== 'AbortError') console.error(e); });
      });
    });
  })();
  </script>
{% endblock %}
```

- [ ] **Step 3: Create about.html.twig**

```twig
{% extends "base.html.twig" %}

{% block title %}{{ trans('about.title') }} — Minoo{% endblock %}

{% block content %}
  <article class="about-page">
    <h1>{{ trans('about.heading') }}</h1>
    <p>{{ trans('about.body') }}</p>
    <a href="{{ lang_url('/') }}" class="btn btn--secondary">{{ trans('about.back_to_feed') }}</a>
  </article>
{% endblock %}
```

- [ ] **Step 4: Commit**

```bash
git add templates/feed.html.twig templates/components/feed-card.html.twig templates/about.html.twig
git commit -m "feat(feed): add feed and card templates with infinite scroll JS"
```

---

### Task 10: CSS Feed Components

**Files:**
- Modify: `public/css/minoo.css` — add feed component styles to `@layer components`

- [ ] **Step 1: Add feed CSS custom properties to `@layer tokens`**

Add after existing custom properties:

```css
/* Feed type colors */
--color-feed-event: oklch(0.78 0.12 75);      /* amber */
--color-feed-business: oklch(0.68 0.15 35);    /* coral */
--color-feed-group: oklch(0.72 0.10 175);      /* teal */
--color-feed-person: oklch(0.72 0.14 290);     /* violet */
--color-feed-featured: oklch(0.78 0.10 220);   /* sky */
```

- [ ] **Step 2: Add feed component styles to `@layer components`**

```css
/* === Feed Components === */
.feed-header {
  position: sticky;
  top: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  gap: var(--space-m);
  padding: var(--space-s) var(--space-m);
  background: var(--color-surface);
  border-block-end: 1px solid var(--color-border);
}

.feed-header__logo {
  font-size: var(--text-l);
  color: var(--color-primary);
}

.feed-header__search {
  display: flex;
  flex: 1;
  gap: var(--space-xs);
}

.feed-header__input {
  flex: 1;
  padding: var(--space-xs) var(--space-s);
  border-radius: var(--radius-full);
  border: 1px solid var(--color-border);
  background: var(--color-surface-raised);
  color: var(--color-text);
}

.feed-header__submit {
  padding: var(--space-xs) var(--space-m);
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  border: none;
  cursor: pointer;
}

.feed-chips {
  display: flex;
  gap: var(--space-xs);
  padding: var(--space-s) var(--space-m);
  overflow-x: auto;
  border-block-end: 1px solid var(--color-border);
  scrollbar-width: none;
}

.feed-chip {
  padding: var(--space-xs) var(--space-m);
  border-radius: var(--radius-full);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: var(--text-s);
  white-space: nowrap;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.feed-chip--active {
  background: var(--color-primary);
  color: var(--color-on-primary);
  border-color: var(--color-primary);
}

.feed-container {
  max-inline-size: 40rem;
  margin-inline: auto;
  padding: var(--space-m);
  display: flex;
  flex-direction: column;
  gap: var(--space-m);
}

.feed-card {
  padding: var(--space-m);
  background: var(--color-surface-raised);
  border-radius: var(--radius-m);
  border: 1px solid var(--color-border);
  transition: box-shadow 0.15s;
}

.feed-card:hover {
  box-shadow: 0 2px 8px oklch(0 0 0 / 0.1);
}

.feed-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-block-end: var(--space-xs);
}

.feed-badge {
  display: inline-block;
  padding: 2px var(--space-xs);
  border-radius: var(--radius-s);
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.feed-badge--event { background: oklch(from var(--color-feed-event) l c h / 0.15); color: var(--color-feed-event); }
.feed-badge--business { background: oklch(from var(--color-feed-business) l c h / 0.15); color: var(--color-feed-business); }
.feed-badge--group { background: oklch(from var(--color-feed-group) l c h / 0.15); color: var(--color-feed-group); }
.feed-badge--person { background: oklch(from var(--color-feed-person) l c h / 0.15); color: var(--color-feed-person); }
.feed-badge--featured { background: oklch(from var(--color-feed-featured) l c h / 0.15); color: var(--color-feed-featured); }

.feed-card__title { font-size: var(--text-m); font-weight: 600; }
.feed-card__title a { color: inherit; text-decoration: none; }
.feed-card__title a:hover { text-decoration: underline; }
.feed-card__subtitle { font-size: var(--text-s); color: var(--color-text-muted); margin-block-start: var(--space-2xs); }
.feed-card__distance { font-size: var(--text-xs); color: var(--color-text-muted); }
.feed-card__community { font-size: var(--text-s); color: var(--color-text-muted); margin-block-start: var(--space-2xs); }
.feed-card__meta { font-size: var(--text-s); color: var(--color-text-muted); margin-block-start: var(--space-2xs); }

.feed-card--featured {
  border-color: var(--color-feed-featured);
  background: linear-gradient(135deg, var(--color-surface-raised), var(--color-surface));
}

.feed-card--welcome {
  border-style: dashed;
  text-align: center;
  padding: var(--space-l);
}

.feed-card--communities {
  border-style: dashed;
}

.feed-pills {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xs);
  margin-block-start: var(--space-s);
}

.feed-pill {
  padding: var(--space-2xs) var(--space-s);
  border-radius: var(--radius-full);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  color: var(--color-text);
  font-size: var(--text-s);
  text-decoration: none;
}

.feed-pill:hover {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.feed-card--loading {
  animation: pulse 1.5s ease-in-out infinite;
}

.feed-card__skeleton {
  block-size: 4rem;
  border-radius: var(--radius-s);
  background: var(--color-border);
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.feed-end {
  text-align: center;
  padding: var(--space-l);
  color: var(--color-text-muted);
  font-size: var(--text-s);
}
```

- [ ] **Step 3: Verify the page renders correctly**

Run: `php -S localhost:8081 -t public` and visit `http://localhost:8081/`

- [ ] **Step 4: Commit**

```bash
git add public/css/minoo.css
git commit -m "feat(feed): add feed component CSS with type colors and skeleton loading"
```

---

### Task 11: Migration Cleanup

**Files:**
- Delete: `src/Controller/HomeController.php`
- Delete: `templates/page.html.twig`
- Delete: `templates/components/homepage-card.html.twig`
- Modify: `public/css/minoo.css` — remove `homepage-*` classes
- Update: Any tests referencing `HomeController`

- [ ] **Step 1: Verify no other templates reference homepage-* classes**

Run: `grep -r 'homepage-' templates/ --include='*.twig'`
Expected: Only `page.html.twig` and `homepage-card.html.twig` (being deleted)

- [ ] **Step 2: Verify no other code references HomeController**

Run: `grep -r 'HomeController' src/ tests/ --include='*.php'`
Expected: Only `HomeController.php` itself and its test (if any)

- [ ] **Step 3: Delete old files**

```bash
rm src/Controller/HomeController.php
rm templates/page.html.twig
rm templates/components/homepage-card.html.twig
```

- [ ] **Step 4: Remove homepage-* CSS classes from minoo.css**

Search for `.homepage-` in `public/css/minoo.css` and remove all matching rule blocks.

- [ ] **Step 5: Delete stale manifest cache**

Run: `rm -f storage/framework/packages.php`

- [ ] **Step 6: Run full test suite**

Run: `./vendor/bin/phpunit -v`
Expected: All tests pass. If any test references `HomeController`, update it to use `FeedController` or delete if obsolete.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore(feed): remove HomeController, page.html.twig, and homepage-* CSS"
```

---

### Task 12: Integration Smoke Test

**Files:**
- Modify or create: `tests/Minoo/Integration/FeedSmokeTest.php`

- [ ] **Step 1: Write integration test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Http\HttpKernel;

#[CoversNothing]
final class FeedSmokeTest extends TestCase
{
    private static HttpKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        putenv('WAASEYAA_DB=:memory:');

        $projectRoot = dirname(__DIR__, 3);
        $cacheFile = $projectRoot . '/storage/framework/packages.php';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        self::$kernel = new HttpKernel($projectRoot);

        $ref = new \ReflectionMethod(self::$kernel, 'boot');
        $ref->setAccessible(true);
        $ref->invoke(self::$kernel);
    }

    #[Test]
    public function feed_controller_is_resolvable(): void
    {
        // Verify that the kernel can resolve the FeedController route
        $etm = self::$kernel->getEntityTypeManager();
        $this->assertNotNull($etm->getStorage('event'));
        $this->assertNotNull($etm->getStorage('group'));
        $this->assertNotNull($etm->getStorage('resource_person'));
    }
}
```

- [ ] **Step 2: Run integration tests**

Run: `./vendor/bin/phpunit --testsuite MinooIntegration -v`
Expected: PASS

- [ ] **Step 3: Run full test suite**

Run: `./vendor/bin/phpunit -v`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add tests/Minoo/Integration/FeedSmokeTest.php
git commit -m "test(feed): add integration smoke test for feed pipeline"
```

---

### Task 13: Manual Verification

- [ ] **Step 1: Start dev server**

Run: `php -S localhost:8081 -t public`

- [ ] **Step 2: Verify homepage renders as feed**

Visit `http://localhost:8081/` — should show compact header, filter chips, feed cards

- [ ] **Step 3: Verify filter chips work**

Click each filter chip — feed should reload with filtered content

- [ ] **Step 4: Verify infinite scroll**

Scroll to bottom — should load more items (or show "You're all caught up")

- [ ] **Step 5: Verify /api/feed returns JSON**

Visit `http://localhost:8081/api/feed` — should return JSON with items array

- [ ] **Step 6: Verify /about page**

Visit `http://localhost:8081/about` — should render the about page

- [ ] **Step 7: Verify /explore redirect**

Visit `http://localhost:8081/explore?type=events&q=pow+wow` — should redirect to `/events?q=pow+wow`

- [ ] **Step 8: Take Playwright snapshots for visual verification**

Use Playwright MCP to snapshot the homepage, feed with filter, and about page.

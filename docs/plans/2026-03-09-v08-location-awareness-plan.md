# v0.8 — Location Awareness & Local Relevance Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add location detection (IP + browser + manual) so the platform feels local — context bar, form pre-fill, directory filtering, and homepage personalization.

**Architecture:** `LocationService` resolves coordinates via MaxMind GeoIP2 (local DB), then finds the nearest `Community` entity using existing `GeoDistance::haversine()`. Result is stored in `$_SESSION['minoo_location']` + cookie. The location bar in `base.html.twig` is JS-powered (reads cookie for display, calls `/api/location/set` for manual override). Server-side features (form pre-fill, filtering) read session via `LocationService::fromRequest()`. Homepage gets a dedicated controller for location-aware content.

**Tech Stack:** PHP 8.3, MaxMind GeoIP2 (`geoip2/geoip2`), vanilla JS, existing Haversine + community entities

---

## Task 1: LocationContext Value Object

**Files:**
- Create: `src/Geo/LocationContext.php`
- Test: `tests/Minoo/Unit/Geo/LocationContextTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Geo;

use Minoo\Geo\LocationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocationContext::class)]
final class LocationContextTest extends TestCase
{
    #[Test]
    public function has_location_returns_true_when_community_set(): void
    {
        $ctx = new LocationContext(
            communityId: 1,
            communityName: 'Sagamok',
            latitude: 46.15,
            longitude: -81.77,
            source: 'ip',
        );

        self::assertTrue($ctx->hasLocation());
        self::assertSame(1, $ctx->communityId);
        self::assertSame('Sagamok', $ctx->communityName);
        self::assertSame('ip', $ctx->source);
    }

    #[Test]
    public function has_location_returns_false_when_no_community(): void
    {
        $ctx = LocationContext::none();

        self::assertFalse($ctx->hasLocation());
        self::assertNull($ctx->communityId);
        self::assertSame('none', $ctx->source);
    }

    #[Test]
    public function to_array_returns_serializable_data(): void
    {
        $ctx = new LocationContext(
            communityId: 1,
            communityName: 'Sagamok',
            latitude: 46.15,
            longitude: -81.77,
            source: 'manual',
        );

        $arr = $ctx->toArray();

        self::assertSame(1, $arr['communityId']);
        self::assertSame('Sagamok', $arr['communityName']);
        self::assertSame(46.15, $arr['latitude']);
        self::assertSame(-81.77, $arr['longitude']);
        self::assertSame('manual', $arr['source']);
    }

    #[Test]
    public function from_array_hydrates_from_session_data(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 2,
            'communityName' => 'Sudbury',
            'latitude' => 46.49,
            'longitude' => -81.0,
            'source' => 'browser',
        ]);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(2, $ctx->communityId);
        self::assertSame('browser', $ctx->source);
    }

    #[Test]
    public function from_array_returns_none_for_empty_array(): void
    {
        $ctx = LocationContext::fromArray([]);

        self::assertFalse($ctx->hasLocation());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Geo/LocationContextTest.php -v`
Expected: FAIL — class `LocationContext` not found.

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Geo;

final readonly class LocationContext
{
    public function __construct(
        public ?int $communityId,
        public ?string $communityName,
        public ?float $latitude,
        public ?float $longitude,
        public string $source,
    ) {}

    public static function none(): self
    {
        return new self(null, null, null, null, 'none');
    }

    public function hasLocation(): bool
    {
        return $this->communityId !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'communityId' => $this->communityId,
            'communityName' => $this->communityName,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'source' => $this->source,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (!isset($data['communityId'])) {
            return self::none();
        }

        return new self(
            communityId: (int) $data['communityId'],
            communityName: (string) ($data['communityName'] ?? ''),
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            source: (string) ($data['source'] ?? 'none'),
        );
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Geo/LocationContextTest.php -v`
Expected: PASS (5 tests)

**Step 5: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: All 223+ tests pass.

**Step 6: Commit**

```bash
git add src/Geo/LocationContext.php tests/Minoo/Unit/Geo/LocationContextTest.php
git commit -m "feat(#XX): LocationContext value object with serialization"
```

---

## Task 2: CommunityFinder — Nearest Community by Coordinates

**Files:**
- Create: `src/Geo/CommunityFinder.php`
- Test: `tests/Minoo/Unit/Geo/CommunityFinderTest.php`

This extracts the "find nearest community" logic that `VolunteerRanker` does inline, making it reusable for IP/browser geolocation.

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Geo;

use Minoo\Geo\CommunityFinder;
use Minoo\Geo\GeoDistance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;

#[CoversClass(CommunityFinder::class)]
final class CommunityFinderTest extends TestCase
{
    #[Test]
    public function find_nearest_returns_closest_community(): void
    {
        // Sagamok: 46.15, -81.77
        // Sudbury: 46.49, -81.00
        // Sault Ste. Marie: 46.52, -84.35
        $communities = [
            $this->makeCommunity(1, 'Sagamok', 46.15, -81.77),
            $this->makeCommunity(2, 'Sudbury', 46.49, -81.00),
            $this->makeCommunity(3, 'Sault Ste. Marie', 46.52, -84.35),
        ];

        $finder = new CommunityFinder();
        // Point near Sagamok
        $result = $finder->findNearest(46.16, -81.75, $communities);

        self::assertNotNull($result);
        self::assertSame(1, $result['community']->id());
    }

    #[Test]
    public function find_nearest_returns_null_for_empty_list(): void
    {
        $finder = new CommunityFinder();
        $result = $finder->findNearest(46.0, -81.0, []);

        self::assertNull($result);
    }

    #[Test]
    public function find_nearby_returns_sorted_by_distance(): void
    {
        $communities = [
            $this->makeCommunity(1, 'Sagamok', 46.15, -81.77),
            $this->makeCommunity(2, 'Sudbury', 46.49, -81.00),
            $this->makeCommunity(3, 'Sault Ste. Marie', 46.52, -84.35),
        ];

        $finder = new CommunityFinder();
        // Point near Sudbury
        $nearby = $finder->findNearby(46.50, -81.01, $communities, limit: 2);

        self::assertCount(2, $nearby);
        self::assertSame(2, $nearby[0]['community']->id()); // Sudbury closest
        self::assertSame(1, $nearby[1]['community']->id()); // Sagamok next
    }

    #[Test]
    public function skips_communities_without_coordinates(): void
    {
        $communities = [
            $this->makeCommunity(1, 'No Coords', null, null),
            $this->makeCommunity(2, 'Sudbury', 46.49, -81.00),
        ];

        $finder = new CommunityFinder();
        $result = $finder->findNearest(46.50, -81.01, $communities);

        self::assertNotNull($result);
        self::assertSame(2, $result['community']->id());
    }

    private function makeCommunity(int $id, string $name, ?float $lat, ?float $lon): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnCallback(fn (string $field) => match ($field) {
            'name' => $name,
            'latitude' => $lat,
            'longitude' => $lon,
            default => null,
        });
        return $mock;
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Geo/CommunityFinderTest.php -v`
Expected: FAIL — class `CommunityFinder` not found.

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Geo;

use Waaseyaa\Entity\ContentEntityBase;

final class CommunityFinder
{
    /**
     * Find the nearest community to given coordinates.
     *
     * @param ContentEntityBase[] $communities
     * @return array{community: ContentEntityBase, distanceKm: float}|null
     */
    public function findNearest(float $lat, float $lon, array $communities): ?array
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($communities as $community) {
            $cLat = $community->get('latitude');
            $cLon = $community->get('longitude');

            if ($cLat === null || $cLon === null) {
                continue;
            }

            $distance = GeoDistance::haversine($lat, $lon, (float) $cLat, (float) $cLon);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = ['community' => $community, 'distanceKm' => $distance];
            }
        }

        return $nearest;
    }

    /**
     * Find nearby communities sorted by distance.
     *
     * @param ContentEntityBase[] $communities
     * @return array<array{community: ContentEntityBase, distanceKm: float}>
     */
    public function findNearby(float $lat, float $lon, array $communities, int $limit = 5): array
    {
        $results = [];

        foreach ($communities as $community) {
            $cLat = $community->get('latitude');
            $cLon = $community->get('longitude');

            if ($cLat === null || $cLon === null) {
                continue;
            }

            $distance = GeoDistance::haversine($lat, $lon, (float) $cLat, (float) $cLon);
            $results[] = ['community' => $community, 'distanceKm' => $distance];
        }

        usort($results, static fn (array $a, array $b): int => $a['distanceKm'] <=> $b['distanceKm']);

        return array_slice($results, 0, $limit);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Geo/CommunityFinderTest.php -v`
Expected: PASS (4 tests)

**Step 5: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass.

**Step 6: Commit**

```bash
git add src/Geo/CommunityFinder.php tests/Minoo/Unit/Geo/CommunityFinderTest.php
git commit -m "feat(#XX): CommunityFinder extracts nearest-community resolution"
```

---

## Task 3: LocationService — Session, Cookie, and IP Resolution

**Files:**
- Create: `src/Geo/LocationService.php`
- Test: `tests/Minoo/Unit/Geo/LocationServiceTest.php`
- Modify: `composer.json` — add `geoip2/geoip2`
- Modify: `config/waaseyaa.php` — add `location` config block
- Modify: `.gitignore` — add `storage/geoip/`

**Step 1: Add MaxMind GeoIP2 dependency**

Run: `composer require geoip2/geoip2:^3.0`

**Step 2: Add location config to `config/waaseyaa.php`**

Add after the `search` block (around line 67):

```php
    // Location detection.
    'location' => [
        'geoip_db' => getenv('GEOIP_DB_PATH') ?: __DIR__ . '/../storage/geoip/GeoLite2-City.mmdb',
        'default_coordinates' => [46.49, -81.00], // Sudbury fallback for dev/private IPs
        'cookie_name' => 'minoo_location',
        'cookie_ttl' => 86400 * 30, // 30 days
    ],
```

**Step 3: Add `storage/geoip/` to `.gitignore`**

```
storage/geoip/
```

**Step 4: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Geo;

use Minoo\Geo\CommunityFinder;
use Minoo\Geo\LocationContext;
use Minoo\Geo\LocationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(LocationService::class)]
final class LocationServiceTest extends TestCase
{
    #[Test]
    public function from_request_returns_context_from_session(): void
    {
        $request = HttpRequest::create('/');
        $request->attributes->set('_session', [
            'minoo_location' => [
                'communityId' => 1,
                'communityName' => 'Sagamok',
                'latitude' => 46.15,
                'longitude' => -81.77,
                'source' => 'manual',
            ],
        ]);

        $etm = $this->createMock(EntityTypeManager::class);
        $service = new LocationService($etm, []);
        $ctx = $service->fromRequest($request);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(1, $ctx->communityId);
        self::assertSame('manual', $ctx->source);
    }

    #[Test]
    public function from_request_returns_context_from_cookie(): void
    {
        $sessionData = json_encode([
            'communityId' => 2,
            'communityName' => 'Sudbury',
            'latitude' => 46.49,
            'longitude' => -81.0,
            'source' => 'ip',
        ], JSON_THROW_ON_ERROR);

        $request = HttpRequest::create('/');
        $request->cookies->set('minoo_location', $sessionData);
        $request->attributes->set('_session', []);

        $etm = $this->createMock(EntityTypeManager::class);
        $service = new LocationService($etm, []);
        $ctx = $service->fromRequest($request);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(2, $ctx->communityId);
    }

    #[Test]
    public function from_request_returns_none_when_no_location_data(): void
    {
        $request = HttpRequest::create('/');
        $request->attributes->set('_session', []);

        $etm = $this->createMock(EntityTypeManager::class);
        $service = new LocationService($etm, [
            'geoip_db' => '/nonexistent.mmdb',
            'default_coordinates' => null,
        ]);
        $ctx = $service->fromRequest($request);

        self::assertFalse($ctx->hasLocation());
        self::assertSame('none', $ctx->source);
    }

    #[Test]
    public function from_request_uses_default_coordinates_for_private_ip(): void
    {
        $request = HttpRequest::create('/');
        $request->attributes->set('_session', []);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $community = $this->createMock(ContentEntityBase::class);
        $community->method('id')->willReturn(2);
        $community->method('get')->willReturnCallback(fn (string $f) => match ($f) {
            'name' => 'Sudbury',
            'latitude' => 46.49,
            'longitude' => -81.0,
            default => null,
        });

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn(new class {
            public function condition(string $f, mixed $v): static { return $this; }
            public function sort(string $f, string $d): static { return $this; }
            public function execute(): array { return [2]; }
        });
        $storage->method('loadMultiple')->willReturn([2 => $community]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $service = new LocationService($etm, [
            'geoip_db' => '/nonexistent.mmdb',
            'default_coordinates' => [46.49, -81.00],
        ]);
        $ctx = $service->fromRequest($request);

        self::assertTrue($ctx->hasLocation());
        self::assertSame('ip', $ctx->source);
    }

    #[Test]
    public function resolve_from_coordinates_finds_nearest(): void
    {
        $community = $this->createMock(ContentEntityBase::class);
        $community->method('id')->willReturn(1);
        $community->method('get')->willReturnCallback(fn (string $f) => match ($f) {
            'name' => 'Sagamok',
            'latitude' => 46.15,
            'longitude' => -81.77,
            default => null,
        });

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn(new class {
            public function condition(string $f, mixed $v): static { return $this; }
            public function sort(string $f, string $d): static { return $this; }
            public function execute(): array { return [1]; }
        });
        $storage->method('loadMultiple')->willReturn([1 => $community]);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $service = new LocationService($etm, []);
        $ctx = $service->resolveFromCoordinates(46.16, -81.75);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(1, $ctx->communityId);
        self::assertSame('Sagamok', $ctx->communityName);
    }

    #[Test]
    public function resolve_from_community_id_returns_context(): void
    {
        $community = $this->createMock(ContentEntityBase::class);
        $community->method('id')->willReturn(3);
        $community->method('get')->willReturnCallback(fn (string $f) => match ($f) {
            'name' => 'Sault Ste. Marie',
            'latitude' => 46.52,
            'longitude' => -84.35,
            default => null,
        });

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('load')->with(3)->willReturn($community);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);

        $service = new LocationService($etm, []);
        $ctx = $service->resolveFromCommunityId(3);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(3, $ctx->communityId);
        self::assertSame('manual', $ctx->source);
    }
}
```

**Step 5: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Geo/LocationServiceTest.php -v`
Expected: FAIL — class `LocationService` not found.

**Step 6: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Geo;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Entity\EntityTypeManager;

final class LocationService
{
    private readonly CommunityFinder $finder;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly array $config,
    ) {
        $this->finder = new CommunityFinder();
    }

    public function fromRequest(HttpRequest $request): LocationContext
    {
        // 1. Check session
        $session = $request->attributes->get('_session') ?? ($_SESSION ?? []);
        if (isset($session['minoo_location'])) {
            return LocationContext::fromArray($session['minoo_location']);
        }

        // 2. Check cookie
        $cookieJson = $request->cookies->get($this->config['cookie_name'] ?? 'minoo_location');
        if ($cookieJson !== null) {
            $data = json_decode($cookieJson, true);
            if (is_array($data) && isset($data['communityId'])) {
                return LocationContext::fromArray($data);
            }
        }

        // 3. IP geolocation
        return $this->resolveFromIp($request);
    }

    public function resolveFromCoordinates(float $lat, float $lon, string $source = 'browser'): LocationContext
    {
        $communities = $this->loadAllCommunities();
        $nearest = $this->finder->findNearest($lat, $lon, $communities);

        if ($nearest === null) {
            return LocationContext::none();
        }

        return new LocationContext(
            communityId: $nearest['community']->id(),
            communityName: (string) $nearest['community']->get('name'),
            latitude: $lat,
            longitude: $lon,
            source: $source,
        );
    }

    public function resolveFromCommunityId(int $communityId): LocationContext
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $community = $storage->load($communityId);

        if ($community === null) {
            return LocationContext::none();
        }

        return new LocationContext(
            communityId: $community->id(),
            communityName: (string) $community->get('name'),
            latitude: $community->get('latitude') !== null ? (float) $community->get('latitude') : null,
            longitude: $community->get('longitude') !== null ? (float) $community->get('longitude') : null,
            source: 'manual',
        );
    }

    public function storeInSession(LocationContext $ctx): void
    {
        $_SESSION['minoo_location'] = $ctx->toArray();
    }

    public function setCookie(LocationContext $ctx): void
    {
        $ttl = $this->config['cookie_ttl'] ?? 86400 * 30;
        $json = json_encode($ctx->toArray(), JSON_THROW_ON_ERROR);
        setcookie(
            $this->config['cookie_name'] ?? 'minoo_location',
            $json,
            [
                'expires' => time() + $ttl,
                'path' => '/',
                'httponly' => false, // JS needs to read it for location bar
                'samesite' => 'Lax',
            ],
        );
    }

    private function resolveFromIp(HttpRequest $request): LocationContext
    {
        $ip = $request->getClientIp() ?? '127.0.0.1';

        // Private/local IPs: use default coordinates
        if ($this->isPrivateIp($ip)) {
            $defaults = $this->config['default_coordinates'] ?? null;
            if ($defaults === null) {
                return LocationContext::none();
            }
            return $this->resolveFromCoordinates($defaults[0], $defaults[1], 'ip');
        }

        // Try MaxMind GeoIP2
        $dbPath = $this->config['geoip_db'] ?? '';
        if ($dbPath === '' || !file_exists($dbPath)) {
            return LocationContext::none();
        }

        try {
            $reader = new \GeoIp2\Database\Reader($dbPath);
            $record = $reader->city($ip);
            $lat = $record->location->latitude;
            $lon = $record->location->longitude;

            if ($lat === null || $lon === null) {
                return LocationContext::none();
            }

            return $this->resolveFromCoordinates($lat, $lon, 'ip');
        } catch (\Throwable) {
            return LocationContext::none();
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** @return \Waaseyaa\Entity\ContentEntityBase[] */
    private function loadAllCommunities(): array
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC')
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }
}
```

**Step 7: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Geo/LocationServiceTest.php -v`
Expected: PASS (6 tests)

**Step 8: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass.

**Step 9: Commit**

```bash
git add src/Geo/LocationService.php tests/Minoo/Unit/Geo/LocationServiceTest.php config/waaseyaa.php .gitignore composer.json composer.lock
git commit -m "feat(#XX): LocationService with session/cookie/IP resolution"
```

---

## Task 4: Location API Controller

**Files:**
- Create: `src/Controller/LocationController.php`
- Test: `tests/Minoo/Unit/Controller/LocationControllerTest.php`
- Modify: `src/Provider/CommunityServiceProvider.php` — add location API routes

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\LocationController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Twig\Environment;

#[CoversClass(LocationController::class)]
final class LocationControllerTest extends TestCase
{
    #[Test]
    public function current_returns_none_when_no_location(): void
    {
        [$controller, ] = $this->makeController();
        $request = HttpRequest::create('/api/location/current');
        $request->attributes->set('_session', []);
        $account = $this->createMock(AccountInterface::class);

        $response = $controller->current([], [], $account, $request);

        $data = json_decode($response->getContent(), true);
        self::assertFalse($data['hasLocation']);
    }

    #[Test]
    public function set_stores_community_and_returns_context(): void
    {
        [$controller, $storage] = $this->makeController();

        $community = $this->createMock(ContentEntityBase::class);
        $community->method('id')->willReturn(1);
        $community->method('get')->willReturnCallback(fn (string $f) => match ($f) {
            'name' => 'Sagamok',
            'latitude' => 46.15,
            'longitude' => -81.77,
            default => null,
        });
        $storage->method('load')->with(1)->willReturn($community);

        $request = HttpRequest::create('/api/location/set', 'POST', [], [], [], [], json_encode(['community_id' => 1]));
        $request->headers->set('Content-Type', 'application/json');
        $request->attributes->set('_session', []);
        $account = $this->createMock(AccountInterface::class);

        $response = $controller->set([], [], $account, $request);

        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['success']);
        self::assertSame('Sagamok', $data['community']['name']);
    }

    #[Test]
    public function update_resolves_from_coordinates(): void
    {
        [$controller, $storage] = $this->makeController();

        $community = $this->createMock(ContentEntityBase::class);
        $community->method('id')->willReturn(2);
        $community->method('get')->willReturnCallback(fn (string $f) => match ($f) {
            'name' => 'Sudbury',
            'latitude' => 46.49,
            'longitude' => -81.0,
            default => null,
        });

        $queryObj = new class {
            public function condition(string $f, mixed $v): static { return $this; }
            public function sort(string $f, string $d): static { return $this; }
            public function execute(): array { return [2]; }
        };
        $storage->method('getQuery')->willReturn($queryObj);
        $storage->method('loadMultiple')->willReturn([2 => $community]);

        $request = HttpRequest::create('/api/location/update', 'POST', [], [], [], [], json_encode([
            'latitude' => 46.50,
            'longitude' => -81.01,
        ]));
        $request->headers->set('Content-Type', 'application/json');
        $request->attributes->set('_session', []);
        $account = $this->createMock(AccountInterface::class);

        $response = $controller->update([], [], $account, $request);

        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['success']);
        self::assertSame('Sudbury', $data['community']['name']);
    }

    /**
     * @return array{LocationController, EntityStorageInterface&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function makeController(): array
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('community')->willReturn($storage);
        $twig = $this->createMock(Environment::class);

        $controller = new LocationController($etm, $twig);
        return [$controller, $storage];
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/LocationControllerTest.php -v`
Expected: FAIL — class `LocationController` not found.

**Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Geo\LocationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class LocationController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** GET /api/location/current */
    public function current(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $service = $this->makeService();
        $ctx = $service->fromRequest($request);

        return new JsonResponse([
            'hasLocation' => $ctx->hasLocation(),
            ...$ctx->toArray(),
        ]);
    }

    /** POST /api/location/set — manual community selection */
    public function set(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $communityId = (int) ($body['community_id'] ?? 0);

        if ($communityId <= 0) {
            return new JsonResponse(['error' => 'community_id is required'], 400);
        }

        $service = $this->makeService();
        $ctx = $service->resolveFromCommunityId($communityId);

        if (!$ctx->hasLocation()) {
            return new JsonResponse(['error' => 'Community not found'], 404);
        }

        $service->storeInSession($ctx);
        $service->setCookie($ctx);

        return new JsonResponse([
            'success' => true,
            'community' => [
                'id' => $ctx->communityId,
                'name' => $ctx->communityName,
                'latitude' => $ctx->latitude,
                'longitude' => $ctx->longitude,
            ],
        ]);
    }

    /** POST /api/location/update — browser geolocation */
    public function update(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $lat = isset($body['latitude']) ? (float) $body['latitude'] : null;
        $lon = isset($body['longitude']) ? (float) $body['longitude'] : null;

        if ($lat === null || $lon === null) {
            return new JsonResponse(['error' => 'latitude and longitude are required'], 400);
        }

        $service = $this->makeService();
        $ctx = $service->resolveFromCoordinates($lat, $lon, 'browser');

        if (!$ctx->hasLocation()) {
            return new JsonResponse(['error' => 'No nearby community found'], 404);
        }

        $service->storeInSession($ctx);
        $service->setCookie($ctx);

        return new JsonResponse([
            'success' => true,
            'community' => [
                'id' => $ctx->communityId,
                'name' => $ctx->communityName,
            ],
        ]);
    }

    private function makeService(): LocationService
    {
        $config = [];
        $configPath = dirname(__DIR__, 2) . '/config/waaseyaa.php';
        if (file_exists($configPath)) {
            $allConfig = require $configPath;
            $config = $allConfig['location'] ?? [];
        }

        return new LocationService($this->entityTypeManager, $config);
    }
}
```

**Step 4: Add routes to `CommunityServiceProvider.php`**

Read the file first. Add these routes in the `routes()` method:

```php
$routes->add('GET', '/api/location/current', 'Minoo\Controller\LocationController::current');
$routes->add('POST', '/api/location/set', 'Minoo\Controller\LocationController::set');
$routes->add('POST', '/api/location/update', 'Minoo\Controller\LocationController::update');
```

**Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Minoo/Unit/Controller/LocationControllerTest.php -v`
Expected: PASS (3 tests)

**Step 6: Run full suite and clear manifest**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All tests pass.

**Step 7: Commit**

```bash
git add src/Controller/LocationController.php tests/Minoo/Unit/Controller/LocationControllerTest.php src/Provider/CommunityServiceProvider.php
git commit -m "feat(#XX): Location API controller with set/update/current endpoints"
```

---

## Task 5: Location Bar UI — Template + CSS + JavaScript

**Files:**
- Create: `templates/components/location-bar.html.twig`
- Modify: `templates/base.html.twig` — include location bar + JS
- Modify: `public/css/minoo.css` — add location bar styles

**Step 1: Create location bar component**

```twig
{# templates/components/location-bar.html.twig #}
<div class="location-bar" id="location-bar" data-cookie-name="minoo_location">
  <div class="location-bar__inner">
    <span class="location-bar__icon" aria-hidden="true">&#x1F4CD;</span>
    <span class="location-bar__text" id="location-text">Detecting location&hellip;</span>
    <button class="location-bar__toggle" id="location-toggle" type="button" aria-expanded="false">Change</button>
  </div>
  <div class="location-bar__dropdown" id="location-dropdown" hidden>
    <label for="location-search" class="visually-hidden">Search communities</label>
    <input class="location-bar__input" id="location-search" type="search"
           placeholder="Search communities&hellip;" autocomplete="off">
    <ul class="location-bar__results" id="location-results" role="listbox"></ul>
  </div>
</div>
```

**Step 2: Add location bar to `base.html.twig`**

After the `</header>` tag (line 39), add:

```twig
    {% include "components/location-bar.html.twig" %}
```

**Step 3: Add location JavaScript to `base.html.twig`**

Add after the nav-toggle script (after line 58). This JS reads the cookie for display, handles the autocomplete dropdown, and triggers browser geolocation refinement. Uses safe DOM methods (textContent, createElement) to avoid XSS.

```javascript
  <script>
  (function() {
    const bar = document.getElementById('location-bar');
    if (!bar) return;
    const textEl = document.getElementById('location-text');
    const toggle = document.getElementById('location-toggle');
    const dropdown = document.getElementById('location-dropdown');
    const searchInput = document.getElementById('location-search');
    const resultsList = document.getElementById('location-results');
    const cookieName = bar.dataset.cookieName || 'minoo_location';

    function readCookie() {
      const match = document.cookie.split('; ').find(c => c.startsWith(cookieName + '='));
      if (!match) return null;
      try { return JSON.parse(decodeURIComponent(match.split('=').slice(1).join('='))); }
      catch { return null; }
    }

    function render(loc) {
      if (loc && loc.communityName) {
        textEl.textContent = 'Near ' + loc.communityName;
        toggle.textContent = 'Change';
      } else {
        textEl.textContent = 'Set your location';
        toggle.textContent = '';
      }
    }

    render(readCookie());

    toggle.addEventListener('click', function() {
      const open = dropdown.hidden;
      dropdown.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) searchInput.focus();
    });
    textEl.addEventListener('click', function() {
      if (!readCookie()) {
        dropdown.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        searchInput.focus();
      }
    });
    textEl.style.cursor = 'pointer';

    let debounce;
    searchInput.addEventListener('input', function() {
      clearTimeout(debounce);
      debounce = setTimeout(async () => {
        const q = searchInput.value.trim();
        if (q.length < 2) { resultsList.replaceChildren(); return; }
        try {
          const res = await fetch('/api/communities/autocomplete?q=' + encodeURIComponent(q));
          const items = await res.json();
          resultsList.replaceChildren();
          items.forEach(c => {
            const li = document.createElement('li');
            li.className = 'location-bar__result';
            li.setAttribute('role', 'option');
            li.dataset.id = c.id;
            li.textContent = c.name;
            if (c.community_type) {
              const small = document.createElement('small');
              small.textContent = ' (' + c.community_type.replace('_', ' ') + ')';
              li.appendChild(small);
            }
            resultsList.appendChild(li);
          });
        } catch {
          resultsList.replaceChildren();
          const li = document.createElement('li');
          li.textContent = 'Error loading results';
          resultsList.appendChild(li);
        }
      }, 300);
    });

    resultsList.addEventListener('click', async function(e) {
      const li = e.target.closest('[data-id]');
      if (!li) return;
      try {
        const res = await fetch('/api/location/set', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({community_id: parseInt(li.dataset.id, 10)})
        });
        const data = await res.json();
        if (data.success) location.reload();
      } catch {}
    });

    const loc = readCookie();
    if ((!loc || loc.source === 'ip' || loc.source === 'none') && navigator.geolocation && !sessionStorage.getItem('minoo_geo_asked')) {
      sessionStorage.setItem('minoo_geo_asked', '1');
      navigator.geolocation.getCurrentPosition(async (pos) => {
        try {
          const res = await fetch('/api/location/update', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({latitude: pos.coords.latitude, longitude: pos.coords.longitude})
          });
          const data = await res.json();
          if (data.success) render({communityName: data.community.name, source: 'browser'});
        } catch {}
      }, () => {}, {timeout: 10000, maximumAge: 300000});
    }
  })();
  </script>
```

**Step 4: Add CSS to `minoo.css`**

Add in `@layer components`:

```css
/* Location bar */
.location-bar {
  background: oklch(0.97 0.01 240);
  border-block-end: 1px solid oklch(0.9 0.01 240);
  font-size: var(--step--1);
}

.location-bar__inner {
  max-inline-size: var(--content-max, 72rem);
  margin-inline: auto;
  padding-inline: var(--space-s);
  padding-block: var(--space-2xs);
  display: flex;
  align-items: center;
  gap: var(--space-2xs);
}

.location-bar__icon {
  font-size: var(--step-0);
}

.location-bar__text {
  flex: 1;
}

.location-bar__toggle {
  background: none;
  border: none;
  color: oklch(0.5 0.15 240);
  cursor: pointer;
  font-size: inherit;
  text-decoration: underline;
  padding: 0;
}

.location-bar__toggle:hover {
  color: oklch(0.4 0.15 240);
}

.location-bar__dropdown {
  max-inline-size: var(--content-max, 72rem);
  margin-inline: auto;
  padding-inline: var(--space-s);
  padding-block-end: var(--space-xs);
}

.location-bar__input {
  inline-size: 100%;
  max-inline-size: 24rem;
  padding: var(--space-3xs) var(--space-2xs);
  border: 1px solid oklch(0.8 0.01 240);
  border-radius: 0.25rem;
  font-size: inherit;
}

.location-bar__results {
  list-style: none;
  padding: 0;
  margin: 0;
  max-inline-size: 24rem;
  max-block-size: 12rem;
  overflow-y: auto;
}

.location-bar__result {
  padding: var(--space-3xs) var(--space-2xs);
  cursor: pointer;
  border-block-end: 1px solid oklch(0.95 0.01 240);
}

.location-bar__result:hover {
  background: oklch(0.95 0.02 240);
}

.location-bar__result small {
  color: oklch(0.6 0.01 240);
}
```

**Step 5: Manual test with dev server**

Run: `php -S localhost:8081 -t public`
Visit: `http://localhost:8081/`
Expected: Location bar appears below nav showing "Near Sudbury" (dev fallback) or "Set your location".

**Step 6: Commit**

```bash
git add templates/components/location-bar.html.twig templates/base.html.twig public/css/minoo.css
git commit -m "feat(#XX): location bar UI with autocomplete and browser geolocation"
```

---

## Task 6: Form Pre-Fill — Elder Request + Volunteer Signup

**Files:**
- Modify: `src/Controller/ElderSupportController.php` — pass location context to request form
- Modify: `templates/elders/request.html.twig` — pre-fill community from location
- Modify: `src/Controller/VolunteerController.php` — pass location context + add community field
- Modify: `templates/elders/volunteer.html.twig` — add community field with pre-fill

**Step 1: Read existing controllers to find render calls**

Read: `src/Controller/ElderSupportController.php` and `src/Controller/VolunteerController.php`

**Step 2: Modify `ElderSupportController::requestForm()`**

Add location context to the template render. After constructing the render array, add:

```php
$service = new \Minoo\Geo\LocationService($this->entityTypeManager, $this->loadLocationConfig());
$location = $service->fromRequest($request);

// In the render call, add:
'location' => $location,
```

Add a private helper for loading config:

```php
private function loadLocationConfig(): array
{
    $configPath = dirname(__DIR__, 2) . '/config/waaseyaa.php';
    if (!file_exists($configPath)) {
        return [];
    }
    $allConfig = require $configPath;
    return $allConfig['location'] ?? [];
}
```

**Step 3: Modify `templates/elders/request.html.twig`**

Change the community input (line 37-39) to pre-fill:

```twig
        <input class="form__input" type="text" id="community" name="community"
               value="{{ values.community ?? (location is defined and location.hasLocation() ? location.communityName : '') }}"
               autocomplete="address-level2">
```

**Step 4: Modify `VolunteerController` to pass location + handle community**

Add location context to `signupForm()` render and capture `community` in `submitSignup()`.

**Step 5: Modify `templates/elders/volunteer.html.twig`**

Add community field after the phone field (after line 33):

```twig
      <div class="form__field">
        <label class="form__label" for="community">Community <span class="form__label-optional">(optional)</span></label>
        <input class="form__input" type="text" id="community" name="community"
               value="{{ values.community ?? (location is defined and location.hasLocation() ? location.communityName : '') }}"
               autocomplete="address-level2">
      </div>
```

**Step 6: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass.

**Step 7: Commit**

```bash
git add src/Controller/ElderSupportController.php src/Controller/VolunteerController.php templates/elders/request.html.twig templates/elders/volunteer.html.twig
git commit -m "feat(#XX): form pre-fill with location context for elder request and volunteer signup"
```

---

## Task 7: Homepage Location-Aware Content

**Files:**
- Create: `src/Controller/HomeController.php`
- Modify: `src/Provider/CommunityServiceProvider.php` — add homepage route
- Modify: `templates/page.html.twig` — replace with location-aware homepage

**Step 1: Create HomeController**

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Geo\CommunityFinder;
use Minoo\Geo\LocationService;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class HomeController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $config = $this->loadLocationConfig();
        $service = new LocationService($this->entityTypeManager, $config);
        $location = $service->fromRequest($request);

        $templateVars = [
            'path' => '/',
            'account' => $account,
            'location' => $location,
        ];

        if ($location->hasLocation()) {
            $service->storeInSession($location);
            $service->setCookie($location);

            $communities = $this->loadAllCommunities();
            $finder = new CommunityFinder();
            $templateVars['nearby_communities'] = $finder->findNearby(
                $location->latitude ?? 0.0,
                $location->longitude ?? 0.0,
                $communities,
                limit: 3,
            );

            $templateVars['events'] = $this->loadUpcomingEvents(3);
        }

        $html = $this->twig->render('page.html.twig', $templateVars);
        return new SsrResponse(content: $html);
    }

    private function loadUpcomingEvents(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->sort('date', 'ASC')
            ->execute();

        $events = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
        return array_slice($events, 0, $limit);
    }

    private function loadAllCommunities(): array
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->execute();
        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    private function loadLocationConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/waaseyaa.php';
        if (!file_exists($configPath)) {
            return [];
        }
        $allConfig = require $configPath;
        return $allConfig['location'] ?? [];
    }
}
```

**Step 2: Add homepage route to `CommunityServiceProvider`**

```php
$routes->add('GET', '/', 'Minoo\Controller\HomeController::index');
```

Note: This route must be registered. Check if framework resolves `/` via `tryRenderPathTemplate` first or app routes first. If app routes take precedence, this works. If not, the homepage template will be updated to handle both scenarios.

**Step 3: Update `templates/page.html.twig`**

```twig
{% extends "base.html.twig" %}

{% block title %}Minoo — Indigenous Knowledge Platform{% endblock %}

{% block content %}
  <div class="flow-lg">
    <div class="hero">
      <h1>Minoo</h1>
      <p class="text-secondary">Connecting Indigenous communities with knowledge, services, and each other.</p>
    </div>

    {% if location is defined and location.hasLocation() %}
      <section class="flow">
        <h2>Your Community</h2>
        <p>You're near <a href="/communities">{{ location.communityName }}</a>.</p>
      </section>

      {% if nearby_communities is defined and nearby_communities|length > 0 %}
        <section class="flow">
          <h2>Nearby Communities</h2>
          <div class="card-grid">
            {% for item in nearby_communities %}
              <a href="/communities/{{ item.community.get('slug') }}" class="card card--community">
                <div class="card__body">
                  <h3 class="card__title">{{ item.community.get('name') }}</h3>
                  <p class="card__meta">{{ item.distanceKm|round }} km away</p>
                </div>
              </a>
            {% endfor %}
          </div>
          <p><a href="/communities">View all communities</a></p>
        </section>
      {% endif %}

      {% if events is defined and events|length > 0 %}
        <section class="flow">
          <h2>Upcoming Events</h2>
          <div class="card-grid">
            {% for event in events %}
              {% include "components/event-card.html.twig" with {event: event} %}
            {% endfor %}
          </div>
          <p><a href="/events">View all events</a></p>
        </section>
      {% endif %}
    {% else %}
      <section class="flow">
        <h2>Explore</h2>
        <div class="card-grid">
          <a href="/communities" class="card">
            <div class="card__body">
              <h3 class="card__title">Communities</h3>
              <p class="card__meta">First Nations and municipalities</p>
            </div>
          </a>
          <a href="/elders" class="card">
            <div class="card__body">
              <h3 class="card__title">Elder Support</h3>
              <p class="card__meta">Request help or volunteer</p>
            </div>
          </a>
          <a href="/events" class="card">
            <div class="card__body">
              <h3 class="card__title">Events</h3>
              <p class="card__meta">Community gatherings</p>
            </div>
          </a>
          <a href="/teachings" class="card">
            <div class="card__body">
              <h3 class="card__title">Teachings</h3>
              <p class="card__meta">Traditional knowledge</p>
            </div>
          </a>
        </div>
      </section>
    {% endif %}
  </div>
{% endblock %}
```

**Step 4: Manual test with dev server**

Run: `php -S localhost:8081 -t public`
Visit: `http://localhost:8081/`
Expected: Homepage shows location-aware content with nearby communities and events (or explore cards if no location).

**Step 5: Run full suite**

Run: `rm -f storage/framework/packages.php && ./vendor/bin/phpunit`
Expected: All tests pass.

**Step 6: Commit**

```bash
git add src/Controller/HomeController.php src/Provider/CommunityServiceProvider.php templates/page.html.twig
git commit -m "feat(#XX): location-aware homepage with nearby communities and events"
```

---

## Task 8: Communities Page — Proximity Sorting + Highlight

**Files:**
- Modify: `src/Controller/CommunityController.php` — pass location, sort by proximity
- Modify: `templates/communities.html.twig` — highlight nearest community

**Step 1: Modify `CommunityController::list()`**

Add location context and proximity sorting:

```php
$service = new \Minoo\Geo\LocationService($this->entityTypeManager, $this->loadLocationConfig());
$location = $service->fromRequest($request);

// After loading communities, sort by proximity if location known
if ($location->hasLocation() && $location->latitude !== null) {
    $finder = new \Minoo\Geo\CommunityFinder();
    $sorted = $finder->findNearby($location->latitude, $location->longitude, $communities, count($communities));
    $communities = array_map(fn ($r) => $r['community'], $sorted);
}

// In render call, add:
'location' => $location,
```

**Step 2: Modify `templates/communities.html.twig`**

Add "nearest" highlight to the first community card when location is known:

```twig
{% for c in communities %}
  <a href="/communities/{{ c.get('slug') }}" class="card card--community{% if location is defined and location.hasLocation() and loop.first %} card--nearest{% endif %}">
```

Add a note above the grid:

```twig
{% if location is defined and location.hasLocation() %}
  <p class="text-secondary">Sorted by distance from {{ location.communityName }}.</p>
{% endif %}
```

**Step 3: Add `.card--nearest` CSS to `minoo.css`**

```css
.card--nearest {
  outline: 2px solid oklch(0.6 0.15 150);
  outline-offset: -2px;
}
```

**Step 4: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass.

**Step 5: Commit**

```bash
git add src/Controller/CommunityController.php templates/communities.html.twig public/css/minoo.css
git commit -m "feat(#XX): communities page sorts by proximity and highlights nearest"
```

---

## Task 9: Resource Directory — Auto-Filter by Location

**Files:**
- Modify: `src/Controller/PeopleController.php` — add location-based filtering
- Modify: `templates/people.html.twig` — show filter status

Note: Read `src/Controller/PeopleController.php` and `templates/people.html.twig` first to see current structure.

**Step 1: Modify `PeopleController::list()`**

Add location context. If the resource_person entity has a `community` field, filter by nearby communities. If not, skip this task (resource people may not have location data).

Check field definitions first — if `resource_person` has no `community` or location fields, this task becomes "show a message" only:

```php
$service = new \Minoo\Geo\LocationService($this->entityTypeManager, $this->loadLocationConfig());
$location = $service->fromRequest($request);

// Pass to template
'location' => $location,
```

**Step 2: Modify `templates/people.html.twig`**

If location is known, show a note:

```twig
{% if location is defined and location.hasLocation() %}
  <p class="text-secondary">Showing results near {{ location.communityName }}. <a href="/people?all=1">Show all</a></p>
{% endif %}
```

**Step 3: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add src/Controller/PeopleController.php templates/people.html.twig
git commit -m "feat(#XX): resource directory shows location context"
```

---

## Task 10: Playwright Smoke Tests for Location Flows

**Files:** None (Playwright MCP tests run interactively)

Run these Playwright smoke tests against `http://localhost:8081`:

**Test 1: Location bar appears on all pages**
- Navigate to `/`
- Verify location bar is visible with "Near Sudbury" or "Set your location"
- Navigate to `/events`, `/communities`, `/elders` — verify bar persists

**Test 2: Manual location selection**
- Click "Change" or "Set your location" in location bar
- Type "Sag" in the search input
- Verify autocomplete results appear
- Click a result
- Verify page reloads with updated location

**Test 3: Form pre-fill**
- Navigate to `/elders/request`
- Verify community field is pre-filled with detected community name
- Navigate to `/elders/volunteer`
- Verify community field is pre-filled

**Test 4: Communities proximity**
- Navigate to `/communities`
- Verify "Sorted by distance from..." message appears
- Verify first community card has nearest highlight

**Test 5: Homepage location-aware**
- Navigate to `/`
- Verify "Your Community" section appears
- Verify "Nearby Communities" section appears
- Verify "Upcoming Events" section appears (if events exist)

**Step: Run and fix any failures**

Fix any issues found during smoke testing. Re-run full PHPUnit suite after fixes.

**Step: Commit any fixes**

```bash
git add -A
git commit -m "fix(#XX): smoke test fixes for location flows"
```

---

## Task 11: Final Verification + Cleanup

**Step 1: Run full PHPUnit suite**

Run: `./vendor/bin/phpunit`
Expected: All tests pass (218+ existing + new location tests).

**Step 2: Run full Playwright smoke suite**

Re-run all smoke tests from Task 10 plus the original v0.7 suite (homepage, nav, events, groups, teachings, language, people, communities, elders, auth, dashboards).

**Step 3: Update MEMORY.md**

Update the active milestone entry and test counts.

**Step 4: Push to GitHub**

```bash
git push origin main
```

**Step 5: Close v0.8 issues on GitHub**

Close all v0.8 issues with completion comments.

**Step 6: Close v0.8 milestone**

```bash
gh api repos/waaseyaa/minoo/milestones/{N} -X PATCH -f state=closed
```

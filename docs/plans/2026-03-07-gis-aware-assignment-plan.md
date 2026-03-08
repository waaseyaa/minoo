# GIS-Aware Volunteer Assignment — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add proximity-aware volunteer ranking to the coordinator dashboard so volunteers are sorted by distance from the request's community.

**Architecture:** Pure PHP Haversine distance calculation (no PostGIS, no external services). A `VolunteerRanker` service ranks volunteers by distance from the request's community. The coordinator dashboard template shows distance beside each volunteer name. A new optional `max_travel_km` field on volunteers flags those beyond their stated range.

**Tech Stack:** PHP 8.3+, PHPUnit 10.5, Twig, Waaseyaa entity system (EntityTypeManager, EntityInterface, SsrResponse)

**Design doc:** `docs/plans/2026-03-07-gis-aware-assignment-design.md`

---

## Task 1: GeoDistance — Haversine Calculator

**Files:**
- Create: `src/Geo/GeoDistance.php`
- Test: `tests/Minoo/Unit/Geo/GeoDistanceTest.php`

### Step 1: Write the failing test

Create `tests/Minoo/Unit/Geo/GeoDistanceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Geo;

use Minoo\Geo\GeoDistance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeoDistance::class)]
final class GeoDistanceTest extends TestCase
{
    #[Test]
    public function same_point_returns_zero(): void
    {
        $this->assertSame(0.0, GeoDistance::km(46.49, -80.99, 46.49, -80.99));
    }

    #[Test]
    public function sudbury_to_sault_ste_marie(): void
    {
        // Sudbury (46.49, -80.99) to Sault Ste. Marie (46.52, -84.35)
        $km = GeoDistance::km(46.49, -80.99, 46.52, -84.35);
        $this->assertEqualsWithDelta(262, $km, 5);
    }

    #[Test]
    public function sagamok_to_sudbury(): void
    {
        // Sagamok (46.15, -81.72) to Sudbury (46.49, -80.99)
        $km = GeoDistance::km(46.15, -81.72, 46.49, -80.99);
        $this->assertEqualsWithDelta(68, $km, 5);
    }

    #[Test]
    public function antipodal_points(): void
    {
        // North pole to south pole ≈ 20,015 km
        $km = GeoDistance::km(90.0, 0.0, -90.0, 0.0);
        $this->assertEqualsWithDelta(20015, $km, 10);
    }

    #[Test]
    public function equator_crossing(): void
    {
        // (1, 0) to (-1, 0) ≈ 222 km
        $km = GeoDistance::km(1.0, 0.0, -1.0, 0.0);
        $this->assertEqualsWithDelta(222, $km, 5);
    }
}
```

### Step 2: Run test to verify it fails

Run: `vendor/bin/phpunit tests/Minoo/Unit/Geo/GeoDistanceTest.php`
Expected: FAIL — class `Minoo\Geo\GeoDistance` not found.

### Step 3: Write minimal implementation

Create `src/Geo/GeoDistance.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Geo;

final class GeoDistance
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
```

### Step 4: Run test to verify it passes

Run: `vendor/bin/phpunit tests/Minoo/Unit/Geo/GeoDistanceTest.php`
Expected: OK (5 tests, 5 assertions)

### Step 5: Commit

```bash
git add src/Geo/GeoDistance.php tests/Minoo/Unit/Geo/GeoDistanceTest.php
git commit -m "feat(geo): add Haversine distance calculator"
```

---

## Task 2: RankedVolunteer Value Object

**Files:**
- Create: `src/Geo/RankedVolunteer.php`

### Step 1: Create the value object

Create `src/Geo/RankedVolunteer.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Geo;

use Waaseyaa\Entity\EntityInterface;

final readonly class RankedVolunteer
{
    public function __construct(
        public EntityInterface $volunteer,
        public ?float $distanceKm,
        public bool $sameCommunity,
        public bool $withinRange,
    ) {}
}
```

No standalone test needed — this is a plain value object with no logic. It will be tested thoroughly via `VolunteerRankerTest` in Task 3.

### Step 2: Run existing tests to verify nothing breaks

Run: `vendor/bin/phpunit`
Expected: All existing tests pass.

### Step 3: Commit

```bash
git add src/Geo/RankedVolunteer.php
git commit -m "feat(geo): add RankedVolunteer value object"
```

---

## Task 3: VolunteerRanker Service

**Files:**
- Create: `src/Geo/VolunteerRanker.php`
- Test: `tests/Minoo/Unit/Geo/VolunteerRankerTest.php`

### Step 1: Write the failing tests

Create `tests/Minoo/Unit/Geo/VolunteerRankerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Geo;

use Minoo\Geo\RankedVolunteer;
use Minoo\Geo\VolunteerRanker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(VolunteerRanker::class)]
final class VolunteerRankerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
    }

    #[Test]
    public function empty_volunteer_list_returns_empty_array(): void
    {
        $ranker = new VolunteerRanker($this->entityTypeManager);
        $result = $ranker->rank([], $this->makeCommunity(1, 46.49, -80.99));
        $this->assertSame([], $result);
    }

    #[Test]
    public function volunteers_sorted_by_distance_ascending(): void
    {
        // Request community: Sagamok (46.15, -81.72)
        $requestCommunity = $this->makeCommunity(1, 46.15, -81.72);

        // Volunteer in Sault Ste. Marie (far) — community 3
        $volFar = $this->makeVolunteer(10, 3, null);
        // Volunteer in Sudbury (near) — community 2
        $volNear = $this->makeVolunteer(20, 2, null);

        $this->setupCommunityStorage([
            2 => $this->makeCommunity(2, 46.49, -80.99),  // Sudbury ~68km
            3 => $this->makeCommunity(3, 46.52, -84.35),  // SSM ~262km
        ]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $result = $ranker->rank([$volFar, $volNear], $requestCommunity);

        $this->assertCount(2, $result);
        $this->assertSame(20, $result[0]->volunteer->id()); // Sudbury first
        $this->assertSame(10, $result[1]->volunteer->id()); // SSM second
        $this->assertEqualsWithDelta(68, $result[0]->distanceKm, 5);
        $this->assertEqualsWithDelta(262, $result[1]->distanceKm, 5);
    }

    #[Test]
    public function same_community_volunteer_sorts_first(): void
    {
        $requestCommunity = $this->makeCommunity(1, 46.15, -81.72);

        $volSame = $this->makeVolunteer(10, 1, null);   // Same community
        $volNear = $this->makeVolunteer(20, 2, null);    // Nearby

        $this->setupCommunityStorage([
            1 => $requestCommunity,
            2 => $this->makeCommunity(2, 46.49, -80.99),
        ]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $result = $ranker->rank([$volNear, $volSame], $requestCommunity);

        $this->assertSame(10, $result[0]->volunteer->id());
        $this->assertTrue($result[0]->sameCommunity);
        $this->assertSame(0.0, $result[0]->distanceKm);
    }

    #[Test]
    public function volunteer_with_no_community_sorts_last(): void
    {
        $requestCommunity = $this->makeCommunity(1, 46.15, -81.72);

        $volNoCommunity = $this->makeVolunteer(10, null, null);
        $volWithCommunity = $this->makeVolunteer(20, 2, null);

        $this->setupCommunityStorage([
            2 => $this->makeCommunity(2, 46.49, -80.99),
        ]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $result = $ranker->rank([$volNoCommunity, $volWithCommunity], $requestCommunity);

        $this->assertSame(20, $result[0]->volunteer->id());
        $this->assertSame(10, $result[1]->volunteer->id());
        $this->assertNull($result[1]->distanceKm);
    }

    #[Test]
    public function request_with_no_community_returns_all_with_null_distance(): void
    {
        $volA = $this->makeVolunteer(10, 1, null);
        $volA->method('label')->willReturn('Alice');
        $volB = $this->makeVolunteer(20, 2, null);
        $volB->method('label')->willReturn('Bob');

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $result = $ranker->rank([$volB, $volA], null);

        $this->assertCount(2, $result);
        // Sorted by name when no distances
        $this->assertSame(10, $result[0]->volunteer->id()); // Alice
        $this->assertSame(20, $result[1]->volunteer->id()); // Bob
        $this->assertNull($result[0]->distanceKm);
        $this->assertNull($result[1]->distanceKm);
    }

    #[Test]
    public function volunteer_beyond_max_travel_km_flagged_but_not_excluded(): void
    {
        $requestCommunity = $this->makeCommunity(1, 46.15, -81.72);

        // Volunteer in Sudbury (~68 km away), max_travel_km = 50
        $vol = $this->makeVolunteer(10, 2, 50);

        $this->setupCommunityStorage([
            2 => $this->makeCommunity(2, 46.49, -80.99),
        ]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $result = $ranker->rank([$vol], $requestCommunity);

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]->withinRange);
        $this->assertEqualsWithDelta(68, $result[0]->distanceKm, 5);
    }

    #[Test]
    public function volunteer_with_null_max_travel_km_always_within_range(): void
    {
        $requestCommunity = $this->makeCommunity(1, 46.15, -81.72);

        // Volunteer in SSM (~262 km), no max_travel_km set
        $vol = $this->makeVolunteer(10, 3, null);

        $this->setupCommunityStorage([
            3 => $this->makeCommunity(3, 46.52, -84.35),
        ]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $result = $ranker->rank([$vol], $requestCommunity);

        $this->assertTrue($result[0]->withinRange);
    }

    #[Test]
    public function multiple_volunteers_in_same_community_all_get_zero(): void
    {
        $requestCommunity = $this->makeCommunity(1, 46.15, -81.72);

        $vol1 = $this->makeVolunteer(10, 1, null);
        $vol2 = $this->makeVolunteer(20, 1, null);

        $this->setupCommunityStorage([
            1 => $requestCommunity,
        ]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $result = $ranker->rank([$vol1, $vol2], $requestCommunity);

        $this->assertSame(0.0, $result[0]->distanceKm);
        $this->assertSame(0.0, $result[1]->distanceKm);
        $this->assertTrue($result[0]->sameCommunity);
        $this->assertTrue($result[1]->sameCommunity);
    }

    #[Test]
    public function community_with_missing_coordinates_treated_as_unknown(): void
    {
        $requestCommunity = $this->makeCommunity(1, 46.15, -81.72);

        // Volunteer's community has no coordinates
        $volNoCoords = $this->makeVolunteer(10, 4, null);
        $volWithCoords = $this->makeVolunteer(20, 2, null);

        $communityNoCoords = $this->createMock(EntityInterface::class);
        $communityNoCoords->method('id')->willReturn(4);
        $communityNoCoords->method('get')->willReturnMap([
            ['latitude', null],
            ['longitude', null],
        ]);

        $this->setupCommunityStorage([
            2 => $this->makeCommunity(2, 46.49, -80.99),
            4 => $communityNoCoords,
        ]);

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $result = $ranker->rank([$volNoCoords, $volWithCoords], $requestCommunity);

        $this->assertSame(20, $result[0]->volunteer->id()); // With coords first
        $this->assertSame(10, $result[1]->volunteer->id()); // No coords last
        $this->assertNull($result[1]->distanceKm);
    }

    // -- Helpers --

    private function makeCommunity(int $id, ?float $lat, ?float $lon): EntityInterface
    {
        $community = $this->createMock(EntityInterface::class);
        $community->method('id')->willReturn($id);
        $community->method('get')->willReturnMap([
            ['latitude', $lat],
            ['longitude', $lon],
        ]);
        return $community;
    }

    private function makeVolunteer(int $id, ?int $communityId, ?int $maxTravelKm): EntityInterface
    {
        $vol = $this->createMock(EntityInterface::class);
        $vol->method('id')->willReturn($id);
        $vol->method('get')->willReturnMap([
            ['community', $communityId],
            ['max_travel_km', $maxTravelKm],
        ]);
        $vol->method('label')->willReturn('Volunteer ' . $id);
        return $vol;
    }

    /** @param array<int, EntityInterface> $communities */
    private function setupCommunityStorage(array $communities): void
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('load')->willReturnCallback(
            fn (int $id) => $communities[$id] ?? null
        );

        $this->entityTypeManager->method('getStorage')
            ->with('community')
            ->willReturn($storage);
    }
}
```

### Step 2: Run test to verify it fails

Run: `vendor/bin/phpunit tests/Minoo/Unit/Geo/VolunteerRankerTest.php`
Expected: FAIL — class `Minoo\Geo\VolunteerRanker` not found.

### Step 3: Write minimal implementation

Create `src/Geo/VolunteerRanker.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Geo;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class VolunteerRanker
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    /**
     * @param EntityInterface[] $volunteers
     * @return RankedVolunteer[]
     */
    public function rank(array $volunteers, ?EntityInterface $requestCommunity): array
    {
        if ($volunteers === []) {
            return [];
        }

        $reqLat = $requestCommunity?->get('latitude');
        $reqLon = $requestCommunity?->get('longitude');
        $reqCommunityId = $requestCommunity?->id();
        $hasRequestCoords = $reqLat !== null && $reqLon !== null;

        $ranked = [];
        foreach ($volunteers as $vol) {
            $ranked[] = $this->scoreVolunteer($vol, $reqCommunityId, $reqLat, $reqLon, $hasRequestCoords);
        }

        usort($ranked, $this->buildComparator());

        return $ranked;
    }

    private function scoreVolunteer(
        EntityInterface $volunteer,
        ?int $reqCommunityId,
        ?float $reqLat,
        ?float $reqLon,
        bool $hasRequestCoords,
    ): RankedVolunteer {
        $volCommunityId = $volunteer->get('community');
        $maxTravelKm = $volunteer->get('max_travel_km');

        if (!$hasRequestCoords || $volCommunityId === null) {
            return new RankedVolunteer($volunteer, null, false, true);
        }

        $sameCommunity = $volCommunityId === $reqCommunityId;

        if ($sameCommunity) {
            return new RankedVolunteer($volunteer, 0.0, true, true);
        }

        $volCommunity = $this->entityTypeManager->getStorage('community')->load($volCommunityId);
        $volLat = $volCommunity?->get('latitude');
        $volLon = $volCommunity?->get('longitude');

        if ($volLat === null || $volLon === null) {
            return new RankedVolunteer($volunteer, null, false, true);
        }

        $distanceKm = GeoDistance::km($reqLat, $reqLon, $volLat, $volLon);
        $withinRange = $maxTravelKm === null || $distanceKm <= $maxTravelKm;

        return new RankedVolunteer($volunteer, $distanceKm, false, $withinRange);
    }

    /** @return callable(RankedVolunteer, RankedVolunteer): int */
    private function buildComparator(): callable
    {
        return static function (RankedVolunteer $a, RankedVolunteer $b): int {
            // 1. Same community first
            if ($a->sameCommunity !== $b->sameCommunity) {
                return $a->sameCommunity ? -1 : 1;
            }

            // 2. Has distance before no distance
            $aHasDist = $a->distanceKm !== null;
            $bHasDist = $b->distanceKm !== null;
            if ($aHasDist !== $bHasDist) {
                return $aHasDist ? -1 : 1;
            }

            // 3. Within range before beyond range
            if ($aHasDist && $bHasDist && $a->withinRange !== $b->withinRange) {
                return $a->withinRange ? -1 : 1;
            }

            // 4. Sort by distance (or by name if no distance)
            if ($aHasDist && $bHasDist) {
                return $a->distanceKm <=> $b->distanceKm;
            }

            return $a->volunteer->label() <=> $b->volunteer->label();
        };
    }
}
```

### Step 4: Run test to verify it passes

Run: `vendor/bin/phpunit tests/Minoo/Unit/Geo/VolunteerRankerTest.php`
Expected: OK (9 tests, assertions pass)

### Step 5: Run full test suite

Run: `vendor/bin/phpunit`
Expected: All tests pass (existing + new).

### Step 6: Commit

```bash
git add src/Geo/VolunteerRanker.php tests/Minoo/Unit/Geo/VolunteerRankerTest.php
git commit -m "feat(geo): add VolunteerRanker with proximity sorting"
```

---

## Task 4: Add `max_travel_km` Field to Volunteer Entity

**Files:**
- Modify: `src/Provider/ElderSupportServiceProvider.php:52-61` (volunteer fieldDefinitions)

### Step 1: Add field definition

In `src/Provider/ElderSupportServiceProvider.php`, add `max_travel_km` between `availability` (weight 5) and `skills` (weight 10):

```php
// After this line (line 55):
'availability' => ['type' => 'string', 'label' => 'Availability', 'weight' => 5],

// Add:
'max_travel_km' => ['type' => 'integer', 'label' => 'Max Travel Distance (km)', 'weight' => 6],
```

### Step 2: Run existing tests

Run: `vendor/bin/phpunit`
Expected: All tests pass. Adding a field definition doesn't break anything.

### Step 3: Commit

```bash
git add src/Provider/ElderSupportServiceProvider.php
git commit -m "feat(elder): add max_travel_km field to volunteer entity"
```

---

## Task 5: Volunteer Signup Form — Accept `max_travel_km`

**Files:**
- Modify: `templates/elders/volunteer.html.twig:40-41` (after availability field, before skills fieldset)
- Modify: `src/Controller/VolunteerController.php:36-41` (extract max_travel_km from request)
- Modify: `src/Controller/VolunteerController.php:62-71` (include in entity creation)

### Step 1: Add form field to template

In `templates/elders/volunteer.html.twig`, after the availability `</div>` (line 40) and before the skills `<fieldset>` (line 42), add:

```html
      <div class="form__field">
        <label class="form__label" for="max_travel_km">Maximum Travel Distance (km) <span class="form__label-optional">(optional)</span></label>
        <p class="form__hint">How far are you willing to travel to help an elder? Leave blank if no limit.</p>
        <input class="form__input" type="number" id="max_travel_km" name="max_travel_km"
               value="{{ values.max_travel_km ?? '' }}"
               min="1" max="500" placeholder="e.g. 50">
      </div>
```

### Step 2: Extract field in controller

In `src/Controller/VolunteerController.php`, in `submitSignup()`, after the `$notes` extraction (line 41), add:

```php
$maxTravelKm = $request->request->get('max_travel_km');
$maxTravelKm = $maxTravelKm !== null && $maxTravelKm !== '' ? (int) $maxTravelKm : null;
```

### Step 3: Include in `compact()` for error re-render

In `src/Controller/VolunteerController.php:55`, update the `compact()` call:

```php
// Before:
'values' => compact('name', 'phone', 'availability', 'skills', 'notes'),

// After:
'values' => compact('name', 'phone', 'availability', 'skills', 'notes', 'maxTravelKm'),
```

Note: template uses `values.max_travel_km` but compact uses `maxTravelKm`. Fix the template to use `values.maxTravelKm` OR pass it explicitly. Simpler: pass explicitly alongside compact:

```php
'values' => array_merge(
    compact('name', 'phone', 'availability', 'skills', 'notes'),
    ['max_travel_km' => $maxTravelKm],
),
```

### Step 4: Include in entity creation

In `src/Controller/VolunteerController.php`, in the `$storage->create()` call (lines 62-71), add `max_travel_km`:

```php
$entity = $storage->create([
    'name' => $name,
    'phone' => $phone,
    'availability' => $availability,
    'skills' => $skills,
    'notes' => $notes,
    'max_travel_km' => $maxTravelKm,
    'status' => 'active',
    'created_at' => time(),
    'updated_at' => time(),
]);
```

### Step 5: Run existing tests

Run: `vendor/bin/phpunit`
Expected: All tests pass (existing volunteer tests don't submit max_travel_km, so null is fine).

### Step 6: Commit

```bash
git add templates/elders/volunteer.html.twig src/Controller/VolunteerController.php
git commit -m "feat(elder): add max_travel_km to volunteer signup form"
```

---

## Task 6: Wire VolunteerRanker into DashboardServiceProvider

**Files:**
- Modify: `src/Provider/DashboardServiceProvider.php` (construct VolunteerRanker, pass to controller)

### Step 1: Understand current wiring

`DashboardServiceProvider` currently has no `register()` logic. The controller wiring happens in
Waaseyaa's service provider base class — when a route references a controller class, the framework
resolves it. We need to check how controllers get their dependencies.

Look at how `DashboardServiceProvider::routes()` references `CoordinatorDashboardController::index`
as a string. Waaseyaa resolves this string to an actual method call. The provider must set up the
controller's dependencies.

**Check:** Read `DashboardServiceProvider.php` — it uses string-based controller references. The
framework likely instantiates controllers via the service provider. We need to add a controller
factory or register the controller with its dependencies.

**Approach:** The existing pattern shows controllers take `EntityTypeManager` and `Environment` —
both provided by the framework. We need to add `VolunteerRanker` as a third dependency to
`CoordinatorDashboardController`. Since Waaseyaa uses explicit wiring, we need to register
the controller instance in the provider.

This step depends on how Waaseyaa resolves controller strings. The engineer should:

1. Check how `Minoo\Controller\CoordinatorDashboardController::index` is resolved in the Waaseyaa
   framework's router. Look at `WaaseyaaRouter` or `RouteBuilder` to understand controller resolution.
2. If the framework uses a container, register `VolunteerRanker` as a service.
3. If the framework instantiates controllers directly, override the controller factory in the provider.

**Likely implementation** (based on Waaseyaa patterns seen in the codebase — explicit constructor
injection via provider):

In `DashboardServiceProvider::register()`, register the `VolunteerRanker`:

```php
use Minoo\Geo\VolunteerRanker;

public function register(): void
{
    $this->container->set(VolunteerRanker::class, function ($container) {
        return new VolunteerRanker(
            $container->get(EntityTypeManager::class),
        );
    });
}
```

**Note for engineer:** The exact wiring depends on how Waaseyaa's DI works. Check
`vendor/waaseyaa/` for the `ServiceProvider` base class and how controllers receive dependencies.
The pattern should match what other providers do. If the framework auto-resolves constructor
parameters from the container, simply registering `VolunteerRanker` in the container is sufficient.

### Step 2: Run tests

Run: `vendor/bin/phpunit`
Expected: All pass.

### Step 3: Commit

```bash
git add src/Provider/DashboardServiceProvider.php
git commit -m "feat(dashboard): wire VolunteerRanker into service provider"
```

---

## Task 7: Update CoordinatorDashboardController

**Files:**
- Modify: `src/Controller/CoordinatorDashboardController.php:15-17` (add VolunteerRanker to constructor)
- Modify: `src/Controller/CoordinatorDashboardController.php:45-58` (rank volunteers per request)

### Step 1: Add VolunteerRanker dependency

In `src/Controller/CoordinatorDashboardController.php`, update the constructor:

```php
use Minoo\Geo\VolunteerRanker;

public function __construct(
    private readonly EntityTypeManager $entityTypeManager,
    private readonly Environment $twig,
    private readonly VolunteerRanker $volunteerRanker,
) {}
```

### Step 2: Resolve community and rank volunteers

Replace the volunteer loading + template rendering section (lines 45-59). After grouping requests
by status, for each open request resolve its community entity. Then rank volunteers.

```php
$volunteerStorage = $this->entityTypeManager->getStorage('volunteer');
$volunteerIds = $volunteerStorage->getQuery()
    ->condition('status', 'active')
    ->sort('name', 'ASC')
    ->execute();

$volunteers = $volunteerIds !== [] ? $volunteerStorage->loadMultiple($volunteerIds) : [];

// Resolve community for each open request and rank volunteers
$communityStorage = $this->entityTypeManager->getStorage('community');
$rankedByRequest = [];
foreach ($open as $req) {
    $communityId = $req->get('community');
    $community = $communityId !== null ? $communityStorage->load($communityId) : null;
    $rankedByRequest[$req->id()] = $this->volunteerRanker->rank($volunteers, $community);
}

$html = $this->twig->render('dashboard/coordinator.html.twig', [
    'open_requests' => $open,
    'assigned_requests' => $assigned,
    'pending_confirmation' => $pendingConfirmation,
    'confirmed_requests' => $confirmed,
    'volunteers' => $volunteers,
    'ranked_by_request' => $rankedByRequest,
]);
```

**Note:** We keep passing `volunteers` for backward compatibility (the Volunteer Pool section at the
bottom of the template still uses it).

### Step 3: Run tests

Run: `vendor/bin/phpunit`
Expected: The existing `CoordinatorDashboardController` tests (if any) may need updating to pass
`VolunteerRanker` to the constructor. If there are no existing controller tests, all tests pass.

### Step 4: Commit

```bash
git add src/Controller/CoordinatorDashboardController.php
git commit -m "feat(dashboard): rank volunteers by proximity for each open request"
```

---

## Task 8: Update Coordinator Dashboard Template

**Files:**
- Modify: `templates/dashboard/coordinator.html.twig:26-31` (volunteer dropdown with distance)

### Step 1: Update the volunteer assignment dropdown

In `templates/dashboard/coordinator.html.twig`, replace the volunteer `<select>` block (lines 26-31):

```html
{# Before: #}
<select class="form__select" name="volunteer_id" id="volunteer-{{ req.id() }}" required>
  <option value="">Select volunteer...</option>
  {% for vol in volunteers %}
  <option value="{{ vol.id() }}">{{ vol.get('name') }}{% if vol.get('community') %} ({{ vol.get('community') }}){% endif %}</option>
  {% endfor %}
</select>

{# After: #}
<select class="form__select" name="volunteer_id" id="volunteer-{{ req.id() }}" required>
  <option value="">Select volunteer...</option>
  {% for rv in ranked_by_request[req.id()] ?? [] %}
  <option value="{{ rv.volunteer.id() }}"
    {% if not rv.withinRange %} class="volunteer-option--beyond-range"{% endif %}>
    {{- rv.volunteer.get('name') -}}
    {% if rv.sameCommunity %} (same community)
    {% elseif rv.distanceKm is not null %} ({{ rv.distanceKm|number_format(0) }} km)
    {% else %} (distance unknown)
    {% endif %}
    {%- if not rv.withinRange and rv.distanceKm is not null %} — beyond stated range{% endif -%}
  </option>
  {% endfor %}
</select>
```

### Step 2: Verify template renders

Run the app manually or check that tests pass:

Run: `vendor/bin/phpunit`
Expected: All tests pass.

### Step 3: Commit

```bash
git add templates/dashboard/coordinator.html.twig
git commit -m "feat(dashboard): show volunteer distance in assignment dropdown"
```

---

## Task 9: CSS for Distance Indicators

**Files:**
- Modify: `public/css/minoo.css` (add volunteer distance styling)

### Step 1: Add CSS rules

In `public/css/minoo.css`, inside the `@layer components` block, after the `.card__note` rule
(line 972), add:

```css
  /* Volunteer distance indicators */
  .volunteer-option--beyond-range {
    color: var(--color-sun-500);
  }

  .form__hint {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-block: 0;
  }
```

### Step 2: Run full test suite

Run: `vendor/bin/phpunit`
Expected: All tests pass (CSS changes don't affect tests).

### Step 3: Commit

```bash
git add public/css/minoo.css
git commit -m "style: add distance indicator and form hint styles"
```

---

## Task 10: Final Verification

### Step 1: Run full test suite

Run: `vendor/bin/phpunit`
Expected: All tests pass — existing + 14 new tests (5 GeoDistance + 9 VolunteerRanker).

### Step 2: Verify file structure

```bash
ls -la src/Geo/
# Expected:
# GeoDistance.php
# RankedVolunteer.php
# VolunteerRanker.php

ls -la tests/Minoo/Unit/Geo/
# Expected:
# GeoDistanceTest.php
# VolunteerRankerTest.php
```

### Step 3: Verify no regressions

Run: `vendor/bin/phpunit --testsuite=MinooUnit`
Expected: All unit tests pass.

---

## Summary of Changes

| Phase | Files Created | Files Modified | Tests Added |
|-------|---------------|----------------|-------------|
| 1 — Core Geo | `src/Geo/GeoDistance.php`, `src/Geo/RankedVolunteer.php`, `src/Geo/VolunteerRanker.php` | — | 14 |
| 2 — Entity/Form | — | `ElderSupportServiceProvider.php`, `volunteer.html.twig`, `VolunteerController.php` | 0 |
| 3 — Dashboard | — | `DashboardServiceProvider.php`, `CoordinatorDashboardController.php`, `coordinator.html.twig` | 0 |
| 4 — Polish | — | `minoo.css` | 0 |

**Total new files:** 3 source + 2 test
**Total modified files:** 6
**Total new tests:** 14
**Commits:** 9

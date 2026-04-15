# Events Page Refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `/events` and `/events/{slug}` into a discovery-first page with a smart-mix default feed, filters, list and calendar views, card upgrades, and ICS export.

**Architecture:** Introduce `src/Domain/Events/` (service + value objects) mirroring `Domain/Geo/`. `EventController::list` becomes a thin adapter that parses `EventFilters` from the request, resolves `LocationContext`, and delegates to `EventFeedBuilder`, which returns a typed `EventFeedResult`. Templates switch on `filters.view` (feed / list / calendar). Single CSS file, Twig component convention preserved.

**Tech Stack:** PHP 8.4, Waaseyaa framework, Twig 3, PHPUnit 10.5, Playwright, vanilla CSS (no build step). Reference spec: `docs/plans/2026-04-14-events-page-refactor-design.md`.

**Issue number placeholder:** Every commit message in this plan uses `#717` as a placeholder. Task 0 creates the GitHub issue and produces the real number — substitute it throughout the plan before implementing later tasks (or in each commit as you go).

---

## Task 0: Preflight — create issue and branch

**Files:** none

- [ ] **Step 1: Create GitHub issue and assign to active milestone**

Run:
```bash
gh issue create \
  --title "refactor(/events): discovery-first redesign with smart-mix feed, filters, calendar" \
  --body-file docs/plans/2026-04-14-events-page-refactor-design.md \
  --milestone "v0.13"
```
Expected: URL ending with `/issues/N`. Record `N` and substitute every `#717` in this plan with `#N`.

If `v0.13` milestone is closed, check `gh api repos/waaseyaa/minoo/milestones?state=open` and use the current active milestone (see `bin/check-milestones` output at session start).

- [ ] **Step 2: Create feature branch**

Run:
```bash
git checkout -b feat/events-refactor main
```
Expected: branch switch confirmation.

- [ ] **Step 3: Verify baseline tests pass**

Run:
```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```
Expected: all tests pass. If baseline fails, stop and report.

---

## Task 1: `EventFilters` value object

**Files:**
- Create: `src/Domain/Events/ValueObject/EventFilters.php`
- Test:   `tests/App/Unit/Domain/Events/EventFiltersTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/App/Unit/Domain/Events/EventFiltersTest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Events;

use App\Domain\Events\ValueObject\EventFilters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(EventFilters::class)]
final class EventFiltersTest extends TestCase
{
    #[Test]
    public function defaults_when_query_is_empty(): void
    {
        $f = EventFilters::fromRequest(Request::create('/events'));
        $this->assertSame([], $f->types);
        $this->assertNull($f->communityId);
        $this->assertSame('all', $f->when);
        $this->assertFalse($f->near);
        $this->assertNull($f->q);
        $this->assertSame('feed', $f->view);
        $this->assertNull($f->month);
        $this->assertSame('soonest', $f->sort);
        $this->assertSame(1, $f->page);
        $this->assertFalse($f->isActive());
    }

    #[Test]
    public function parses_and_whitelists_values(): void
    {
        $r = Request::create('/events', 'GET', [
            'type' => ['powwow', 'gathering', 'hacked'],
            'community_id' => 'abc-123',
            'when' => 'week',
            'near' => '1',
            'q' => '  Sunrise  ',
            'view' => 'calendar',
            'month' => '2026-05',
            'sort' => 'latest',
            'page' => '3',
        ]);
        $f = EventFilters::fromRequest($r);
        $this->assertSame(['powwow', 'gathering'], $f->types);
        $this->assertSame('abc-123', $f->communityId);
        $this->assertSame('week', $f->when);
        $this->assertTrue($f->near);
        $this->assertSame('Sunrise', $f->q);
        $this->assertSame('calendar', $f->view);
        $this->assertSame('2026-05', $f->month);
        $this->assertSame('latest', $f->sort);
        $this->assertSame(3, $f->page);
        $this->assertTrue($f->isActive());
    }

    #[Test]
    public function rejects_invalid_whens_views_sorts_and_months(): void
    {
        $r = Request::create('/events', 'GET', [
            'when' => 'eternity',
            'view' => 'grid',
            'sort' => 'random',
            'month' => '2026/05',
            'page' => '-4',
        ]);
        $f = EventFilters::fromRequest($r);
        $this->assertSame('all', $f->when);
        $this->assertSame('feed', $f->view);
        $this->assertSame('soonest', $f->sort);
        $this->assertNull($f->month);
        $this->assertSame(1, $f->page);
    }

    #[Test]
    public function is_active_only_when_narrowing_filter_set(): void
    {
        $only_view = EventFilters::fromRequest(Request::create('/events', 'GET', ['view' => 'list']));
        $this->assertFalse($only_view->isActive(), 'view change alone is not a narrowing filter');

        $with_type = EventFilters::fromRequest(Request::create('/events', 'GET', ['type' => ['ceremony']]));
        $this->assertTrue($with_type->isActive());
    }

    #[Test]
    public function without_drops_one_param_and_preserves_others(): void
    {
        $r = Request::create('/events', 'GET', ['type' => ['powwow'], 'when' => 'month']);
        $f = EventFilters::fromRequest($r);
        $w = $f->without('type', 'powwow');
        $this->assertSame([], $w->types);
        $this->assertSame('month', $w->when);
    }
}
```

- [ ] **Step 2: Run test — expect failure**

Run: `./vendor/bin/phpunit --filter EventFiltersTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `EventFilters`**

Create `src/Domain/Events/ValueObject/EventFilters.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObject;

use Symfony\Component\HttpFoundation\Request;

final class EventFilters
{
    public const ALLOWED_TYPES  = ['powwow', 'gathering', 'ceremony', 'tournament'];
    public const ALLOWED_WHEN   = ['all', 'week', 'month', '3mo', 'past', 'day'];
    public const ALLOWED_VIEWS  = ['feed', 'list', 'calendar'];
    public const ALLOWED_SORTS  = ['soonest', 'latest'];

    /**
     * @param list<string> $types
     */
    public function __construct(
        public readonly array $types,
        public readonly ?string $communityId,
        public readonly string $when,
        public readonly bool $near,
        public readonly ?string $q,
        public readonly string $view,
        public readonly ?string $month,
        public readonly ?string $date,
        public readonly string $sort,
        public readonly int $page,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $rawTypes = (array) $request->query->all('type');
        $types = array_values(array_filter(
            array_map('strval', $rawTypes),
            static fn (string $t): bool => in_array($t, self::ALLOWED_TYPES, true),
        ));

        $communityId = $request->query->get('community_id');
        $communityId = is_string($communityId) && $communityId !== '' ? $communityId : null;

        $when = (string) $request->query->get('when', 'all');
        if (!in_array($when, self::ALLOWED_WHEN, true)) {
            $when = 'all';
        }

        $near = $request->query->getBoolean('near', false);

        $q = $request->query->get('q');
        $q = is_string($q) ? trim($q) : '';
        $q = $q === '' ? null : $q;

        $view = (string) $request->query->get('view', 'feed');
        if (!in_array($view, self::ALLOWED_VIEWS, true)) {
            $view = 'feed';
        }

        $month = $request->query->get('month');
        $month = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;

        $date = $request->query->get('date');
        $date = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;

        $sort = (string) $request->query->get('sort', 'soonest');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'soonest';
        }

        $page = (int) $request->query->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        return new self($types, $communityId, $when, $near, $q, $view, $month, $date, $sort, $page);
    }

    public function isActive(): bool
    {
        return $this->types !== []
            || $this->communityId !== null
            || $this->when !== 'all'
            || $this->near
            || $this->q !== null
            || $this->date !== null;
    }

    public function without(string $key, ?string $value = null): self
    {
        return new self(
            types:       $key === 'type'         ? array_values(array_filter($this->types, fn ($t) => $t !== $value)) : $this->types,
            communityId: $key === 'community_id' ? null : $this->communityId,
            when:        $key === 'when'         ? 'all' : $this->when,
            near:        $key === 'near'         ? false : $this->near,
            q:           $key === 'q'            ? null : $this->q,
            view:        $this->view,
            month:       $key === 'month'        ? null : $this->month,
            date:        $key === 'date'         ? null : $this->date,
            sort:        $this->sort,
            page:        1,
        );
    }
}
```

- [ ] **Step 4: Run test — expect pass**

Run: `./vendor/bin/phpunit --filter EventFiltersTest`
Expected: 5 tests, all passing.

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Events/ValueObject/EventFilters.php tests/App/Unit/Domain/Events/EventFiltersTest.php
git commit -m "feat(#717): add EventFilters value object with query-string parsing"
```

---

## Task 2: `EventFeedRanker`

**Files:**
- Create: `src/Domain/Events/Service/EventFeedRanker.php`
- Test:   `tests/App/Unit/Domain/Events/EventFeedRankerTest.php`

The ranker is pure: inputs are an entity-like stub plus a `LocationContext`, output is an int score.

- [ ] **Step 1: Write failing test**

Create `tests/App/Unit/Domain/Events/EventFeedRankerTest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Events;

use App\Domain\Events\Service\EventFeedRanker;
use App\Domain\Geo\ValueObject\LocationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;

#[CoversClass(EventFeedRanker::class)]
final class EventFeedRankerTest extends TestCase
{
    #[Test]
    public function zero_when_no_signals(): void
    {
        $event = $this->mockEvent(type: 'gathering', communityId: null);
        $ranker = new EventFeedRanker();
        $this->assertSame(0, $ranker->score($event, location: null, featuredEventIds: [], communityCoords: []));
    }

    #[Test]
    public function plus_three_when_featured(): void
    {
        $event = $this->mockEvent(id: 42, type: 'gathering');
        $ranker = new EventFeedRanker();
        $this->assertSame(3, $ranker->score($event, location: null, featuredEventIds: [42], communityCoords: []));
    }

    #[Test]
    public function plus_two_when_nearby(): void
    {
        $event = $this->mockEvent(communityId: 'c-1');
        $location = new LocationContext('c-0', 'Home', 46.49, -80.99, 'session');
        $communityCoords = ['c-1' => [46.52, -81.00]];
        $ranker = new EventFeedRanker();
        $this->assertSame(2, $ranker->score($event, $location, [], $communityCoords));
    }

    #[Test]
    public function zero_distance_when_beyond_150km(): void
    {
        $event = $this->mockEvent(communityId: 'c-far');
        $location = new LocationContext('c-0', 'Home', 46.49, -80.99, 'session');
        $communityCoords = ['c-far' => [43.65, -79.38]]; // Toronto, ~300km
        $ranker = new EventFeedRanker();
        $this->assertSame(0, $ranker->score($event, $location, [], $communityCoords));
    }

    #[Test]
    public function plus_one_for_ceremony_or_powwow(): void
    {
        $ranker = new EventFeedRanker();
        $this->assertSame(1, $ranker->score($this->mockEvent(type: 'ceremony'), null, [], []));
        $this->assertSame(1, $ranker->score($this->mockEvent(type: 'powwow'), null, [], []));
        $this->assertSame(0, $ranker->score($this->mockEvent(type: 'tournament'), null, [], []));
    }

    #[Test]
    public function scores_stack(): void
    {
        $event = $this->mockEvent(id: 7, type: 'ceremony', communityId: 'c-near');
        $location = new LocationContext('c-0', 'Home', 46.49, -80.99, 'session');
        $communityCoords = ['c-near' => [46.50, -81.00]];
        $ranker = new EventFeedRanker();
        // featured(+3) + near(+2) + ceremony(+1) = 6
        $this->assertSame(6, $ranker->score($event, $location, [7], $communityCoords));
    }

    private function mockEvent(int $id = 1, string $type = 'gathering', ?string $communityId = null): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnMap([
            ['type', $type],
            ['community_id', $communityId],
        ]);
        return $mock;
    }
}
```

- [ ] **Step 2: Run test — expect failure**

Run: `./vendor/bin/phpunit --filter EventFeedRankerTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `EventFeedRanker`**

Create `src/Domain/Events/Service/EventFeedRanker.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Events\Service;

use App\Domain\Geo\ValueObject\LocationContext;
use Waaseyaa\Entity\ContentEntityBase;

final class EventFeedRanker
{
    private const NEAR_KM = 150.0;
    private const CULTURALLY_SIGNIFICANT = ['ceremony', 'powwow'];

    /**
     * @param list<int>                        $featuredEventIds
     * @param array<string, array{float, float}> $communityCoords
     */
    public function score(
        ContentEntityBase $event,
        ?LocationContext $location,
        array $featuredEventIds,
        array $communityCoords,
    ): int {
        $score = 0;

        if (in_array((int) $event->id(), $featuredEventIds, true)) {
            $score += 3;
        }

        if ($location !== null) {
            $cid = $event->get('community_id');
            if (is_string($cid) && isset($communityCoords[$cid])) {
                [$lat, $lon] = $communityCoords[$cid];
                if ($this->haversineKm($location->latitude, $location->longitude, $lat, $lon) <= self::NEAR_KM) {
                    $score += 2;
                }
            }
        }

        if (in_array((string) $event->get('type'), self::CULTURALLY_SIGNIFICANT, true)) {
            $score += 1;
        }

        return $score;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
```

- [ ] **Step 4: Run test — expect pass**

Run: `./vendor/bin/phpunit --filter EventFeedRankerTest`
Expected: 6 tests, all passing. If `LocationContext` constructor shape differs, open `src/Domain/Geo/ValueObject/LocationContext.php` and match the real signature (adjust test factory accordingly — the spec notes the fields are communityId, name, lat, lon, source).

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Events/Service/EventFeedRanker.php tests/App/Unit/Domain/Events/EventFeedRankerTest.php
git commit -m "feat(#717): add EventFeedRanker for on-the-horizon scoring"
```

---

## Task 3: `EventFeedResult` value object

**Files:**
- Create: `src/Domain/Events/ValueObject/EventFeedResult.php`
- Create: `src/Domain/Events/ValueObject/Pagination.php`

This is a data holder. Tested transitively via `EventFeedBuilderTest`. No standalone test.

- [ ] **Step 1: Create `Pagination`**

Create `src/Domain/Events/ValueObject/Pagination.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObject;

final class Pagination
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
    ) {}

    public function totalPages(): int
    {
        return $this->total === 0 ? 1 : (int) ceil($this->total / $this->perPage);
    }

    public function hasPrev(): bool { return $this->page > 1; }
    public function hasNext(): bool { return $this->page < $this->totalPages(); }
}
```

- [ ] **Step 2: Create `EventFeedResult`**

Create `src/Domain/Events/ValueObject/EventFeedResult.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObject;

use Waaseyaa\Entity\ContentEntityBase;

final class EventFeedResult
{
    /**
     * @param list<ContentEntityBase>             $featured
     * @param list<ContentEntityBase>             $happeningNow
     * @param list<ContentEntityBase>             $thisWeek
     * @param list<ContentEntityBase>             $comingUp
     * @param list<ContentEntityBase>             $onTheHorizon
     * @param list<ContentEntityBase>             $flatList
     * @param array<string, array<string, mixed>> $communities
     * @param array{types: list<string>, communities: list<array{id:string,name:string}>} $availableFilters
     */
    public function __construct(
        public readonly array $featured,
        public readonly array $happeningNow,
        public readonly array $thisWeek,
        public readonly array $comingUp,
        public readonly array $onTheHorizon,
        public readonly array $flatList,
        public readonly ?CalendarMonth $calendarMonth,
        public readonly array $communities,
        public readonly int $totalUpcoming,
        public readonly EventFilters $activeFilters,
        public readonly array $availableFilters,
        public readonly ?Pagination $pagination,
    ) {}

    public function hasAnySectionContent(): bool
    {
        return $this->featured !== []
            || $this->happeningNow !== []
            || $this->thisWeek !== []
            || $this->comingUp !== []
            || $this->onTheHorizon !== [];
    }
}
```

Note: `CalendarMonth` class does not yet exist. Task 16 creates it. For now, add a forward-declared import that will work once Task 16 lands; PHP doesn't require the class to exist until a `CalendarMonth` instance is constructed, so the file autoloads fine.

Add the import at the top:
```php
use App\Domain\Events\ValueObject\CalendarMonth;
```

Since Tasks 4–15 will pass `null` for `$calendarMonth`, this is safe.

- [ ] **Step 3: Commit**

```bash
git add src/Domain/Events/ValueObject/Pagination.php src/Domain/Events/ValueObject/EventFeedResult.php
git commit -m "feat(#717): add EventFeedResult and Pagination value objects"
```

---

## Task 4: `EventFeedBuilder` — sectioning (happening now + this week)

**Files:**
- Create: `src/Domain/Events/Service/EventFeedBuilder.php`
- Test:   `tests/App/Unit/Domain/Events/EventFeedBuilderTest.php`

The builder takes an `EntityTypeManager` and a `LocationService`; in tests we inject stub storage.

- [ ] **Step 1: Write failing test with fixture harness**

Create `tests/App/Unit/Domain/Events/EventFeedBuilderTest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Events;

use App\Domain\Events\Service\EventFeedBuilder;
use App\Domain\Events\Service\EventFeedRanker;
use App\Domain\Events\ValueObject\EventFilters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(EventFeedBuilder::class)]
final class EventFeedBuilderTest extends TestCase
{
    #[Test]
    public function happening_now_section_contains_only_active_events(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [
            $this->event(1, type: 'powwow',    starts: $now - 3600, ends: $now + 3600),  // happening
            $this->event(2, type: 'ceremony',  starts: $now + 7200, ends: $now + 10800), // this week
            $this->event(3, type: 'gathering', starts: $now - 86400, ends: $now - 3600), // past
        ];
        $builder = $this->buildWith($events, $now);
        $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

        $this->assertCount(1, $result->happeningNow);
        $this->assertSame(1, $result->happeningNow[0]->id());
    }

    #[Test]
    public function this_week_section_contains_events_in_next_7_days(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [
            $this->event(10, starts: $now + 86400),       // +1d
            $this->event(11, starts: $now + 6 * 86400),   // +6d
            $this->event(12, starts: $now + 9 * 86400),   // +9d — should NOT be in thisWeek
        ];
        $builder = $this->buildWith($events, $now);
        $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

        $ids = array_map(fn ($e) => $e->id(), $result->thisWeek);
        $this->assertSame([10, 11], $ids);
    }

    /** @param list<ContentEntityBase> $events */
    private function buildWith(array $events, int $now): EventFeedBuilder
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('loadMultiple')->willReturn($events);

        // getQuery()->condition(...)->sort(...)->execute() returns IDs; loadMultiple returns entities keyed by id.
        $queryStub = new class ($events) {
            public function __construct(private array $events) {}
            public function condition(...$args): self { return $this; }
            public function sort(...$args): self { return $this; }
            public function execute(): array { return array_map(fn ($e) => $e->id(), $this->events); }
        };
        $storage->method('getQuery')->willReturn($queryStub);
        $storage->method('loadMultiple')->willReturnCallback(
            fn (array $ids) => array_combine($ids, array_values($this->eventsById($events, $ids)))
        );

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        return new EventFeedBuilder($etm, new EventFeedRanker(), clock: fn () => $now);
    }

    /** @param list<ContentEntityBase> $events @param list<int> $ids @return list<ContentEntityBase> */
    private function eventsById(array $events, array $ids): array
    {
        $byId = [];
        foreach ($events as $e) { $byId[$e->id()] = $e; }
        return array_values(array_filter(array_map(fn ($id) => $byId[$id] ?? null, $ids)));
    }

    private function event(int $id, string $type = 'gathering', ?string $communityId = null, int $starts = 0, ?int $ends = null): ContentEntityBase
    {
        $ends ??= $starts + 3600;
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnMap([
            ['type', $type],
            ['community_id', $communityId],
            ['starts_at', $starts],
            ['ends_at', $ends],
            ['status', 1],
            ['slug', 'e-' . $id],
            ['title', 'Event ' . $id],
            ['description', ''],
            ['location', ''],
        ]);
        return $mock;
    }
}
```

- [ ] **Step 2: Run test — expect failure**

Run: `./vendor/bin/phpunit --filter EventFeedBuilderTest`
Expected: FAIL — `EventFeedBuilder` not found.

- [ ] **Step 3: Implement builder skeleton + happening-now + this-week**

Create `src/Domain/Events/Service/EventFeedBuilder.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Events\Service;

use App\Domain\Events\ValueObject\EventFeedResult;
use App\Domain\Events\ValueObject\EventFilters;
use App\Domain\Geo\ValueObject\LocationContext;
use Closure;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;

final class EventFeedBuilder
{
    /** @var Closure():int */
    private Closure $clock;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EventFeedRanker $ranker,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function build(EventFilters $filters, ?LocationContext $location): EventFeedResult
    {
        $now = ($this->clock)();
        $all = $this->loadUpcomingAndActive($now);

        $happeningNow = array_values(array_filter(
            $all,
            fn (ContentEntityBase $e) => $this->isHappeningNow($e, $now)
        ));
        usort($happeningNow, fn ($a, $b) => (int) $a->get('starts_at') <=> (int) $b->get('starts_at'));

        $thisWeek = array_values(array_filter(
            $all,
            fn (ContentEntityBase $e) => $this->isThisWeek($e, $now)
        ));
        usort($thisWeek, fn ($a, $b) => (int) $a->get('starts_at') <=> (int) $b->get('starts_at'));

        return new EventFeedResult(
            featured:         [],
            happeningNow:     $happeningNow,
            thisWeek:         $thisWeek,
            comingUp:         [],
            onTheHorizon:     [],
            flatList:         [],
            calendarMonth:    null,
            communities:      [],
            totalUpcoming:    count($all),
            activeFilters:    $filters,
            availableFilters: ['types' => [], 'communities' => []],
            pagination:       null,
        );
    }

    /** @return list<ContentEntityBase> */
    private function loadUpcomingAndActive(int $now): array
    {
        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('ends_at', $now, '>=')
            ->sort('starts_at', 'ASC')
            ->execute();
        if ($ids === []) {
            return [];
        }
        return array_values($storage->loadMultiple($ids));
    }

    private function isHappeningNow(ContentEntityBase $e, int $now): bool
    {
        $s = (int) $e->get('starts_at');
        $f = (int) $e->get('ends_at');
        return $s <= $now && $now <= $f;
    }

    private function isThisWeek(ContentEntityBase $e, int $now): bool
    {
        $s = (int) $e->get('starts_at');
        return $s > $now && $s <= $now + 7 * 86400;
    }
}
```

- [ ] **Step 4: Run test — expect pass**

Run: `./vendor/bin/phpunit --filter EventFeedBuilderTest`
Expected: 2 tests passing. If the mocked `getQuery()->condition()->condition()->sort()->execute()` chain mismatches storage usage, adjust the anonymous-class stub accordingly.

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Events/Service/EventFeedBuilder.php tests/App/Unit/Domain/Events/EventFeedBuilderTest.php
git commit -m "feat(#717): add EventFeedBuilder with happening-now and this-week sections"
```

---

## Task 5: Builder — coming-up + diversity rules

**Files:**
- Modify: `src/Domain/Events/Service/EventFeedBuilder.php`
- Modify: `tests/App/Unit/Domain/Events/EventFeedBuilderTest.php`

- [ ] **Step 1: Add failing tests**

Append to `EventFeedBuilderTest`:
```php
#[Test]
public function coming_up_caps_at_12(): void
{
    $now = strtotime('2026-04-14 12:00:00');
    $events = [];
    for ($i = 0; $i < 20; $i++) {
        $events[] = $this->event($i + 100, starts: $now + 10 * 86400 + $i * 3600);
    }
    $builder = $this->buildWith($events, $now);
    $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);
    $this->assertCount(12, $result->comingUp);
}

#[Test]
public function coming_up_limits_three_same_type_in_a_row(): void
{
    $now = strtotime('2026-04-14 12:00:00');
    // Four powwows back-to-back in the coming-up window; the 4th should be deferred.
    $events = [
        $this->event(200, type: 'powwow',    starts: $now + 10 * 86400),
        $this->event(201, type: 'powwow',    starts: $now + 11 * 86400),
        $this->event(202, type: 'powwow',    starts: $now + 12 * 86400),
        $this->event(203, type: 'powwow',    starts: $now + 13 * 86400), // violates
        $this->event(204, type: 'gathering', starts: $now + 14 * 86400),
    ];
    $builder = $this->buildWith($events, $now);
    $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

    $types = array_map(fn ($e) => $e->get('type'), $result->comingUp);
    $this->assertNotSame(['powwow', 'powwow', 'powwow', 'powwow', 'gathering'], $types,
        'diversity rule should move the 4th consecutive powwow back');
    $this->assertSame('gathering', $types[3] ?? null, '4th slot should be gathering after diversity swap');
}

#[Test]
public function coming_up_limits_two_from_same_community_in_top_six(): void
{
    $now = strtotime('2026-04-14 12:00:00');
    $events = [
        $this->event(300, type: 'powwow',    communityId: 'A', starts: $now + 10 * 86400),
        $this->event(301, type: 'gathering', communityId: 'A', starts: $now + 11 * 86400),
        $this->event(302, type: 'ceremony',  communityId: 'A', starts: $now + 12 * 86400),
        $this->event(303, type: 'gathering', communityId: 'B', starts: $now + 13 * 86400),
        $this->event(304, type: 'powwow',    communityId: 'B', starts: $now + 14 * 86400),
        $this->event(305, type: 'ceremony',  communityId: 'C', starts: $now + 15 * 86400),
    ];
    $builder = $this->buildWith($events, $now);
    $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

    $top6 = array_slice($result->comingUp, 0, 6);
    $communities = array_map(fn ($e) => $e->get('community_id'), $top6);
    $counts = array_count_values($communities);
    foreach ($counts as $cid => $count) {
        $this->assertLessThanOrEqual(2, $count, "community $cid should appear at most 2x in top 6");
    }
}
```

- [ ] **Step 2: Run — expect failure**

Run: `./vendor/bin/phpunit --filter EventFeedBuilderTest`
Expected: 3 new tests FAIL.

- [ ] **Step 3: Implement coming-up with diversity**

Edit `src/Domain/Events/Service/EventFeedBuilder.php`. In `build()`, after computing `$thisWeek`, add:

```php
$comingUpWindow = array_values(array_filter(
    $all,
    fn (ContentEntityBase $e) => $this->isInComingUpWindow($e, $now)
));
usort($comingUpWindow, fn ($a, $b) => (int) $a->get('starts_at') <=> (int) $b->get('starts_at'));
$comingUp = $this->applyDiversity($comingUpWindow, cap: 12);
```

And pass `comingUp: $comingUp,` into the result instead of `[]`.

Add methods:
```php
private function isInComingUpWindow(ContentEntityBase $e, int $now): bool
{
    $s = (int) $e->get('starts_at');
    return $s > $now + 7 * 86400 && $s <= $now + 30 * 86400;
}

/**
 * @param list<ContentEntityBase> $events
 * @return list<ContentEntityBase>
 */
private function applyDiversity(array $events, int $cap): array
{
    $out = [];
    $remaining = $events;

    while (count($out) < $cap && $remaining !== []) {
        $picked = null;
        foreach ($remaining as $i => $candidate) {
            if ($this->violatesDiversity($out, $candidate)) {
                continue;
            }
            $picked = $candidate;
            unset($remaining[$i]);
            $remaining = array_values($remaining);
            break;
        }
        if ($picked === null) {
            // No candidate satisfies constraints — relax and take the next in order.
            $picked = array_shift($remaining);
        }
        $out[] = $picked;
    }
    return $out;
}

/**
 * @param list<ContentEntityBase> $picked
 */
private function violatesDiversity(array $picked, ContentEntityBase $candidate): bool
{
    $type = (string) $candidate->get('type');
    $community = $candidate->get('community_id');

    // No more than 3 of the same type in a row.
    $tail = array_slice($picked, -3);
    if (count($tail) === 3 && array_reduce($tail, fn ($ok, $e) => $ok && $e->get('type') === $type, true)) {
        return true;
    }

    // No more than 2 from the same community in the top 6.
    if (count($picked) < 6 && is_string($community)) {
        $top = array_slice($picked, 0, 6);
        $same = array_filter($top, fn ($e) => $e->get('community_id') === $community);
        if (count($same) >= 2) {
            return true;
        }
    }
    return false;
}
```

- [ ] **Step 4: Run — expect pass**

Run: `./vendor/bin/phpunit --filter EventFeedBuilderTest`
Expected: all 5 tests passing.

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Events/Service/EventFeedBuilder.php tests/App/Unit/Domain/Events/EventFeedBuilderTest.php
git commit -m "feat(#717): add coming-up section with type/community diversity"
```

---

## Task 6: Builder — on-the-horizon + featured strip

**Files:**
- Modify: `src/Domain/Events/Service/EventFeedBuilder.php`
- Modify: `tests/App/Unit/Domain/Events/EventFeedBuilderTest.php`

- [ ] **Step 1: Add failing test**

Append to `EventFeedBuilderTest`:
```php
#[Test]
public function on_the_horizon_caps_at_6_and_ranks_featured_first(): void
{
    $now = strtotime('2026-04-14 12:00:00');
    $events = [];
    for ($i = 0; $i < 10; $i++) {
        $events[] = $this->event($i + 500, type: 'gathering', starts: $now + (60 + $i) * 86400);
    }
    // Mark id 509 as featured (latest start but highest score).
    $builder = $this->buildWithFeatured($events, $now, featuredEventIds: [509]);
    $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

    $this->assertCount(6, $result->onTheHorizon);
    $this->assertSame(509, $result->onTheHorizon[0]->id(), 'featured event should lead');
}

/**
 * @param list<ContentEntityBase> $events
 * @param list<int>               $featuredEventIds
 */
private function buildWithFeatured(array $events, int $now, array $featuredEventIds): EventFeedBuilder
{
    // Build a builder whose "featured lookup" closure returns the provided IDs.
    // For simplicity, we inject via a test-only setter on the builder; real wiring uses storage.
    $builder = $this->buildWith($events, $now);
    $builder->setFeaturedEventIdsForTesting($featuredEventIds);
    return $builder;
}
```

- [ ] **Step 2: Run — expect failure**

Run: `./vendor/bin/phpunit --filter EventFeedBuilderTest`
Expected: FAIL.

- [ ] **Step 3: Implement horizon + featured**

In `EventFeedBuilder`, add:

```php
/** @var list<int>|null */
private ?array $featuredOverride = null;

/** @internal for tests */
public function setFeaturedEventIdsForTesting(array $ids): void
{
    $this->featuredOverride = $ids;
}
```

Update `build()` to also compute `$onTheHorizon` and `$featured`:
```php
$featuredIds = $this->featuredOverride ?? $this->loadFeaturedEventIds($now);
$communityCoords = $this->loadCommunityCoords($all);

$horizonWindow = array_values(array_filter(
    $all,
    fn (ContentEntityBase $e) => $this->isOnHorizon($e, $now)
));
usort($horizonWindow, function (ContentEntityBase $a, ContentEntityBase $b) use ($location, $featuredIds, $communityCoords) {
    $sa = $this->ranker->score($a, $location, $featuredIds, $communityCoords);
    $sb = $this->ranker->score($b, $location, $featuredIds, $communityCoords);
    if ($sa !== $sb) return $sb <=> $sa;
    return (int) $a->get('starts_at') <=> (int) $b->get('starts_at');
});
$onTheHorizon = array_slice($horizonWindow, 0, 6);

$featured = array_values(array_filter(
    $all,
    fn (ContentEntityBase $e) => in_array((int) $e->id(), $featuredIds, true)
));
```

Pass `featured: $featured,` and `onTheHorizon: $onTheHorizon,` to the result. Dedupe the sections so a featured event that also ranks into the horizon appears only once in horizon — skip it in horizon if it's already in featured:
```php
$featuredIdsSet = array_flip(array_map(fn ($e) => (int) $e->id(), $featured));
$onTheHorizon = array_values(array_filter($onTheHorizon, fn ($e) => !isset($featuredIdsSet[(int) $e->id()])));
$onTheHorizon = array_slice($onTheHorizon, 0, 6);
```

Add helpers:
```php
private function isOnHorizon(ContentEntityBase $e, int $now): bool
{
    $s = (int) $e->get('starts_at');
    return $s > $now + 30 * 86400 && $s <= $now + 730 * 86400;
}

/** @return list<int> */
private function loadFeaturedEventIds(int $now): array
{
    $storage = $this->entityTypeManager->getStorage('featured_item');
    $ids = $storage->getQuery()
        ->condition('status', 1)
        ->condition('entity_type', 'event')
        ->condition('starts_at', $now, '<=')
        ->condition('ends_at', $now, '>=')
        ->execute();
    if ($ids === []) return [];
    $items = $storage->loadMultiple($ids);
    return array_values(array_map(fn ($i) => (int) $i->get('entity_id'), $items));
}

/**
 * @param list<ContentEntityBase> $events
 * @return array<string, array{float, float}>
 */
private function loadCommunityCoords(array $events): array
{
    $ids = [];
    foreach ($events as $e) {
        $cid = $e->get('community_id');
        if (is_string($cid) && $cid !== '') {
            $ids[$cid] = true;
        }
    }
    if ($ids === []) return [];
    $storage = $this->entityTypeManager->getStorage('community');
    $communities = $storage->loadMultiple(array_keys($ids));
    $out = [];
    foreach ($communities as $cid => $c) {
        $lat = $c->get('latitude');
        $lon = $c->get('longitude');
        if (is_numeric($lat) && is_numeric($lon)) {
            $out[(string) $cid] = [(float) $lat, (float) $lon];
        }
    }
    return $out;
}
```

- [ ] **Step 4: Run — expect pass**

Run: `./vendor/bin/phpunit --filter EventFeedBuilderTest`
Expected: all tests passing. If `community` entity field names differ (`latitude`/`longitude`), inspect `src/Entity/Community.php` / community storage and align.

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Events/Service/EventFeedBuilder.php tests/App/Unit/Domain/Events/EventFeedBuilderTest.php
git commit -m "feat(#717): add on-the-horizon ranking and featured strip"
```

---

## Task 7: Builder — flat-list short-circuit on active filters

**Files:**
- Modify: `src/Domain/Events/Service/EventFeedBuilder.php`
- Modify: `tests/App/Unit/Domain/Events/EventFeedBuilderTest.php`

- [ ] **Step 1: Add failing tests**

```php
#[Test]
public function active_filter_returns_flat_list_not_sections(): void
{
    $now = strtotime('2026-04-14 12:00:00');
    $events = [
        $this->event(700, type: 'powwow',    starts: $now + 86400),
        $this->event(701, type: 'ceremony',  starts: $now + 2 * 86400),
        $this->event(702, type: 'powwow',    starts: $now + 3 * 86400),
    ];
    $builder = $this->buildWith($events, $now);
    $filters = EventFilters::fromRequest(Request::create('/events', 'GET', ['type' => ['powwow']]));
    $result = $builder->build($filters, null);

    $this->assertSame([], $result->happeningNow);
    $this->assertSame([], $result->thisWeek);
    $this->assertSame([], $result->comingUp);
    $this->assertSame([700, 702], array_map(fn ($e) => $e->id(), $result->flatList));
}

#[Test]
public function past_filter_returns_ended_events_desc(): void
{
    $now = strtotime('2026-04-14 12:00:00');
    $events = [
        $this->event(800, starts: $now - 10 * 86400, ends: $now - 9 * 86400),
        $this->event(801, starts: $now - 3 * 86400,  ends: $now - 2 * 86400),
        $this->event(802, starts: $now + 86400,      ends: $now + 2 * 86400), // upcoming, excluded
    ];
    $builder = $this->buildWith($events, $now, loadPast: true);
    $filters = EventFilters::fromRequest(Request::create('/events', 'GET', ['when' => 'past']));
    $result = $builder->build($filters, null);

    $this->assertSame([801, 800], array_map(fn ($e) => $e->id(), $result->flatList));
}
```

Update `buildWith` signature to accept `bool $loadPast = false` and return past events from storage when requested (adjust anonymous query stub to always return all event IDs; filtering happens in the builder for this simpler setup).

- [ ] **Step 2: Run — expect failure**

Run: `./vendor/bin/phpunit --filter EventFeedBuilderTest`
Expected: FAIL.

- [ ] **Step 3: Implement filter path**

At the top of `EventFeedBuilder::build()`, after `$now = ...`:

```php
if ($filters->isActive()) {
    return $this->buildFiltered($filters, $location, $now);
}
```

Add:
```php
private function buildFiltered(EventFilters $filters, ?LocationContext $location, int $now): EventFeedResult
{
    $storage = $this->entityTypeManager->getStorage('event');
    $q = $storage->getQuery()->condition('status', 1);

    $past = $filters->when === 'past';
    if ($past) {
        $q->condition('ends_at', $now, '<');
    } else {
        $q->condition('ends_at', $now, '>=');
    }

    if ($filters->types !== []) {
        $q->condition('type', $filters->types, 'IN');
    }
    if ($filters->communityId !== null) {
        $q->condition('community_id', $filters->communityId);
    }
    if ($filters->when === 'week')  { $q->condition('starts_at', $now + 7 * 86400, '<='); }
    if ($filters->when === 'month') { $q->condition('starts_at', $now + 30 * 86400, '<='); }
    if ($filters->when === '3mo')   { $q->condition('starts_at', $now + 90 * 86400, '<='); }
    if ($filters->when === 'day' && $filters->date !== null) {
        $start = strtotime($filters->date . ' 00:00:00');
        $end   = strtotime($filters->date . ' 23:59:59');
        $q->condition('starts_at', $end, '<=');
        $q->condition('ends_at',   $start, '>=');
    }

    $dir = $past || $filters->sort === 'latest' ? 'DESC' : 'ASC';
    $q->sort('starts_at', $dir);

    $ids = $q->execute();
    $all = $ids === [] ? [] : array_values($storage->loadMultiple($ids));

    // Naive text filter — simple LIKE on loaded set is fine for current volume.
    if ($filters->q !== null) {
        $needle = mb_strtolower($filters->q);
        $all = array_values(array_filter($all, function (ContentEntityBase $e) use ($needle) {
            foreach (['title', 'description', 'location'] as $f) {
                if (str_contains(mb_strtolower((string) $e->get($f)), $needle)) return true;
            }
            return false;
        }));
    }

    $perPage = 30;
    $total = count($all);
    $slice = array_slice($all, ($filters->page - 1) * $perPage, $perPage);

    return new EventFeedResult(
        featured:         [],
        happeningNow:     [],
        thisWeek:         [],
        comingUp:         [],
        onTheHorizon:     [],
        flatList:         $slice,
        calendarMonth:    null,
        communities:      $this->buildCommunityLookup($slice),
        totalUpcoming:    $total,
        activeFilters:    $filters,
        availableFilters: $this->availableFiltersFor($all),
        pagination:       new \App\Domain\Events\ValueObject\Pagination($filters->page, $perPage, $total),
    );
}

/** @param list<ContentEntityBase> $events @return array<string, array<string, mixed>> */
private function buildCommunityLookup(array $events): array
{
    $ids = [];
    foreach ($events as $e) {
        $cid = $e->get('community_id');
        if (is_string($cid) && $cid !== '') { $ids[$cid] = true; }
    }
    if ($ids === []) return [];
    $storage = $this->entityTypeManager->getStorage('community');
    $out = [];
    foreach ($storage->loadMultiple(array_keys($ids)) as $cid => $c) {
        $out[(string) $cid] = [
            'name' => (string) $c->get('name'),
            'slug' => (string) $c->get('slug'),
        ];
    }
    return $out;
}

/**
 * @param list<ContentEntityBase> $events
 * @return array{types: list<string>, communities: list<array{id:string,name:string}>}
 */
private function availableFiltersFor(array $events): array
{
    $types = []; $communities = [];
    foreach ($events as $e) {
        $t = (string) $e->get('type');
        if ($t !== '') $types[$t] = true;
        $c = $e->get('community_id');
        if (is_string($c) && $c !== '') $communities[$c] = true;
    }
    $lookup = $this->buildCommunityLookup($events);
    $cList = [];
    foreach (array_keys($communities) as $cid) {
        $cList[] = ['id' => $cid, 'name' => $lookup[$cid]['name'] ?? $cid];
    }
    sort($types);
    usort($cList, fn ($a, $b) => strcmp($a['name'], $b['name']));
    return ['types' => array_values(array_keys($types)), 'communities' => $cList];
}
```

Also populate `$communities` and `$availableFilters` in the non-filtered path by calling those helpers with `$all`.

- [ ] **Step 4: Run — expect pass**

Run: `./vendor/bin/phpunit --filter EventFeedBuilderTest`
Expected: all tests passing.

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Events/Service/EventFeedBuilder.php tests/App/Unit/Domain/Events/EventFeedBuilderTest.php
git commit -m "feat(#717): add filter short-circuit with past and q search"
```

---

## Task 8: Register services + wire controller

**Files:**
- Modify: `src/Provider/AppServiceProvider.php`
- Modify: `src/Controller/EventController.php`

- [ ] **Step 1: Register singletons**

Open `src/Provider/AppServiceProvider.php`. Find the `register()` method where other singletons are bound. Add:

```php
$this->singleton(\App\Domain\Events\Service\EventFeedRanker::class, fn () => new \App\Domain\Events\Service\EventFeedRanker());
$this->singleton(\App\Domain\Events\Service\EventFeedBuilder::class, fn () => new \App\Domain\Events\Service\EventFeedBuilder(
    $this->resolve(\Waaseyaa\Entity\EntityTypeManager::class),
    $this->resolve(\App\Domain\Events\Service\EventFeedRanker::class),
));
```

If a `LocationService` is needed inside the controller (not the builder), it's already resolvable via the existing `resolveLocation()` helper pattern used elsewhere — do not add it to the builder constructor.

- [ ] **Step 2: Refactor EventController::list**

Edit `src/Controller/EventController.php`. Change the constructor to inject the builder:
```php
public function __construct(
    private readonly EntityTypeManager $entityTypeManager,
    private readonly Environment $twig,
    private readonly \App\Domain\Events\Service\EventFeedBuilder $eventFeedBuilder,
) {}
```

Replace `list()`:
```php
public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $filters  = \App\Domain\Events\ValueObject\EventFilters::fromRequest($request);
    $location = $this->resolveLocation($request, $account); // existing helper pattern; if missing, resolve LocationService lazily and call resolve()
    $result   = $this->eventFeedBuilder->build($filters, $location);

    $html = $this->twig->render('events.html.twig', LayoutTwigContext::withAccount($account, [
        'path'    => $request->getPathInfo(),
        'filters' => $filters,
        'feed'    => $result,
    ]));
    return new Response($html);
}
```

If `resolveLocation()` doesn't exist on the controller yet, add:
```php
private function resolveLocation(HttpRequest $request, AccountInterface $account): ?\App\Domain\Geo\ValueObject\LocationContext
{
    try {
        $service = app()->resolve(\App\Domain\Geo\Service\LocationService::class);
        return $service->resolve($request, $account);
    } catch (\Throwable) {
        return null;
    }
}
```

(Adjust to match the actual `LocationService` API; memory says it resolves via session → cookie → IP.)

- [ ] **Step 3: Clear manifest and run full tests**

Run:
```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```
Expected: all tests pass. Existing `EventControllerTest` may need minor adjustment if it asserted the old flat-list shape — update the assertions to read from `feed.happeningNow` / `feed.thisWeek` etc. in the rendered HTML (or just assert a 200 and presence of titles).

- [ ] **Step 4: Commit**

```bash
git add src/Provider/AppServiceProvider.php src/Controller/EventController.php tests/
git commit -m "feat(#717): wire EventFeedBuilder into EventController"
```

---

## Task 9: Rewrite events.html.twig listing branch (feed view only, no filter UI yet)

**Files:**
- Modify: `templates/events.html.twig`
- Create: `templates/components/event-feed.html.twig`
- Create: `templates/components/event-feed-section.html.twig`
- Modify: `resources/lang/en.php`

- [ ] **Step 1: Add translation keys**

Append to `resources/lang/en.php` under an `events` namespace (match the file's existing nested-array convention):

```php
'events' => [
    // ... existing keys kept ...
    'section_featured'      => 'Featured',
    'section_happening_now' => 'Happening now',
    'section_this_week'     => 'This week',
    'section_coming_up'     => 'Coming up',
    'section_on_horizon'    => 'On the horizon',
    'see_all_upcoming'      => 'See all upcoming →',
    'view_feed'             => 'Feed',
    'view_list'             => 'List',
    'view_calendar'         => 'Calendar',
    'filter_all'            => 'All upcoming',
    'filter_week'           => 'This week',
    'filter_month'          => 'This month',
    'filter_3mo'            => 'Next 3 months',
    'filter_past'           => 'Past',
    'filter_near_me'        => 'Near me',
    'filter_search_ph'      => 'Search events…',
    'filter_type_label'     => 'Type',
    'filter_community_label'=> 'Community',
    'filter_dismiss'        => 'Remove filter',
    'no_results_heading'    => 'No events match those filters',
    'no_results_body'       => 'Try removing a filter, or browse all upcoming events.',
    'ics_download'          => 'Add to calendar',
    'similar_upcoming'      => 'Similar upcoming events',
    'distance_km'           => '{km} km away',
    'rel_today'             => 'Today',
    'rel_tomorrow'          => 'Tomorrow',
    'rel_in_days'           => 'in {n} days',
    'rel_this_week'         => 'This week',
    'rel_happening_now'     => 'Happening now',
    'rel_past'              => '{date}',
],
```

- [ ] **Step 2: Create section component**

`templates/components/event-feed-section.html.twig`:
```twig
{% if events|length > 0 %}
  <section class="event-feed__section">
    <header class="event-feed__section-header">
      <h2>{{ title }}</h2>
      <span class="event-feed__section-count">{{ events|length }}</span>
    </header>
    <div class="card-grid">
      {% for e in events %}
        {% set comm = communities[e.get('community_id')]|default({}) %}
        {% include "components/event-card.html.twig" with {
          title: e.get('title'),
          type: e.get('type')|default('')|capitalize,
          date: e.get('starts_at') ? e.get('starts_at')|date('F j, Y') : '',
          starts_at: e.get('starts_at'),
          ends_at: e.get('ends_at'),
          location: e.get('location')|default(''),
          excerpt: e.get('description')|default('')|split("\n\n")|first|length > 120
            ? e.get('description')|default('')|split("\n\n")|first|slice(0, 120) ~ '…'
            : e.get('description')|default('')|split("\n\n")|first,
          url: "/events/" ~ e.get('slug'),
          community_name: comm.name|default(''),
          community_slug: comm.slug|default(''),
          type_key: e.get('type')|default('')
        } %}
      {% endfor %}
    </div>
  </section>
{% endif %}
```

- [ ] **Step 3: Create feed wrapper**

`templates/components/event-feed.html.twig`:
```twig
{% include "components/event-feed-section.html.twig" with {
  title: trans('events.section_featured'),
  events: feed.featured,
  communities: feed.communities
} %}
{% include "components/event-feed-section.html.twig" with {
  title: trans('events.section_happening_now'),
  events: feed.happeningNow,
  communities: feed.communities
} %}
{% include "components/event-feed-section.html.twig" with {
  title: trans('events.section_this_week'),
  events: feed.thisWeek,
  communities: feed.communities
} %}
{% include "components/event-feed-section.html.twig" with {
  title: trans('events.section_coming_up'),
  events: feed.comingUp,
  communities: feed.communities
} %}
{% include "components/event-feed-section.html.twig" with {
  title: trans('events.section_on_horizon'),
  events: feed.onTheHorizon,
  communities: feed.communities
} %}

{% if not feed.hasAnySectionContent() %}
  {% include "components/empty-state.html.twig" with {
    heading: trans('events.empty_heading'),
    body: trans('events.empty_body'),
    action_url: lang_url('/communities'),
    action_label: trans('events.explore_button')
  } %}
{% endif %}

<p class="event-feed__see-all">
  <a href="{{ lang_url('/events') }}?view=list">{{ trans('events.see_all_upcoming') }}</a>
</p>
```

- [ ] **Step 4: Rewrite listing branch in `events.html.twig`**

Replace the `{% if path == '/events' %}` branch body with:
```twig
<div class="flow-lg">
  <div class="listing-hero">
    <h1>{{ trans('events.title') }}</h1>
    <p class="listing-hero__subtitle">{{ trans('events.subtitle') }}</p>
  </div>

  {% if filters.view == 'feed' and not filters.isActive() %}
    {% include "components/event-feed.html.twig" %}
  {% else %}
    {# filter/list/calendar views land in later tasks #}
    {% include "components/event-feed.html.twig" %}
  {% endif %}
</div>
```

- [ ] **Step 5: Manual smoke + commit**

Run dev server and open `/events`:
```bash
php -S localhost:8080 -t public public/index.php
```
Open in browser: `http://localhost:8080/events`. Verify sections render and that past events no longer appear. Stop server.

```bash
git add templates/events.html.twig templates/components/event-feed.html.twig templates/components/event-feed-section.html.twig resources/lang/en.php
git commit -m "feat(#717): render sectioned event feed in listing view"
```

---

## Task 10: Filter bar component + wiring

**Files:**
- Create: `templates/components/event-filters.html.twig`
- Modify: `templates/events.html.twig`
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Create filter bar**

`templates/components/event-filters.html.twig`:
```twig
<form class="event-filters" method="get" action="{{ lang_url('/events') }}">
  {# View toggle #}
  <div class="event-filters__views" role="tablist" aria-label="{{ trans('events.title') }}">
    {% for v in ['feed','list','calendar'] %}
      <button type="submit" name="view" value="{{ v }}"
              class="event-filters__view{{ filters.view == v ? ' is-active' : '' }}"
              aria-pressed="{{ filters.view == v ? 'true' : 'false' }}">
        {{ trans('events.view_' ~ v) }}
      </button>
    {% endfor %}
  </div>

  {# Time chips #}
  <div class="event-filters__chips" role="group" aria-label="When">
    {% for w in ['all','week','month','3mo','past'] %}
      <button type="submit" name="when" value="{{ w }}"
              class="event-filters__chip{{ filters.when == w ? ' is-active' : '' }}"
              aria-pressed="{{ filters.when == w ? 'true' : 'false' }}">
        {{ trans('events.filter_' ~ w) }}
      </button>
    {% endfor %}
  </div>

  <div class="event-filters__row">
    <label class="event-filters__field">
      <span>{{ trans('events.filter_type_label') }}</span>
      <select name="type[]" multiple size="4">
        {% for t in ['powwow','gathering','ceremony','tournament'] %}
          <option value="{{ t }}"{% if t in filters.types %} selected{% endif %}>{{ t|capitalize }}</option>
        {% endfor %}
      </select>
    </label>

    {% if feed.availableFilters.communities|length > 0 %}
      <label class="event-filters__field">
        <span>{{ trans('events.filter_community_label') }}</span>
        <select name="community_id">
          <option value="">—</option>
          {% for c in feed.availableFilters.communities %}
            <option value="{{ c.id }}"{% if c.id == filters.communityId %} selected{% endif %}>{{ c.name }}</option>
          {% endfor %}
        </select>
      </label>
    {% endif %}

    <label class="event-filters__field event-filters__field--search">
      <span class="visually-hidden">Search</span>
      <input type="search" name="q" value="{{ filters.q }}" placeholder="{{ trans('events.filter_search_ph') }}">
    </label>

    {% if location is defined and location %}
      <label class="event-filters__field event-filters__field--near">
        <input type="checkbox" name="near" value="1"{% if filters.near %} checked{% endif %}>
        <span>{{ trans('events.filter_near_me') }}</span>
      </label>
    {% endif %}

    <button type="submit" class="btn btn--primary event-filters__apply">Apply</button>
    {% if filters.isActive() %}
      <a href="{{ lang_url('/events') }}?view={{ filters.view }}" class="event-filters__clear">Clear all</a>
    {% endif %}
  </div>

  {# Active filter chips #}
  {% if filters.isActive() %}
    <div class="event-filters__active">
      {% for t in filters.types %}
        <a class="event-filters__active-chip" href="{{ path_without(filters, 'type', t) }}">
          {{ t|capitalize }} ×
        </a>
      {% endfor %}
      {% if filters.communityId %}
        <a class="event-filters__active-chip" href="{{ path_without(filters, 'community_id') }}">
          {{ (feed.communities[filters.communityId].name)|default(filters.communityId) }} ×
        </a>
      {% endif %}
      {% if filters.when != 'all' %}
        <a class="event-filters__active-chip" href="{{ path_without(filters, 'when') }}">
          {{ trans('events.filter_' ~ filters.when) }} ×
        </a>
      {% endif %}
      {% if filters.q %}
        <a class="event-filters__active-chip" href="{{ path_without(filters, 'q') }}">"{{ filters.q }}" ×</a>
      {% endif %}
      {% if filters.near %}
        <a class="event-filters__active-chip" href="{{ path_without(filters, 'near') }}">
          {{ trans('events.filter_near_me') }} ×
        </a>
      {% endif %}
    </div>
  {% endif %}
</form>
```

- [ ] **Step 2: Implement `path_without` Twig function**

The simplest path: add a helper method to `EventFilters` already — `without()` — and a Twig function that URL-encodes. Add to the controller's Twig context:

In `EventController::list`, compute the URLs for every active filter chip before rendering, or simpler: register a Twig function in the existing app Twig extension.

Find where existing app Twig functions like `lang_url` are registered (search for `new TwigFunction('lang_url'`). Alongside, add:
```php
new TwigFunction('path_without', function (\App\Domain\Events\ValueObject\EventFilters $f, string $key, ?string $value = null) {
    $next = $f->without($key, $value);
    $params = array_filter([
        'view'         => $next->view !== 'feed' ? $next->view : null,
        'when'         => $next->when !== 'all' ? $next->when : null,
        'type'         => $next->types !== [] ? $next->types : null,
        'community_id' => $next->communityId,
        'q'            => $next->q,
        'near'         => $next->near ? '1' : null,
        'month'        => $next->month,
        'date'         => $next->date,
        'sort'         => $next->sort !== 'soonest' ? $next->sort : null,
    ], fn ($v) => $v !== null && $v !== '');
    return '/events' . ($params ? '?' . http_build_query($params) : '');
})
```

- [ ] **Step 3: Flat list when filters active**

Modify `templates/events.html.twig` listing branch:
```twig
<div class="flow-lg">
  <div class="listing-hero">
    <h1>{{ trans('events.title') }}</h1>
    <p class="listing-hero__subtitle">{{ trans('events.subtitle') }}</p>
  </div>

  {% include "components/event-filters.html.twig" %}

  {% if filters.isActive() %}
    {% if feed.flatList|length > 0 %}
      <div class="card-grid">
        {% for e in feed.flatList %}
          {% set comm = feed.communities[e.get('community_id')]|default({}) %}
          {% include "components/event-card.html.twig" with {
            title: e.get('title'),
            type: e.get('type')|default('')|capitalize,
            type_key: e.get('type')|default(''),
            date: e.get('starts_at') ? e.get('starts_at')|date('F j, Y') : '',
            starts_at: e.get('starts_at'),
            ends_at: e.get('ends_at'),
            location: e.get('location')|default(''),
            excerpt: e.get('description')|default('')|split("\n\n")|first|slice(0, 120),
            url: "/events/" ~ e.get('slug'),
            community_name: comm.name|default(''),
            community_slug: comm.slug|default('')
          } %}
        {% endfor %}
      </div>
    {% else %}
      {% include "components/empty-state.html.twig" with {
        heading: trans('events.no_results_heading'),
        body: trans('events.no_results_body'),
        action_url: lang_url('/events'),
        action_label: trans('events.filter_all')
      } %}
    {% endif %}
  {% elseif filters.view == 'feed' %}
    {% include "components/event-feed.html.twig" %}
  {% else %}
    {# list/calendar handled in later tasks; fall back to feed for now #}
    {% include "components/event-feed.html.twig" %}
  {% endif %}
</div>
```

- [ ] **Step 4: Add CSS**

Append to `public/css/minoo.css` under `@layer components`:
```css
.event-filters {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  padding: var(--space-sm);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);

  &__views,
  &__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
  }

  &__view,
  &__chip {
    padding: 0.375rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: transparent;
    cursor: pointer;
    font-size: 0.9rem;

    &.is-active {
      background: var(--accent);
      color: var(--accent-contrast);
      border-color: var(--accent);
    }
  }

  &__row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-sm);
    align-items: end;
  }

  &__field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.85rem;

    &--search { flex: 1 1 12rem; }
    &--near { flex-direction: row; align-items: center; }
  }

  &__active {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
  }

  &__active-chip {
    padding: 0.125rem 0.5rem;
    background: var(--muted);
    border-radius: 999px;
    font-size: 0.8rem;
    text-decoration: none;
    color: inherit;
  }
}
```

- [ ] **Step 5: Commit**

```bash
git add templates/components/event-filters.html.twig templates/events.html.twig public/css/minoo.css src/
git commit -m "feat(#717): add filter bar with active-chip dismissal"
```

---

## Task 11: `|relative_date` Twig filter

**Files:**
- Create: `src/Support/EventsTwigExtension.php` (or extend existing extension if one exists — search first)
- Modify: `src/Provider/AppServiceProvider.php`
- Test:   `tests/App/Unit/Support/EventsTwigExtensionTest.php`

- [ ] **Step 1: Check for existing extension**

Run: `grep -rln "AbstractExtension\|getFilters" src/Support/ src/Twig/ 2>/dev/null`
If one exists, add the filter there. Otherwise create a new one.

- [ ] **Step 2: Write failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\EventsTwigExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventsTwigExtension::class)]
final class EventsTwigExtensionTest extends TestCase
{
    #[Test]
    public function relative_date_labels(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $ext = new EventsTwigExtension(clock: fn () => $now);

        $this->assertSame('Happening now', $ext->relativeDate($now, $now + 3600));
        $this->assertSame('Today', $ext->relativeDate($now + 3600, $now + 7200));
        $this->assertSame('Tomorrow', $ext->relativeDate($now + 86400 + 3600, null));
        $this->assertSame('in 3 days', $ext->relativeDate($now + 3 * 86400, null));
        $this->assertSame('This week', $ext->relativeDate($now + 6 * 86400, null));
    }
}
```

- [ ] **Step 3: Implement**

`src/Support/EventsTwigExtension.php`:
```php
<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class EventsTwigExtension extends AbstractExtension
{
    /** @var Closure():int */
    private Closure $clock;

    public function __construct(?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('relative_date', [$this, 'relativeDate']),
        ];
    }

    public function relativeDate(?int $starts, ?int $ends = null): string
    {
        if (!$starts) return '';
        $now = ($this->clock)();

        if ($ends && $starts <= $now && $now <= $ends) return 'Happening now';

        $delta = $starts - $now;
        if ($delta < 0) return date('M j, Y', $starts);
        if ($delta < 86400) return 'Today';
        if ($delta < 2 * 86400) return 'Tomorrow';
        if ($delta < 7 * 86400) return 'in ' . (int) floor($delta / 86400) . ' days';
        if ($delta < 14 * 86400) return 'This week';
        return date('M j, Y', $starts);
    }
}
```

- [ ] **Step 4: Register**

In `AppServiceProvider::boot()` (or wherever Twig extensions are added — search for `->addExtension(`):
```php
$this->resolve(\Twig\Environment::class)->addExtension(new \App\Support\EventsTwigExtension());
```

- [ ] **Step 5: Run tests**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add src/Support/EventsTwigExtension.php src/Provider/AppServiceProvider.php tests/App/Unit/Support/EventsTwigExtensionTest.php
git commit -m "feat(#717): add relative_date Twig filter"
```

---

## Task 12: Event card upgrades

**Files:**
- Modify: `templates/components/event-card.html.twig`
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Inspect current card**

Run: `cat templates/components/event-card.html.twig`
Note the existing markup. Upgrade the card without breaking the other pages that include it (check: `grep -rln "event-card.html.twig" templates/`).

- [ ] **Step 2: Add relative-time, type-color border, happening-now accent**

Replace the card template (preserve existing structure where possible):
```twig
{% set _type = type_key|default('') %}
<a href="{{ url }}" class="card event-card event-card--{{ _type }}{% if starts_at is defined and ends_at is defined and "now"|date('U') >= starts_at and "now"|date('U') <= ends_at %} is-happening{% endif %}">
  {% if type %}<span class="card__badge card__badge--event">{{ type }}</span>{% endif %}
  <h3 class="card__title">{{ title }}</h3>
  {% if starts_at is defined and starts_at %}
    <span class="card__rel-date">{{ starts_at|relative_date(ends_at|default(null)) }}</span>
    <time class="card__date" datetime="{{ starts_at|date('c') }}">{{ date }}</time>
  {% endif %}
  {% if location %}<span class="card__meta">{{ location }}</span>{% endif %}
  {% if distance_km is defined and distance_km %}
    <span class="card__distance">{{ distance_km|number_format(0) }} km away</span>
  {% endif %}
  {% if community_name %}
    <span class="card__meta">{{ community_name }}</span>
  {% endif %}
  {% if excerpt %}<p class="card__excerpt">{{ excerpt }}</p>{% endif %}
</a>
```

- [ ] **Step 3: Add CSS**

Append to `minoo.css` under `@layer tokens`:
```css
:root {
  --event-powwow:     oklch(70% 0.18 30);
  --event-gathering:  oklch(72% 0.14 150);
  --event-ceremony:   oklch(68% 0.16 280);
  --event-tournament: oklch(72% 0.16 60);
}
```

Under `@layer components`:
```css
.event-card {
  border-inline-start: 4px solid var(--border);

  &--powwow     { border-inline-start-color: var(--event-powwow); }
  &--gathering  { border-inline-start-color: var(--event-gathering); }
  &--ceremony   { border-inline-start-color: var(--event-ceremony); }
  &--tournament { border-inline-start-color: var(--event-tournament); }

  &.is-happening {
    outline: 2px solid var(--event-ceremony);
    outline-offset: -2px;
  }

  .card__rel-date {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--accent);
  }

  .card__distance {
    font-size: 0.8rem;
    color: var(--muted-fg);
  }
}
```

- [ ] **Step 4: Bump CSS cache-bust**

In `templates/base.html.twig`, find `?v=` on `minoo.css` and increment it by 1.

- [ ] **Step 5: Smoke + commit**

Open `/events` in dev. Verify type-colored borders, relative-date labels, "Happening now" outline on active events.

```bash
git add templates/components/event-card.html.twig public/css/minoo.css templates/base.html.twig
git commit -m "feat(#717): upgrade event card with relative date, type color, happening-now accent"
```

---

## Task 13: List view + pagination

**Files:**
- Create: `templates/components/event-list.html.twig`
- Modify: `templates/events.html.twig`

- [ ] **Step 1: Create list component**

`templates/components/event-list.html.twig`:
```twig
{% if feed.flatList|length == 0 %}
  {% include "components/empty-state.html.twig" with {
    heading: trans('events.empty_heading'),
    body:    trans('events.empty_body'),
    action_url: lang_url('/communities'),
    action_label: trans('events.explore_button')
  } %}
{% else %}
  {% set last_month = '' %}
  <div class="event-list">
    {% for e in feed.flatList %}
      {% set month = e.get('starts_at') ? e.get('starts_at')|date('F Y') : '' %}
      {% if month != last_month %}
        <h2 class="event-list__month">{{ month }}</h2>
        {% set last_month = month %}
      {% endif %}
      {% set comm = feed.communities[e.get('community_id')]|default({}) %}
      <a href="/events/{{ e.get('slug') }}" class="event-list__row event-card--{{ e.get('type')|default('') }}">
        <span class="event-list__date">{{ e.get('starts_at')|date('D M j') }}</span>
        <span class="event-list__title">{{ e.get('title') }}</span>
        <span class="event-list__type">{{ e.get('type')|default('')|capitalize }}</span>
        <span class="event-list__loc">{{ e.get('location')|default('') }}{% if comm.name %} · {{ comm.name }}{% endif %}</span>
      </a>
    {% endfor %}
  </div>

  {% if feed.pagination and feed.pagination.totalPages() > 1 %}
    <nav class="pagination" aria-label="Events pagination">
      {% if feed.pagination.hasPrev() %}
        <a href="?{{ query_string_with_page(filters, feed.pagination.page - 1) }}">← Prev</a>
      {% endif %}
      <span>Page {{ feed.pagination.page }} of {{ feed.pagination.totalPages() }}</span>
      {% if feed.pagination.hasNext() %}
        <a href="?{{ query_string_with_page(filters, feed.pagination.page + 1) }}">Next →</a>
      {% endif %}
    </nav>
  {% endif %}
{% endif %}
```

- [ ] **Step 2: Add Twig helper `query_string_with_page`**

Alongside `path_without`, register:
```php
new TwigFunction('query_string_with_page', function (\App\Domain\Events\ValueObject\EventFilters $f, int $page): string {
    $params = array_filter([
        'view'         => $f->view,
        'when'         => $f->when !== 'all' ? $f->when : null,
        'type'         => $f->types !== [] ? $f->types : null,
        'community_id' => $f->communityId,
        'q'            => $f->q,
        'near'         => $f->near ? '1' : null,
        'sort'         => $f->sort !== 'soonest' ? $f->sort : null,
        'page'         => $page,
    ], fn ($v) => $v !== null && $v !== '');
    return http_build_query($params);
}),
```

- [ ] **Step 3: Extend builder to produce flatList for unfiltered list view**

In `EventFeedBuilder::build()`, after `$filters->isActive()` short-circuit:
```php
if ($filters->view === 'list' || $filters->view === 'calendar') {
    // Force flat list path even when no filters are active.
    $synthetic = $filters; // list view just wants all upcoming
    return $this->buildFiltered(new EventFilters(
        types: $filters->types,
        communityId: $filters->communityId,
        when: $filters->when,
        near: $filters->near,
        q: $filters->q,
        view: $filters->view,
        month: $filters->month,
        date: $filters->date,
        sort: $filters->sort,
        page: $filters->page,
    ), $location, $now);
}
```

But `buildFiltered` checks `isActive` indirectly via its condition assembly — fine, because without filters it adds only the `status=1` and `ends_at >= now` conditions. Good.

Refactor: pull the condition setup into a helper so `buildFiltered` works whether filters are active or not. Simplest: delete the `isActive()` short-circuit gate entirely and always route to `buildFiltered` when `view != 'feed'` OR `filters->isActive()`. Keep sectioned feed as default for `view=feed && !isActive()`.

Final control flow in `build()`:
```php
if ($filters->view === 'feed' && !$filters->isActive()) {
    return $this->buildSectioned($filters, $location, $now);
}
return $this->buildFiltered($filters, $location, $now);
```

Rename the existing body into `buildSectioned(...)`. Tests from Tasks 4–6 already cover the sectioned path.

- [ ] **Step 4: Wire list view in listing branch**

Update the `{% if filters.isActive() %}` block in `events.html.twig`:
```twig
{% if filters.view == 'calendar' %}
  {# task 16 #}
  {% include "components/event-list.html.twig" %}
{% elseif filters.view == 'list' or filters.isActive() %}
  {% include "components/event-list.html.twig" %}
{% else %}
  {% include "components/event-feed.html.twig" %}
{% endif %}
```

- [ ] **Step 5: CSS + commit**

Append CSS:
```css
.event-list {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;

  &__month {
    margin-block: var(--space-md) var(--space-xs);
    font-size: 1.1rem;
  }

  &__row {
    display: grid;
    grid-template-columns: 7rem 1fr 8rem 12rem;
    gap: var(--space-sm);
    padding: 0.5rem var(--space-sm);
    border-block-end: 1px solid var(--border);
    text-decoration: none;
    color: inherit;

    &:hover { background: var(--surface-2); }

    @container (max-width: 40rem) {
      grid-template-columns: 7rem 1fr;
      & > :nth-child(n+3) { display: none; }
    }
  }
}

.pagination {
  display: flex;
  gap: var(--space-md);
  align-items: center;
  justify-content: center;
  margin-block: var(--space-lg);
}
```

```bash
git add templates/components/event-list.html.twig templates/events.html.twig src/ public/css/minoo.css
git commit -m "feat(#717): add paginated month-grouped list view"
```

---

## Task 14: Calendar value objects + view

**Files:**
- Create: `src/Domain/Events/ValueObject/CalendarMonth.php`
- Create: `src/Domain/Events/ValueObject/CalendarDay.php`
- Create: `tests/App/Unit/Domain/Events/CalendarMonthTest.php`
- Create: `templates/components/event-calendar.html.twig`
- Modify: `src/Domain/Events/Service/EventFeedBuilder.php`
- Modify: `templates/events.html.twig`
- Modify: `public/css/minoo.css`

- [ ] **Step 1: Failing tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Events;

use App\Domain\Events\ValueObject\CalendarMonth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CalendarMonth::class)]
final class CalendarMonthTest extends TestCase
{
    #[Test]
    public function build_6_week_grid_with_leading_and_trailing_days(): void
    {
        $m = CalendarMonth::build(2026, 4, events: []);
        $this->assertCount(6, $m->weeks);
        foreach ($m->weeks as $week) {
            $this->assertCount(7, $week);
        }
    }

    #[Test]
    public function leap_year_february(): void
    {
        $m = CalendarMonth::build(2024, 2, events: []);
        $in_month_days = 0;
        foreach ($m->weeks as $week) {
            foreach ($week as $day) {
                if ($day->inMonth) $in_month_days++;
            }
        }
        $this->assertSame(29, $in_month_days);
    }

    #[Test]
    public function prev_next_month_helpers(): void
    {
        $m = CalendarMonth::build(2026, 1, events: []);
        $this->assertSame('2025-12', $m->prevMonth);
        $this->assertSame('2026-02', $m->nextMonth);
    }
}
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement value objects**

`CalendarDay.php`:
```php
<?php
declare(strict_types=1);
namespace App\Domain\Events\ValueObject;
use Waaseyaa\Entity\ContentEntityBase;

final class CalendarDay
{
    /** @param list<ContentEntityBase> $events */
    public function __construct(
        public readonly string $date,
        public readonly bool $inMonth,
        public readonly bool $isToday,
        public readonly array $events,
    ) {}
}
```

`CalendarMonth.php`:
```php
<?php
declare(strict_types=1);
namespace App\Domain\Events\ValueObject;
use Waaseyaa\Entity\ContentEntityBase;

final class CalendarMonth
{
    /** @param list<list<CalendarDay>> $weeks */
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly array $weeks,
        public readonly string $prevMonth,
        public readonly string $nextMonth,
    ) {}

    /** @param list<ContentEntityBase> $events */
    public static function build(int $year, int $month, array $events, ?int $today = null): self
    {
        $today ??= time();
        $firstOfMonth = mktime(0, 0, 0, $month, 1, $year);
        $weekday = (int) date('w', $firstOfMonth); // 0=Sun
        $gridStart = strtotime('-' . $weekday . ' days', $firstOfMonth);

        // Bucket events by YYYY-MM-DD.
        $byDay = [];
        foreach ($events as $e) {
            $s = (int) $e->get('starts_at');
            $f = (int) ($e->get('ends_at') ?: $s);
            for ($t = strtotime('midnight', $s); $t <= $f; $t += 86400) {
                $byDay[date('Y-m-d', $t)][] = $e;
            }
        }

        $todayKey = date('Y-m-d', $today);
        $weeks = [];
        for ($w = 0; $w < 6; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $ts = strtotime('+' . ($w * 7 + $d) . ' days', $gridStart);
                $key = date('Y-m-d', $ts);
                $week[] = new CalendarDay(
                    date:    $key,
                    inMonth: (int) date('n', $ts) === $month,
                    isToday: $key === $todayKey,
                    events:  $byDay[$key] ?? [],
                );
            }
            $weeks[] = $week;
        }

        $prev = date('Y-m', strtotime('-1 month', $firstOfMonth));
        $next = date('Y-m', strtotime('+1 month', $firstOfMonth));

        return new self($year, $month, $weeks, $prev, $next);
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: Build CalendarMonth in builder when view=calendar**

In `EventFeedBuilder::build()`, when `$filters->view === 'calendar'`:
```php
if ($filters->view === 'calendar') {
    [$year, $month] = $filters->month
        ? array_map('intval', explode('-', $filters->month))
        : [(int) date('Y', $now), (int) date('n', $now)];
    $monthStart = mktime(0, 0, 0, $month, 1, $year);
    $monthEnd   = mktime(23, 59, 59, $month + 1, 0, $year);

    $storage = $this->entityTypeManager->getStorage('event');
    $ids = $storage->getQuery()
        ->condition('status', 1)
        ->condition('starts_at', $monthEnd + 7 * 86400, '<=')
        ->condition('ends_at',   $monthStart - 7 * 86400, '>=')
        ->execute();
    $events = $ids === [] ? [] : array_values($storage->loadMultiple($ids));
    $cm = CalendarMonth::build($year, $month, $events, $now);

    return new EventFeedResult(
        featured: [], happeningNow: [], thisWeek: [], comingUp: [], onTheHorizon: [],
        flatList: [],
        calendarMonth: $cm,
        communities: $this->buildCommunityLookup($events),
        totalUpcoming: count($events),
        activeFilters: $filters,
        availableFilters: $this->availableFiltersFor($events),
        pagination: null,
    );
}
```

- [ ] **Step 6: Create calendar template**

`templates/components/event-calendar.html.twig`:
```twig
{% set cm = feed.calendarMonth %}
<nav class="event-calendar__nav">
  <a href="?view=calendar&month={{ cm.prevMonth }}">← {{ cm.prevMonth }}</a>
  <strong>{{ cm.year }}-{{ '%02d'|format(cm.month) }}</strong>
  <a href="?view=calendar&month={{ cm.nextMonth }}">{{ cm.nextMonth }} →</a>
</nav>
<table class="event-calendar" role="grid">
  <thead>
    <tr>{% for d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] %}<th scope="col">{{ d }}</th>{% endfor %}</tr>
  </thead>
  <tbody>
    {% for week in cm.weeks %}
      <tr>
        {% for day in week %}
          <td class="event-calendar__day{% if not day.inMonth %} is-out{% endif %}{% if day.isToday %} is-today{% endif %}"
              aria-label="{{ day.date }}{% if day.events|length %} — {{ day.events|length }} event{{ day.events|length > 1 ? 's' : '' }}{% endif %}">
            <a href="?view=list&when=day&date={{ day.date }}" class="event-calendar__day-link">
              <span class="event-calendar__num">{{ day.date|split('-')|last }}</span>
              {% for e in day.events|slice(0, 3) %}
                <span class="event-calendar__dot event-calendar__dot--{{ e.get('type')|default('') }}" title="{{ e.get('title') }}"></span>
              {% endfor %}
              {% if day.events|length > 3 %}
                <span class="event-calendar__more">+{{ day.events|length - 3 }}</span>
              {% endif %}
            </a>
          </td>
        {% endfor %}
      </tr>
    {% endfor %}
  </tbody>
</table>
```

- [ ] **Step 7: Wire into events.html.twig**

```twig
{% elseif filters.view == 'calendar' %}
  {% include "components/event-calendar.html.twig" %}
```

- [ ] **Step 8: CSS**

```css
.event-calendar {
  width: 100%;
  border-collapse: collapse;

  th, td { border: 1px solid var(--border); padding: 0.25rem; vertical-align: top; }
  th { background: var(--surface-2); font-weight: 500; }

  &__day {
    height: 5rem;
    position: relative;
    &.is-out { opacity: 0.5; }
    &.is-today { outline: 2px solid var(--accent); outline-offset: -2px; }
  }
  &__day-link { display: flex; flex-direction: column; gap: 0.125rem; text-decoration: none; color: inherit; height: 100%; }
  &__num { font-size: 0.85rem; font-weight: 600; }
  &__dot {
    display: inline-block; width: 0.5rem; height: 0.5rem; border-radius: 50%;
    background: var(--accent);
    &--powwow     { background: var(--event-powwow); }
    &--gathering  { background: var(--event-gathering); }
    &--ceremony   { background: var(--event-ceremony); }
    &--tournament { background: var(--event-tournament); }
  }
  &__more { font-size: 0.75rem; color: var(--muted-fg); }

  @media (max-width: 640px) {
    display: block;
    thead { display: none; }
    tbody, tr, td { display: block; width: 100%; }
    td.is-out { display: none; }
  }
}

.event-calendar__nav {
  display: flex; justify-content: space-between; align-items: center; margin-block: var(--space-sm);
}
```

- [ ] **Step 9: Commit**

```bash
git add src/Domain/Events/ValueObject/CalendarMonth.php src/Domain/Events/ValueObject/CalendarDay.php src/Domain/Events/Service/EventFeedBuilder.php tests/App/Unit/Domain/Events/CalendarMonthTest.php templates/components/event-calendar.html.twig templates/events.html.twig public/css/minoo.css
git commit -m "feat(#717): add month calendar view with day-click drilldown"
```

---

## Task 15: ICS export route

**Files:**
- Modify: `src/Controller/EventController.php`
- Modify: `src/Provider/AppServiceProvider.php`
- Modify: `templates/events.html.twig` (detail branch)
- Create: `src/Support/IcsBuilder.php`
- Test:   `tests/App/Unit/Support/IcsBuilderTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
declare(strict_types=1);
namespace App\Tests\Unit\Support;

use App\Support\IcsBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IcsBuilder::class)]
final class IcsBuilderTest extends TestCase
{
    #[Test]
    public function builds_valid_ics_document(): void
    {
        $ics = (new IcsBuilder())->buildForEvent(
            uid: 'evt-42@minoo.live',
            title: 'Pow Wow',
            starts: strtotime('2026-07-01 14:00:00 UTC'),
            ends: strtotime('2026-07-01 22:00:00 UTC'),
            location: 'Sudbury, ON',
            description: 'Annual gathering'
        );
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('UID:evt-42@minoo.live', $ics);
        $this->assertStringContainsString('SUMMARY:Pow Wow', $ics);
        $this->assertStringContainsString('DTSTART:20260701T140000Z', $ics);
        $this->assertStringContainsString('DTEND:20260701T220000Z', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
    }
}
```

- [ ] **Step 2: Implement**

`src/Support/IcsBuilder.php`:
```php
<?php
declare(strict_types=1);
namespace App\Support;

final class IcsBuilder
{
    public function buildForEvent(
        string $uid,
        string $title,
        int $starts,
        int $ends,
        string $location = '',
        string $description = '',
    ): string {
        $esc = fn (string $s): string => addcslashes(
            str_replace(["\r\n", "\n", "\r"], '\\n', $s),
            ",;\\"
        );
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Minoo//Events//EN',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . gmdate('Ymd\THis\Z', $starts),
            'DTEND:'   . gmdate('Ymd\THis\Z', $ends),
            'SUMMARY:' . $esc($title),
            'LOCATION:' . $esc($location),
            'DESCRIPTION:' . $esc($description),
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", $lines) . "\r\n";
    }
}
```

- [ ] **Step 3: Add controller method + route**

In `EventController`:
```php
public function ics(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
{
    $slug = (string) ($params['slug'] ?? '');
    $storage = $this->entityTypeManager->getStorage('event');
    $ids = $storage->getQuery()->condition('status', 1)->condition('slug', $slug)->execute();
    $events = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    if ($events === []) {
        return new Response('Not found', 404);
    }
    $e = $events[0];
    $ics = (new \App\Support\IcsBuilder())->buildForEvent(
        uid:         'evt-' . $e->id() . '@minoo.live',
        title:       (string) $e->get('title'),
        starts:      (int) $e->get('starts_at'),
        ends:        (int) ($e->get('ends_at') ?: $e->get('starts_at') + 3600),
        location:    (string) $e->get('location'),
        description: (string) $e->get('description'),
    );
    return new Response($ics, 200, [
        'Content-Type' => 'text/calendar; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="' . $slug . '.ics"',
    ]);
}
```

In `AppServiceProvider`, add the route next to existing event routes:
```php
$router->addRoute(
    'events.ics',
    RouteBuilder::create('/events/{slug}.ics')
        ->controller('App\\Controller\\EventController::ics')
        ->allowAll()
);
```

Order matters — register `/events/{slug}.ics` before `/events/{slug}` so the literal `.ics` suffix matches first. Verify by inspecting the route registration.

- [ ] **Step 4: Add "Add to calendar" button in detail**

In `events.html.twig` detail branch, just after `<h1 class="detail-hero__title">`:
```twig
<a href="/events/{{ event.get('slug') }}.ics" class="btn btn--secondary detail-hero__ics">
  {{ trans('events.ics_download') }}
</a>
```

- [ ] **Step 5: Run + commit**

```bash
rm -f storage/framework/packages.php
./vendor/bin/phpunit
```

```bash
git add src/Support/IcsBuilder.php src/Controller/EventController.php src/Provider/AppServiceProvider.php templates/events.html.twig tests/App/Unit/Support/IcsBuilderTest.php
git commit -m "feat(#717): add .ics export for event detail pages"
```

---

## Task 16: Similar upcoming events on detail

**Files:**
- Modify: `src/Controller/EventController.php`
- Modify: `templates/events.html.twig`

- [ ] **Step 1: Extend controller::show to compute similar events**

In the existing `show()` method, after loading `$event`, before rendering:
```php
$similar = [];
if ($event !== null) {
    $now = time();
    $storage = $this->entityTypeManager->getStorage('event');
    $ids = $storage->getQuery()
        ->condition('status', 1)
        ->condition('type', (string) $event->get('type'))
        ->condition('starts_at', $now, '>=')
        ->condition('starts_at', $now + 60 * 86400, '<=')
        ->sort('starts_at', 'ASC')
        ->execute();
    $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $event->id()));
    $similar = $ids === [] ? [] : array_slice(array_values($storage->loadMultiple($ids)), 0, 3);
}
```

Pass `'similar_events' => $similar` into the Twig context.

- [ ] **Step 2: Render**

In detail branch, before closing `</div>` of `.detail`:
```twig
{% if similar_events is defined and similar_events|length > 0 %}
  <section class="related-section">
    <h2>{{ trans('events.similar_upcoming') }}</h2>
    <div class="card-grid card-grid--compact">
      {% for s in similar_events %}
        <a href="/events/{{ s.get('slug') }}" class="card card--compact">
          <span class="card__badge card__badge--event">{{ s.get('type')|capitalize }}</span>
          <h3 class="card__title">{{ s.get('title') }}</h3>
          <span class="card__date">{{ s.get('starts_at')|date('M j, Y') }}</span>
        </a>
      {% endfor %}
    </div>
  </section>
{% endif %}
```

- [ ] **Step 3: Commit**

```bash
git add src/Controller/EventController.php templates/events.html.twig
git commit -m "feat(#717): add similar upcoming events section on detail page"
```

---

## Task 17: Playwright coverage

**Files:**
- Create: `tests/playwright/events.spec.ts`

- [ ] **Step 1: Author tests**

```ts
import { test, expect } from '@playwright/test';

test.describe('/events', () => {
  test('defaults to feed view with sections', async ({ page }) => {
    await page.goto('/events');
    await expect(page.locator('.event-feed__section').first()).toBeVisible();
  });

  test('view toggle switches to list and persists in URL', async ({ page }) => {
    await page.goto('/events');
    await page.getByRole('button', { name: 'List', exact: true }).click();
    await expect(page).toHaveURL(/view=list/);
    await expect(page.locator('.event-list')).toBeVisible();
  });

  test('filter chip round-trip', async ({ page }) => {
    await page.goto('/events');
    await page.getByRole('button', { name: 'This week' }).click();
    await expect(page).toHaveURL(/when=week/);
    await expect(page.locator('.event-filters__active-chip')).toBeVisible();
    await page.locator('.event-filters__active-chip').first().click();
    await expect(page).not.toHaveURL(/when=week/);
  });

  test('calendar navigation', async ({ page }) => {
    await page.goto('/events?view=calendar');
    await expect(page.locator('.event-calendar')).toBeVisible();
    await page.getByRole('link', { name: /→/ }).click();
    await expect(page).toHaveURL(/month=\d{4}-\d{2}/);
  });

  test('ics download', async ({ page }) => {
    // Needs a known slug in the dev DB; skip if none.
    const anyEvent = await page.goto('/events?view=list').then(() => page.locator('.event-list__row').first());
    if (!(await anyEvent.isVisible())) test.skip();
    const href = await anyEvent.getAttribute('href');
    test.skip(!href, 'no event in fixtures');
    const response = await page.request.get(href! + '.ics');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/calendar');
  });
});
```

- [ ] **Step 2: Run Playwright**

```bash
npx playwright test tests/playwright/events.spec.ts
```
Expected: all tests pass (or the ics test skips cleanly if no seed events).

- [ ] **Step 3: Commit**

```bash
git add tests/playwright/events.spec.ts
git commit -m "test(#717): add Playwright coverage for events page"
```

---

## Task 18: Final integration + PR

**Files:**
- Modify: `templates/base.html.twig` (CSS cache bust)

- [ ] **Step 1: Bump CSS cache**

In `templates/base.html.twig`, increment `?v=` on `minoo.css` one more time to cover accumulated changes.

- [ ] **Step 2: Full test suite**

```bash
rm -f storage/framework/packages.php
composer dump-autoload
./vendor/bin/phpunit
npx playwright test
```
Expected: everything passes. Fix any regressions before committing.

- [ ] **Step 3: Manual walkthrough**

Start dev server and manually verify:
- `/events` opens on feed with sections
- Filter bar chips work and update URL
- `/events?view=list` paginates
- `/events?view=calendar` shows month + navigates
- An event detail page shows "Add to calendar" and "Similar upcoming"
- Past events do not appear in default feed

- [ ] **Step 4: Push + PR**

```bash
git push -u origin feat/events-refactor
gh pr create --title "refactor(#717): discovery-first /events redesign" --body "$(cat <<'EOF'
## Summary
- Replaces flat, DESC-sorted `/events` with a sectioned smart-mix feed (happening now / this week / coming up / on the horizon / featured)
- Adds filter bar (time, type, community, search, near-me) with active-chip dismissal
- Adds list and calendar views
- Upgrades event card (relative time, type color, happening-now accent, distance badge)
- Adds `.ics` export and "Similar upcoming" on detail pages
- New `Domain/Events/` bounded context with `EventFeedBuilder`, `EventFeedRanker`, and value objects

## Test plan
- [ ] `./vendor/bin/phpunit` — new unit tests cover filters parsing, ranker scoring, builder sectioning, diversity rules, horizon ranking, flat-list filter path, calendar month math, ICS generation
- [ ] `npx playwright test tests/playwright/events.spec.ts` — UI flows
- [ ] Manual smoke on dev: all three views, filter round-trip, calendar navigation, ICS download

Closes #717
EOF
)"
```

- [ ] **Step 5: Update memory**

After PR merges, update `MEMORY.md` with an entry noting the new `Domain/Events/` context pattern as the precedent for future list-page refactors.

---

## Self-Review

**Spec coverage**

- §Architecture → Tasks 1–6 (filters, ranker, builder, result)
- §Algorithm → Tasks 4–7
- §Controller → Task 8
- §Templates (feed/list/calendar, filters) → Tasks 9, 10, 13, 14
- §Card upgrades → Task 12
- §Detail additions (ics, similar) → Tasks 15, 16
- §i18n → Task 9 (en.php additions)
- §Testing (unit + Playwright) → Tasks 1, 2, 4, 5, 6, 7, 11, 14, 15, 17
- §Rollout order → plan order matches spec 1→9

Not covered as separate tasks, by intent:
- Map embed on detail — deferred in spec, not in plan
- Day-filter URL shape — implemented inside Tasks 1 and 14 (`when=day` + `date=YYYY-MM-DD`)

**Placeholder scan**

- No "TODO/TBD" in task bodies. `#717` is a named substitution placeholder with an explicit instruction at the top of the plan.
- One spot asks the implementer to verify real API shape: `LocationContext` constructor, `resolveLocation` helper presence, existing Twig extension name. These are "verify and align with real codebase" checks, not missing implementations — each step shows fallback code if the thing isn't there.

**Type consistency**

- `EventFilters` field names used identically across Tasks 1, 7, 8, 10, 13, 14.
- `EventFeedResult` field list matches spec; `Pagination::totalPages()`, `hasPrev()`, `hasNext()` used consistently in Task 13.
- `CalendarMonth::build(year, month, events, today)` signature matches both Task 14 test and usage.
- `EventFeedBuilder::build(EventFilters, ?LocationContext)` signature consistent across tasks.
- `setFeaturedEventIdsForTesting` introduced in Task 6 and used only in Tasks 6–7 tests; internal-only, fine.

No issues found.

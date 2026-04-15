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
use Waaseyaa\Entity\Storage\EntityQueryInterface;
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

    #[Test]
    public function coming_up_caps_at_12(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [];
        // 20 events in the coming-up window (8d - 30d), all distinct types & communities
        for ($i = 0; $i < 20; $i++) {
            $events[] = $this->event(
                100 + $i,
                type: 'type-' . $i,
                communityId: 'c-' . $i,
                starts: $now + (8 + $i) * 86400,
            );
        }
        $builder = $this->buildWith($events, $now);
        $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

        $this->assertCount(12, $result->comingUp);
    }

    #[Test]
    public function coming_up_limits_three_same_type_in_a_row(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        // 5 powwows chronologically, then a ceremony — greedy picks should never
        // place more than 3 consecutive powwows.
        $events = [
            $this->event(200, type: 'powwow',   communityId: 'a', starts: $now + 8 * 86400),
            $this->event(201, type: 'powwow',   communityId: 'b', starts: $now + 9 * 86400),
            $this->event(202, type: 'powwow',   communityId: 'c', starts: $now + 10 * 86400),
            $this->event(203, type: 'powwow',   communityId: 'd', starts: $now + 11 * 86400),
            $this->event(204, type: 'powwow',   communityId: 'e', starts: $now + 12 * 86400),
            $this->event(205, type: 'ceremony', communityId: 'f', starts: $now + 13 * 86400),
        ];
        $builder = $this->buildWith($events, $now);
        $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

        $types = array_map(fn ($e) => $e->get('type'), $result->comingUp);
        // Scan the output: no window of 4 consecutive entries should all be the same type.
        for ($i = 0; $i + 3 < count($types); $i++) {
            $window = array_slice($types, $i, 4);
            $this->assertGreaterThan(
                1,
                count(array_unique($window)),
                'Found 4 consecutive events of the same type in comingUp'
            );
        }
    }

    #[Test]
    public function coming_up_limits_two_from_same_community_in_top_six(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        // 4 chronologically-first events from community 'alpha', then others.
        $events = [
            $this->event(300, type: 't1', communityId: 'alpha', starts: $now + 8 * 86400),
            $this->event(301, type: 't2', communityId: 'alpha', starts: $now + 9 * 86400),
            $this->event(302, type: 't3', communityId: 'alpha', starts: $now + 10 * 86400),
            $this->event(303, type: 't4', communityId: 'alpha', starts: $now + 11 * 86400),
            $this->event(304, type: 't5', communityId: 'beta',  starts: $now + 12 * 86400),
            $this->event(305, type: 't6', communityId: 'gamma', starts: $now + 13 * 86400),
            $this->event(306, type: 't7', communityId: 'delta', starts: $now + 14 * 86400),
            $this->event(307, type: 't8', communityId: 'epsilon', starts: $now + 15 * 86400),
        ];
        $builder = $this->buildWith($events, $now);
        $result = $builder->build(EventFilters::fromRequest(Request::create('/events')), null);

        $top6 = array_slice($result->comingUp, 0, 6);
        $communities = array_map(fn ($e) => $e->get('community_id'), $top6);
        $counts = array_count_values(array_map('strval', $communities));
        foreach ($counts as $community => $count) {
            $this->assertLessThanOrEqual(
                2,
                $count,
                sprintf('Community %s appeared %d times in top 6', $community, $count)
            );
        }
    }

    /** @param list<ContentEntityBase> $events */
    private function buildWith(array $events, int $now): EventFeedBuilder
    {
        $byId = [];
        foreach ($events as $e) {
            $byId[$e->id()] = $e;
        }

        $queryStub = new class ($events) implements EntityQueryInterface {
            /** @param list<ContentEntityBase> $events */
            public function __construct(private array $events) {}
            public function condition(string $field, mixed $value, string $operator = '='): static { return $this; }
            public function exists(string $field): static { return $this; }
            public function notExists(string $field): static { return $this; }
            public function sort(string $field, string $direction = 'ASC'): static { return $this; }
            public function range(int $offset, int $limit): static { return $this; }
            public function count(): static { return $this; }
            public function accessCheck(bool $check = true): static { return $this; }
            public function execute(): array
            {
                return array_map(fn ($e) => $e->id(), $this->events);
            }
        };

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturn($queryStub);
        $storage->method('loadMultiple')->willReturnCallback(
            fn (array $ids = []) => array_combine(
                $ids,
                array_values($this->eventsById($events, $ids))
            )
        );

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        return new EventFeedBuilder($etm, new EventFeedRanker(), clock: fn () => $now);
    }

    /**
     * @param list<ContentEntityBase> $events
     * @param list<int> $ids
     * @return list<ContentEntityBase>
     */
    private function eventsById(array $events, array $ids): array
    {
        $byId = [];
        foreach ($events as $e) {
            $byId[$e->id()] = $e;
        }
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

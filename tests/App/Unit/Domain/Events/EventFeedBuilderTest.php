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

    #[Test]
    public function filter_short_circuit_returns_flat_list_and_empty_sections(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [
            $this->event(600, type: 'ceremony',  starts: $now + 2 * 86400),
            $this->event(601, type: 'powwow',    starts: $now + 3 * 86400),
            $this->event(602, type: 'ceremony',  starts: $now + 4 * 86400),
            $this->event(603, type: 'gathering', starts: $now + 5 * 86400),
        ];
        $builder = $this->buildWith($events, $now);
        $request = Request::create('/events?type[]=ceremony');
        $result = $builder->build(EventFilters::fromRequest($request), null);

        $ids = array_map(fn ($e) => $e->id(), $result->flatList);
        $this->assertSame([600, 602], $ids);
        $this->assertSame([], $result->happeningNow);
        $this->assertSame([], $result->thisWeek);
        $this->assertSame([], $result->comingUp);
        $this->assertSame([], $result->onTheHorizon);
        $this->assertNotNull($result->pagination);
        $this->assertSame(2, $result->pagination->total);
    }

    #[Test]
    public function past_filter_sorts_desc_and_excludes_future(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [
            $this->event(700, starts: $now - 10 * 86400, ends: $now - 10 * 86400 + 3600),
            $this->event(701, starts: $now - 2 * 86400,  ends: $now - 2 * 86400 + 3600),
            $this->event(702, starts: $now - 5 * 86400,  ends: $now - 5 * 86400 + 3600),
            $this->event(703, starts: $now + 86400,      ends: $now + 86400 + 3600),
        ];
        $builder = $this->buildWith($events, $now);
        $request = Request::create('/events?when=past');
        $result = $builder->build(EventFilters::fromRequest($request), null);

        $ids = array_map(fn ($e) => $e->id(), $result->flatList);
        $this->assertSame([701, 702, 700], $ids);
        foreach ($result->flatList as $e) {
            $this->assertLessThan($now, (int) $e->get('ends_at'));
        }
    }

    #[Test]
    public function view_list_without_filters_returns_paginated_flat_list(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [
            $this->event(800, starts: $now + 2 * 86400),
            $this->event(801, starts: $now + 5 * 86400),
            $this->event(802, starts: $now + 10 * 86400),
        ];
        $builder = $this->buildWith($events, $now);
        $request = Request::create('/events?view=list');
        $result = $builder->build(EventFilters::fromRequest($request), null);

        $this->assertCount(3, $result->flatList);
        $this->assertNotNull($result->pagination);
        $this->assertSame(1, $result->pagination->page);
        $this->assertSame(30, $result->pagination->perPage);
        $this->assertSame(3, $result->pagination->total);
        $this->assertSame([], $result->happeningNow);
        $this->assertSame([], $result->thisWeek);
    }

    #[Test]
    public function text_search_matches_title_or_description_or_location(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [
            $this->eventWithText(900, starts: $now + 2 * 86400, title: 'Spring Powwow', description: '', location: ''),
            $this->eventWithText(901, starts: $now + 3 * 86400, title: 'Moose Lake', description: 'Community spring ceremony', location: ''),
            $this->eventWithText(902, starts: $now + 4 * 86400, title: 'Winter Gathering', description: '', location: 'Spring River Hall'),
            $this->eventWithText(903, starts: $now + 5 * 86400, title: 'Summer Feast', description: 'no match', location: 'Sudbury'),
        ];
        $builder = $this->buildWith($events, $now);
        $request = Request::create('/events?q=spring');
        $result = $builder->build(EventFilters::fromRequest($request), null);

        $ids = array_map(fn ($e) => $e->id(), $result->flatList);
        sort($ids);
        $this->assertSame([900, 901, 902], $ids);
    }

    #[Test]
    public function when_week_constrains_to_next_7_days(): void
    {
        $now = strtotime('2026-04-14 12:00:00');
        $events = [
            $this->event(1000, starts: $now + 86400),            // +1d
            $this->event(1001, starts: $now + 6 * 86400),        // +6d
            $this->event(1002, starts: $now + 8 * 86400),        // +8d (out)
            $this->event(1003, starts: $now + 20 * 86400),       // +20d (out)
        ];
        $builder = $this->buildWith($events, $now);
        $request = Request::create('/events?when=week');
        $result = $builder->build(EventFilters::fromRequest($request), null);

        $ids = array_map(fn ($e) => $e->id(), $result->flatList);
        $this->assertSame([1000, 1001], $ids);
    }

    #[Test]
    public function calendar_view_builds_calendar_month_and_leaves_sections_empty(): void
    {
        $now = strtotime('2026-04-14 12:00:00 UTC');
        $events = [
            $this->event(2000, starts: strtotime('2026-04-10 09:00:00 UTC'), ends: strtotime('2026-04-10 17:00:00 UTC')),
            $this->event(2001, starts: strtotime('2026-04-20 09:00:00 UTC'), ends: strtotime('2026-04-20 17:00:00 UTC')),
        ];
        $builder = $this->buildWith($events, $now);
        $request = Request::create('/events?view=calendar&month=2026-04');
        $result = $builder->build(EventFilters::fromRequest($request), null);

        $this->assertNotNull($result->calendarMonth);
        $this->assertSame(2026, $result->calendarMonth->year);
        $this->assertSame(4, $result->calendarMonth->month);
        $this->assertSame([], $result->happeningNow);
        $this->assertSame([], $result->thisWeek);
        $this->assertSame([], $result->comingUp);
        $this->assertSame([], $result->onTheHorizon);
        $this->assertSame([], $result->flatList);

        // Find days with events.
        $placed = [];
        foreach ($result->calendarMonth->weeks as $week) {
            foreach ($week as $day) {
                if ($day->events !== []) {
                    $placed[$day->date->format('Y-m-d')] = array_map(
                        fn ($e) => $e->id(),
                        $day->events,
                    );
                }
            }
        }
        $this->assertArrayHasKey('2026-04-10', $placed);
        $this->assertSame([2000], $placed['2026-04-10']);
        $this->assertArrayHasKey('2026-04-20', $placed);
        $this->assertSame([2001], $placed['2026-04-20']);
    }

    private function eventWithText(int $id, int $starts, string $title, string $description, string $location): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnMap([
            ['type', 'gathering'],
            ['community_id', null],
            ['starts_at', $starts],
            ['ends_at', $starts + 3600],
            ['status', 1],
            ['slug', 'e-' . $id],
            ['title', $title],
            ['description', $description],
            ['location', $location],
        ]);
        return $mock;
    }

    /**
     * @param list<ContentEntityBase> $events
     * @param list<int>               $featuredEventIds
     */
    private function buildWithFeatured(array $events, int $now, array $featuredEventIds): EventFeedBuilder
    {
        $builder = $this->buildWith($events, $now);
        $builder->setFeaturedEventIdsForTesting($featuredEventIds);
        return $builder;
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
            function (array $ids = []) use ($events) {
                $byId = [];
                foreach ($events as $e) {
                    $byId[$e->id()] = $e;
                }
                $out = [];
                foreach ($ids as $id) {
                    if (isset($byId[$id])) {
                        $out[$id] = $byId[$id];
                    }
                }
                return $out;
            }
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

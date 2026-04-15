<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Events;

use App\Domain\Events\ValueObject\EventFeedResult;
use App\Domain\Events\ValueObject\EventFilters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\ContentEntityBase;

#[CoversClass(EventFeedResult::class)]
final class EventFeedResultMonthGroupsTest extends TestCase
{
    #[Test]
    public function groups_flat_list_by_year_month_preserving_order(): void
    {
        $events = [
            $this->event(1, strtotime('2026-04-10 00:00:00 UTC')),
            $this->event(2, strtotime('2026-04-25 00:00:00 UTC')),
            $this->event(3, strtotime('2026-05-02 00:00:00 UTC')),
            $this->event(4, strtotime('2026-05-18 00:00:00 UTC')),
            $this->event(5, strtotime('2026-07-01 00:00:00 UTC')),
        ];

        $result = new EventFeedResult(
            featured: [],
            happeningNow: [],
            thisWeek: [],
            comingUp: [],
            onTheHorizon: [],
            flatList: $events,
            calendarMonth: null,
            communities: [],
            totalUpcoming: 5,
            activeFilters: EventFilters::fromRequest(Request::create('/events')),
            availableFilters: ['types' => [], 'communities' => []],
            pagination: null,
        );

        $groups = $result->monthGroups();

        $this->assertCount(3, $groups);
        $this->assertSame('2026-04', $groups[0]['key']);
        $this->assertSame('April 2026', $groups[0]['label']);
        $this->assertCount(2, $groups[0]['events']);
        $this->assertSame([1, 2], array_map(fn ($e) => $e->id(), $groups[0]['events']));

        $this->assertSame('2026-05', $groups[1]['key']);
        $this->assertSame([3, 4], array_map(fn ($e) => $e->id(), $groups[1]['events']));

        $this->assertSame('2026-07', $groups[2]['key']);
        $this->assertSame([5], array_map(fn ($e) => $e->id(), $groups[2]['events']));
    }

    #[Test]
    public function empty_flat_list_yields_no_groups(): void
    {
        $result = new EventFeedResult(
            featured: [], happeningNow: [], thisWeek: [], comingUp: [],
            onTheHorizon: [], flatList: [], calendarMonth: null,
            communities: [], totalUpcoming: 0,
            activeFilters: EventFilters::fromRequest(Request::create('/events')),
            availableFilters: ['types' => [], 'communities' => []],
            pagination: null,
        );

        $this->assertSame([], $result->monthGroups());
    }

    private function event(int $id, int $startsAt): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnMap([
            ['starts_at', $startsAt],
            ['ends_at', $startsAt + 3600],
        ]);
        return $mock;
    }
}

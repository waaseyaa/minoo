<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Events;

use App\Domain\Events\ValueObject\CalendarMonth;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;

#[CoversClass(CalendarMonth::class)]
#[CoversClass(\App\Domain\Events\ValueObject\CalendarDay::class)]
final class CalendarMonthTest extends TestCase
{
    #[Test]
    public function january_2026_grid_starts_on_sunday_before_the_first(): void
    {
        $cal = CalendarMonth::fromEvents(2026, 1, []);

        $this->assertCount(6, $cal->weeks);
        foreach ($cal->weeks as $week) {
            $this->assertCount(7, $week);
        }

        // Jan 1 2026 is Thursday → grid starts Sunday Dec 28 2025.
        $first = $cal->weeks[0][0];
        $this->assertSame('2025-12-28', $first->date->format('Y-m-d'));
        $this->assertFalse($first->inMonth);

        // Total 42 cells
        $cellCount = 0;
        foreach ($cal->weeks as $week) {
            $cellCount += count($week);
        }
        $this->assertSame(42, $cellCount);
    }

    #[Test]
    public function february_2024_has_29_days_in_month(): void
    {
        $cal = CalendarMonth::fromEvents(2024, 2, []);

        $inMonth = 0;
        foreach ($cal->weeks as $week) {
            foreach ($week as $day) {
                if ($day->inMonth) {
                    $inMonth++;
                }
            }
        }
        $this->assertSame(29, $inMonth);
    }

    #[Test]
    public function non_leap_february_2026_has_28_days_in_month(): void
    {
        $cal = CalendarMonth::fromEvents(2026, 2, []);

        $inMonth = 0;
        foreach ($cal->weeks as $week) {
            foreach ($week as $day) {
                if ($day->inMonth) {
                    $inMonth++;
                }
            }
        }
        $this->assertSame(28, $inMonth);
    }

    #[Test]
    public function multi_day_event_appears_on_all_days_within_window(): void
    {
        // Event runs April 10–12 2026 (3 days).
        $start = strtotime('2026-04-10 09:00:00 UTC');
        $end   = strtotime('2026-04-12 17:00:00 UTC');
        $event = $this->eventMock(42, $start, $end);

        $cal = CalendarMonth::fromEvents(2026, 4, [$event]);

        $matched = [];
        foreach ($cal->weeks as $week) {
            foreach ($week as $day) {
                foreach ($day->events as $e) {
                    if ($e->id() === 42) {
                        $matched[] = $day->date->format('Y-m-d');
                    }
                }
            }
        }
        sort($matched);
        $this->assertSame(['2026-04-10', '2026-04-11', '2026-04-12'], $matched);
    }

    #[Test]
    public function prev_and_next_month_handle_year_rollover(): void
    {
        $jan = CalendarMonth::fromEvents(2026, 1, []);
        $this->assertSame('2025-12', $jan->prevMonth);
        $this->assertSame('2026-02', $jan->nextMonth);

        $dec = CalendarMonth::fromEvents(2026, 12, []);
        $this->assertSame('2026-11', $dec->prevMonth);
        $this->assertSame('2027-01', $dec->nextMonth);
    }

    #[Test]
    public function today_flag_is_set_when_today_is_in_grid(): void
    {
        $today = new DateTimeImmutable('2026-04-15', new DateTimeZone('UTC'));
        $cal = CalendarMonth::fromEvents(2026, 4, [], $today);

        $todayDays = [];
        foreach ($cal->weeks as $week) {
            foreach ($week as $day) {
                if ($day->isToday) {
                    $todayDays[] = $day->date->format('Y-m-d');
                }
            }
        }
        $this->assertSame(['2026-04-15'], $todayDays);
    }

    #[Test]
    public function today_flag_not_set_when_today_outside_grid(): void
    {
        $today = new DateTimeImmutable('2026-04-15', new DateTimeZone('UTC'));
        // Build a Jan 2026 calendar; today (April) is not in grid.
        $cal = CalendarMonth::fromEvents(2026, 1, [], $today);

        foreach ($cal->weeks as $week) {
            foreach ($week as $day) {
                $this->assertFalse($day->isToday);
            }
        }
    }

    #[Test]
    public function label_returns_english_month_and_year(): void
    {
        $cal = CalendarMonth::fromEvents(2026, 4, []);
        $this->assertSame('April 2026', $cal->label());
    }

    private function eventMock(int $id, int $startsAt, int $endsAt): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $mock->method('id')->willReturn($id);
        $mock->method('get')->willReturnMap([
            ['starts_at', $startsAt],
            ['ends_at', $endsAt],
            ['type', 'gathering'],
            ['title', 'Event ' . $id],
            ['slug', 'event-' . $id],
            ['status', 1],
        ]);
        return $mock;
    }
}

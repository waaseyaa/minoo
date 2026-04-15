<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Waaseyaa\Entity\ContentEntityBase;

final class CalendarMonth
{
    /**
     * @param list<list<CalendarDay>> $weeks
     */
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly array $weeks,
        public readonly string $prevMonth,
        public readonly string $nextMonth,
    ) {}

    /**
     * @param list<ContentEntityBase> $events
     */
    public static function fromEvents(
        int $year,
        int $month,
        array $events,
        ?DateTimeImmutable $today = null,
    ): self {
        $utc = new DateTimeZone('UTC');
        $firstOfMonth = new DateTimeImmutable(
            sprintf('%04d-%02d-01 00:00:00', $year, $month),
            $utc,
        );

        // Roll back to the Sunday on or before the 1st.
        // PHP: sun=0 mon=1 ... sat=6
        $dowOfFirst = (int) $firstOfMonth->format('w');
        $gridStart = $firstOfMonth->modify('-' . $dowOfFirst . ' days');

        $todayStr = $today !== null
            ? $today->setTimezone($utc)->format('Y-m-d')
            : null;

        $weeks = [];
        $cursor = $gridStart;
        for ($w = 0; $w < 6; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $dayStart = (int) $cursor->format('U');
                $dayEnd   = $dayStart + 86400;

                $dayEvents = [];
                foreach ($events as $event) {
                    $starts = (int) $event->get('starts_at');
                    $ends   = (int) $event->get('ends_at');
                    if ($ends <= 0) {
                        $ends = $starts;
                    }
                    // Overlap test: [starts, ends] overlaps [dayStart, dayEnd)
                    if ($starts < $dayEnd && $ends >= $dayStart) {
                        $dayEvents[] = $event;
                    }
                }

                $week[] = new CalendarDay(
                    date:    $cursor,
                    inMonth: ((int) $cursor->format('n')) === $month
                             && ((int) $cursor->format('Y')) === $year,
                    isToday: $todayStr !== null && $cursor->format('Y-m-d') === $todayStr,
                    events:  $dayEvents,
                );

                $cursor = $cursor->modify('+1 day');
            }
            $weeks[] = $week;
        }

        $prevYear  = $month === 1  ? $year - 1 : $year;
        $prevMonth = $month === 1  ? 12        : $month - 1;
        $nextYear  = $month === 12 ? $year + 1 : $year;
        $nextMonth = $month === 12 ? 1         : $month + 1;

        return new self(
            year:      $year,
            month:     $month,
            weeks:     $weeks,
            prevMonth: sprintf('%04d-%02d', $prevYear, $prevMonth),
            nextMonth: sprintf('%04d-%02d', $nextYear, $nextMonth),
        );
    }

    public function label(): string
    {
        $dt = new DateTimeImmutable(
            sprintf('%04d-%02d-01', $this->year, $this->month),
            new DateTimeZone('UTC'),
        );
        return $dt->format('F Y');
    }

    /**
     * First date in the 6-week grid (Sunday on/before the 1st).
     */
    public function gridStart(): DateTimeImmutable
    {
        return $this->weeks[0][0]->date;
    }

    /**
     * Last date in the 6-week grid (Saturday 6 weeks later).
     */
    public function gridEnd(): DateTimeImmutable
    {
        return $this->weeks[5][6]->date;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObject;

use App\Domain\Events\ValueObject\CalendarMonth;
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

    /**
     * Group `flatList` by YYYY-MM for the list view. Preserves input order.
     * Computed in PHP so templates don't perform O(n²) `|merge` rebuilds.
     *
     * @return list<array{key: string, label: string, events: list<ContentEntityBase>}>
     */
    public function monthGroups(): array
    {
        $groups = [];
        $index  = []; // key => position in $groups
        foreach ($this->flatList as $event) {
            $startsAt = $event->get('starts_at');
            if (is_int($startsAt) && $startsAt > 0) {
                $key   = date('Y-m', $startsAt);
                $label = date('F Y', $startsAt);
            } elseif (is_string($startsAt) && $startsAt !== '' && ($ts = strtotime($startsAt)) !== false) {
                $key   = date('Y-m', $ts);
                $label = date('F Y', $ts);
            } else {
                $key   = 'undated';
                $label = 'Undated';
            }
            if (!isset($index[$key])) {
                $index[$key]  = count($groups);
                $groups[]     = ['key' => $key, 'label' => $label, 'events' => []];
            }
            $groups[$index[$key]]['events'][] = $event;
        }
        return $groups;
    }
}

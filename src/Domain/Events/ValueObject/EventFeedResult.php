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
}

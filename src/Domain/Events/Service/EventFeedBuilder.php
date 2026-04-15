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

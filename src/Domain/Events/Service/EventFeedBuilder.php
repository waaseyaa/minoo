<?php

declare(strict_types=1);

namespace App\Domain\Events\Service;

use App\Domain\Events\ValueObject\CalendarMonth;
use App\Domain\Events\ValueObject\EventFeedResult;
use App\Domain\Events\ValueObject\EventFilters;
use App\Domain\Events\ValueObject\Pagination;
use App\Domain\Geo\ValueObject\LocationContext;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;

final class EventFeedBuilder
{
    /** @var Closure():int */
    private Closure $clock;

    /** @var list<int>|null */
    private ?array $featuredOverride = null;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EventFeedRanker $ranker,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @internal for tests only
     * @param list<int> $ids
     */
    public function setFeaturedEventIdsForTesting(array $ids): void
    {
        $this->featuredOverride = $ids;
    }

    public function build(EventFilters $filters, ?LocationContext $location): EventFeedResult
    {
        $now = ($this->clock)();

        if ($filters->view === 'calendar') {
            return $this->buildCalendar($filters, $now);
        }

        if ($filters->isActive() || $filters->view === 'list') {
            return $this->buildFiltered($filters, $location, $now);
        }

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

        $comingUpCandidates = array_values(array_filter(
            $all,
            fn (ContentEntityBase $e) => $this->isInComingUpWindow($e, $now)
        ));
        usort($comingUpCandidates, fn ($a, $b) => (int) $a->get('starts_at') <=> (int) $b->get('starts_at'));
        $comingUp = $this->applyDiversity($comingUpCandidates, 12);

        $featuredIds = $this->featuredOverride ?? $this->loadFeaturedEventIds($now);
        $communityCoords = $this->loadCommunityCoords($all);

        $horizonWindow = array_values(array_filter(
            $all,
            fn (ContentEntityBase $e) => $this->isOnHorizon($e, $now)
        ));
        usort($horizonWindow, function (ContentEntityBase $a, ContentEntityBase $b) use ($location, $featuredIds, $communityCoords) {
            $sa = $this->ranker->score($a, $location, $featuredIds, $communityCoords);
            $sb = $this->ranker->score($b, $location, $featuredIds, $communityCoords);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            return (int) $a->get('starts_at') <=> (int) $b->get('starts_at');
        });
        // Featured events render in their own section on top of the feed.
        // Exclude them from the horizon strip so the same card isn't shown
        // twice on the page (previously deduped in Twig via |merge loops).
        if ($featuredIds !== []) {
            $featuredLookup = array_flip($featuredIds);
            $horizonWindow = array_values(array_filter(
                $horizonWindow,
                static fn (ContentEntityBase $e): bool => !isset($featuredLookup[(int) $e->id()]),
            ));
        }
        $onTheHorizon = array_slice($horizonWindow, 0, 6);

        $featured = array_values(array_filter(
            $all,
            fn (ContentEntityBase $e) => in_array((int) $e->id(), $featuredIds, true)
        ));

        return new EventFeedResult(
            featured:         $featured,
            happeningNow:     $happeningNow,
            thisWeek:         $thisWeek,
            comingUp:         $comingUp,
            onTheHorizon:     $onTheHorizon,
            flatList:         [],
            calendarMonth:    null,
            communities:      [],
            totalUpcoming:    count($all),
            activeFilters:    $filters,
            availableFilters: ['types' => [], 'communities' => []],
            pagination:       null,
        );
    }

    private function buildFiltered(EventFilters $filters, ?LocationContext $location, int $now): EventFeedResult
    {
        $perPage = 30;
        $isPast = $filters->when === 'past';
        $candidates = $this->loadForFilter($now, $isPast);

        // Filter by type
        if ($filters->types !== []) {
            $candidates = array_values(array_filter(
                $candidates,
                fn (ContentEntityBase $e) => in_array((string) $e->get('type'), $filters->types, true),
            ));
        }

        // Filter by community
        if ($filters->communityId !== null) {
            $candidates = array_values(array_filter(
                $candidates,
                fn (ContentEntityBase $e) => (string) $e->get('community_id') === $filters->communityId,
            ));
        }

        // Filter by when-window
        $candidates = $this->applyWhenFilter($candidates, $filters, $now);

        // Text search (naive LIKE across title/description/location)
        if ($filters->q !== null) {
            $needle = $filters->q;
            $candidates = array_values(array_filter($candidates, function (ContentEntityBase $e) use ($needle): bool {
                foreach (['title', 'description', 'location'] as $field) {
                    $value = $e->get($field);
                    if (is_string($value) && $value !== '' && stripos($value, $needle) !== false) {
                        return true;
                    }
                }
                return false;
            }));
        }

        // Sort
        $descending = $isPast || $filters->sort === 'latest';
        usort(
            $candidates,
            fn (ContentEntityBase $a, ContentEntityBase $b): int => $descending
                ? (int) $b->get('starts_at') <=> (int) $a->get('starts_at')
                : (int) $a->get('starts_at') <=> (int) $b->get('starts_at'),
        );

        $total = count($candidates);
        $offset = ($filters->page - 1) * $perPage;
        $flatList = array_values(array_slice($candidates, $offset, $perPage));

        return new EventFeedResult(
            featured:         [],
            happeningNow:     [],
            thisWeek:         [],
            comingUp:         [],
            onTheHorizon:     [],
            flatList:         $flatList,
            calendarMonth:    null,
            communities:      [],
            totalUpcoming:    $total,
            activeFilters:    $filters,
            availableFilters: ['types' => [], 'communities' => []],
            pagination:       new Pagination($filters->page, $perPage, $total),
        );
    }

    private function buildCalendar(EventFilters $filters, int $now): EventFeedResult
    {
        // Render month boundaries in the audience's local TZ; a 23:30
        // America/Toronto event must land in its local day, not tomorrow's
        // UTC cell. CalendarMonth defaults to the same TZ.
        $tz = new DateTimeZone('America/Toronto');

        // Determine target month (YYYY-MM); default to current local month.
        if ($filters->month !== null && preg_match('/^(\d{4})-(\d{2})$/', $filters->month, $m)) {
            $year  = (int) $m[1];
            $month = (int) $m[2];
            if ($month < 1 || $month > 12) {
                $nowDt = (new DateTimeImmutable('@' . $now))->setTimezone($tz);
                $year  = (int) $nowDt->format('Y');
                $month = (int) $nowDt->format('n');
            }
        } else {
            $nowDt = (new DateTimeImmutable('@' . $now))->setTimezone($tz);
            $year  = (int) $nowDt->format('Y');
            $month = (int) $nowDt->format('n');
        }

        $todayDt = (new DateTimeImmutable('@' . $now))->setTimezone($tz);

        // First build an empty month to compute the grid window in local TZ.
        $empty = CalendarMonth::fromEvents($year, $month, [], $todayDt, $tz);
        $gridStart = (int) $empty->gridStart()->format('U');
        // Grid ends at the start of the day AFTER the last grid cell (local TZ).
        $gridEnd   = (int) $empty->gridEnd()->modify('+1 day')->format('U');

        // Load events whose [starts_at, ends_at] overlaps [gridStart, gridEnd).
        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('starts_at', $gridEnd, '<')
            ->condition('ends_at', $gridStart, '>=')
            ->execute();
        $events = $ids === [] ? [] : array_values($storage->loadMultiple($ids));

        $calendar = CalendarMonth::fromEvents($year, $month, $events, $todayDt, $tz);

        return new EventFeedResult(
            featured:         [],
            happeningNow:     [],
            thisWeek:         [],
            comingUp:         [],
            onTheHorizon:     [],
            flatList:         [],
            calendarMonth:    $calendar,
            communities:      [],
            totalUpcoming:    count($events),
            activeFilters:    $filters,
            availableFilters: ['types' => [], 'communities' => []],
            pagination:       null,
        );
    }

    /**
     * @param  list<ContentEntityBase> $events
     * @return list<ContentEntityBase>
     */
    private function applyWhenFilter(array $events, EventFilters $filters, int $now): array
    {
        switch ($filters->when) {
            case 'week':
                return array_values(array_filter($events, function (ContentEntityBase $e) use ($now): bool {
                    $s = (int) $e->get('starts_at');
                    return $s > $now && $s <= $now + 7 * 86400;
                }));
            case 'month':
                return array_values(array_filter($events, function (ContentEntityBase $e) use ($now): bool {
                    $s = (int) $e->get('starts_at');
                    return $s > $now && $s <= $now + 30 * 86400;
                }));
            case '3mo':
                return array_values(array_filter($events, function (ContentEntityBase $e) use ($now): bool {
                    $s = (int) $e->get('starts_at');
                    return $s > $now && $s <= $now + 90 * 86400;
                }));
            case 'past':
                return array_values(array_filter($events, function (ContentEntityBase $e) use ($now): bool {
                    return (int) $e->get('ends_at') < $now;
                }));
            case 'day':
                if ($filters->date === null) {
                    return $events;
                }
                $dayStart = strtotime($filters->date . ' 00:00:00 UTC');
                if ($dayStart === false) {
                    return $events;
                }
                $dayEnd = $dayStart + 86400;
                return array_values(array_filter($events, function (ContentEntityBase $e) use ($dayStart, $dayEnd): bool {
                    $s = (int) $e->get('starts_at');
                    return $s >= $dayStart && $s < $dayEnd;
                }));
            default:
                return $events;
        }
    }

    /** @return list<ContentEntityBase> */
    private function loadForFilter(int $now, bool $includePast): array
    {
        $storage = $this->entityTypeManager->getStorage('event');
        $query = $storage->getQuery()->condition('status', 1);
        if ($includePast) {
            $query->condition('ends_at', $now, '<');
        } else {
            $query->condition('ends_at', $now, '>=');
        }
        $ids = $query->execute();
        if ($ids === []) {
            return [];
        }
        return array_values($storage->loadMultiple($ids));
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

    private function isInComingUpWindow(ContentEntityBase $e, int $now): bool
    {
        $s = (int) $e->get('starts_at');
        return $s > $now + 7 * 86400 && $s <= $now + 30 * 86400;
    }

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
        if ($ids === []) {
            return [];
        }
        $items = $storage->loadMultiple($ids);
        return array_values(array_map(fn ($i) => (int) $i->get('entity_id'), $items));
    }

    /**
     * @param  list<ContentEntityBase>           $events
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
        if ($ids === []) {
            return [];
        }
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

    /**
     * Greedy diversity selection over chronologically-sorted candidates.
     *
     * Rules:
     *   - No more than 3 events of the same `type` in a row.
     *   - No more than 2 events from the same `community_id` in the top 6.
     *   - Cap at $limit events.
     *
     * Walks candidates in order; at each position, picks the first candidate that
     * doesn't violate the constraints. If none satisfies, relaxes (takes the next
     * remaining candidate) so we still fill the slot deterministically.
     *
     * @param list<ContentEntityBase> $candidates
     * @return list<ContentEntityBase>
     */
    private function applyDiversity(array $candidates, int $limit): array
    {
        $selected = [];
        $remaining = $candidates;

        while ($remaining !== [] && count($selected) < $limit) {
            $pickedIndex = null;
            foreach ($remaining as $idx => $candidate) {
                if (!$this->violatesDiversity($candidate, $selected)) {
                    $pickedIndex = $idx;
                    break;
                }
            }
            // Relaxation: if no candidate passes, take the first remaining one.
            if ($pickedIndex === null) {
                $pickedIndex = array_key_first($remaining);
            }
            $selected[] = $remaining[$pickedIndex];
            unset($remaining[$pickedIndex]);
            $remaining = array_values($remaining);
        }

        return $selected;
    }

    /**
     * @param list<ContentEntityBase> $selected
     */
    private function violatesDiversity(ContentEntityBase $candidate, array $selected): bool
    {
        // Rule 1: no more than 3 of the same type in a row.
        $type = (string) $candidate->get('type');
        $tail = array_slice($selected, -3);
        if (count($tail) === 3) {
            $sameType = true;
            foreach ($tail as $e) {
                if ((string) $e->get('type') !== $type) {
                    $sameType = false;
                    break;
                }
            }
            if ($sameType) {
                return true;
            }
        }

        // Rule 2: no more than 2 from the same community in the top 6.
        if (count($selected) < 6) {
            $communityId = (string) $candidate->get('community_id');
            if ($communityId !== '') {
                $top6 = array_slice($selected, 0, 6);
                $sameCommunity = 0;
                foreach ($top6 as $e) {
                    if ((string) $e->get('community_id') === $communityId) {
                        $sameCommunity++;
                    }
                }
                if ($sameCommunity >= 2) {
                    return true;
                }
            }
        }

        return false;
    }
}

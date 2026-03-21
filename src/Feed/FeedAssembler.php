<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Minoo\Support\GeoDistance;

final class FeedAssembler implements FeedAssemblerInterface
{
    public function __construct(
        private readonly EntityLoaderService $loader,
        private readonly FeedItemFactory $factory,
        private readonly ?EngagementCounter $engagementCounter = null,
    ) {}

    public function assemble(FeedContext $ctx): FeedResponse
    {
        // 1. Gather
        $events = $this->loader->loadUpcomingEvents($ctx->limit * 2);
        $groups = $this->loader->loadGroups($ctx->limit * 2);
        $businesses = $this->loader->loadBusinesses($ctx->limit * 2);
        $people = $this->loader->loadPublicPeople($ctx->limit * 2);
        $posts = $this->loader->loadPosts($ctx->limit * 2);
        $featuredRaw = $this->loader->loadFeaturedItems();
        $communities = $this->loader->loadAllCommunities();

        // Build community coordinate map for distance calculation
        $communityCoords = $this->buildCommunityCoords($communities);

        // Build community name/slug map for factory lookups
        $this->factory->setCommunityMap($this->buildCommunityMap($communities));

        // 2. Transform — assign typeSlots cyclically for round-robin
        $items = [];
        $slotCounter = 0;

        foreach ($featuredRaw as $raw) {
            $items[] = $this->factory->fromEntity(
                'featured',
                $raw['featured'],
                typeSlot: $slotCounter++ % 5,
            );
        }

        $sources = [
            ['type' => 'event', 'entities' => $events, 'communityField' => 'community_id'],
            ['type' => 'group', 'entities' => $groups, 'communityField' => 'community_id'],
            ['type' => 'business', 'entities' => $businesses, 'communityField' => 'community_id'],
            ['type' => 'person', 'entities' => $people, 'communityField' => 'community'],
            ['type' => 'post', 'entities' => $posts, 'communityField' => 'community_id'],
        ];

        foreach ($sources as $sourceIdx => $source) {
            foreach ($source['entities'] as $entity) {
                $coords = $this->resolveEntityCoords($entity, $source['communityField'], $communityCoords);
                $items[] = $this->factory->fromEntity(
                    $source['type'],
                    $entity,
                    typeSlot: $sourceIdx,
                    lat: $ctx->latitude,
                    lon: $ctx->longitude,
                    entityLat: $coords['lat'] ?? null,
                    entityLon: $coords['lon'] ?? null,
                );
            }
        }

        // 3. Inject synthetic items
        $communityData = array_map(fn($c) => [
            'name' => $c->get('name') ?? '',
            'slug' => $c->get('slug') ?? '',
        ], $ctx->hasLocation()
            ? array_slice($this->sortCommunitiesByDistance($communities, $ctx->latitude, $ctx->longitude), 0, 6)
            : array_slice($communities, 0, 6)
        );
        $items[] = $this->factory->createCommunities($communityData);

        if ($ctx->isFirstVisit) {
            $items[] = $this->factory->createWelcome();
        }

        // 4. Filter
        if ($ctx->activeFilter !== 'all') {
            $items = array_values(array_filter($items, function (FeedItem $item) use ($ctx) {
                if ($item->isSynthetic()) {
                    return true;
                }
                return $item->type === $ctx->activeFilter;
            }));
        }

        // 5. Sort
        usort($items, fn(FeedItem $a, FeedItem $b) => strcmp($a->sortKey, $b->sortKey));

        // 6. Paginate
        $startIdx = 0;
        if ($ctx->cursor !== null) {
            $cursorData = FeedCursor::decode($ctx->cursor);
            if ($cursorData !== null) {
                // Find position after cursor
                foreach ($items as $idx => $item) {
                    if ($item->sortKey === $cursorData['lastSortKey'] && $item->id === $cursorData['lastId']) {
                        $startIdx = $idx + 1;
                        break;
                    }
                }
            }
        }

        $pageItems = array_slice($items, $startIdx, $ctx->limit);

        // 6b. Attach engagement counts only to the page (not all items)
        if ($this->engagementCounter !== null) {
            $pageItems = $this->attachEngagementCounts($pageItems);
        }

        $nextCursor = null;
        if ($pageItems !== [] && ($startIdx + $ctx->limit) < count($items)) {
            $lastItem = end($pageItems);
            $nextCursor = FeedCursor::encode($lastItem->sortKey, $lastItem->type, $lastItem->id);
        }

        return new FeedResponse(
            items: $pageItems,
            nextCursor: $nextCursor,
            activeFilter: $ctx->activeFilter,
        );
    }

    /** @return array<string|int, array{lat: float, lon: float}> */
    private function buildCommunityCoords(array $communities): array
    {
        $coords = [];
        foreach ($communities as $c) {
            $cLat = $c->get('latitude');
            $cLon = $c->get('longitude');
            if ($cLat !== null && $cLon !== null) {
                $coords[(int) $c->id()] = ['lat' => (float) $cLat, 'lon' => (float) $cLon];
                $name = $c->get('name');
                if ($name !== null) {
                    $coords['name:' . $name] = ['lat' => (float) $cLat, 'lon' => (float) $cLon];
                }
            }
        }
        return $coords;
    }

    /** @return array{lat: ?float, lon: ?float} */
    private function resolveEntityCoords(mixed $entity, string $communityField, array $communityCoords): array
    {
        if ($communityField === 'community') {
            $name = $entity->get('community');
            $coords = $name !== null ? ($communityCoords['name:' . $name] ?? null) : null;
        } else {
            $cid = $entity->get($communityField);
            $coords = $cid !== null ? ($communityCoords[(int) $cid] ?? null) : null;
        }

        return $coords ?? ['lat' => null, 'lon' => null];
    }

    private function sortCommunitiesByDistance(array $communities, ?float $lat, ?float $lon): array
    {
        if ($lat === null || $lon === null) {
            return $communities;
        }

        usort($communities, function ($a, $b) use ($lat, $lon) {
            $aLat = $a->get('latitude');
            $aLon = $a->get('longitude');
            $bLat = $b->get('latitude');
            $bLon = $b->get('longitude');

            $distA = ($aLat !== null && $aLon !== null) ? GeoDistance::haversine($lat, $lon, (float) $aLat, (float) $aLon) : PHP_FLOAT_MAX;
            $distB = ($bLat !== null && $bLon !== null) ? GeoDistance::haversine($lat, $lon, (float) $bLat, (float) $bLon) : PHP_FLOAT_MAX;

            return $distA <=> $distB;
        });

        return $communities;
    }

    /** @return array<int, array{slug: string, name: string}> */
    private function buildCommunityMap(array $communities): array
    {
        $map = [];
        foreach ($communities as $c) {
            $id = (int) $c->id();
            $map[$id] = [
                'slug' => (string) ($c->get('slug') ?? ''),
                'name' => (string) ($c->get('name') ?? ''),
            ];
        }
        return $map;
    }

    /**
     * Attach reaction and comment counts to feed items via EngagementCounter.
     * Since FeedItem is readonly, we reconstruct items with engagement data.
     *
     * @param list<FeedItem> $items
     * @return list<FeedItem>
     */
    private function attachEngagementCounts(array $items): array
    {
        $ids = array_map(fn(FeedItem $item) => $item->id, $items);
        $counts = $this->engagementCounter->getCounts($ids);

        return array_map(function (FeedItem $item) use ($counts) {
            $itemCounts = $counts[$item->id] ?? null;
            if ($itemCounts === null) {
                return $item;
            }

            return new FeedItem(
                id: $item->id,
                type: $item->type,
                title: $item->title,
                url: $item->url,
                badge: $item->badge,
                weight: $item->weight,
                createdAt: $item->createdAt,
                sortKey: $item->sortKey,
                entity: $item->entity,
                subtitle: $item->subtitle,
                date: $item->date,
                distance: $item->distance,
                communityName: $item->communityName,
                meta: $item->meta,
                payload: $item->payload,
                reactionCount: $itemCounts['reactions'] ?? 0,
                commentCount: $itemCounts['comments'] ?? 0,
                userReaction: $item->userReaction,
                relativeTime: $item->relativeTime,
                communitySlug: $item->communitySlug,
                communityInitial: $item->communityInitial,
            );
        }, $items);
    }
}

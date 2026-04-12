<?php

declare(strict_types=1);

namespace App\Feed;

use App\Feed\Scoring\FeedScorer;
use Waaseyaa\Geo\GeoDistance;

final class FeedAssembler implements FeedAssemblerInterface
{
    public function __construct(
        private readonly EntityLoaderService $loader,
        private readonly FeedItemFactory $factory,
        private readonly ?EngagementCounter $engagementCounter = null,
        private readonly ?FeedScorer $scorer = null,
    ) {}

    public function assemble(FeedContext $ctx): FeedResponse
    {
        // 1. Gather
        $events = $this->loader->loadUpcomingEvents($ctx->limit * 2);
        $groups = $this->loader->loadGroups($ctx->limit * 2);
        $businesses = $this->loader->loadBusinesses($ctx->limit * 2);
        $posts = $this->loader->loadPosts($ctx->limit * 2);
        $featuredRaw = $this->loader->loadFeaturedItems();
        $communities = $this->loader->loadAllCommunities();

        // Build community coordinate map for distance calculation
        $communityCoords = $this->buildCommunityCoords($communities);

        // Build community name/slug map for factory lookups
        $this->factory->setCommunityMap($this->buildCommunityMap($communities));

        // Build user name map for post author attribution
        $this->factory->setUserMap($this->buildUserMap($posts));

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

        // Posts first with a weight boost so they appear before other content
        $postCount = 0;
        foreach ($posts as $entity) {
            $coords = $this->resolveEntityCoords($entity, 'community_id', $communityCoords);
            $weight = $postCount < 2 ? 50 : 0;
            $items[] = $this->factory->fromEntity(
                'post',
                $entity,
                typeSlot: 0,
                lat: $ctx->latitude,
                lon: $ctx->longitude,
                entityLat: $coords['lat'] ?? null,
                entityLon: $coords['lon'] ?? null,
                weight: $weight,
            );
            $postCount++;
        }

        $sources = [
            ['type' => 'event', 'entities' => $events, 'communityField' => 'community_id'],
            ['type' => 'group', 'entities' => $groups, 'communityField' => 'community_id'],
            ['type' => 'business', 'entities' => $businesses, 'communityField' => 'community_id'],
        ];

        foreach ($sources as $sourceIdx => $source) {
            foreach ($source['entities'] as $entity) {
                $coords = $this->resolveEntityCoords($entity, $source['communityField'], $communityCoords);
                $items[] = $this->factory->fromEntity(
                    $source['type'],
                    $entity,
                    typeSlot: $sourceIdx + 1,
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

        // 5. Score + Sort + Diversify (or fallback to static sort)
        $scored = false;
        if ($this->scorer !== null) {
            try {
                $sourceMap = $this->buildSourceMap($items);
                $items = $this->scorer->score(
                    $items,
                    $ctx->userId,
                    $this->resolveUserCommunityId($ctx),
                    $ctx->hasLocation() ? ['lat' => $ctx->latitude, 'lon' => $ctx->longitude] : null,
                    $this->buildSourceLocations($communityCoords),
                    $sourceMap,
                );
                $scored = true;
            } catch (\Throwable $e) {
                error_log('[FeedAssembler] Scorer failed, falling back to static sort: ' . $e->getMessage());
            }
        }
        if (!$scored) {
            usort($items, fn(FeedItem $a, FeedItem $b) => strcmp($a->sortKey, $b->sortKey));
        }

        // 6. Paginate
        $startIdx = 0;
        if ($ctx->cursor !== null) {
            $cursorData = FeedCursor::decode($ctx->cursor);
            if ($cursorData !== null) {
                foreach ($items as $idx => $item) {
                    if ($item->sortKey === $cursorData['lastSortKey'] && $item->id === $cursorData['lastId']) {
                        $startIdx = $idx + 1;
                        break;
                    }
                }
            }
        }

        $pageItems = array_slice($items, $startIdx, $ctx->limit);

        // 6b. Attach engagement counts (skipped when scorer already provided them)
        if ($this->scorer === null && $this->engagementCounter !== null) {
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

    /**
     * Build user ID → display name map from post entities.
     *
     * @return array<int, string>
     */
    private function buildUserMap(array $posts): array
    {
        $userIds = array_unique(array_filter(array_map(
            fn($p) => $p->get('user_id') !== null ? (int) $p->get('user_id') : null,
            $posts,
        )));

        if ($userIds === []) {
            return [];
        }

        try {
            $storage = $this->loader->getEntityTypeManager()->getStorage('user');
            $users = $storage->loadMultiple($userIds);
            $map = [];
            foreach ($users as $user) {
                $name = $user->get('name') ?? $user->get('display_name') ?? '';
                if ($name !== '') {
                    $map[(int) $user->id()] = (string) $name;
                }
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
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
     * Build a map of item ID → source key for affinity scoring.
     *
     * @param FeedItem[] $items
     * @return array<string, string>
     */
    private function buildSourceMap(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            if ($item->isSynthetic()) {
                continue;
            }

            $entity = $item->entity;
            $sourceKey = match ($item->type) {
                'post' => $entity !== null && $entity->get('user_id') !== null
                    ? 'user:' . $entity->get('user_id')
                    : 'post:' . $item->id,
                'event', 'teaching' => $entity !== null && $entity->get('community_id') !== null
                    ? 'community:' . $entity->get('community_id')
                    : $item->type . ':' . $item->id,
                'featured' => $entity !== null && $entity->get('user_id') !== null
                    ? 'user:' . $entity->get('user_id')
                    : 'featured:' . $item->id,
                default => $item->type . ':' . $item->id,
            };

            $map[$item->id] = $sourceKey;
        }

        return $map;
    }

    /**
     * Build source locations map from community coordinates.
     *
     * @return array<string, array{lat: float, lon: float, community_id?: int}>
     */
    private function buildSourceLocations(array $communityCoords): array
    {
        $locations = [];
        foreach ($communityCoords as $key => $coords) {
            if (is_int($key)) {
                $locations['community:' . $key] = [
                    'lat' => $coords['lat'],
                    'lon' => $coords['lon'],
                    'community_id' => $key,
                ];
            }
        }

        return $locations;
    }

    private function resolveUserCommunityId(FeedContext $ctx): ?int
    {
        if ($ctx->userId === null) {
            return null;
        }

        try {
            $user = $this->loader->getEntityTypeManager()->getStorage('user')->load($ctx->userId);

            return $user !== null && $user->get('community_id') !== null
                ? (int) $user->get('community_id')
                : null;
        } catch (\Throwable) {
            return null;
        }
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
        $ids = array_map(fn(FeedItem $item) => ['type' => $item->type, 'id' => (int) $item->id], $items);
        $counts = $this->engagementCounter->getCounts($ids);

        return array_map(function (FeedItem $item) use ($counts) {
            $itemCounts = $counts[$item->type . ':' . $item->id] ?? null;
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
                authorName: $item->authorName,
            );
        }, $items);
    }
}

<?php

declare(strict_types=1);

namespace App\Feed\Scoring;

use Waaseyaa\Geo\GeoDistance;
use Waaseyaa\Entity\EntityTypeManager;

final class AffinityCalculator
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly AffinityCache $cache,
        private readonly float $baseAffinity = 1.0,
        private readonly float $followPoints = 4.0,
        private readonly float $sameCommunityPoints = 3.0,
        private readonly float $reactionPoints = 1.0,
        private readonly float $reactionMax = 5.0,
        private readonly float $commentPoints = 2.0,
        private readonly float $commentMax = 6.0,
        private readonly float $geoCloseKm = 50.0,
        private readonly float $geoClosePoints = 2.0,
        private readonly float $geoMidKm = 150.0,
        private readonly float $geoMidPoints = 1.0,
        private readonly int $lookbackDays = 30,
    ) {}

    /**
     * @param string[] $sourceKeys
     * @param array{lat: float, lon: float}|null $userLocation
     * @param array<string, array{lat: float, lon: float, community_id?: int}>|null $sourceLocations
     * @return array<string, float>|null Null for anonymous users
     */
    public function computeBatch(
        ?int $userId,
        array $sourceKeys,
        ?int $userCommunityId,
        ?array $userLocation,
        ?array $sourceLocations = null,
    ): ?array {
        if ($userId === null) {
            return null;
        }

        if ($sourceKeys === []) {
            return [];
        }

        $cached = $this->cache->get($userId);
        if ($cached !== null && $this->allKeysPresent($cached, $sourceKeys)) {
            return array_intersect_key($cached, array_flip($sourceKeys));
        }

        $follows = $this->queryFollows($userId);
        $reactionCounts = $this->queryInteractionCounts('reaction', $userId);
        $commentCounts = $this->queryInteractionCounts('comment', $userId);

        $scores = [];
        foreach ($sourceKeys as $key) {
            $score = $this->baseAffinity;

            if (isset($follows[$key])) {
                $score += $this->followPoints;
            }

            if (isset($reactionCounts[$key])) {
                $score += min($reactionCounts[$key] * $this->reactionPoints, $this->reactionMax);
            }

            if (isset($commentCounts[$key])) {
                $score += min($commentCounts[$key] * $this->commentPoints, $this->commentMax);
            }

            $sourceMeta = $sourceLocations[$key] ?? null;

            if ($sourceMeta !== null && $userCommunityId !== null
                && isset($sourceMeta['community_id']) && $sourceMeta['community_id'] === $userCommunityId) {
                $score += $this->sameCommunityPoints;
            }

            if ($sourceMeta !== null && $userLocation !== null) {
                $distance = GeoDistance::haversine(
                    $userLocation['lat'],
                    $userLocation['lon'],
                    $sourceMeta['lat'],
                    $sourceMeta['lon'],
                );

                if ($distance <= $this->geoCloseKm) {
                    $score += $this->geoClosePoints;
                } elseif ($distance <= $this->geoMidKm) {
                    $score += $this->geoMidPoints;
                }
            }

            $scores[$key] = $score;
        }

        $toCache = $cached !== null ? array_merge($cached, $scores) : $scores;
        $this->cache->set($userId, $toCache);

        return $scores;
    }

    /** @param array<string, float> $cached */
    private function allKeysPresent(array $cached, array $sourceKeys): bool
    {
        foreach ($sourceKeys as $key) {
            if (!isset($cached[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, true> "target_type:target_id" => true
     */
    private function queryFollows(int $userId): array
    {
        try {
            $storage = $this->entityTypeManager->getStorage('follow');
            $ids = $storage->getQuery()
                ->condition('user_id', $userId)
                ->execute();

            if ($ids === []) {
                return [];
            }

            $follows = [];
            foreach ($storage->loadMultiple($ids) as $follow) {
                $type = $follow->get('target_type');
                $id = $follow->get('target_id');
                if ($type !== null && $id !== null) {
                    $follows[$type . ':' . $id] = true;
                }
            }

            return $follows;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, int> "target_type:target_id" => count
     */
    private function queryInteractionCounts(string $entityType, int $userId): array
    {
        try {
            $storage = $this->entityTypeManager->getStorage($entityType);
            $cutoff = time() - ($this->lookbackDays * 86400);

            $ids = $storage->getQuery()
                ->condition('user_id', $userId)
                ->condition('created_at', $cutoff, '>=')
                ->execute();

            if ($ids === []) {
                return [];
            }

            $counts = [];
            foreach ($storage->loadMultiple($ids) as $entity) {
                $type = $entity->get('target_type');
                $id = $entity->get('target_id');
                if ($type !== null && $id !== null) {
                    $key = $type . ':' . $id;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }

            return $counts;
        } catch (\Throwable) {
            return [];
        }
    }
}

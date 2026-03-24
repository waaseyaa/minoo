<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Minoo\Support\GeoDistance;
use Waaseyaa\Database\DatabaseInterface;

final class AffinityCalculator
{
    public function __construct(
        private readonly DatabaseInterface $database,
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
     * Compute affinity scores for a user against a set of source keys.
     *
     * @param int|null $userId Null for anonymous users
     * @param string[] $sourceKeys Source identifiers to score
     * @param int|null $userCommunityId User's community for same-community bonus
     * @param array{lat: float, lon: float}|null $userLocation User's location
     * @param array<string, array{lat: float, lon: float, community_id?: int}>|null $sourceLocations Source locations keyed by source key
     * @return array<string, float>|null Null for anonymous users, otherwise sourceKey => score
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

        // Check cache — return cached scores if all requested keys are present.
        $cached = $this->cache->get($userId);
        if ($cached !== null && $this->allKeysPresent($cached, $sourceKeys)) {
            return array_intersect_key($cached, array_flip($sourceKeys));
        }

        $follows = $this->queryFollows($userId);
        $reactionCounts = $this->queryGroupedCounts('reaction', $userId);
        $commentCounts = $this->queryGroupedCounts('comment', $userId);

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

            if ($sourceMeta !== null && $userLocation !== null
                && isset($sourceMeta['lat'], $sourceMeta['lon'])) {
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

        // Merge with any existing cached scores and store.
        $toCache = $cached !== null ? array_merge($cached, $scores) : $scores;
        $this->cache->set($userId, $toCache);

        return $scores;
    }

    /**
     * @param array<string, float> $cached
     * @param string[] $sourceKeys
     */
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
     * Query follow relationships for the user (all time).
     *
     * @return array<string, true> Followed source keys as keys
     */
    private function queryFollows(int $userId): array
    {
        $rows = $this->database->select('follow', 'f')
            ->fields('f', ['target_type', 'target_id'])
            ->condition('f.user_id', $userId)
            ->execute();

        $follows = [];
        foreach ($rows as $row) {
            $follows[$row['target_type'] . ':' . $row['target_id']] = true;
        }

        return $follows;
    }

    /**
     * Query grouped counts per target within the lookback window.
     *
     * @return array<string, int> "target_type:target_id" => count
     */
    private function queryGroupedCounts(string $table, int $userId): array
    {
        $cutoff = time() - ($this->lookbackDays * 86400);

        $rows = $this->database->select($table, 't')
            ->fields('t', ['target_type', 'target_id'])
            ->condition('t.user_id', $userId)
            ->condition('t.created_at', $cutoff, '>=')
            ->execute();

        $counts = [];
        foreach ($rows as $row) {
            $key = $row['target_type'] . ':' . $row['target_id'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }
}

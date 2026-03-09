<?php

declare(strict_types=1);

namespace Minoo\Domain\Geo\Service;

use Minoo\Support\GeoDistance;
use Waaseyaa\Entity\ContentEntityBase;

final class CommunityFinder
{
    /**
     * Find the nearest community to the given coordinates.
     *
     * @param array<ContentEntityBase> $communities
     * @return array{community: ContentEntityBase, distanceKm: float}|null
     */
    public function findNearest(float $lat, float $lon, array $communities): ?array
    {
        $nearest = null;

        foreach ($communities as $community) {
            $communityLat = $community->get('latitude');
            $communityLon = $community->get('longitude');

            if ($communityLat === null || $communityLon === null) {
                continue;
            }

            $distance = GeoDistance::haversine($lat, $lon, (float) $communityLat, (float) $communityLon);

            if ($nearest === null || $distance < $nearest['distanceKm']) {
                $nearest = [
                    'community' => $community,
                    'distanceKm' => $distance,
                ];
            }
        }

        return $nearest;
    }

    /**
     * Find nearby communities sorted by distance, limited to $limit results.
     *
     * @param array<ContentEntityBase> $communities
     * @param int $limit
     * @return array<array{community: ContentEntityBase, distanceKm: float}>
     */
    public function findNearby(float $lat, float $lon, array $communities, int $limit = 5): array
    {
        $results = [];

        foreach ($communities as $community) {
            $communityLat = $community->get('latitude');
            $communityLon = $community->get('longitude');

            if ($communityLat === null || $communityLon === null) {
                continue;
            }

            $results[] = [
                'community' => $community,
                'distanceKm' => GeoDistance::haversine($lat, $lon, (float) $communityLat, (float) $communityLon),
            ];
        }

        usort($results, fn(array $a, array $b): int => $a['distanceKm'] <=> $b['distanceKm']);

        return array_slice($results, 0, $limit);
    }
}

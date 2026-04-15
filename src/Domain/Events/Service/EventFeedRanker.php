<?php

declare(strict_types=1);

namespace App\Domain\Events\Service;

use App\Domain\Geo\ValueObject\LocationContext;
use Waaseyaa\Entity\ContentEntityBase;

final class EventFeedRanker
{
    private const NEAR_KM = 150.0;
    private const CULTURALLY_SIGNIFICANT = ['ceremony', 'powwow'];

    /**
     * @param list<int>                          $featuredEventIds
     * @param array<string, array{float, float}> $communityCoords
     */
    public function score(
        ContentEntityBase $event,
        ?LocationContext $location,
        array $featuredEventIds,
        array $communityCoords,
    ): int {
        $score = 0;

        if (in_array((int) $event->id(), $featuredEventIds, true)) {
            $score += 3;
        }

        if ($location !== null && $location->latitude !== null && $location->longitude !== null) {
            $cid = $event->get('community_id');
            if (is_string($cid) && isset($communityCoords[$cid])) {
                [$lat, $lon] = $communityCoords[$cid];
                if ($this->haversineKm($location->latitude, $location->longitude, $lat, $lon) <= self::NEAR_KM) {
                    $score += 2;
                }
            }
        }

        if (in_array((string) $event->get('type'), self::CULTURALLY_SIGNIFICANT, true)) {
            $score += 1;
        }

        return $score;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}

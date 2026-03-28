<?php

declare(strict_types=1);

namespace Minoo\Domain\Geo\Service;

use Minoo\Domain\Geo\ValueObject\RankedVolunteer;
use Waaseyaa\Geo\GeoDistance;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;

final class VolunteerRanker
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    /**
     * Rank volunteers by distance from the request's community.
     *
     * Sort order:
     *  1. Same community (distance = 0)
     *  2. Has coordinates, sorted by distance ASC
     *  3. Missing coordinates, sorted by name ASC
     *
     * @param ContentEntityBase[] $volunteers
     * @return RankedVolunteer[]
     */
    public function rank(array $volunteers, ContentEntityBase $request): array
    {
        /** @var array<int, array{float, float}|null> */
        $coordsCache = [];
        $requestCoords = $this->resolveCoords($request, $coordsCache);

        $withDistance = [];
        $withoutDistance = [];

        foreach ($volunteers as $volunteer) {
            $volCoords = $this->resolveCoords($volunteer, $coordsCache);

            if ($requestCoords === null || $volCoords === null) {
                $withoutDistance[] = new RankedVolunteer($volunteer, null);
                continue;
            }

            $distance = GeoDistance::haversine(
                $requestCoords[0],
                $requestCoords[1],
                $volCoords[0],
                $volCoords[1],
            );

            $maxTravel = $volunteer->get('max_travel_km');
            $exceeds = $maxTravel !== null && $maxTravel !== '' && $distance > (float) $maxTravel;

            $withDistance[] = new RankedVolunteer($volunteer, $distance, $exceeds);
        }

        usort($withDistance, static fn (RankedVolunteer $a, RankedVolunteer $b): int =>
            $a->distanceKm <=> $b->distanceKm);

        usort($withoutDistance, static fn (RankedVolunteer $a, RankedVolunteer $b): int =>
            strcasecmp(
                (string) $a->volunteer->get('name'),
                (string) $b->volunteer->get('name'),
            ));

        return [...$withDistance, ...$withoutDistance];
    }

    /**
     * @param array<int, array{float, float}|null> $cache
     * @return array{float, float}|null [latitude, longitude] or null if missing
     */
    private function resolveCoords(ContentEntityBase $entity, array &$cache): ?array
    {
        $communityRef = $entity->get('community');

        if ($communityRef === null || $communityRef === '' || $communityRef === 0) {
            return null;
        }

        $communityId = (int) $communityRef;

        if (array_key_exists($communityId, $cache)) {
            return $cache[$communityId];
        }

        $communityStorage = $this->entityTypeManager->getStorage('community');
        $community = $communityStorage->load($communityId);

        if ($community === null) {
            $cache[$communityId] = null;
            return null;
        }

        $lat = $community->get('latitude');
        $lon = $community->get('longitude');

        if ($lat === null || $lon === null) {
            $cache[$communityId] = null;
            return null;
        }

        $coords = [(float) $lat, (float) $lon];
        $cache[$communityId] = $coords;

        return $coords;
    }
}

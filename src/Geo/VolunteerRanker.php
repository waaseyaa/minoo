<?php

declare(strict_types=1);

namespace Minoo\Geo;

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
        $requestCoords = $this->resolveCoords($request);

        $withDistance = [];
        $withoutDistance = [];

        foreach ($volunteers as $volunteer) {
            $volCoords = $this->resolveCoords($volunteer);

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

            $withDistance[] = new RankedVolunteer($volunteer, $distance);
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
     * @return array{float, float}|null [latitude, longitude] or null if missing
     */
    private function resolveCoords(ContentEntityBase $entity): ?array
    {
        $communityRef = $entity->get('community');

        if ($communityRef === null || $communityRef === '' || $communityRef === 0) {
            return null;
        }

        $communityStorage = $this->entityTypeManager->getStorage('community');
        $community = $communityStorage->load((int) $communityRef);

        if ($community === null) {
            return null;
        }

        $lat = $community->get('latitude');
        $lon = $community->get('longitude');

        if ($lat === null || $lon === null) {
            return null;
        }

        return [(float) $lat, (float) $lon];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Geo\Service;

use App\Domain\Geo\ValueObject\LocationContext;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Resolves a member's PLACE on Mother Earth into a vantage point on the
 * community graph (#816). Consent-first and COARSE by construction:
 *
 *  - location only comes from an explicit, consented choice — a chosen home
 *    community, or a one-time "use my location" the member opts into. There is
 *    NO automatic IP geolocation.
 *  - exact user coordinates are NEVER stored or returned. resolveCoordinates()
 *    uses them transiently to find the nearest community, then returns only that
 *    COMMUNITY's centroid. The LocationContext a controller persists holds the
 *    community centroid, never the device position.
 */
final class LocationService
{
    private CommunityFinder $finder;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        ?CommunityFinder $finder = null,
    ) {
        $this->finder = $finder ?? new CommunityFinder();
    }

    /**
     * A member opted into sharing their device location once. Snap it to the
     * nearest community centroid and discard the precise coordinates.
     */
    public function resolveCoordinates(float $latitude, float $longitude): LocationContext
    {
        $nearest = $this->finder->findNearest($latitude, $longitude, $this->geocodedCommunities());
        if ($nearest === null) {
            return LocationContext::none();
        }

        return $this->contextFor($nearest['community'], 'coordinates');
    }

    /** A member chose a home territory explicitly (e.g. the diaspora picker). */
    public function resolveCommunity(int $communityId): LocationContext
    {
        if ($communityId <= 0) {
            return LocationContext::none();
        }

        $community = $this->entityTypeManager->getStorage('community')->load($communityId);
        if (!$community instanceof ContentEntityBase) {
            return LocationContext::none();
        }

        return $this->contextFor($community, 'chosen');
    }

    /**
     * Communities near a vantage point, nearest first — the spatial slice of the
     * graph that is "yours".
     *
     * @return list<array{community: ContentEntityBase, distanceKm: float}>
     */
    public function nearbyCommunities(LocationContext $context, int $limit = 5): array
    {
        if (!$context->hasLocation() || $context->latitude === null || $context->longitude === null) {
            return [];
        }

        return $this->finder->findNearby($context->latitude, $context->longitude, $this->geocodedCommunities(), $limit);
    }

    private function contextFor(ContentEntityBase $community, string $source): LocationContext
    {
        return new LocationContext(
            communityId: (int) $community->id(),
            communityName: (string) $community->get('name'),
            latitude: (float) $community->get('latitude'),
            longitude: (float) $community->get('longitude'),
            source: $source,
        );
    }

    /**
     * Published communities that carry coordinates.
     *
     * @return list<ContentEntityBase>
     */
    private function geocodedCommunities(): array
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($storage->loadMultiple($ids) as $community) {
            if ($community instanceof ContentEntityBase && $community->get('latitude') !== null) {
                $out[] = $community;
            }
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace Minoo\Service;

use Minoo\Domain\Geo\Service\CommunityFinder;
use Minoo\Domain\Geo\Service\LocationService;
use Minoo\Domain\Geo\ValueObject\LocationContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;

final class LocationResolver
{
    private LocationService $locationService;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly CommunityFinder $communityFinder,
    ) {
        $configPath = dirname(__DIR__, 2) . '/config/waaseyaa.php';
        $config = file_exists($configPath) ? (require $configPath)['location'] ?? [] : [];
        $this->locationService = new LocationService($this->entityTypeManager, $config);
    }

    public function resolveLocation(HttpRequest $request): LocationContext
    {
        return $this->locationService->fromRequest($request);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function resolveCoordinates(LocationContext $location): ?array
    {
        if ($location->latitude === null || $location->longitude === null) {
            return null;
        }

        return ['lat' => $location->latitude, 'lng' => $location->longitude];
    }

    /**
     * @param array<ContentEntityBase> $communities
     * @return array<array{community: ContentEntityBase, distanceKm: float}>
     */
    public function resolveNearbyCommunities(LocationContext $location, array $communities, int $limit = 20): array
    {
        if ($location->latitude === null || $location->longitude === null) {
            return [];
        }

        return $this->communityFinder->findNearby(
            $location->latitude,
            $location->longitude,
            $communities,
            $limit,
        );
    }

    /**
     * @param array<ContentEntityBase> $firstNations
     * @param array<ContentEntityBase> $municipalities
     * @return array<array{community: ContentEntityBase, distanceKm: float}>
     */
    public function resolveMixedNearby(
        LocationContext $location,
        array $firstNations,
        array $municipalities,
        int $limit = 20,
    ): array {
        return $this->resolveNearbyCommunities(
            $location,
            array_merge($firstNations, $municipalities),
            $limit,
        );
    }
}

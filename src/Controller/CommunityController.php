<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\NorthCloudCommunityDictionaryClientInterface;
use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Geo\GeoDistance;

final class CommunityController
{
    private const SAGAMOK_SPANISH_RIVER_FLOOD_SLUG = 'sagamok-anishnawbek';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly ?NorthCloudCommunityDictionaryClientInterface $northCloudClient = null,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $location = $this->resolveLocation($request);

        $typeFilter = $request->query->getString('type');

        $queryBuilder = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC');

        if ($typeFilter === 'first-nations') {
            $queryBuilder->condition('community_type', 'first_nation');
        } elseif ($typeFilter === 'municipalities') {
            $queryBuilder->condition('community_type', 'first_nation', '!=');
        }

        $ids = $queryBuilder->execute();
        $communities = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        // Sort by proximity when location is known, delegating to LocationResolver
        if ($location->hasLocation()) {
            $resolver = new \App\Service\LocationResolver(
                $this->entityTypeManager,
                new \App\Domain\Geo\Service\CommunityFinder(),
            );
            $communities = array_map(
                static fn (array $r) => $r['community'],
                $resolver->resolveNearbyCommunities($location, $communities, count($communities)),
            );
        }

        // Serialize communities for client-side Alpine.js
        $communitiesJson = [];
        foreach ($communities as $community) {
            $communitiesJson[] = [
                'id' => $community->id(),
                'name' => $community->get('name'),
                'slug' => $community->get('slug'),
                'lat' => (float) $community->get('latitude'),
                'lng' => (float) $community->get('longitude'),
                'province' => $community->get('province'),
                'nation' => $community->get('nation'),
                'population' => (int) $community->get('population'),
                'population_year' => (int) $community->get('population_year'),
                'community_type' => (string) ($community->get('community_type') ?? ''),
            ];
        }

        $businessStorage = $this->entityTypeManager->getStorage('group');
        $businessIds = $businessStorage->getQuery()
            ->condition('type', 'business')
            ->condition('status', 1)
            ->execute();
        $businesses = $businessIds !== [] ? array_values($businessStorage->loadMultiple($businessIds)) : [];

        $businessesJson = [];
        foreach ($businesses as $business) {
            $lat = $business->get('latitude');
            $lng = $business->get('longitude');
            $source = $business->get('coordinate_source');
            if ($lat === null || $lng === null || $source !== 'address') {
                continue;
            }
            $businessesJson[] = [
                'id' => $business->id(),
                'name' => $business->get('name'),
                'slug' => $business->get('slug'),
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'community_name' => $business->get('community_name') ?? '',
                'type' => 'business',
            ];
        }

        // LocationService::fromRequest() already checks session → cookie → IP GeoIP2
        $locationJson = $location->hasLocation()
            ? ['lat' => $location->latitude, 'lng' => $location->longitude, 'name' => $location->communityName]
            : null;

        $html = $this->twig->render('pages/communities/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/communities',
            'communities_json' => json_encode($communitiesJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
            'location_json' => json_encode($locationJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
            'businesses_json' => json_encode($businessesJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function spanishRiverFlood(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug !== self::SAGAMOK_SPANISH_RIVER_FLOOD_SLUG) {
            return $this->communityListNotFoundResponse($account);
        }

        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();

        $community = $ids !== [] ? $storage->load(reset($ids)) : null;

        if ($community === null) {
            return $this->communityListNotFoundResponse($account);
        }

        $html = $this->twig->render('pages/communities/spanish-river-flood.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/communities/' . $slug . '/spanish-river-flood',
            'community' => $community,
        ]));

        return new Response($html);
    }

    private function communityListNotFoundResponse(AccountInterface $account): Response
    {
        $html = $this->twig->render('pages/communities/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/communities',
            'communities_json' => '[]',
            'location_json' => 'null',
        ]));

        return new Response($html, 404);
    }

    private const NEARBY_LIMIT = 6;
    private const NEARBY_MAX_KM = 200.0;

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();

        $community = $ids !== [] ? $storage->load(reset($ids)) : null;

        if ($community === null) {
            $html = $this->twig->render('pages/communities/index.html.twig', LayoutTwigContext::withAccount($account, [
                'path' => '/communities',
                'communities_json' => '[]',
                'location_json' => 'null',
            ]));

            return new Response($html, 404);
        }

        $nearby = [];
        $location = $this->resolveLocation($request);
        $people = null;
        $bandOffice = null;

        if ($community !== null) {
            $nearby = $this->findNearbyCommunities($community, $storage);

            $ncId = $community->get('nc_id');
            if ($ncId !== null && $ncId !== '' && $this->northCloudClient !== null) {
                try {
                    $people = $this->northCloudClient->getPeople((string) $ncId);
                    $bandOffice = $this->northCloudClient->getBandOffice((string) $ncId);
                } catch (\Throwable) {
                    // NorthCloud unavailable — page renders without contact data
                }
            }
        }

        $distanceFromUser = null;
        if ($location->hasLocation() && $community !== null) {
            $distanceFromUser = GeoDistance::haversine(
                $location->latitude,
                $location->longitude,
                (float) $community->get('latitude'),
                (float) $community->get('longitude')
            );
        }

        // Local content by community_id
        $communityId = $community->get('cid');

        $eventStorage = $this->entityTypeManager->getStorage('event');
        $eventIds = $eventStorage->getQuery()
            ->condition('community_id', $communityId)
            ->condition('status', 1)
            ->range(0, 6)
            ->execute();
        $localEvents = $eventIds !== [] ? array_values($eventStorage->loadMultiple($eventIds)) : [];

        $teachingStorage = $this->entityTypeManager->getStorage('teaching');
        $teachingIds = $teachingStorage->getQuery()
            ->condition('community_id', $communityId)
            ->condition('status', 1)
            ->range(0, 6)
            ->execute();
        $localTeachings = $teachingIds !== [] ? array_values($teachingStorage->loadMultiple($teachingIds)) : [];

        $groupStorage = $this->entityTypeManager->getStorage('group');
        $businessIds = $groupStorage->getQuery()
            ->condition('community_id', $communityId)
            ->condition('type', 'business')
            ->condition('status', 1)
            ->range(0, 6)
            ->execute();
        $localBusinesses = $businessIds !== [] ? array_values($groupStorage->loadMultiple($businessIds)) : [];

        $personStorage = $this->entityTypeManager->getStorage('resource_person');
        $personIds = $personStorage->getQuery()
            ->condition('community', $community->get('name'))
            ->condition('status', 1)
            ->range(0, 6)
            ->execute();
        $localPeople = $personIds !== [] ? array_values($personStorage->loadMultiple($personIds)) : [];

        $html = $this->twig->render('pages/communities/show.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/communities/' . $slug,
            'community' => $community,
            'nearby' => $nearby,
            'location' => $location,
            'people' => $people,
            'band_office' => $bandOffice,
            'distance_from_user' => $distanceFromUser,
            'local_events' => $localEvents,
            'local_teachings' => $localTeachings,
            'local_businesses' => $localBusinesses,
            'local_people' => $localPeople,
        ]));

        return new Response($html);
    }

    /**
     * @return array<array{community: \Waaseyaa\Entity\ContentEntityBase, distanceKm: float}>
     */
    private function findNearbyCommunities(
        \Waaseyaa\Entity\ContentEntityBase $community,
        \Waaseyaa\Entity\Storage\EntityStorageInterface $storage,
    ): array {
        $lat = $community->get('latitude');
        $lon = $community->get('longitude');

        if ($lat === null || $lon === null) {
            return [];
        }

        $allIds = $storage->getQuery()
            ->condition('status', 1)
            ->execute();
        $all = $allIds !== [] ? $storage->loadMultiple($allIds) : [];

        $finder = new \App\Domain\Geo\Service\CommunityFinder();
        $results = $finder->findNearby((float) $lat, (float) $lon, array_values($all), self::NEARBY_LIMIT + 1);

        // Filter out the current community and cap at distance limit.
        $nearby = [];
        $currentId = $community->id();

        foreach ($results as $result) {
            if ($result['community']->id() === $currentId) {
                continue;
            }
            if ($result['distanceKm'] > self::NEARBY_MAX_KM) {
                break;
            }
            $nearby[] = $result;
            if (count($nearby) >= self::NEARBY_LIMIT) {
                break;
            }
        }

        return $nearby;
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function autocomplete(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $term = trim($request->query->getString('q'));

        if (strlen($term) < 2) {
            return new JsonResponse([]);
        }

        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('name', '%' . $term . '%', 'LIKE')
            ->condition('status', 1)
            ->range(0, 10)
            ->execute();

        $results = [];
        if ($ids !== []) {
            foreach ($storage->loadMultiple($ids) as $community) {
                $results[] = [
                    'id' => (string) $community->id(),
                    'name' => (string) $community->get('name'),
                    'community_type' => (string) ($community->get('community_type') ?? ''),
                    'province' => (string) ($community->get('province') ?? ''),
                ];
            }
        }

        return new JsonResponse($results);
    }

    private function resolveLocation(HttpRequest $request): \App\Domain\Geo\ValueObject\LocationContext
    {
        return (new \App\Service\LocationResolver(
            $this->entityTypeManager,
            new \App\Domain\Geo\Service\CommunityFinder(),
        ))->resolveLocation($request);
    }
}

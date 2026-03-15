<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Geo\Service\CommunityFinder;
use Minoo\Domain\Geo\Service\LocationService;
use Minoo\Support\GeoDistance;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class HomeController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function explore(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $type = $query['type'] ?? 'all';
        $q = trim($query['q'] ?? '');

        $targets = [
            'businesses' => '/groups',
            'people' => '/people',
            'events' => '/events',
            'all' => '/groups',
        ];

        $target = $targets[$type] ?? '/groups';

        if ($q !== '') {
            $target .= '?' . http_build_query(['q' => $q]);
        }

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $target]);
    }

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $config = $this->loadLocationConfig();
        $service = new LocationService($this->entityTypeManager, $config);
        $location = $service->fromRequest($request);

        $templateVars = [
            'path' => '/',
            'account' => $account,
            'location' => $location,
        ];

        if ($location->hasLocation()) {
            $service->storeInSession($location);
            $service->setCookie($location);

            $lat = $location->latitude ?? 0.0;
            $lon = $location->longitude ?? 0.0;

            $communities = $this->loadAllCommunities();
            $communityCoords = $this->buildCommunityCoords($communities);

            $finder = new CommunityFinder();
            $templateVars['nearby_communities'] = $finder->findNearby($lat, $lon, $communities, limit: 6);
            $templateVars['nearby_mixed'] = $this->buildNearbyMixed($lat, $lon, $communityCoords);
            $templateVars['tab_events'] = $this->sortByProximity(
                $this->loadUpcomingEventsFiltered(6), 'community_id', $lat, $lon, $communityCoords
            );
            $templateVars['tab_people'] = $this->sortByProximity(
                $this->loadPublicPeople(6), 'community', $lat, $lon, $communityCoords
            );
            $templateVars['tab_groups'] = $this->sortByProximity(
                $this->loadGroups(6), 'community_id', $lat, $lon, $communityCoords
            );
        } else {
            $templateVars['nearby_communities'] = [];
            $templateVars['nearby_mixed'] = [];
            $templateVars['tab_events'] = $this->loadUpcomingEventsFiltered(6);
            $templateVars['tab_people'] = $this->loadPublicPeople(6);
            $templateVars['tab_groups'] = $this->loadGroups(6);
        }

        $html = $this->twig->render('page.html.twig', $templateVars);
        return new SsrResponse(content: $html);
    }

    private function loadUpcomingEventsFiltered(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('event');
        $now = date('Y-m-d\TH:i:s');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('starts_at', $now, '>=')
            ->sort('starts_at', 'ASC')
            ->range(0, $limit)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $events = array_values($storage->loadMultiple($ids));

        return array_values(array_filter($events, function ($entity) {
            $mediaId = $entity->get('media_id');
            if ($mediaId === null || $mediaId === '') {
                return true;
            }
            $status = $entity->get('copyright_status');
            return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
        }));
    }

    private function loadPublicPeople(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $ids = $storage->getQuery()
            ->condition('consent_public', 1)
            ->condition('status', 1)
            ->range(0, $limit)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    private function loadGroups(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->range(0, $limit)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    private function sortByProximity(array $entities, string $communityField, float $lat, float $lon, array $communityCoords): array
    {
        usort($entities, function ($a, $b) use ($communityField, $lat, $lon, $communityCoords) {
            $distA = $this->entityDistance($a, $communityField, $lat, $lon, $communityCoords);
            $distB = $this->entityDistance($b, $communityField, $lat, $lon, $communityCoords);
            return $distA <=> $distB;
        });

        return $entities;
    }

    private function entityDistance(mixed $entity, string $communityField, float $lat, float $lon, array $communityCoords): float
    {
        if ($communityField === 'community') {
            $name = $entity->get('community');
            $coords = $name !== null ? ($communityCoords['name:' . $name] ?? null) : null;
        } else {
            $cid = $entity->get($communityField);
            $coords = $cid !== null ? ($communityCoords[(int)$cid] ?? null) : null;
        }

        if ($coords === null) {
            return PHP_FLOAT_MAX;
        }

        return GeoDistance::haversine($lat, $lon, $coords['lat'], $coords['lon']);
    }

    /** @return array<string|int, array{lat: float, lon: float}> */
    private function buildCommunityCoords(array $communities): array
    {
        $coords = [];
        foreach ($communities as $c) {
            $cLat = $c->get('latitude');
            $cLon = $c->get('longitude');
            if ($cLat !== null && $cLon !== null) {
                $coords[(int)$c->id()] = ['lat' => (float)$cLat, 'lon' => (float)$cLon];
                $name = $c->get('name');
                if ($name !== null) {
                    $coords['name:' . $name] = ['lat' => (float)$cLat, 'lon' => (float)$cLon];
                }
            }
        }
        return $coords;
    }

    private function buildNearbyMixed(float $lat, float $lon, array $communityCoords): array
    {
        $groups = $this->sortByProximity($this->loadGroups(3), 'community_id', $lat, $lon, $communityCoords);
        $events = $this->sortByProximity($this->loadUpcomingEventsFiltered(3), 'community_id', $lat, $lon, $communityCoords);
        $people = $this->sortByProximity($this->loadPublicPeople(3), 'community', $lat, $lon, $communityCoords);

        $taggedGroups = array_map(fn($e) => ['entity' => $e, 'type' => 'group'], $groups);
        $taggedEvents = array_map(fn($e) => ['entity' => $e, 'type' => 'event'], $events);
        $taggedPeople = array_map(fn($e) => ['entity' => $e, 'type' => 'person'], $people);

        $result = [];
        $sources = [$taggedGroups, $taggedEvents, $taggedPeople];
        while (count($result) < 6) {
            $added = false;
            foreach ($sources as &$source) {
                if ($source !== []) {
                    $result[] = array_shift($source);
                    $added = true;
                    if (count($result) >= 6) break 2;
                }
            }
            unset($source);
            if (!$added) break;
        }

        return $result;
    }

    private function loadAllCommunities(): array
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->execute();
        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    private function loadLocationConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/waaseyaa.php';
        if (!file_exists($configPath)) {
            return [];
        }
        $allConfig = require $configPath;
        return $allConfig['location'] ?? [];
    }
}

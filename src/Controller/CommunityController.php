<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\NorthCloudCache;
use Minoo\Support\NorthCloudClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class CommunityController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $location = $this->resolveLocation($request);

        $typeFilter = $request->query->getString('type');

        $queryBuilder = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC');

        if ($typeFilter === 'first-nations') {
            $queryBuilder->condition('is_municipality', 0);
        } elseif ($typeFilter === 'municipalities') {
            $queryBuilder->condition('is_municipality', 1);
        }

        $ids = $queryBuilder->execute();
        $communities = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        // Sort by proximity when location is known, delegating to LocationResolver
        if ($location->hasLocation()) {
            $resolver = new \Minoo\Service\LocationResolver(
                $this->entityTypeManager,
                new \Minoo\Domain\Geo\Service\CommunityFinder(),
            );
            $communities = array_map(
                static fn (array $r) => $r['community'],
                $resolver->resolveNearbyCommunities($location, $communities, count($communities)),
            );
        }

        $html = $this->twig->render('communities.html.twig', [
            'path' => '/communities',
            'communities' => array_values($communities),
            'type_filter' => $typeFilter,
            'location' => $location,
        ]);

        return new SsrResponse(content: $html);
    }

    private const NEARBY_LIMIT = 6;
    private const NEARBY_MAX_KM = 200.0;

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();

        $community = $ids !== [] ? $storage->load(reset($ids)) : null;

        $nearby = [];
        $location = $this->resolveLocation($request);
        $people = null;
        $bandOffice = null;

        if ($community !== null) {
            $nearby = $this->findNearbyCommunities($community, $storage);

            $ncId = $community->get('nc_id');
            if ($ncId !== null && $ncId !== '') {
                $ncClient = $this->createNorthCloudClient();
                $people = $ncClient->getPeople((string) $ncId);
                $bandOffice = $ncClient->getBandOffice((string) $ncId);
            }
        }

        $html = $this->twig->render('communities.html.twig', [
            'path' => '/communities/' . $slug,
            'community' => $community,
            'nearby' => $nearby,
            'location' => $location,
            'people' => $people,
            'band_office' => $bandOffice,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $community !== null ? 200 : 404,
        );
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

        $finder = new \Minoo\Domain\Geo\Service\CommunityFinder();
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

    private function createNorthCloudClient(): NorthCloudClient
    {
        $configPath = dirname(__DIR__, 2) . '/config/waaseyaa.php';
        $config = file_exists($configPath) ? (require $configPath)['northcloud'] ?? [] : [];

        $projectRoot = dirname(__DIR__, 2);
        $dbPath = getenv('WAASEYAA_DB') ?: $projectRoot . '/waaseyaa.sqlite';
        $cache = null;
        if (file_exists($dbPath)) {
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $cache = new NorthCloudCache($pdo, (int) ($config['cache_ttl'] ?? 3600));
        }

        return new NorthCloudClient(
            baseUrl: (string) ($config['base_url'] ?? 'https://northcloud.web.net'),
            timeout: (int) ($config['timeout'] ?? 5),
            cache: $cache,
        );
    }

    private function resolveLocation(HttpRequest $request): \Minoo\Domain\Geo\ValueObject\LocationContext
    {
        return (new \Minoo\Service\LocationResolver(
            $this->entityTypeManager,
            new \Minoo\Domain\Geo\Service\CommunityFinder(),
        ))->resolveLocation($request);
    }
}

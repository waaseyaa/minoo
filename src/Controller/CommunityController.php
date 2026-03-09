<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Search\CommunityAutocompleteClient;
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

        $queryBuilder = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC');

        $typeFilter = $request->query->getString('type');
        if ($typeFilter !== '') {
            $queryBuilder->condition('community_type', $typeFilter);
        }

        $ids = $queryBuilder->execute();
        $communities = $ids !== [] ? $storage->loadMultiple($ids) : [];

        // Resolve location for proximity sorting
        $location = $this->resolveLocation($request);

        if ($location->hasLocation() && $location->latitude !== null) {
            $finder = new \Minoo\Geo\CommunityFinder();
            $sorted = $finder->findNearby(
                $location->latitude,
                $location->longitude,
                array_values($communities),
                count($communities),
            );
            $communities = array_map(static fn (array $r) => $r['community'], $sorted);
        }

        $html = $this->twig->render('communities.html.twig', [
            'path' => '/communities',
            'communities' => array_values($communities),
            'type_filter' => $typeFilter,
            'location' => $location,
        ]);

        return new SsrResponse(content: $html);
    }

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

        $html = $this->twig->render('communities.html.twig', [
            'path' => '/communities/' . $slug,
            'community' => $community,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $community !== null ? 200 : 404,
        );
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function autocomplete(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $term = $request->query->getString('q');

        $client = new CommunityAutocompleteClient(
            baseUrl: (string) getenv('NORTHCLOUD_API_URL'),
            timeout: 5,
        );

        return new JsonResponse($client->suggest($term));
    }

    private function resolveLocation(HttpRequest $request): \Minoo\Geo\LocationContext
    {
        $configPath = dirname(__DIR__, 2) . '/config/waaseyaa.php';
        $config = file_exists($configPath) ? (require $configPath)['location'] ?? [] : [];
        $service = new \Minoo\Geo\LocationService($this->entityTypeManager, $config);
        return $service->fromRequest($request);
    }
}

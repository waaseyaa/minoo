<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Geo\Service\CommunityFinder;
use Minoo\Domain\Geo\Service\LocationService;
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

            $communities = $this->loadAllCommunities();
            $finder = new CommunityFinder();
            $templateVars['nearby_communities'] = $finder->findNearby(
                $location->latitude ?? 0.0,
                $location->longitude ?? 0.0,
                $communities,
                limit: 3,
            );

            $templateVars['events'] = $this->loadUpcomingEvents(3);
        }

        $html = $this->twig->render('page.html.twig', $templateVars);
        return new SsrResponse(content: $html);
    }

    private function loadUpcomingEvents(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->sort('date', 'ASC')
            ->execute();

        $events = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
        return array_slice($events, 0, $limit);
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

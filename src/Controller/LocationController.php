<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Geo\Service\LocationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class LocationController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function current(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $service = $this->makeService();
        $ctx = $service->fromRequest($request);

        return new JsonResponse([
            'hasLocation' => $ctx->hasLocation(),
            ...$ctx->toArray(),
        ]);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function set(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $communityId = $body['community_id'] ?? null;

        if ($communityId === null || $communityId === '' || $communityId === 0) {
            return new JsonResponse(['error' => 'community_id is required'], 400);
        }

        if (is_numeric($communityId)) {
            $communityId = (int) $communityId;
        }

        $service = $this->makeService();
        $ctx = $service->resolveFromCommunityId($communityId);

        if (!$ctx->hasLocation()) {
            return new JsonResponse(['error' => 'Community not found'], 404);
        }

        $service->storeInSession($ctx);
        $service->setCookie($ctx);

        return new JsonResponse([
            'success' => true,
            ...$ctx->toArray(),
        ]);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function update(array $params, array $query, AccountInterface $account, HttpRequest $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $latitude = $body['latitude'] ?? null;
        $longitude = $body['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            return new JsonResponse(['error' => 'latitude and longitude are required'], 400);
        }

        $service = $this->makeService();
        $ctx = $service->resolveFromCoordinates((float) $latitude, (float) $longitude, 'browser');

        if (!$ctx->hasLocation()) {
            return new JsonResponse(['error' => 'No nearby community found'], 404);
        }

        $service->storeInSession($ctx);
        $service->setCookie($ctx);

        return new JsonResponse([
            'success' => true,
            ...$ctx->toArray(),
        ]);
    }

    private function makeService(): LocationService
    {
        $config = [];
        $configPath = dirname(__DIR__, 2) . '/config/waaseyaa.php';
        if (file_exists($configPath)) {
            $allConfig = require $configPath;
            $config = $allConfig['location'] ?? [];
        }

        return new LocationService($this->entityTypeManager, $config);
    }
}

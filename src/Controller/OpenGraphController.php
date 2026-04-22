<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\CrisisOgAutomationPolicy;
use App\Support\CrisisOgImageService;
use App\Support\CrisisIncidentResolver;
use App\Support\CrisisResolveContext;
use App\Support\OgImageRenderer;
use App\Support\PublicOgEntityLoader;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class OpenGraphController
{
    private const CACHE_MAX_AGE = 86400;

    private const SAGAMOK_COMMUNITY_SLUG = 'sagamok-anishnawbek';

    private const SAGAMOK_INCIDENT_SLUG = 'spanish-river-flood';

    /** @var array{0: int, 1: int, 2: int} */
    private const ACCENT_BUSINESS = [216, 86, 64];

    /** @var array{0: int, 1: int, 2: int} */
    private const ACCENT_EVENT = [230, 57, 70];

    /** @var array{0: int, 1: int, 2: int} */
    private const ACCENT_TEACHING = [244, 162, 97];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly OgImageRenderer $ogImageRenderer,
        private readonly CrisisIncidentResolver $crisisIncidentResolver,
        private readonly CrisisOgImageService $crisisOgImageService,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function businessPng(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $entity = PublicOgEntityLoader::loadBusiness($this->entityTypeManager, $slug);

        return $this->pngForEntity($request, $entity, 'Business', self::ACCENT_BUSINESS, static function (EntityInterface $e): string {
            return (string) $e->get('name');
        });
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function eventPng(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $entity = PublicOgEntityLoader::loadEvent($this->entityTypeManager, $slug);

        return $this->pngForEntity($request, $entity, 'Event', self::ACCENT_EVENT, static function (EntityInterface $e): string {
            return (string) $e->get('title');
        });
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function teachingPng(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $entity = PublicOgEntityLoader::loadTeaching($this->entityTypeManager, $slug);

        return $this->pngForEntity($request, $entity, 'Teaching', self::ACCENT_TEACHING, static function (EntityInterface $e): string {
            return (string) $e->get('title');
        });
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function sagamokSpanishRiverFloodPng(
        array $params,
        array $query,
        AccountInterface $account,
        HttpRequest $request,
    ): Response {
        return $this->crisisIncidentPngInternal($request, self::SAGAMOK_COMMUNITY_SLUG, self::SAGAMOK_INCIDENT_SLUG);
    }

    /**
     * Generic crisis OG PNG: static file under public/ when present, else dynamic GD render per policy.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function crisisIncidentPng(
        array $params,
        array $query,
        AccountInterface $account,
        HttpRequest $request,
    ): Response {
        $community = (string) ($params['community_slug'] ?? '');
        $incident = (string) ($params['incident_slug'] ?? '');

        return $this->crisisIncidentPngInternal($request, $community, $incident);
    }

    private function crisisIncidentPngInternal(HttpRequest $request, string $communitySlug, string $incidentSlug): Response
    {
        $resolved = $this->crisisIncidentResolver->resolve($communitySlug, $incidentSlug, CrisisResolveContext::publicWeb());
        if ($resolved === null) {
            return new Response('', 404);
        }

        $registry = $resolved['registry'];
        $incident = $resolved['incident'];
        $webPath = trim((string) ($incident['og_image_path'] ?? ''));
        if ($webPath === '' || !CrisisOgAutomationPolicy::isManagedGeneratedWebPath($webPath)) {
            return new Response('', 404);
        }

        $community = $this->crisisOgImageService->loadPublishedCommunity($communitySlug);
        if ($community === null) {
            return new Response('', 404);
        }

        $abs = $this->crisisOgImageService->absolutePublicFilePath($webPath);
        $static = $this->crisisOgImageService->readStaticPngIfPresent($webPath);
        if ($static !== null) {
            $etag = $this->crisisOgImageService->etagForStaticFile($resolved, $abs);
            $response = new Response($static);
            $response->headers->set('Content-Type', 'image/png');
            $response->setPublic();
            $response->setMaxAge(self::CACHE_MAX_AGE);
            $response->setEtag($etag);
            if ($response->isNotModified($request)) {
                return $response;
            }

            return $response;
        }

        if (CrisisOgAutomationPolicy::httpDynamicWhenMissingIneligibilityReason($registry, $incident) !== null) {
            return new Response('', 404);
        }

        if (!extension_loaded('gd')) {
            return new Response('', 503);
        }

        $etagPayload = $this->crisisOgImageService->etagPayloadForDynamic($resolved, $community);
        $etag = '"' . hash('sha256', $etagPayload) . '"';

        $response = new Response();
        $response->headers->set('Content-Type', 'image/png');
        $response->setPublic();
        $response->setMaxAge(self::CACHE_MAX_AGE);
        $response->setEtag($etag);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $binary = $this->crisisOgImageService->buildDynamicPngBinary($resolved, $community);
        $response->setContent($binary);

        $this->crisisOgImageService->dispatchBackgroundMaterialize($communitySlug, $incidentSlug, $resolved);

        return $response;
    }

    /**
     * @param array{0: int, 1: int, 2: int} $accentRgb
     * @param callable(EntityInterface): string $titleResolver
     */
    private function pngForEntity(
        HttpRequest $request,
        ?EntityInterface $entity,
        string $subtitle,
        array $accentRgb,
        callable $titleResolver,
    ): Response {
        if ($entity === null) {
            return new Response('', 404);
        }

        $title = $titleResolver($entity);
        $etag = '"' . hash('sha256', $entity->uuid() . '|' . $subtitle . '|' . $title) . '"';

        $response = new Response();
        $response->headers->set('Content-Type', 'image/png');
        $response->setPublic();
        $response->setMaxAge(self::CACHE_MAX_AGE);
        $response->setEtag($etag);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $binary = $this->ogImageRenderer->renderPng($title, $subtitle, $accentRgb);
        $response->setContent($binary);

        return $response;
    }
}

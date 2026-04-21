<?php

declare(strict_types=1);

namespace App\Controller;

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

    /** @var array{0: int, 1: int, 2: int} */
    private const ACCENT_BUSINESS = [216, 86, 64];

    /** @var array{0: int, 1: int, 2: int} */
    private const ACCENT_EVENT = [230, 57, 70];

    /** @var array{0: int, 1: int, 2: int} */
    private const ACCENT_TEACHING = [244, 162, 97];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly OgImageRenderer $ogImageRenderer,
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

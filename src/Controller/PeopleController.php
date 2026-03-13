<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class PeopleController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $queryBuilder = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC');

        $ids = $queryBuilder->execute();
        $people = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $mediaIds = [];
        $allRoles = [];
        $allOfferings = [];

        foreach ($people as $person) {
            $mid = $person->get('media_id');
            if ($mid !== null && $mid !== '') {
                $mediaIds[] = (int) $mid;
            }

            $roles = $person->get('roles');
            if (is_array($roles)) {
                foreach ($roles as $role) {
                    $allRoles[$role] = true;
                }
            }

            $offerings = $person->get('offerings');
            if (is_array($offerings)) {
                foreach ($offerings as $offering) {
                    $allOfferings[$offering] = true;
                }
            }
        }

        $photoUrls = $mediaIds !== [] ? $this->resolvePhotoUrls($mediaIds) : [];
        $location = $this->resolveLocation($request);

        $html = $this->twig->render('people.html.twig', [
            'path' => '/people',
            'people' => $people,
            'photo_urls' => $photoUrls,
            'all_roles' => array_keys($allRoles),
            'all_offerings' => array_keys($allOfferings),
            'location' => $location,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();

        $person = $ids !== [] ? $storage->load(reset($ids)) : null;

        $photoUrl = '';
        if ($person !== null) {
            $mid = $person->get('media_id');
            if ($mid !== null && $mid !== '') {
                $urls = $this->resolvePhotoUrls([(int) $mid]);
                $photoUrl = $urls[(int) $mid] ?? '';
            }
        }

        $html = $this->twig->render('people.html.twig', [
            'path' => '/people/' . $slug,
            'person' => $person,
            'photo_url' => $photoUrl,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $person !== null ? 200 : 404,
        );
    }

    private function resolveLocation(HttpRequest $request): \Minoo\Domain\Geo\ValueObject\LocationContext
    {
        return (new \Minoo\Service\LocationResolver(
            $this->entityTypeManager,
            new \Minoo\Domain\Geo\Service\CommunityFinder(),
        ))->resolveLocation($request);
    }

    /**
     * @param int[] $mediaIds
     * @return array<int, string> Map of media ID to file URL
     */
    private function resolvePhotoUrls(array $mediaIds): array
    {
        $mediaStorage = $this->entityTypeManager->getStorage('media');
        $mediaEntities = $mediaStorage->loadMultiple($mediaIds);

        $urls = [];
        foreach ($mediaEntities as $media) {
            /** @var EntityInterface $media */
            $url = $media->get('file_url');
            if (is_string($url) && $url !== '') {
                $urls[(int) $media->id()] = $url;
            }
        }

        return $urls;
    }
}

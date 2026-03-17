<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\CommunityLookup;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class TeachingController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('teaching');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->sort('title', 'ASC')
            ->execute();
        $teachings = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $teachings = array_filter($teachings, function ($entity) {
            $mediaId = $entity->get('media_id');
            if ($mediaId === null || $mediaId === '') {
                return true;
            }
            $status = $entity->get('copyright_status');
            return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
        });
        $teachings = array_values($teachings);

        $communities = CommunityLookup::build($this->entityTypeManager, $teachings);

        $html = $this->twig->render('teachings.html.twig', [
            'path' => '/teachings',
            'teachings' => $teachings,
            'communities' => $communities,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('teaching');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        $teaching = $ids !== [] ? $storage->load(reset($ids)) : null;

        if ($teaching !== null) {
            $mediaId = $teaching->get('media_id');
            if ($mediaId !== null && $mediaId !== '') {
                $status = $teaching->get('copyright_status');
                if (!in_array($status, ['community_owned', 'cc_by_nc_sa'], true)) {
                    $teaching = null;
                }
            }
        }

        $relatedEvents = [];
        $knowledgeKeepers = [];

        if ($teaching !== null) {
            $communityId = $teaching->get('community_id');

            if ($communityId !== null && $communityId !== '') {
                $eventStorage = $this->entityTypeManager->getStorage('event');
                $eventIds = $eventStorage->getQuery()
                    ->condition('community_id', $communityId)
                    ->condition('status', 1)
                    ->range(0, 4)
                    ->execute();
                $relatedEvents = $eventIds !== [] ? array_values($eventStorage->loadMultiple($eventIds)) : [];

                $personStorage = $this->entityTypeManager->getStorage('resource_person');
                $personIds = $personStorage->getQuery()
                    ->condition('community', $communityId)
                    ->condition('status', 1)
                    ->range(0, 4)
                    ->execute();
                $knowledgeKeepers = $personIds !== [] ? array_values($personStorage->loadMultiple($personIds)) : [];
            }
        }

        $imageUrl = '';
        $imageCredit = '';
        if ($teaching !== null) {
            $mid = $teaching->get('media_id');
            if ($mid !== null && $mid !== '') {
                $status = $teaching->get('copyright_status');
                if (in_array($status, ['community_owned', 'cc_by_nc_sa'], true)) {
                    $urls = $this->resolvePhotoUrls([(int) $mid]);
                    $imageUrl = $urls[(int) $mid] ?? '';
                }
            }
        }

        $html = $this->twig->render('teachings.html.twig', [
            'path' => '/teachings/' . $slug,
            'teaching' => $teaching,
            'related_events' => $relatedEvents,
            'knowledge_keepers' => $knowledgeKeepers,
            'image_url' => $imageUrl,
            'image_credit' => $imageCredit,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $teaching !== null ? 200 : 404,
        );
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

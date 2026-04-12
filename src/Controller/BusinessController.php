<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\CommunityLookup;
use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;

final class BusinessController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('type', 'business')
            ->condition('status', 1)
            ->sort('name', 'ASC')
            ->execute();
        $businesses = $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];

        $businesses = array_filter($businesses, function ($entity) {
            $mediaId = $entity->get('media_id');
            if ($mediaId === null || $mediaId === '') {
                return true;
            }
            $status = $entity->get('copyright_status');
            return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
        });
        $businesses = array_values($businesses);

        $communities = CommunityLookup::build($this->entityTypeManager, $businesses);

        $html = $this->twig->render('businesses.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/businesses',
            'businesses' => $businesses,
            'communities' => $communities,
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('type', 'business')
            ->condition('status', 1)
            ->execute();
        $business = $ids !== [] ? $storage->load(reset($ids)) : null;

        // Verify it's actually a business (belt-and-suspenders with query condition)
        if ($business !== null && $business->get('type') !== 'business') {
            $business = null;
        }

        if ($business !== null) {
            $mediaId = $business->get('media_id');
            if ($mediaId !== null && $mediaId !== '') {
                $status = $business->get('copyright_status');
                if (!in_array($status, ['community_owned', 'cc_by_nc_sa'], true)) {
                    $business = null;
                }
            }
        }

        // Load linked owner (ResourcePerson) — only if consented
        $owner = null;
        if ($business !== null) {
            $personStorage = $this->entityTypeManager->getStorage('resource_person');
            $ownerIds = $personStorage->getQuery()
                ->condition('linked_group_id', $business->id())
                ->condition('status', 1)
                ->condition('consent_public', 1)
                ->execute();
            $owner = $ownerIds !== [] ? $personStorage->load(reset($ownerIds)) : null;
        }

        // Decode social posts JSON
        $socialPosts = [];
        if ($business !== null) {
            $raw = $business->get('social_posts') ?? '';
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $socialPosts = $decoded;
                }
            }
        }

        // Load linked community
        $community = null;
        if ($business !== null && $business->get('community_id')) {
            $communityStorage = $this->entityTypeManager->getStorage('community');
            $communityIds = $communityStorage->getQuery()
                ->condition('cid', $business->get('community_id'))
                ->range(0, 1)
                ->execute();
            $community = $communityIds ? $communityStorage->load(reset($communityIds)) : null;
        }

        $imageUrl = '';
        $imageCredit = '';
        if ($business !== null) {
            $mid = $business->get('media_id');
            if ($mid !== null && $mid !== '') {
                $status = $business->get('copyright_status');
                if (in_array($status, ['community_owned', 'cc_by_nc_sa'], true)) {
                    $urls = $this->resolvePhotoUrls([(int) $mid]);
                    $imageUrl = $urls[(int) $mid] ?? '';
                }
            }
        }

        $html = $this->twig->render('businesses.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/businesses/' . $slug,
            'business' => $business,
            'owner' => $owner,
            'social_posts' => $socialPosts,
            'community' => $community,
            'image_url' => $imageUrl,
            'image_credit' => $imageCredit,
        ]));

        return new Response($html, $business !== null ? 200 : 404);
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

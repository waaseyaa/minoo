<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\CommunityLookup;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class BusinessController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        $html = $this->twig->render('businesses.html.twig', [
            'path' => '/businesses',
            'businesses' => $businesses,
            'communities' => $communities,
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        // Load linked owner (ResourcePerson)
        $owner = null;
        if ($business !== null) {
            $personStorage = $this->entityTypeManager->getStorage('resource_person');
            $ownerIds = $personStorage->getQuery()
                ->condition('linked_group_id', $business->id())
                ->condition('status', 1)
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

        $html = $this->twig->render('businesses.html.twig', [
            'path' => '/businesses/' . $slug,
            'business' => $business,
            'owner' => $owner,
            'social_posts' => $socialPosts,
            'community' => $community,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $business !== null ? 200 : 404,
        );
    }
}

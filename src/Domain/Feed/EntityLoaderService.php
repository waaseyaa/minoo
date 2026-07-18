<?php

declare(strict_types=1);

namespace App\Domain\Feed;

use Waaseyaa\Entity\EntityTypeManager;

class EntityLoaderService
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    public function getEntityTypeManager(): EntityTypeManager
    {
        return $this->entityTypeManager;
    }

    /**
     * Upcoming events. Guarded so the feed works before the Events surface is
     * restored (Phase 2 order: feed first, then Events) — returns [] until the
     * 'event' type is registered, then auto-joins the feed.
     */
    public function loadUpcomingEvents(int $limit): array
    {
        if (!$this->entityTypeManager->hasDefinition('event')) {
            return [];
        }

        $repository = $this->entityTypeManager->getRepository('event');
        $now = date('Y-m-d\TH:i:s');
        $ids = $repository->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->condition('starts_at', $now, '>=')
            ->sort('starts_at', 'ASC')
            ->range(0, $limit)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $events = $repository->findMany($ids);

        return array_values(array_filter($events, function ($entity) {
            $mediaId = $entity->get('media_id');
            if ($mediaId === null || $mediaId === '') {
                return true;
            }
            $status = $entity->get('copyright_status');
            return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
        }));
    }

    /**
     * Groups (non-business). Guarded like events — returns [] until the
     * 'community_group' type is registered. Businesses are CUT and never
     * loaded here.
     */
    public function loadGroups(int $limit): array
    {
        if (!$this->entityTypeManager->hasDefinition('community_group')) {
            return [];
        }

        $repository = $this->entityTypeManager->getRepository('community_group');
        $ids = $repository->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->condition('type', 'business', '!=')
            ->range(0, $limit)
            ->execute();

        // findMany([]) is fail-closed (returns []) — no empty-ids guard needed.
        return $repository->findMany($ids);
    }

    /** @return list<array{featured: mixed, entity: mixed}> */
    public function loadFeaturedItems(): array
    {
        try {
            $repository = $this->entityTypeManager->getRepository('featured_item');
        } catch (\PDOException $e) {
            error_log(sprintf('[EntityLoaderService::%s] Database error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        } catch (\RuntimeException $e) {
            error_log(sprintf('[EntityLoaderService::%s] Runtime error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $ids = $repository->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->condition('starts_at', $now, '<=')
            ->condition('ends_at', $now, '>=')
            ->sort('weight', 'DESC')
            ->execute();

        if ($ids === []) {
            return [];
        }

        $items = [];
        foreach ($repository->findMany($ids) as $featured) {
            $entityType = $featured->get('entity_type');
            $entityId = $featured->get('entity_id');

            if ($entityType === null || $entityId === null) {
                continue;
            }

            try {
                $refRepository = $this->entityTypeManager->getRepository($entityType);
                $entity = $refRepository->find((string) $entityId);
            } catch (\PDOException $e) {
                error_log(sprintf('[EntityLoaderService::%s] Database error loading %s:%s: %s', __FUNCTION__, $entityType, $entityId, $e->getMessage()));
                continue;
            } catch (\RuntimeException $e) {
                error_log(sprintf('[EntityLoaderService::%s] Runtime error loading %s:%s: %s', __FUNCTION__, $entityType, $entityId, $e->getMessage()));
                continue;
            }

            if ($entity === null) {
                continue;
            }

            $items[] = ['featured' => $featured, 'entity' => $entity];
        }

        return $items;
    }

    public function loadPosts(int $limit): array
    {
        try {
            $repository = $this->entityTypeManager->getRepository('post');
        } catch (\PDOException $e) {
            error_log(sprintf('[EntityLoaderService::%s] Database error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        } catch (\RuntimeException $e) {
            // Post entity type may not exist yet
            error_log(sprintf('[EntityLoaderService::%s] Runtime error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        }

        $ids = $repository->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->sort('created_at', 'DESC')
            ->range(0, $limit)
            ->execute();

        // findMany([]) is fail-closed (returns []) — no empty-ids guard needed.
        return $repository->findMany($ids);
    }

    public function loadAllCommunities(): array
    {
        $repository = $this->entityTypeManager->getRepository('community');
        $ids = $repository->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->execute();

        // findMany([]) is fail-closed (returns []) — no empty-ids guard needed.
        return $repository->findMany($ids);
    }
}

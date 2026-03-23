<?php

declare(strict_types=1);

namespace Minoo\Feed;

use Waaseyaa\Entity\EntityTypeManager;

class EntityLoaderService
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function getEntityTypeManager(): EntityTypeManager
    {
        return $this->entityTypeManager;
    }

    public function loadUpcomingEvents(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('event');
        $now = date('Y-m-d\TH:i:s');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('starts_at', $now, '>=')
            ->sort('starts_at', 'ASC')
            ->range(0, $limit)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $events = array_values($storage->loadMultiple($ids));

        return array_values(array_filter($events, function ($entity) {
            $mediaId = $entity->get('media_id');
            if ($mediaId === null || $mediaId === '') {
                return true;
            }
            $status = $entity->get('copyright_status');
            return in_array($status, ['community_owned', 'cc_by_nc_sa'], true);
        }));
    }

    public function loadGroups(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('type', 'business', '!=')
            ->range(0, $limit)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    public function loadBusinesses(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('type', 'business')
            ->range(0, $limit)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    public function loadPublicPeople(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('resource_person');
        $ids = $storage->getQuery()
            ->condition('consent_public', 1)
            ->condition('status', 1)
            ->range(0, $limit)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    /** @return list<array{featured: mixed, entity: mixed, url: string}> */
    public function loadFeaturedItems(): array
    {
        try {
            $storage = $this->entityTypeManager->getStorage('featured_item');
        } catch (\PDOException $e) {
            error_log(sprintf('[EntityLoaderService::%s] Database error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        } catch (\RuntimeException $e) {
            error_log(sprintf('[EntityLoaderService::%s] Runtime error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('starts_at', $now, '<=')
            ->condition('ends_at', $now, '>=')
            ->sort('weight', 'DESC')
            ->execute();

        if ($ids === []) {
            return [];
        }

        $items = [];
        foreach ($storage->loadMultiple($ids) as $featured) {
            $entityType = $featured->get('entity_type');
            $entityId = $featured->get('entity_id');

            if ($entityType === null || $entityId === null) {
                continue;
            }

            try {
                $refStorage = $this->entityTypeManager->getStorage($entityType);
                $entity = $refStorage->load((int) $entityId);
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
            $storage = $this->entityTypeManager->getStorage('post');
        } catch (\PDOException $e) {
            error_log(sprintf('[EntityLoaderService::%s] Database error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        } catch (\RuntimeException $e) {
            // Post entity type may not exist yet
            error_log(sprintf('[EntityLoaderService::%s] Runtime error: %s', __FUNCTION__, $e->getMessage()));
            return [];
        }

        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->sort('created_at', 'DESC')
            ->range(0, $limit)
            ->execute();

        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }

    public function loadAllCommunities(): array
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->execute();
        return $ids !== [] ? array_values($storage->loadMultiple($ids)) : [];
    }
}

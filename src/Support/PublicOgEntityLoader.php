<?php

declare(strict_types=1);

namespace App\Support;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Loads entities for Open Graph image URLs using the same public visibility
 * rules as the corresponding HTML detail controllers.
 */
final class PublicOgEntityLoader
{
    public static function loadBusiness(EntityTypeManager $entityTypeManager, string $slug): ?EntityInterface
    {
        if ($slug === '') {
            return null;
        }

        $storage = $entityTypeManager->getStorage('group');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('type', 'business')
            ->condition('status', 1)
            ->execute();
        $business = $ids !== [] ? $storage->load(reset($ids)) : null;

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

        return $business;
    }

    public static function loadEvent(EntityTypeManager $entityTypeManager, string $slug): ?EntityInterface
    {
        if ($slug === '') {
            return null;
        }

        $storage = $entityTypeManager->getStorage('event');
        $ids = $storage->getQuery()
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->execute();
        $event = $ids !== [] ? $storage->load(reset($ids)) : null;

        if ($event !== null) {
            $mediaId = $event->get('media_id');
            if ($mediaId !== null && $mediaId !== '') {
                $status = $event->get('copyright_status');
                if (!in_array($status, ['community_owned', 'cc_by_nc_sa'], true)) {
                    $event = null;
                }
            }
        }

        return $event;
    }

    public static function loadTeaching(EntityTypeManager $entityTypeManager, string $slug): ?EntityInterface
    {
        if ($slug === '') {
            return null;
        }

        $storage = $entityTypeManager->getStorage('teaching');
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

        return $teaching;
    }
}

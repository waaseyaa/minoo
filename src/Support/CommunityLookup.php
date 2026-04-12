<?php

declare(strict_types=1);

namespace App\Support;

use Waaseyaa\Entity\EntityTypeManager;

final class CommunityLookup
{
    /**
     * Build a lookup map of community names and slugs keyed by community ID.
     *
     * @param list<\Waaseyaa\Entity\EntityInterface> $entities
     * @return array<string, array{name: string, slug: string}>
     */
    public static function build(EntityTypeManager $entityTypeManager, array $entities): array
    {
        $communityIds = array_filter(array_unique(array_map(
            fn ($e) => $e->get('community_id'),
            $entities
        )));

        if ($communityIds === []) {
            return [];
        }

        $communityStorage = $entityTypeManager->getStorage('community');
        $communities = $communityStorage->loadMultiple($communityIds);
        $lookup = [];
        foreach ($communities as $community) {
            $lookup[(string) $community->id()] = [
                'name' => $community->get('name') ?? $community->label(),
                'slug' => $community->get('slug'),
            ];
        }

        return $lookup;
    }
}

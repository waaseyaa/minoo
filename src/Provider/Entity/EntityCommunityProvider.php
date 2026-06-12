<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Entity\Editorial\FeaturedItem;
use App\Entity\Language\DialectRegion;
use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityType;

/**
 * Editorial + dialect regions.
 *
 * Language-platform slimming (2026-06): community, resource_person,
 * elder_support_request, and volunteer entity types de-registered;
 * their tables stay dormant in the database.
 */
final class EntityCommunityProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
        // =====================================================================
        // --- Featured Items ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'featured_item',
            label: 'Featured Item',
            class: FeaturedItem::class,
            keys: ['id' => 'fid', 'uuid' => 'uuid', 'label' => 'headline'],
            group: 'editorial',
            _fieldDefinitions: [
                'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'description' => 'Referenced entity type (dictionary_entry, word_part, speaker).', 'weight' => 1],
                'entity_id' => ['type' => 'integer', 'label' => 'Entity ID', 'description' => 'Referenced entity ID.', 'weight' => 2],
                'headline' => ['type' => 'string', 'label' => 'Headline', 'description' => 'Display headline (overrides entity title when set).', 'weight' => 3],
                'subheadline' => ['type' => 'string', 'label' => 'Subheadline', 'description' => 'Optional subtitle or context line.', 'weight' => 4],
                'weight' => ['type' => 'integer', 'label' => 'Weight', 'description' => 'Sort order (higher = more prominent).', 'default' => 0, 'weight' => 10],
                'starts_at' => ['type' => 'datetime', 'label' => 'Starts At', 'description' => 'When this item begins appearing.', 'weight' => 20],
                'ends_at' => ['type' => 'datetime', 'label' => 'Ends At', 'description' => 'When this item stops appearing.', 'weight' => 21],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'default' => 1, 'weight' => 30],
            ],
        ));

        // =====================================================================
        // --- Dialect Regions ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'dialect_region',
            label: 'Dialect Region',
            class: DialectRegion::class,
            keys: ['id' => 'code', 'label' => 'name'],
            group: 'language',
        ));
    }
}

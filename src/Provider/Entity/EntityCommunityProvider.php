<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Entity\Community\Community;
use App\Entity\Editorial\FeaturedItem;
use App\Entity\ElderSupport\ElderSupportRequest;
use App\Entity\Language\DialectRegion;
use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityType;

/**
 * Editorial + dialect regions + elder-support requests.
 *
 * Language-platform slimming (2026-06): community, resource_person, and
 * volunteer entity types stay de-registered (tables dormant). elder_support_request
 * was re-registered for the coordinator triage workflow (#801).
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

        // =====================================================================
        // --- Community graph: the 646-row community table (#815) ---
        // Public institutional data (no consent gate). The authoritative geo
        // index for "location as vantage point"; the curated MamaweswenNations
        // (#60) layers on top for the seven nations' detail pages.
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'community',
            label: 'Community',
            class: Community::class,
            keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'communities',
            _fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'community_type' => ['type' => 'string', 'label' => 'Community Type', 'weight' => 5],
                'municipality_type' => ['type' => 'string', 'label' => 'Municipality Type', 'weight' => 6],
                'province' => ['type' => 'string', 'label' => 'Province', 'weight' => 10],
                'region' => ['type' => 'string', 'label' => 'Region', 'weight' => 11],
                'latitude' => ['type' => 'float', 'label' => 'Latitude', 'weight' => 15],
                'longitude' => ['type' => 'float', 'label' => 'Longitude', 'weight' => 16],
                'population' => ['type' => 'integer', 'label' => 'Population', 'weight' => 20],
                'population_year' => ['type' => 'integer', 'label' => 'Population Year', 'weight' => 21],
                'nation' => ['type' => 'string', 'label' => 'Nation/Linguistic Group', 'weight' => 25],
                'treaty' => ['type' => 'string', 'label' => 'Treaty', 'weight' => 26],
                'reserve_name' => ['type' => 'string', 'label' => 'Reserve Name', 'weight' => 27],
                'language_group' => ['type' => 'string', 'label' => 'Language Group', 'weight' => 30],
                'website' => ['type' => 'string', 'label' => 'Website', 'weight' => 35],
                'inac_id' => ['type' => 'string', 'label' => 'INAC Band Number', 'weight' => 40],
                'statcan_csd' => ['type' => 'string', 'label' => 'StatsCan CSD Code', 'weight' => 41],
                'nc_id' => ['type' => 'string', 'label' => 'NorthCloud ID', 'weight' => 42],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 50, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 60],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 61],
            ],
        ));

        // =====================================================================
        // --- Elder-support requests (#801) ---
        // Coordinator triage workflow; reads gated to coordinators/admins.
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'elder_support_request',
            label: 'Elder Support Request',
            class: ElderSupportRequest::class,
            keys: ['id' => 'esrid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'community',
            _fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'description' => 'Name of the person needing support.', 'weight' => 1],
                'community' => ['type' => 'string', 'label' => 'Community', 'description' => 'Nation/community slug.', 'weight' => 2],
                'support_type' => ['type' => 'string', 'label' => 'Support type', 'description' => 'Kind of support requested.', 'weight' => 3],
                'message' => ['type' => 'text_long', 'label' => 'Message', 'description' => 'Details of the request.', 'weight' => 4],
                'contact' => ['type' => 'string', 'label' => 'Contact', 'description' => 'How to reach the requester.', 'weight' => 5],
                'status' => ['type' => 'string', 'label' => 'Status', 'description' => 'open | in_progress | closed.', 'default' => 'open', 'weight' => 6],
                'assigned_to' => ['type' => 'integer', 'label' => 'Assigned coordinator', 'description' => 'User id of the coordinator handling this.', 'weight' => 7],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 20],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 21],
            ],
        ));
    }
}

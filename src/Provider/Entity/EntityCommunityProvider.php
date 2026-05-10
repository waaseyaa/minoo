<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Entity\Community\Community;
use App\Entity\Community\ResourcePerson;
use App\Entity\Community\Volunteer;
use App\Entity\Editorial\FeaturedItem;
use App\Entity\ElderSupport\ElderSupportRequest;
use App\Entity\Language\DialectRegion;
use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityType;

final class EntityCommunityProvider extends AppCoreServiceProvider
{
    public function register(): void
    {
        // =====================================================================
        // --- People ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'resource_person',
            label: 'Resource Person',
            class: ResourcePerson::class,
            keys: ['id' => 'rpid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'people',
            _fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 5],
                'community' => ['type' => 'string', 'label' => 'Community', 'description' => 'Community affiliation (e.g. Sagamok Anishnawbek).', 'weight' => 10],
                'roles' => ['type' => 'entity_reference', 'label' => 'Roles', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'person_roles'], 'cardinality' => -1, 'weight' => 15],
                'offerings' => ['type' => 'entity_reference', 'label' => 'Offerings', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'person_offerings'], 'cardinality' => -1, 'weight' => 16],
                'email' => ['type' => 'string', 'label' => 'Email', 'weight' => 20],
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 21],
                'business_name' => ['type' => 'string', 'label' => 'Business Name', 'weight' => 25],
                'website' => ['type' => 'string', 'label' => 'Website', 'weight' => 26],
                'linked_group_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Linked Business',
                    'description' => 'The business group this person is associated with.',
                    'settings' => ['target_type' => 'group'],
                    'weight' => 27,
                ],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo', 'settings' => ['target_type' => 'media'], 'weight' => 28],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'consent_public' => [
                    'type' => 'boolean',
                    'label' => 'Public Consent',
                    'description' => 'Whether this content may be shown on public pages.',
                    'weight' => 28,
                    'default' => 1,
                ],
                'consent_ai_training' => [
                    'type' => 'boolean',
                    'label' => 'AI Training Consent',
                    'description' => 'Whether this content may be used for AI training. Default: no.',
                    'weight' => 29,
                    'default' => 0,
                ],
                'source' => [
                    'type' => 'string',
                    'label' => 'Source',
                    'description' => 'Provenance tag (e.g. manual:russell:2026-03-15).',
                    'weight' => 95,
                ],
                'verified_at' => [
                    'type' => 'datetime',
                    'label' => 'Verified At',
                    'description' => 'When this record was last verified.',
                    'weight' => 96,
                ],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        // =====================================================================
        // --- Elder Support ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'elder_support_request',
            label: 'Elder Support Request',
            class: ElderSupportRequest::class,
            keys: ['id' => 'esrid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'elders',
            _fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 1],
                'community' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 5],
                'type' => ['type' => 'string', 'label' => 'Request Type', 'weight' => 10],
                'notes' => ['type' => 'text_long', 'label' => 'Notes', 'weight' => 15],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 20, 'default' => 'open'],
                'assigned_volunteer' => [
                    'type' => 'integer',
                    'label' => 'Assigned Volunteer',
                    'description' => 'ID of the assigned volunteer entity.',
                    'weight' => 25,
                ],
                'assigned_at' => [
                    'type' => 'timestamp',
                    'label' => 'Assigned At',
                    'weight' => 26,
                ],
                'completion_notes' => ['type' => 'text_long', 'label' => 'Completion Notes', 'weight' => 28],
                'cancelled_reason' => ['type' => 'text_long', 'label' => 'Cancellation Reason', 'weight' => 30],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'volunteer',
            label: 'Volunteer',
            class: Volunteer::class,
            keys: ['id' => 'vid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'elders',
            _fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 1],
                'community' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 3],
                'availability' => ['type' => 'string', 'label' => 'Availability', 'weight' => 5],
                'skills' => ['type' => 'entity_reference', 'label' => 'Skills', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'volunteer_skills'], 'cardinality' => -1, 'weight' => 10],
                'max_travel_km' => ['type' => 'integer', 'label' => 'Max Travel (km)', 'weight' => 12],
                'account_id' => ['type' => 'integer', 'label' => 'Account ID', 'weight' => 35],
                'notes' => ['type' => 'text_long', 'label' => 'Notes', 'weight' => 15],
                'status' => ['type' => 'string', 'label' => 'Status', 'weight' => 20, 'default' => 'active'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));

        // =====================================================================
        // --- Community ---
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
        // --- Featured Items ---
        // =====================================================================

        $this->entityType(new EntityType(
            id: 'featured_item',
            label: 'Featured Item',
            class: FeaturedItem::class,
            keys: ['id' => 'fid', 'uuid' => 'uuid', 'label' => 'headline'],
            group: 'editorial',
            _fieldDefinitions: [
                'entity_type' => ['type' => 'string', 'label' => 'Entity Type', 'description' => 'Referenced entity type (event, teaching, group, resource_person).', 'weight' => 1],
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

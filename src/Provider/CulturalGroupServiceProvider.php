<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\CulturalGroup;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class CulturalGroupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'cultural_group',
            label: 'Cultural Group',
            class: CulturalGroup::class,
            keys: ['id' => 'cgid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'community',
            fieldDefinitions: [
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'parent_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Parent Group',
                    'description' => 'Self-referential hierarchy.',
                    'settings' => ['target_type' => 'cultural_group'],
                    'weight' => 5,
                ],
                'depth_label' => [
                    'type' => 'string',
                    'label' => 'Depth Label',
                    'description' => 'Free-text depth descriptor (nation, tribe, band, clan).',
                    'weight' => 6,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'weight' => 10,
                ],
                'metadata' => [
                    'type' => 'text',
                    'label' => 'Metadata',
                    'description' => 'JSON blob for extensible properties.',
                    'weight' => 15,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
                    'settings' => ['target_type' => 'media'],
                    'weight' => 20,
                ],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 99,
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'label' => 'Sort Order',
                    'description' => 'Manual ordering within siblings.',
                    'weight' => 25,
                    'default' => 0,
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
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 30,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));
    }
}

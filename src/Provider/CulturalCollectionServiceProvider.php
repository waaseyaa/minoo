<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\CulturalCollection;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class CulturalCollectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'cultural_collection',
            label: 'Cultural Collection',
            class: CulturalCollection::class,
            keys: ['id' => 'ccid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'knowledge',
            fieldDefinitions: [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'description' => 'Cultural context and significance.',
                    'weight' => 5,
                ],
                'gallery' => [
                    'type' => 'entity_reference',
                    'label' => 'Gallery',
                    'description' => 'Gallery category (taxonomy term).',
                    'settings' => ['target_type' => 'taxonomy_term'],
                    'weight' => 10,
                ],
                'source_url' => [
                    'type' => 'uri',
                    'label' => 'Source URL',
                    'description' => 'Original URL from ojibwe.lib.umn.edu.',
                    'weight' => 15,
                ],
                'source_attribution' => [
                    'type' => 'string',
                    'label' => 'Source Attribution',
                    'weight' => 16,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Primary Image',
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

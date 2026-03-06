<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\CulturalCollection;
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

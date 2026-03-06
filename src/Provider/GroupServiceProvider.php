<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Group;
use Minoo\Entity\GroupType;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class GroupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'group',
            label: 'Community Group',
            class: Group::class,
            keys: ['id' => 'gid', 'uuid' => 'uuid', 'label' => 'name', 'bundle' => 'type'],
            fieldDefinitions: [
                'slug' => [
                    'type' => 'string',
                    'label' => 'URL Slug',
                    'weight' => 1,
                ],
                'description' => [
                    'type' => 'text_long',
                    'label' => 'Description',
                    'weight' => 5,
                ],
                'url' => [
                    'type' => 'uri',
                    'label' => 'Website',
                    'description' => 'External website URL.',
                    'weight' => 10,
                ],
                'region' => [
                    'type' => 'string',
                    'label' => 'Region',
                    'description' => 'Geographic region.',
                    'weight' => 15,
                ],
                'media_id' => [
                    'type' => 'entity_reference',
                    'label' => 'Image',
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

        $this->entityType(new EntityType(
            id: 'group_type',
            label: 'Group Type',
            class: GroupType::class,
            keys: ['id' => 'type', 'label' => 'name'],
        ));
    }
}

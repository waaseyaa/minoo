<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\ResourcePerson;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'resource_person',
            label: 'Resource Person',
            class: ResourcePerson::class,
            keys: ['id' => 'rpid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'people',
            fieldDefinitions: [
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 5],
                'community' => ['type' => 'string', 'label' => 'Community', 'description' => 'Community affiliation (e.g. Sagamok Anishnawbek).', 'weight' => 10],
                'roles' => ['type' => 'entity_reference', 'label' => 'Roles', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'person_roles'], 'cardinality' => -1, 'weight' => 15],
                'offerings' => ['type' => 'entity_reference', 'label' => 'Offerings', 'settings' => ['target_type' => 'taxonomy_term', 'target_vocabulary' => 'person_offerings'], 'cardinality' => -1, 'weight' => 16],
                'email' => ['type' => 'string', 'label' => 'Email', 'weight' => 20],
                'phone' => ['type' => 'string', 'label' => 'Phone', 'weight' => 21],
                'business_name' => ['type' => 'string', 'label' => 'Business Name', 'weight' => 25],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo', 'settings' => ['target_type' => 'media'], 'weight' => 28],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));
    }
}

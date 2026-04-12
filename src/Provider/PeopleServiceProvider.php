<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\ResourcePerson;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

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
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'people.list',
            RouteBuilder::create('/people')
                ->controller('App\Controller\PeopleController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'people.show',
            RouteBuilder::create('/people/{slug}')
                ->controller('App\Controller\PeopleController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}

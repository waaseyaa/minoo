<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Contributor;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class ContributorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'contributor',
            label: 'Contributor',
            class: Contributor::class,
            keys: ['id' => 'coid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'contributor',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'code' => ['type' => 'string', 'label' => 'Speaker Code', 'description' => 'Abbreviation (e.g., es, nj, gh).', 'weight' => 5],
                'bio' => ['type' => 'text_long', 'label' => 'Biography', 'weight' => 10],
                'role' => ['type' => 'string', 'label' => 'Role', 'description' => 'Contributor role: speaker, storyteller, elder, translator.', 'weight' => 12],
                'community_id' => ['type' => 'entity_reference', 'label' => 'Community', 'settings' => ['target_type' => 'community'], 'weight' => 15],
                'media_id' => ['type' => 'entity_reference', 'label' => 'Photo', 'settings' => ['target_type' => 'media'], 'weight' => 20],
                'copyright_status' => [
                    'type' => 'string',
                    'label' => 'Copyright Status',
                    'description' => 'Media copyright status: community_owned, cc_by_nc_sa, requires_permission, unknown.',
                    'default_value' => 'unknown',
                    'weight' => 25,
                ],
                'consent_public' => ['type' => 'boolean', 'label' => 'Public Consent', 'description' => 'Whether this contributor may be shown on public pages.', 'weight' => 28, 'default' => 0],
                'consent_record' => ['type' => 'boolean', 'label' => 'Recording Consent', 'description' => 'Whether this contributor consents to being recorded.', 'weight' => 29, 'default' => 0],
                'status' => ['type' => 'boolean', 'label' => 'Published', 'weight' => 30, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 40],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 41],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'contributors.list',
            RouteBuilder::create('/contributors')
                ->controller('App\\Controller\\ContributorController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'contributors.show',
            RouteBuilder::create('/contributors/{slug}')
                ->controller('App\\Controller\\ContributorController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );
    }
}

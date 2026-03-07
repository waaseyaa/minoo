<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Community;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class CommunityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'community',
            label: 'Community',
            class: Community::class,
            keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'name'],
            group: 'community',
            fieldDefinitions: [
                'community_type' => [
                    'type' => 'string',
                    'label' => 'Community Type',
                    'description' => 'first_nation, town, or region.',
                    'weight' => 1,
                ],
                'latitude' => [
                    'type' => 'float',
                    'label' => 'Latitude',
                    'weight' => 10,
                ],
                'longitude' => [
                    'type' => 'float',
                    'label' => 'Longitude',
                    'weight' => 11,
                ],
                'population' => [
                    'type' => 'integer',
                    'label' => 'Population',
                    'weight' => 15,
                ],
                'external_ids' => [
                    'type' => 'json',
                    'label' => 'External IDs',
                    'weight' => 20,
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

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'community.autocomplete',
            RouteBuilder::create('/api/communities/autocomplete')
                ->controller('Minoo\Controller\CommunityController::autocomplete')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}

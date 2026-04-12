<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Community;
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
            group: 'communities',
            fieldDefinitions: [
                'name' => ['type' => 'string', 'label' => 'Name', 'weight' => 0],
                'slug' => ['type' => 'string', 'label' => 'URL Slug', 'weight' => 1],
                'community_type' => ['type' => 'string', 'label' => 'Community Type', 'weight' => 5],
                'municipality_type' => ['type' => 'string', 'label' => 'Municipality Type', 'weight' => 6],
                'is_municipality' => ['type' => 'boolean', 'label' => 'Is Municipality', 'weight' => 7, 'default' => 0],
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
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'communities.list',
            RouteBuilder::create('/communities')
                ->controller('App\\Controller\\CommunityController::list')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'communities.show',
            RouteBuilder::create('/communities/{slug}')
                ->controller('App\\Controller\\CommunityController::show')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->requirement('slug', '[a-z0-9][a-z0-9-]*[a-z0-9]')
                ->build(),
        );

        $router->addRoute(
            'communities.autocomplete',
            RouteBuilder::create('/api/communities/autocomplete')
                ->controller('App\\Controller\\CommunityController::autocomplete')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'location.current',
            RouteBuilder::create('/api/location/current')
                ->controller('App\\Controller\\LocationController::current')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'location.set',
            RouteBuilder::create('/api/location/set')
                ->controller('App\\Controller\\LocationController::set')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'location.update',
            RouteBuilder::create('/api/location/update')
                ->controller('App\\Controller\\LocationController::update')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

    }
}

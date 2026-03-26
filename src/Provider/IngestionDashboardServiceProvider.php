<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class IngestionDashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types.
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'admin.ingestion',
            RouteBuilder::create('/admin/ingestion')
                ->controller('Minoo\Controller\IngestionDashboardController::index')
                ->requirePermission('administer content')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.status',
            RouteBuilder::create('/api/admin/nc-sync-status')
                ->controller('Minoo\Controller\IngestionApiController::status')
                ->requirePermission('administer content')
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.envelope',
            RouteBuilder::create('/api/ingestion/envelope')
                ->controller('Minoo\Controller\IngestionApiController::ingestEnvelope')
                ->requirePermission('administer content')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.approve',
            RouteBuilder::create('/api/admin/ingestion/{id}/approve')
                ->controller('Minoo\Controller\IngestionApiController::approve')
                ->requirePermission('administer content')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.reject',
            RouteBuilder::create('/api/admin/ingestion/{id}/reject')
                ->controller('Minoo\Controller\IngestionApiController::reject')
                ->requirePermission('administer content')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'admin.ingestion.materialize',
            RouteBuilder::create('/api/admin/ingestion/{id}/materialize')
                ->controller('Minoo\Controller\IngestionApiController::materialize')
                ->requirePermission('administer content')
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );
    }
}

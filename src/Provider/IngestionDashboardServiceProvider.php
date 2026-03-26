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
    }
}

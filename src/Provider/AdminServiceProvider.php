<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\AdminSurface\AdminSurfaceServiceProvider;
use Waaseyaa\AdminSurface\Host\GenericAdminSurfaceHost;
use Waaseyaa\Api\Schema\SchemaPresenter;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        if ($entityTypeManager === null) {
            return;
        }

        $host = new GenericAdminSurfaceHost(
            entityTypeManager: $entityTypeManager,
            schemaPresenter: new SchemaPresenter(),
            tenantId: 'minoo',
            tenantName: 'Minoo',
            readOnlyTypes: ['ingest_log'],
        );

        AdminSurfaceServiceProvider::registerRoutes($router, $host);

        $router->addRoute(
            'admin.spa',
            RouteBuilder::create('/admin')
                ->controller('App\\Controller\\AdminController::spa')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.spa.catchall',
            RouteBuilder::create('/admin/{path}')
                ->controller('App\\Controller\\AdminController::spa')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('path', '.+')
                ->build(),
        );
    }
}

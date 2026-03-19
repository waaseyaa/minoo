<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Surface\MinooSurfaceHost;
use Waaseyaa\AdminSurface\AdminSurfaceServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Host is constructed in routes() where EntityTypeManager is available.
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        if ($entityTypeManager === null) {
            return;
        }

        $host = new MinooSurfaceHost($entityTypeManager);

        AdminSurfaceServiceProvider::registerRoutes($router, $host);
    }
}

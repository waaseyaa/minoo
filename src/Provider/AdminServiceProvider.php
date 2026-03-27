<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Surface\MinooSurfaceHost;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\AdminSurface\AdminSurfaceServiceProvider;
use Waaseyaa\Api\Schema\SchemaPresenter;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Kernel\Bootstrap\AccessPolicyRegistry;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AdminServiceProvider extends ServiceProvider
{
    private ?EntityAccessHandler $adminSurfaceAccessHandler = null;

    public function register(): void
    {
        // Host is constructed in routes() where EntityTypeManager is available.
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        if ($entityTypeManager === null) {
            return;
        }

        $host = new MinooSurfaceHost(
            $entityTypeManager,
            $this->adminSurfaceAccessHandler(),
            new SchemaPresenter(),
        );

        AdminSurfaceServiceProvider::registerRoutes($router, $host);

        $router->addRoute(
            'admin.spa',
            RouteBuilder::create('/admin')
                ->controller('Minoo\\Controller\\AdminController::spa')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.spa.catchall',
            RouteBuilder::create('/admin/{path}')
                ->controller('Minoo\\Controller\\AdminController::spa')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('path', '.+')
                ->build(),
        );
    }

    private function adminSurfaceAccessHandler(): ?EntityAccessHandler
    {
        if ($this->adminSurfaceAccessHandler !== null) {
            return $this->adminSurfaceAccessHandler;
        }

        $path = $this->projectRoot . '/storage/framework/packages.php';
        if (!is_file($path)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = require $path;
            $manifest = PackageManifest::fromArray($data);
        } catch (\Throwable) {
            return null;
        }

        $registry = new AccessPolicyRegistry(new NullLogger());
        $this->adminSurfaceAccessHandler = $registry->discover($manifest);

        return $this->adminSurfaceAccessHandler;
    }
}

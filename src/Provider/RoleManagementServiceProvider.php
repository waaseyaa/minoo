<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class RoleManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types.
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'dashboard.coordinator.users',
            RouteBuilder::create('/dashboard/coordinator/users')
                ->controller('Minoo\Controller\RoleManagementController::coordinatorList')
                ->requireRole('elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'admin.users',
            RouteBuilder::create('/admin/users')
                ->controller('Minoo\Controller\RoleManagementController::adminList')
                ->requireRole('admin')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.users.roles',
            RouteBuilder::create('/api/users/{uid}/roles')
                ->controller('Minoo\Controller\RoleManagementController::changeRole')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );
    }
}

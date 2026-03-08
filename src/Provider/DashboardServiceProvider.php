<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types.
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'dashboard.volunteer',
            RouteBuilder::create('/dashboard/volunteer')
                ->controller('Minoo\Controller\VolunteerDashboardController::index')
                ->requireRole('volunteer')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.volunteer.edit',
            RouteBuilder::create('/dashboard/volunteer/edit')
                ->controller('Minoo\Controller\VolunteerDashboardController::editForm')
                ->requireRole('volunteer')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.volunteer.edit.submit',
            RouteBuilder::create('/dashboard/volunteer/edit')
                ->controller('Minoo\Controller\VolunteerDashboardController::submitEdit')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'dashboard.volunteer.toggle',
            RouteBuilder::create('/dashboard/volunteer/toggle-availability')
                ->controller('Minoo\Controller\VolunteerDashboardController::toggleAvailability')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator',
            RouteBuilder::create('/dashboard/coordinator')
                ->controller('Minoo\Controller\CoordinatorDashboardController::index')
                ->requireRole('elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}

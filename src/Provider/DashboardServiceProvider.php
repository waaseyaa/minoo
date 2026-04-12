<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types.
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'dashboard.volunteer',
            RouteBuilder::create('/dashboard/volunteer')
                ->controller('App\Controller\VolunteerDashboardController::index')
                ->requireRole('volunteer')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.volunteer.edit',
            RouteBuilder::create('/dashboard/volunteer/edit')
                ->controller('App\Controller\VolunteerDashboardController::editForm')
                ->requireRole('volunteer')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.volunteer.edit.submit',
            RouteBuilder::create('/dashboard/volunteer/edit')
                ->controller('App\Controller\VolunteerDashboardController::submitEdit')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'dashboard.volunteer.toggle',
            RouteBuilder::create('/dashboard/volunteer/toggle-availability')
                ->controller('App\Controller\VolunteerDashboardController::toggleAvailability')
                ->requireRole('volunteer')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator',
            RouteBuilder::create('/dashboard/coordinator')
                ->controller('App\Controller\CoordinatorDashboardController::index')
                ->requireRole('elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator.applications',
            RouteBuilder::create('/dashboard/coordinator/applications')
                ->controller('App\Controller\CoordinatorDashboardController::applications')
                ->requireRole('elder_coordinator')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator.applications.approve',
            RouteBuilder::create('/dashboard/coordinator/applications/{uuid}/approve')
                ->controller('App\Controller\CoordinatorDashboardController::approveApplication')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'dashboard.coordinator.applications.deny',
            RouteBuilder::create('/dashboard/coordinator/applications/{uuid}/deny')
                ->controller('App\Controller\CoordinatorDashboardController::denyApplication')
                ->requireRole('elder_coordinator')
                ->methods('POST')
                ->build(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class PublicAccountRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Account ---
        // =====================================================================

        $router->addRoute(
            'account.home',
            RouteBuilder::create('/account')
                ->controller('App\Http\Controller\Account\AccountHomeController::index')
                ->requireAuthentication()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'account.elder_toggle',
            RouteBuilder::create('/account/elder-toggle')
                ->controller('App\Http\Controller\Account\AccountHomeController::toggleElder')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        // =====================================================================
        // --- Elder-support request workflow (#801) ---
        // Authenticated; the triage inbox gates further to coordinators/admins.
        // Not in public nav until a staffed coordinator program exists.
        // =====================================================================

        $router->addRoute(
            'elder_support.requests',
            RouteBuilder::create('/elder-support/requests')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportController::inbox')
                ->requireAuthentication()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elder_support.form',
            RouteBuilder::create('/elder-support')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportController::form')
                ->requireAuthentication()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'elder_support.submit',
            RouteBuilder::create('/elder-support')
                ->controller('App\Http\Controller\ElderSupport\ElderSupportController::submit')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );
    }
}

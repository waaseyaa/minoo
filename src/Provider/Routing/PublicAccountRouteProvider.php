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
    }
}

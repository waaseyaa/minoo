<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AccountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types.
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'account.home',
            RouteBuilder::create('/account')
                ->controller('App\Controller\AccountHomeController::index')
                ->requireAuthentication()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'account.elder_toggle',
            RouteBuilder::create('/account/elder-toggle')
                ->controller('App\Controller\AccountHomeController::toggleElder')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );
    }
}

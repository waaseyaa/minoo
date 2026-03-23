<?php

declare(strict_types=1);

namespace Minoo\Provider;

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
                ->controller('Minoo\Controller\AccountHomeController::index')
                ->requireAuthentication()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'account.elder_toggle',
            RouteBuilder::create('/account/elder-toggle')
                ->controller('Minoo\Controller\AccountHomeController::toggleElder')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );
    }
}

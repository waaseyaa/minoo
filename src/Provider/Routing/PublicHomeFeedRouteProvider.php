<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Language-platform slimming (2026-06): feed and explore routes removed;
 * the homepage serves everyone.
 */
final class PublicHomeFeedRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'home.index',
            RouteBuilder::create('/')
                ->controller('App\\Http\\Controller\\Home\\HomeController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'home.alias',
            RouteBuilder::create('/home')
                ->controller('App\\Http\\Controller\\Home\\HomeController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}

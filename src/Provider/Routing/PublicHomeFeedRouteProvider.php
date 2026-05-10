<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class PublicHomeFeedRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Homepage ---
        // =====================================================================

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

        // =====================================================================
        // --- Feed ---
        // =====================================================================

        $router->addRoute(
            'feed.page',
            RouteBuilder::create('/feed')
                ->controller('App\\Http\\Controller\\Feed\\FeedController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'feed.api',
            RouteBuilder::create('/api/feed')
                ->controller('App\\Http\\Controller\\Feed\\FeedController::api')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explore.redirect',
            RouteBuilder::create('/explore')
                ->controller('App\\Http\\Controller\\Feed\\FeedController::explore')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

    }
}

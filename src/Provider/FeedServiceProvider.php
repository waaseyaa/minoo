<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class FeedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No entity types — this provider handles feed services and routes only
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'feed.index',
            RouteBuilder::create('/')
                ->controller('Minoo\\Controller\\FeedController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'feed.api',
            RouteBuilder::create('/api/feed')
                ->controller('Minoo\\Controller\\FeedController::api')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explore.redirect',
            RouteBuilder::create('/explore')
                ->controller('Minoo\\Controller\\FeedController::explore')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'home.alias',
            RouteBuilder::create('/home')
                ->controller('Minoo\\Controller\\FeedController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}

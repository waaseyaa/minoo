<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Feed\EntityLoaderService;
use Minoo\Feed\FeedAssembler;
use Minoo\Feed\FeedAssemblerInterface;
use Minoo\Feed\FeedItemFactory;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class FeedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(EntityLoaderService::class, fn(): EntityLoaderService => new EntityLoaderService(
            $this->resolve(EntityTypeManager::class),
        ));

        $this->singleton(FeedItemFactory::class, fn(): FeedItemFactory => new FeedItemFactory());

        $this->singleton(FeedAssemblerInterface::class, fn(): FeedAssemblerInterface => new FeedAssembler(
            $this->resolve(EntityLoaderService::class),
            $this->resolve(FeedItemFactory::class),
        ));
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

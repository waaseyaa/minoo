<?php

declare(strict_types=1);

namespace App\Provider;

use App\Feed\EntityLoaderService;
use App\Feed\FeedAssembler;
use App\Feed\FeedAssemblerInterface;
use App\Feed\FeedItemFactory;
use App\Feed\Scoring\FeedScorer;
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
            null,
            $this->resolve(FeedScorer::class),
        ));
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'feed.index',
            RouteBuilder::create('/')
                ->controller('App\\Controller\\FeedController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'feed.api',
            RouteBuilder::create('/api/feed')
                ->controller('App\\Controller\\FeedController::api')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explore.redirect',
            RouteBuilder::create('/explore')
                ->controller('App\\Controller\\FeedController::explore')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'home.alias',
            RouteBuilder::create('/home')
                ->controller('App\\Controller\\FeedController::index')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}

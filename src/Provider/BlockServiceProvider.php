<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // user_block entity type is registered by framework UserServiceProvider.
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute('blocks.index', RouteBuilder::create('/api/blocks')
            ->controller('Minoo\\Controller\\BlockController::index')
            ->requireAuthentication()
            ->methods('GET')
            ->build());

        $router->addRoute('blocks.store', RouteBuilder::create('/api/blocks')
            ->controller('Minoo\\Controller\\BlockController::store')
            ->requireAuthentication()
            ->methods('POST')
            ->build());

        $router->addRoute('blocks.delete', RouteBuilder::create('/api/blocks/{user_id}')
            ->controller('Minoo\\Controller\\BlockController::delete')
            ->requireAuthentication()
            ->methods('DELETE')
            ->requirement('user_id', '\\d+')
            ->build());
    }
}

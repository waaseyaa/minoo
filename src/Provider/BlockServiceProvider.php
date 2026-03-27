<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\UserBlock;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'user_block',
            label: 'User Block',
            class: UserBlock::class,
            keys: ['id' => 'ubid', 'uuid' => 'uuid', 'label' => 'blocker_id'],
            group: 'messaging',
            fieldDefinitions: [
                'blocker_id' => ['type' => 'integer', 'label' => 'Blocker ID', 'weight' => 0],
                'blocked_id' => ['type' => 'integer', 'label' => 'Blocked ID', 'weight' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
            ],
        ));
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

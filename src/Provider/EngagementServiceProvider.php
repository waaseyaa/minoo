<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\Post;
use Waaseyaa\Media\UploadHandler;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class EngagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // reaction, comment, follow entity types are registered by framework EngagementServiceProvider.

        $this->entityType(new EntityType(
            id: 'post',
            label: 'Post',
            class: Post::class,
            keys: ['id' => 'pid', 'uuid' => 'uuid', 'label' => 'body'],
            group: 'engagement',
            fieldDefinitions: [
                'body' => [
                    'type' => 'text_long',
                    'label' => 'Body',
                    'weight' => 0,
                ],
                'user_id' => [
                    'type' => 'integer',
                    'label' => 'User ID',
                    'weight' => 1,
                ],
                'community_id' => [
                    'type' => 'integer',
                    'label' => 'Community ID',
                    'weight' => 2,
                ],
                'images' => [
                    'type' => 'text_long',
                    'label' => 'Images',
                    'weight' => 3,
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Published',
                    'weight' => 5,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 10,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 11,
                ],
            ],
        ));

        $this->singleton(UploadHandler::class, fn(): UploadHandler => new UploadHandler(
            dirname(__DIR__, 2) . '/storage/uploads',
        ));
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'engagement.react',
            RouteBuilder::create('/api/engagement/react')
                ->controller('App\\Controller\\EngagementController::react')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteReaction',
            RouteBuilder::create('/api/engagement/react/{id}')
                ->controller('App\\Controller\\EngagementController::deleteReaction')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.comment',
            RouteBuilder::create('/api/engagement/comment')
                ->controller('App\\Controller\\EngagementController::comment')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteComment',
            RouteBuilder::create('/api/engagement/comment/{id}')
                ->controller('App\\Controller\\EngagementController::deleteComment')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.getComments',
            RouteBuilder::create('/api/engagement/comments/{target_type}/{target_id}')
                ->controller('App\\Controller\\EngagementController::getComments')
                ->allowAll()
                ->methods('GET')
                ->requirement('target_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.follow',
            RouteBuilder::create('/api/engagement/follow')
                ->controller('App\\Controller\\EngagementController::follow')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteFollow',
            RouteBuilder::create('/api/engagement/follow/{id}')
                ->controller('App\\Controller\\EngagementController::deleteFollow')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.createPost',
            RouteBuilder::create('/api/engagement/post')
                ->controller('App\\Controller\\EngagementController::createPost')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deletePost',
            RouteBuilder::create('/api/engagement/post/{id}')
                ->controller('App\\Controller\\EngagementController::deletePost')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );
    }
}

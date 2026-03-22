<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\Comment;
use Minoo\Entity\Follow;
use Minoo\Entity\Post;
use Minoo\Entity\Reaction;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class EngagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'reaction',
            label: 'Reaction',
            class: Reaction::class,
            keys: ['id' => 'rid', 'uuid' => 'uuid', 'label' => 'reaction_type'],
            group: 'engagement',
            fieldDefinitions: [
                'reaction_type' => [
                    'type' => 'string',
                    'label' => 'Reaction Type',
                    'weight' => 0,
                ],
                'user_id' => [
                    'type' => 'integer',
                    'label' => 'User ID',
                    'weight' => 1,
                ],
                'target_type' => [
                    'type' => 'string',
                    'label' => 'Target Entity Type',
                    'weight' => 2,
                ],
                'target_id' => [
                    'type' => 'integer',
                    'label' => 'Target Entity ID',
                    'weight' => 3,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 10,
                ],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'comment',
            label: 'Comment',
            class: Comment::class,
            keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'body'],
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
                'target_type' => [
                    'type' => 'string',
                    'label' => 'Target Entity Type',
                    'weight' => 2,
                ],
                'target_id' => [
                    'type' => 'integer',
                    'label' => 'Target Entity ID',
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
            ],
        ));

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

        $this->entityType(new EntityType(
            id: 'follow',
            label: 'Follow',
            class: Follow::class,
            keys: ['id' => 'fid', 'uuid' => 'uuid', 'label' => 'target_type'],
            group: 'engagement',
            fieldDefinitions: [
                'user_id' => [
                    'type' => 'integer',
                    'label' => 'User ID',
                    'weight' => 0,
                ],
                'target_type' => [
                    'type' => 'string',
                    'label' => 'Target Entity Type',
                    'weight' => 1,
                ],
                'target_id' => [
                    'type' => 'integer',
                    'label' => 'Target Entity ID',
                    'weight' => 2,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 10,
                ],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'engagement.react',
            RouteBuilder::create('/api/engagement/react')
                ->controller('Minoo\\Controller\\EngagementController::react')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteReaction',
            RouteBuilder::create('/api/engagement/react/{id}')
                ->controller('Minoo\\Controller\\EngagementController::deleteReaction')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.comment',
            RouteBuilder::create('/api/engagement/comment')
                ->controller('Minoo\\Controller\\EngagementController::comment')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteComment',
            RouteBuilder::create('/api/engagement/comment/{id}')
                ->controller('Minoo\\Controller\\EngagementController::deleteComment')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.getComments',
            RouteBuilder::create('/api/engagement/comments/{target_type}/{target_id}')
                ->controller('Minoo\\Controller\\EngagementController::getComments')
                ->allowAll()
                ->methods('GET')
                ->requirement('target_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.follow',
            RouteBuilder::create('/api/engagement/follow')
                ->controller('Minoo\\Controller\\EngagementController::follow')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteFollow',
            RouteBuilder::create('/api/engagement/follow/{id}')
                ->controller('Minoo\\Controller\\EngagementController::deleteFollow')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.createPost',
            RouteBuilder::create('/api/engagement/post')
                ->controller('Minoo\\Controller\\EngagementController::createPost')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deletePost',
            RouteBuilder::create('/api/engagement/post/{id}')
                ->controller('Minoo\\Controller\\EngagementController::deletePost')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );
    }
}

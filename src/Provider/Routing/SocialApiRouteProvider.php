<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class SocialApiRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Chat ---
        // =====================================================================

        $router->addRoute(
            'chat.send',
            RouteBuilder::create('/api/chat')
                ->controller('App\Controller\ChatController::send')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // =====================================================================
        // --- Engagement ---
        // =====================================================================

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

        // =====================================================================
        // --- Messaging ---
        // =====================================================================

        $router->addRoute(
            'messaging.threads.index',
            RouteBuilder::create('/api/messaging/threads')
                ->controller('App\\Controller\\MessagingController::indexThreads')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.store',
            RouteBuilder::create('/api/messaging/threads')
                ->controller('App\\Controller\\MessagingController::createThread')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.show',
            RouteBuilder::create('/api/messaging/threads/{id}')
                ->controller('App\\Controller\\MessagingController::showThread')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.index',
            RouteBuilder::create('/api/messaging/threads/{id}/messages')
                ->controller('App\\Controller\\MessagingController::indexMessages')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.store',
            RouteBuilder::create('/api/messaging/threads/{id}/messages')
                ->controller('App\\Controller\\MessagingController::createMessage')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.participants.store',
            RouteBuilder::create('/api/messaging/threads/{id}/participants')
                ->controller('App\\Controller\\MessagingController::addParticipants')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.participants.delete',
            RouteBuilder::create('/api/messaging/threads/{id}/participants/{user_id}')
                ->controller('App\\Controller\\MessagingController::removeParticipant')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->requirement('user_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.users.search',
            RouteBuilder::create('/api/messaging/users')
                ->controller('App\\Controller\\MessagingController::searchUsers')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.edit',
            RouteBuilder::create('/api/messaging/threads/{id}/messages/{message_id}')
                ->controller('App\\Controller\\MessagingController::editMessage')
                ->requireAuthentication()
                ->methods('PATCH')
                ->requirement('id', '\\d+')
                ->requirement('message_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.delete',
            RouteBuilder::create('/api/messaging/threads/{id}/messages/{message_id}')
                ->controller('App\\Controller\\MessagingController::deleteMessage')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->requirement('message_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.read',
            RouteBuilder::create('/api/messaging/threads/{id}/read')
                ->controller('App\\Controller\\MessagingController::markRead')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.typing',
            RouteBuilder::create('/api/messaging/threads/{id}/typing')
                ->controller('App\\Controller\\MessagingController::typing')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.unread',
            RouteBuilder::create('/api/messaging/unread-count')
                ->controller('App\\Controller\\MessagingController::unreadCount')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.search',
            RouteBuilder::create('/api/messaging/search')
                ->controller('App\\Controller\\MessagingController::searchMessages')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // =====================================================================
        // --- Blocks ---
        // =====================================================================

        $router->addRoute('blocks.index', RouteBuilder::create('/api/blocks')
            ->controller('App\\Controller\\BlockController::index')
            ->requireAuthentication()
            ->methods('GET')
            ->build());

        $router->addRoute('blocks.store', RouteBuilder::create('/api/blocks')
            ->controller('App\\Controller\\BlockController::store')
            ->requireAuthentication()
            ->methods('POST')
            ->build());

        $router->addRoute('blocks.delete', RouteBuilder::create('/api/blocks/{user_id}')
            ->controller('App\\Controller\\BlockController::delete')
            ->requireAuthentication()
            ->methods('DELETE')
            ->requirement('user_id', '\\d+')
            ->build());
    }
}

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
                ->controller('App\Http\Controller\ChatController::send')
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
                ->controller('App\\Http\\Controller\\EngagementController::react')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteReaction',
            RouteBuilder::create('/api/engagement/react/{id}')
                ->controller('App\\Http\\Controller\\EngagementController::deleteReaction')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.comment',
            RouteBuilder::create('/api/engagement/comment')
                ->controller('App\\Http\\Controller\\EngagementController::comment')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteComment',
            RouteBuilder::create('/api/engagement/comment/{id}')
                ->controller('App\\Http\\Controller\\EngagementController::deleteComment')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.getComments',
            RouteBuilder::create('/api/engagement/comments/{target_type}/{target_id}')
                ->controller('App\\Http\\Controller\\EngagementController::getComments')
                ->allowAll()
                ->methods('GET')
                ->requirement('target_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.follow',
            RouteBuilder::create('/api/engagement/follow')
                ->controller('App\\Http\\Controller\\EngagementController::follow')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deleteFollow',
            RouteBuilder::create('/api/engagement/follow/{id}')
                ->controller('App\\Http\\Controller\\EngagementController::deleteFollow')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'engagement.createPost',
            RouteBuilder::create('/api/engagement/post')
                ->controller('App\\Http\\Controller\\EngagementController::createPost')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'engagement.deletePost',
            RouteBuilder::create('/api/engagement/post/{id}')
                ->controller('App\\Http\\Controller\\EngagementController::deletePost')
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
                ->controller('App\\Http\\Controller\\MessagingController::indexThreads')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.store',
            RouteBuilder::create('/api/messaging/threads')
                ->controller('App\\Http\\Controller\\MessagingController::createThread')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.show',
            RouteBuilder::create('/api/messaging/threads/{id}')
                ->controller('App\\Http\\Controller\\MessagingController::showThread')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.index',
            RouteBuilder::create('/api/messaging/threads/{id}/messages')
                ->controller('App\\Http\\Controller\\MessagingController::indexMessages')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.store',
            RouteBuilder::create('/api/messaging/threads/{id}/messages')
                ->controller('App\\Http\\Controller\\MessagingController::createMessage')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.participants.store',
            RouteBuilder::create('/api/messaging/threads/{id}/participants')
                ->controller('App\\Http\\Controller\\MessagingController::addParticipants')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.participants.delete',
            RouteBuilder::create('/api/messaging/threads/{id}/participants/{user_id}')
                ->controller('App\\Http\\Controller\\MessagingController::removeParticipant')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->requirement('user_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.users.search',
            RouteBuilder::create('/api/messaging/users')
                ->controller('App\\Http\\Controller\\MessagingController::searchUsers')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.edit',
            RouteBuilder::create('/api/messaging/threads/{id}/messages/{message_id}')
                ->controller('App\\Http\\Controller\\MessagingController::editMessage')
                ->requireAuthentication()
                ->methods('PATCH')
                ->requirement('id', '\\d+')
                ->requirement('message_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.delete',
            RouteBuilder::create('/api/messaging/threads/{id}/messages/{message_id}')
                ->controller('App\\Http\\Controller\\MessagingController::deleteMessage')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->requirement('message_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.read',
            RouteBuilder::create('/api/messaging/threads/{id}/read')
                ->controller('App\\Http\\Controller\\MessagingController::markRead')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.typing',
            RouteBuilder::create('/api/messaging/threads/{id}/typing')
                ->controller('App\\Http\\Controller\\MessagingController::typing')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.unread',
            RouteBuilder::create('/api/messaging/unread-count')
                ->controller('App\\Http\\Controller\\MessagingController::unreadCount')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.search',
            RouteBuilder::create('/api/messaging/search')
                ->controller('App\\Http\\Controller\\MessagingController::searchMessages')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // =====================================================================
        // --- Blocks ---
        // =====================================================================

        $router->addRoute('blocks.index', RouteBuilder::create('/api/blocks')
            ->controller('App\\Http\\Controller\\BlockController::index')
            ->requireAuthentication()
            ->methods('GET')
            ->build());

        $router->addRoute('blocks.store', RouteBuilder::create('/api/blocks')
            ->controller('App\\Http\\Controller\\BlockController::store')
            ->requireAuthentication()
            ->methods('POST')
            ->build());

        $router->addRoute('blocks.delete', RouteBuilder::create('/api/blocks/{user_id}')
            ->controller('App\\Http\\Controller\\BlockController::delete')
            ->requireAuthentication()
            ->methods('DELETE')
            ->requirement('user_id', '\\d+')
            ->build());
    }
}

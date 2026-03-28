<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Entity types and MercurePublisher are registered by framework providers.
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'messaging.threads.index',
            RouteBuilder::create('/api/messaging/threads')
                ->controller('Minoo\\Controller\\MessagingController::indexThreads')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.store',
            RouteBuilder::create('/api/messaging/threads')
                ->controller('Minoo\\Controller\\MessagingController::createThread')
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.show',
            RouteBuilder::create('/api/messaging/threads/{id}')
                ->controller('Minoo\\Controller\\MessagingController::showThread')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.index',
            RouteBuilder::create('/api/messaging/threads/{id}/messages')
                ->controller('Minoo\\Controller\\MessagingController::indexMessages')
                ->requireAuthentication()
                ->methods('GET')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.store',
            RouteBuilder::create('/api/messaging/threads/{id}/messages')
                ->controller('Minoo\\Controller\\MessagingController::createMessage')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.participants.store',
            RouteBuilder::create('/api/messaging/threads/{id}/participants')
                ->controller('Minoo\\Controller\\MessagingController::addParticipants')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.participants.delete',
            RouteBuilder::create('/api/messaging/threads/{id}/participants/{user_id}')
                ->controller('Minoo\\Controller\\MessagingController::removeParticipant')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->requirement('user_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.users.search',
            RouteBuilder::create('/api/messaging/users')
                ->controller('Minoo\\Controller\\MessagingController::searchUsers')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.edit',
            RouteBuilder::create('/api/messaging/threads/{id}/messages/{message_id}')
                ->controller('Minoo\\Controller\\MessagingController::editMessage')
                ->requireAuthentication()
                ->methods('PATCH')
                ->requirement('id', '\\d+')
                ->requirement('message_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.messages.delete',
            RouteBuilder::create('/api/messaging/threads/{id}/messages/{message_id}')
                ->controller('Minoo\\Controller\\MessagingController::deleteMessage')
                ->requireAuthentication()
                ->methods('DELETE')
                ->requirement('id', '\\d+')
                ->requirement('message_id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.read',
            RouteBuilder::create('/api/messaging/threads/{id}/read')
                ->controller('Minoo\\Controller\\MessagingController::markRead')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.threads.typing',
            RouteBuilder::create('/api/messaging/threads/{id}/typing')
                ->controller('Minoo\\Controller\\MessagingController::typing')
                ->requireAuthentication()
                ->methods('POST')
                ->requirement('id', '\\d+')
                ->build(),
        );

        $router->addRoute(
            'messaging.unread',
            RouteBuilder::create('/api/messaging/unread-count')
                ->controller('Minoo\\Controller\\MessagingController::unreadCount')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'messaging.search',
            RouteBuilder::create('/api/messaging/search')
                ->controller('Minoo\\Controller\\MessagingController::searchMessages')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );
    }
}

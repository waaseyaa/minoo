<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Entity\MessageThread;
use Minoo\Entity\ThreadMessage;
use Minoo\Entity\ThreadParticipant;
use Minoo\Support\MercurePublisher;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'message_thread',
            label: 'Message Thread',
            class: MessageThread::class,
            keys: ['id' => 'mtid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'messaging',
            fieldDefinitions: [
                'title' => ['type' => 'string', 'label' => 'Title', 'weight' => 0],
                'created_by' => ['type' => 'integer', 'label' => 'Created By', 'weight' => 1],
                'thread_type' => ['type' => 'string', 'label' => 'Thread Type', 'weight' => 2, 'default' => 'direct'],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
                'updated_at' => ['type' => 'timestamp', 'label' => 'Updated', 'weight' => 11],
                'last_message_at' => ['type' => 'timestamp', 'label' => 'Last Message At', 'weight' => 12, 'default' => 0],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'thread_participant',
            label: 'Thread Participant',
            class: ThreadParticipant::class,
            keys: ['id' => 'tpid', 'uuid' => 'uuid', 'label' => 'role'],
            group: 'messaging',
            fieldDefinitions: [
                'thread_id' => ['type' => 'integer', 'label' => 'Thread ID', 'weight' => 0],
                'user_id' => ['type' => 'integer', 'label' => 'User ID', 'weight' => 1],
                'thread_creator_id' => ['type' => 'integer', 'label' => 'Thread Creator ID', 'weight' => 2],
                'role' => ['type' => 'string', 'label' => 'Role', 'weight' => 3, 'default' => 'member'],
                'joined_at' => ['type' => 'timestamp', 'label' => 'Joined', 'weight' => 10],
                'last_read_at' => ['type' => 'timestamp', 'label' => 'Last Read', 'weight' => 11, 'default' => 0],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'thread_message',
            label: 'Thread Message',
            class: ThreadMessage::class,
            keys: ['id' => 'tmid', 'uuid' => 'uuid', 'label' => 'body'],
            group: 'messaging',
            fieldDefinitions: [
                'thread_id' => ['type' => 'integer', 'label' => 'Thread ID', 'weight' => 0],
                'sender_id' => ['type' => 'integer', 'label' => 'Sender ID', 'weight' => 1],
                'body' => ['type' => 'text_long', 'label' => 'Body', 'weight' => 2],
                'status' => ['type' => 'boolean', 'label' => 'Status', 'weight' => 3, 'default' => 1],
                'created_at' => ['type' => 'timestamp', 'label' => 'Created', 'weight' => 10],
                'edited_at' => ['type' => 'timestamp', 'label' => 'Edited At', 'weight' => 11, 'default' => null],
                'deleted_at' => ['type' => 'timestamp', 'label' => 'Deleted At', 'weight' => 12, 'default' => null],
            ],
        ));

        $this->singleton(MercurePublisher::class, function (): MercurePublisher {
            $config = $this->config('messaging');
            return new MercurePublisher(
                (string) ($config['mercure_hub_url'] ?? ''),
                (string) ($config['mercure_publisher_jwt'] ?? ''),
            );
        });
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
    }
}

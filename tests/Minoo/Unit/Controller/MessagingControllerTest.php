<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\MessagingController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(MessagingController::class)]
final class MessagingControllerTest extends TestCase
{
    private EntityTypeManager $etm;
    private MessagingController $controller;

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->controller = new MessagingController($this->etm);
    }

    private function mockAccount(int $id): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('isAuthenticated')->willReturn(true);
        $account->method('hasPermission')->willReturn(false);

        return $account;
    }

    private function jsonRequest(array $body): HttpRequest
    {
        return HttpRequest::create('/', 'POST', [], [], [], [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function createMessage_rejects_non_participant(): void
    {
        $account = $this->mockAccount(1);

        $participantStorage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturn($query);
        $query->method('range')->willReturn($query);
        $query->method('execute')->willReturn([]);
        $participantStorage->method('getQuery')->willReturn($query);

        $this->etm->method('getStorage')->willReturnMap([
            ['thread_participant', $participantStorage],
        ]);

        $request = $this->jsonRequest(['body' => 'Hello']);

        $response = $this->controller->createMessage(['id' => '10'], [], $account, $request);

        $this->assertSame(403, $response->statusCode);
    }

    #[Test]
    public function createMessage_creates_message_and_updates_thread(): void
    {
        $account = $this->mockAccount(1);
        $threadId = 10;

        // Participant check passes.
        $participantStorage = $this->createMock(EntityStorageInterface::class);
        $participantQuery = $this->createMock(EntityQueryInterface::class);
        $participantQuery->method('condition')->willReturn($participantQuery);
        $participantQuery->method('range')->willReturn($participantQuery);
        $participantQuery->method('execute')->willReturn([1]);
        $participantStorage->method('getQuery')->willReturn($participantQuery);

        // Thread is loaded and saved after posting.
        $thread = $this->createMock(EntityInterface::class);
        $thread->method('id')->willReturn($threadId);

        $threadStorage = $this->createMock(EntityStorageInterface::class);
        $threadStorage->method('load')->with($threadId)->willReturn($thread);
        $threadStorage->expects($this->once())->method('save')->with($thread)->willReturn(2);

        // Message is created and saved.
        $message = $this->createMock(EntityInterface::class);
        $message->method('id')->willReturn(99);

        $messageStorage = $this->createMock(EntityStorageInterface::class);
        $messageStorage->method('create')->willReturn($message);
        $messageStorage->method('save')->with($message)->willReturn(1);

        $this->etm->method('getStorage')->willReturnMap([
            ['thread_participant', $participantStorage],
            ['message_thread', $threadStorage],
            ['thread_message', $messageStorage],
        ]);

        $request = $this->jsonRequest(['body' => 'Hello']);

        $response = $this->controller->createMessage(['id' => (string) $threadId], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
        $this->assertStringContainsString('"id":99', $response->content);
    }

    #[Test]
    public function createThread_rejects_when_too_few_participants_after_including_creator(): void
    {
        $account = $this->mockAccount(1);

        $request = $this->jsonRequest([
            'participant_ids' => [],
            'title' => 'Test',
            'body' => '',
        ]);

        $response = $this->controller->createThread([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('At least 2 participants', $response->content);
    }

    #[Test]
    public function editMessage_rejects_non_sender(): void
    {
        $account = $this->mockAccount(1);
        $threadId = 10;
        $messageId = 50;

        // Participant check passes.
        $participantStorage = $this->createMock(EntityStorageInterface::class);
        $participantQuery = $this->createMock(EntityQueryInterface::class);
        $participantQuery->method('condition')->willReturn($participantQuery);
        $participantQuery->method('range')->willReturn($participantQuery);
        $participantQuery->method('execute')->willReturn([1]);
        $participantStorage->method('getQuery')->willReturn($participantQuery);

        // Message belongs to user 2, not user 1.
        $message = $this->createMock(EntityInterface::class);
        $message->method('id')->willReturn($messageId);
        $message->method('get')->willReturnMap([
            ['sender_id', 2],
            ['deleted_at', null],
        ]);

        $messageStorage = $this->createMock(EntityStorageInterface::class);
        $messageStorage->method('load')->with($messageId)->willReturn($message);

        $this->etm->method('getStorage')->willReturnMap([
            ['thread_participant', $participantStorage],
            ['thread_message', $messageStorage],
        ]);

        $request = $this->jsonRequest(['body' => 'Edited']);
        $response = $this->controller->editMessage(['id' => (string) $threadId, 'message_id' => (string) $messageId], [], $account, $request);

        $this->assertSame(403, $response->statusCode);
    }

    #[Test]
    public function editMessage_updates_body_and_sets_edited_at(): void
    {
        $account = $this->mockAccount(1);
        $threadId = 10;
        $messageId = 50;

        $participantStorage = $this->createMock(EntityStorageInterface::class);
        $participantQuery = $this->createMock(EntityQueryInterface::class);
        $participantQuery->method('condition')->willReturn($participantQuery);
        $participantQuery->method('range')->willReturn($participantQuery);
        $participantQuery->method('execute')->willReturn([1]);
        $participantStorage->method('getQuery')->willReturn($participantQuery);

        $message = $this->createMock(EntityInterface::class);
        $message->method('id')->willReturn($messageId);
        $message->method('get')->willReturnMap([
            ['sender_id', 1],
            ['deleted_at', null],
        ]);
        $message->method('set')->willReturn($message);

        $messageStorage = $this->createMock(EntityStorageInterface::class);
        $messageStorage->method('load')->with($messageId)->willReturn($message);
        $messageStorage->expects($this->once())->method('save')->with($message);

        $this->etm->method('getStorage')->willReturnMap([
            ['thread_participant', $participantStorage],
            ['thread_message', $messageStorage],
        ]);

        $request = $this->jsonRequest(['body' => 'Edited body']);
        $response = $this->controller->editMessage(['id' => (string) $threadId, 'message_id' => (string) $messageId], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('"body":"Edited body"', $response->content);
        $this->assertStringContainsString('"edited_at":', $response->content);
    }

    #[Test]
    public function deleteMessage_soft_deletes(): void
    {
        $account = $this->mockAccount(1);
        $threadId = 10;
        $messageId = 50;

        $participantStorage = $this->createMock(EntityStorageInterface::class);
        $participantQuery = $this->createMock(EntityQueryInterface::class);
        $participantQuery->method('condition')->willReturn($participantQuery);
        $participantQuery->method('range')->willReturn($participantQuery);
        $participantQuery->method('execute')->willReturn([1]);
        $participantStorage->method('getQuery')->willReturn($participantQuery);

        $message = $this->createMock(EntityInterface::class);
        $message->method('id')->willReturn($messageId);
        $message->method('get')->willReturnMap([
            ['sender_id', 1],
        ]);
        $message->method('set')->willReturn($message);

        $messageStorage = $this->createMock(EntityStorageInterface::class);
        $messageStorage->method('load')->with($messageId)->willReturn($message);
        $messageStorage->expects($this->once())->method('save')->with($message);

        $this->etm->method('getStorage')->willReturnMap([
            ['thread_participant', $participantStorage],
            ['thread_message', $messageStorage],
        ]);

        $request = HttpRequest::create('/', 'DELETE');
        $response = $this->controller->deleteMessage(['id' => (string) $threadId, 'message_id' => (string) $messageId], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('"deleted":true', $response->content);
    }

    #[Test]
    public function markRead_updates_last_read_at(): void
    {
        $account = $this->mockAccount(1);
        $threadId = 10;

        // isParticipant check
        $participantQuery1 = $this->createMock(EntityQueryInterface::class);
        $participantQuery1->method('condition')->willReturn($participantQuery1);
        $participantQuery1->method('range')->willReturn($participantQuery1);
        $participantQuery1->method('execute')->willReturn([1]);

        // markRead query to find the participant row
        $participantQuery2 = $this->createMock(EntityQueryInterface::class);
        $participantQuery2->method('condition')->willReturn($participantQuery2);
        $participantQuery2->method('range')->willReturn($participantQuery2);
        $participantQuery2->method('execute')->willReturn([42]);

        $participant = $this->createMock(EntityInterface::class);
        $participant->method('set')->willReturn($participant);

        $participantStorage = $this->createMock(EntityStorageInterface::class);
        $participantStorage->method('getQuery')
            ->willReturnOnConsecutiveCalls($participantQuery1, $participantQuery2);
        $participantStorage->method('load')->with(42)->willReturn($participant);
        $participantStorage->expects($this->once())->method('save')->with($participant);

        $this->etm->method('getStorage')->willReturnMap([
            ['thread_participant', $participantStorage],
        ]);

        $request = HttpRequest::create('/', 'POST');
        $response = $this->controller->markRead(['id' => (string) $threadId], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('"last_read_at":', $response->content);
    }

    #[Test]
    public function unreadCount_returns_count(): void
    {
        $account = $this->mockAccount(1);

        // participantsForUser returns one participant
        $participant = $this->createMock(EntityInterface::class);
        $participant->method('get')->willReturnMap([
            ['thread_id', 10],
            ['last_read_at', 0],
        ]);

        $participantQuery = $this->createMock(EntityQueryInterface::class);
        $participantQuery->method('condition')->willReturn($participantQuery);
        $participantQuery->method('sort')->willReturn($participantQuery);
        $participantQuery->method('execute')->willReturn([1]);

        $participantStorage = $this->createMock(EntityStorageInterface::class);
        $participantStorage->method('getQuery')->willReturn($participantQuery);
        $participantStorage->method('loadMultiple')->with([1])->willReturn([1 => $participant]);

        // Message storage returns one message from another user
        $msg = $this->createMock(EntityInterface::class);
        $msg->method('get')->willReturnMap([
            ['created_at', 100],
            ['sender_id', 2],
        ]);

        $msgQuery = $this->createMock(EntityQueryInterface::class);
        $msgQuery->method('condition')->willReturn($msgQuery);
        $msgQuery->method('sort')->willReturn($msgQuery);
        $msgQuery->method('range')->willReturn($msgQuery);
        $msgQuery->method('execute')->willReturn([99]);

        $messageStorage = $this->createMock(EntityStorageInterface::class);
        $messageStorage->method('getQuery')->willReturn($msgQuery);
        $messageStorage->method('loadMultiple')->with([99])->willReturn([99 => $msg]);

        $this->etm->method('getStorage')->willReturnMap([
            ['thread_participant', $participantStorage],
            ['thread_message', $messageStorage],
        ]);

        $request = HttpRequest::create('/', 'GET');
        $response = $this->controller->unreadCount([], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('"unread_count":1', $response->content);
    }
}


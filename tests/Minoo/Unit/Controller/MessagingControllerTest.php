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
}


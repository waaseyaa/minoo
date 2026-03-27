<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\BlockController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(BlockController::class)]
final class BlockControllerTest extends TestCase
{
    private EntityTypeManager $etm;
    private BlockController $controller;

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->controller = new BlockController($this->etm);
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

    private function mockBlockStorage(): EntityStorageInterface
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $this->etm->method('getStorage')->willReturnMap([
            ['user_block', $storage],
        ]);

        return $storage;
    }

    private function mockQuery(EntityStorageInterface $storage, array $result = []): EntityQueryInterface
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturn($query);
        $query->method('sort')->willReturn($query);
        $query->method('range')->willReturn($query);
        $query->method('execute')->willReturn($result);
        $storage->method('getQuery')->willReturn($query);

        return $query;
    }

    #[Test]
    public function store_rejects_self_block(): void
    {
        $account = $this->mockAccount(1);
        $request = $this->jsonRequest(['blocked_id' => 1]);

        $response = $this->controller->store([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Cannot block yourself', $response->content);
    }

    #[Test]
    public function store_rejects_missing_blocked_id(): void
    {
        $account = $this->mockAccount(1);
        $request = $this->jsonRequest([]);

        $response = $this->controller->store([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('blocked_id is required', $response->content);
    }

    #[Test]
    public function store_rejects_duplicate_block(): void
    {
        $account = $this->mockAccount(1);
        $storage = $this->mockBlockStorage();
        $this->mockQuery($storage, [42]);

        $request = $this->jsonRequest(['blocked_id' => 2]);

        $response = $this->controller->store([], [], $account, $request);

        $this->assertSame(409, $response->statusCode);
        $this->assertStringContainsString('already blocked', $response->content);
    }

    #[Test]
    public function store_creates_block(): void
    {
        $account = $this->mockAccount(1);
        $storage = $this->mockBlockStorage();
        $this->mockQuery($storage, []);

        $block = $this->createMock(EntityInterface::class);
        $block->method('id')->willReturn(10);
        $storage->method('create')->willReturn($block);
        $storage->method('save')->willReturn(1);

        $request = $this->jsonRequest(['blocked_id' => 2]);

        $response = $this->controller->store([], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
        $this->assertStringContainsString('"id":10', $response->content);
    }

    #[Test]
    public function delete_returns_404_when_not_found(): void
    {
        $account = $this->mockAccount(1);
        $storage = $this->mockBlockStorage();
        $this->mockQuery($storage, []);

        $request = HttpRequest::create('/', 'DELETE');

        $response = $this->controller->delete(['user_id' => '2'], [], $account, $request);

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function delete_removes_block(): void
    {
        $account = $this->mockAccount(1);
        $storage = $this->mockBlockStorage();
        $this->mockQuery($storage, [42]);

        $block = $this->createMock(EntityInterface::class);
        $storage->method('load')->with(42)->willReturn($block);
        $storage->expects($this->once())->method('delete')->with([$block]);

        $request = HttpRequest::create('/', 'DELETE');

        $response = $this->controller->delete(['user_id' => '2'], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('"removed":true', $response->content);
    }

    #[Test]
    public function index_returns_blocks_for_current_user(): void
    {
        $account = $this->mockAccount(1);
        $storage = $this->mockBlockStorage();
        $this->mockQuery($storage, []);
        $storage->method('loadMultiple')->willReturn([]);

        $request = HttpRequest::create('/', 'GET');

        $response = $this->controller->index([], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('"blocks":', $response->content);
    }
}

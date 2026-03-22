<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration\Controller;

use Minoo\Controller\EngagementController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\ContentEntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(EngagementController::class)]
final class EngagementControllerTest extends TestCase
{
    private EntityTypeManager $etm;
    private EngagementController $controller;

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->controller = new EngagementController($this->etm);
    }

    private function mockAccount(int $id = 1, bool $isAdmin = false): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('hasPermission')->willReturn($isAdmin);

        return $account;
    }

    /** @param array<string, mixed> $fieldMap */
    private function mockEntity(array $fieldMap = [], int|string|null $id = null): ContentEntityInterface
    {
        $entity = $this->createMock(ContentEntityInterface::class);
        if ($id !== null) {
            $entity->method('id')->willReturn($id);
        }
        if ($fieldMap !== []) {
            $entity->method('get')->willReturnMap(
                array_map(fn($k, $v) => [$k, $v], array_keys($fieldMap), array_values($fieldMap)),
            );
        }

        return $entity;
    }

    private function mockStorage(string $type): EntityStorageInterface
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        $this->etm->method('getStorage')->with($type)->willReturn($storage);

        return $storage;
    }

    private function jsonRequest(string $uri, string $method = 'POST', array $data = []): HttpRequest
    {
        return HttpRequest::create($uri, $method, [], [], [], [], json_encode($data));
    }

    #[Test]
    public function react_requires_valid_input(): void
    {
        $account = $this->mockAccount();
        $response = $this->controller->react([], [], $account, $this->jsonRequest('/api/engagement/react'));

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Missing required fields', $response->content);
    }

    #[Test]
    public function comment_requires_valid_input(): void
    {
        $account = $this->mockAccount();
        $response = $this->controller->comment([], [], $account, $this->jsonRequest('/api/engagement/comment'));

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Missing required fields', $response->content);
    }

    #[Test]
    public function follow_requires_valid_input(): void
    {
        $account = $this->mockAccount();
        $response = $this->controller->follow([], [], $account, $this->jsonRequest('/api/engagement/follow'));

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Missing required fields', $response->content);
    }

    #[Test]
    public function create_post_requires_valid_input(): void
    {
        $account = $this->mockAccount();
        $response = $this->controller->createPost([], [], $account, $this->jsonRequest('/api/engagement/post'));

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Missing required field', $response->content);
    }

    #[Test]
    public function react_rejects_invalid_target_type(): void
    {
        $account = $this->mockAccount();
        $request = $this->jsonRequest('/api/engagement/react', 'POST', [
            'emoji' => "\u{1F44D}", 'target_type' => 'invalid_type', 'target_id' => 1,
        ]);

        $response = $this->controller->react([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    #[Test]
    public function react_rejects_invalid_emoji(): void
    {
        $account = $this->mockAccount();
        $this->mockStorage('reaction');
        $request = $this->jsonRequest('/api/engagement/react', 'POST', [
            'emoji' => '', 'target_type' => 'event', 'target_id' => 1,
        ]);

        $response = $this->controller->react([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid emoji', $response->content);
    }

    #[Test]
    public function comment_rejects_body_too_long(): void
    {
        $account = $this->mockAccount();
        $request = $this->jsonRequest('/api/engagement/comment', 'POST', [
            'body' => str_repeat('a', 2001), 'target_type' => 'event', 'target_id' => 1,
        ]);

        $response = $this->controller->comment([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('1-2000 characters', $response->content);
    }

    #[Test]
    public function react_creates_reaction_and_returns_201(): void
    {
        $account = $this->mockAccount(42);
        $entity = $this->mockEntity(['emoji' => "\u{1F44D}"], 1);
        $storage = $this->mockStorage('reaction');
        $storage->method('create')->willReturn($entity);

        $request = $this->jsonRequest('/api/engagement/react', 'POST', [
            'emoji' => "\u{1F44D}", 'target_type' => 'event', 'target_id' => 10,
        ]);

        $response = $this->controller->react([], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame(1, $json['id']);
    }

    #[Test]
    public function comment_creates_comment_and_returns_201(): void
    {
        $account = $this->mockAccount(42);
        $entity = $this->mockEntity(['body' => 'Great event!', 'user_id' => 42, 'created_at' => 1700000000], 5);
        $storage = $this->mockStorage('comment');
        $storage->method('create')->willReturn($entity);

        $request = $this->jsonRequest('/api/engagement/comment', 'POST', [
            'body' => 'Great event!', 'target_type' => 'event', 'target_id' => 10,
        ]);

        $response = $this->controller->comment([], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame(5, $json['id']);
        $this->assertSame('Great event!', $json['body']);
    }

    #[Test]
    public function delete_reaction_returns_200_for_owner(): void
    {
        $account = $this->mockAccount(42);
        $entity = $this->mockEntity(['user_id' => 42]);
        $storage = $this->mockStorage('reaction');
        $storage->method('load')->with(1)->willReturn($entity);

        $request = HttpRequest::create('/api/engagement/react/1', 'DELETE');
        $response = $this->controller->deleteReaction(['id' => '1'], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertTrue($json['deleted']);
    }

    #[Test]
    public function delete_reaction_returns_403_for_non_owner(): void
    {
        $account = $this->mockAccount(99);
        $entity = $this->mockEntity(['user_id' => 42]);
        $storage = $this->mockStorage('reaction');
        $storage->method('load')->with(1)->willReturn($entity);

        $request = HttpRequest::create('/api/engagement/react/1', 'DELETE');
        $response = $this->controller->deleteReaction(['id' => '1'], [], $account, $request);

        $this->assertSame(403, $response->statusCode);
        $this->assertStringContainsString('Forbidden', $response->content);
    }

    #[Test]
    public function delete_reaction_allowed_for_admin(): void
    {
        $account = $this->mockAccount(99, isAdmin: true);
        $entity = $this->mockEntity(['user_id' => 42]);
        $storage = $this->mockStorage('reaction');
        $storage->method('load')->with(1)->willReturn($entity);

        $request = HttpRequest::create('/api/engagement/react/1', 'DELETE');
        $response = $this->controller->deleteReaction(['id' => '1'], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertTrue($json['deleted']);
    }

    #[Test]
    public function follow_creates_follow_and_returns_201(): void
    {
        $account = $this->mockAccount(42);
        $entity = $this->mockEntity(id: 7);
        $storage = $this->mockStorage('follow');
        $storage->method('create')->willReturn($entity);

        $request = $this->jsonRequest('/api/engagement/follow', 'POST', [
            'target_type' => 'community', 'target_id' => 5,
        ]);

        $response = $this->controller->follow([], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame(7, $json['id']);
    }

    #[Test]
    public function create_post_returns_201(): void
    {
        $account = $this->mockAccount(42);
        $entity = $this->mockEntity(['body' => 'Hello community!', 'created_at' => 1700000000], 3);
        $storage = $this->mockStorage('post');
        $storage->method('create')->willReturn($entity);

        $request = $this->jsonRequest('/api/engagement/post', 'POST', ['body' => 'Hello community!']);

        $response = $this->controller->createPost([], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame(3, $json['id']);
    }

    #[Test]
    public function create_post_rejects_body_too_long(): void
    {
        $account = $this->mockAccount();
        $request = $this->jsonRequest('/api/engagement/post', 'POST', [
            'body' => str_repeat('x', 5001),
        ]);

        $response = $this->controller->createPost([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('1-5000 characters', $response->content);
    }

    #[Test]
    public function delete_comment_returns_403_for_non_owner(): void
    {
        $account = $this->mockAccount(99);
        $entity = $this->mockEntity(['user_id' => 42]);
        $storage = $this->mockStorage('comment');
        $storage->method('load')->with(1)->willReturn($entity);

        $request = HttpRequest::create('/api/engagement/comment/1', 'DELETE');
        $response = $this->controller->deleteComment(['id' => '1'], [], $account, $request);

        $this->assertSame(403, $response->statusCode);
    }

    #[Test]
    public function delete_post_returns_403_for_non_owner(): void
    {
        $account = $this->mockAccount(99);
        $entity = $this->mockEntity(['user_id' => 42]);
        $storage = $this->mockStorage('post');
        $storage->method('load')->with(1)->willReturn($entity);

        $request = HttpRequest::create('/api/engagement/post/1', 'DELETE');
        $response = $this->controller->deletePost(['id' => '1'], [], $account, $request);

        $this->assertSame(403, $response->statusCode);
    }

    #[Test]
    public function delete_reaction_returns_404_when_not_found(): void
    {
        $account = $this->mockAccount();
        $storage = $this->mockStorage('reaction');
        $storage->method('load')->willReturn(null);

        $request = HttpRequest::create('/api/engagement/react/999', 'DELETE');
        $response = $this->controller->deleteReaction(['id' => '999'], [], $account, $request);

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function follow_rejects_invalid_target_type(): void
    {
        $account = $this->mockAccount();
        $request = $this->jsonRequest('/api/engagement/follow', 'POST', [
            'target_type' => 'bogus', 'target_id' => 1,
        ]);

        $response = $this->controller->follow([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    #[Test]
    public function comment_rejects_invalid_target_type(): void
    {
        $account = $this->mockAccount();
        $request = $this->jsonRequest('/api/engagement/comment', 'POST', [
            'body' => 'Test', 'target_type' => 'bogus', 'target_id' => 1,
        ]);

        $response = $this->controller->comment([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }
}

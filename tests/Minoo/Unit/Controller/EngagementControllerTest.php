<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\EngagementController;
use Minoo\Support\UploadService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        $this->controller = new EngagementController($this->etm, new UploadService(sys_get_temp_dir() . '/minoo-test-uploads'));
    }

    private function mockAccount(int $id = 1, bool $isAdmin = false): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('isAuthenticated')->willReturn(true);
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

    private function jsonRequest(string $method, array $body): HttpRequest
    {
        return HttpRequest::create('/', $method, [], [], [], [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    // --- Target type whitelist ---

    #[Test]
    public function react_rejects_invalid_target_type(): void
    {
        $request = $this->jsonRequest('POST', [
            'reaction_type' => 'like',
            'target_type' => 'malicious_type',
            'target_id' => 1,
        ]);

        $response = $this->controller->react([], [], $this->mockAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    #[Test]
    public function comment_rejects_invalid_target_type(): void
    {
        $request = $this->jsonRequest('POST', [
            'body' => 'Hello',
            'target_type' => 'sql_injection',
            'target_id' => 1,
        ]);

        $response = $this->controller->comment([], [], $this->mockAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    #[Test]
    public function follow_rejects_invalid_target_type(): void
    {
        $request = $this->jsonRequest('POST', [
            'target_type' => 'nonexistent',
            'target_id' => 1,
        ]);

        $response = $this->controller->follow([], [], $this->mockAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    #[Test]
    public function getComments_rejects_invalid_target_type(): void
    {
        $request = HttpRequest::create('/');

        $response = $this->controller->getComments(
            ['target_type' => 'xss_attempt', 'target_id' => '1'],
            [],
            $this->mockAccount(),
            $request,
        );

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    // --- Reaction type validation ---

    #[Test]
    public function react_rejects_invalid_reaction_type(): void
    {
        $request = $this->jsonRequest('POST', [
            'reaction_type' => 'invalid_type',
            'target_type' => 'event',
            'target_id' => 1,
        ]);

        $response = $this->controller->react([], [], $this->mockAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid reaction_type', $response->content);
    }

    // --- Missing fields ---

    #[Test]
    public function react_rejects_missing_fields(): void
    {
        $request = $this->jsonRequest('POST', ['reaction_type' => 'like']);

        $response = $this->controller->react([], [], $this->mockAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Missing required fields', $response->content);
    }

    #[Test]
    public function comment_rejects_missing_fields(): void
    {
        $request = $this->jsonRequest('POST', ['body' => 'Hello']);

        $response = $this->controller->comment([], [], $this->mockAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Missing required fields', $response->content);
    }

    // --- Body length validation ---

    #[Test]
    public function createPost_rejects_empty_body(): void
    {
        $request = $this->jsonRequest('POST', ['body' => '   ', 'community_id' => 1]);

        $response = $this->controller->createPost([], [], $this->mockAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Body must be', $response->content);
    }

    #[Test]
    public function createPost_rejects_oversized_body(): void
    {
        $request = $this->jsonRequest('POST', ['body' => str_repeat('a', 5001), 'community_id' => 1]);

        $response = $this->controller->createPost([], [], $this->mockAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Body must be', $response->content);
    }

    #[Test]
    public function comment_rejects_oversized_body(): void
    {
        $request = $this->jsonRequest('POST', [
            'body' => str_repeat('a', 2001),
            'target_type' => 'event',
            'target_id' => 1,
        ]);

        $response = $this->controller->comment([], [], $this->mockAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Body must be', $response->content);
    }

    // --- Happy-path creation tests ---

    #[Test]
    public function react_creates_reaction_and_returns_201(): void
    {
        $account = $this->mockAccount(42);
        $entity = $this->mockEntity(['reaction_type' => 'interested'], 1);
        $storage = $this->mockStorage('reaction');
        $storage->method('create')->willReturn($entity);

        $request = $this->jsonRequest('POST', [
            'reaction_type' => 'interested', 'target_type' => 'event', 'target_id' => 10,
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

        $request = $this->jsonRequest('POST', [
            'body' => 'Great event!', 'target_type' => 'event', 'target_id' => 10,
        ]);

        $response = $this->controller->comment([], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame(5, $json['id']);
        $this->assertSame('Great event!', $json['body']);
    }

    #[Test]
    public function follow_creates_follow_and_returns_201(): void
    {
        $account = $this->mockAccount(42);
        $entity = $this->mockEntity(id: 7);
        $storage = $this->mockStorage('follow');
        $storage->method('create')->willReturn($entity);

        $request = $this->jsonRequest('POST', [
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

        $request = $this->jsonRequest('POST', ['body' => 'Hello community!', 'community_id' => 1]);

        $response = $this->controller->createPost([], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame(3, $json['id']);
    }

    #[Test]
    public function create_post_accepts_single_multipart_image_field(): void
    {
        $account = $this->mockAccount(42);
        $entity = $this->mockEntity(['body' => 'Hello community!', 'created_at' => 1700000000], 3);
        $storage = $this->mockStorage('post');
        $storage->method('create')->willReturn($entity);

        $tmpImage = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmpImage, 'test-image');
        $upload = new UploadedFile(
            path: $tmpImage,
            originalName: 'photo.jpg',
            mimeType: 'image/jpeg',
            error: UPLOAD_ERR_OK,
            test: true,
        );

        $request = HttpRequest::create(
            uri: '/api/engagement/post',
            method: 'POST',
            parameters: ['body' => 'Hello community!', 'community_id' => 1],
            files: ['images' => $upload],
        );

        $response = $this->controller->createPost([], [], $account, $request);

        $this->assertSame(201, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame(3, $json['id']);
    }

    // --- Delete tests ---

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

    // --- Constructor exception safety net (#453) ---

    #[Test]
    public function react_catches_constructor_invalid_argument_exception(): void
    {
        $account = $this->mockAccount(42);
        $storage = $this->mockStorage('reaction');
        $storage->method('create')->willThrowException(new \InvalidArgumentException('Bad data'));

        $request = $this->jsonRequest('POST', [
            'reaction_type' => 'like', 'target_type' => 'event', 'target_id' => 1,
        ]);

        $response = $this->controller->react([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame('Invalid entity data', $json['error']);
    }

    #[Test]
    public function comment_catches_constructor_invalid_argument_exception(): void
    {
        $account = $this->mockAccount(42);
        $storage = $this->mockStorage('comment');
        $storage->method('create')->willThrowException(new \InvalidArgumentException('Bad data'));

        $request = $this->jsonRequest('POST', [
            'body' => 'A valid comment', 'target_type' => 'event', 'target_id' => 1,
        ]);

        $response = $this->controller->comment([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame('Invalid entity data', $json['error']);
    }

    #[Test]
    public function follow_catches_constructor_invalid_argument_exception(): void
    {
        $account = $this->mockAccount(42);
        $storage = $this->mockStorage('follow');
        $storage->method('create')->willThrowException(new \InvalidArgumentException('Bad data'));

        $request = $this->jsonRequest('POST', [
            'target_type' => 'event', 'target_id' => 1,
        ]);

        $response = $this->controller->follow([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame('Invalid entity data', $json['error']);
    }

    #[Test]
    public function createPost_catches_constructor_invalid_argument_exception(): void
    {
        $account = $this->mockAccount(42);
        $storage = $this->mockStorage('post');
        $storage->method('create')->willThrowException(new \InvalidArgumentException('Bad data'));

        $request = $this->jsonRequest('POST', [
            'body' => 'A valid post body', 'community_id' => 1,
        ]);

        $response = $this->controller->createPost([], [], $account, $request);

        $this->assertSame(422, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertSame('Invalid entity data', $json['error']);
    }
}

<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\EngagementController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(EngagementController::class)]
final class EngagementControllerTest extends TestCase
{
    private function makeController(): EngagementController
    {
        $etm = $this->createMock(EntityTypeManager::class);

        return new EngagementController($etm);
    }

    private function authedAccount(int $id = 1): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn($id);
        $account->method('isAuthenticated')->willReturn(true);

        return $account;
    }

    private function jsonRequest(string $method, array $body): HttpRequest
    {
        return HttpRequest::create('/', $method, [], [], [], [], json_encode($body, JSON_THROW_ON_ERROR));
    }

    // --- Target type whitelist ---

    #[Test]
    public function react_rejects_invalid_target_type(): void
    {
        $controller = $this->makeController();
        $request = $this->jsonRequest('POST', [
            'reaction_type' => 'like',
            'target_type' => 'malicious_type',
            'target_id' => 1,
        ]);

        $response = $controller->react([], [], $this->authedAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    #[Test]
    public function comment_rejects_invalid_target_type(): void
    {
        $controller = $this->makeController();
        $request = $this->jsonRequest('POST', [
            'body' => 'Hello',
            'target_type' => 'sql_injection',
            'target_id' => 1,
        ]);

        $response = $controller->comment([], [], $this->authedAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    #[Test]
    public function follow_rejects_invalid_target_type(): void
    {
        $controller = $this->makeController();
        $request = $this->jsonRequest('POST', [
            'target_type' => 'nonexistent',
            'target_id' => 1,
        ]);

        $response = $controller->follow([], [], $this->authedAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    #[Test]
    public function getComments_rejects_invalid_target_type(): void
    {
        $controller = $this->makeController();
        $request = HttpRequest::create('/');

        $response = $controller->getComments(
            ['target_type' => 'xss_attempt', 'target_id' => '1'],
            [],
            $this->authedAccount(),
            $request,
        );

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid target_type', $response->content);
    }

    // --- Reaction type validation ---

    #[Test]
    public function react_rejects_invalid_reaction_type(): void
    {
        $controller = $this->makeController();
        $request = $this->jsonRequest('POST', [
            'reaction_type' => 'invalid_type',
            'target_type' => 'event',
            'target_id' => 1,
        ]);

        $response = $controller->react([], [], $this->authedAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Invalid reaction_type', $response->content);
    }

    // --- Missing fields ---

    #[Test]
    public function react_rejects_missing_fields(): void
    {
        $controller = $this->makeController();
        $request = $this->jsonRequest('POST', ['reaction_type' => 'like']);

        $response = $controller->react([], [], $this->authedAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Missing required fields', $response->content);
    }

    #[Test]
    public function comment_rejects_missing_fields(): void
    {
        $controller = $this->makeController();
        $request = $this->jsonRequest('POST', ['body' => 'Hello']);

        $response = $controller->comment([], [], $this->authedAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Missing required fields', $response->content);
    }

    #[Test]
    public function createPost_rejects_empty_body(): void
    {
        $controller = $this->makeController();
        $request = $this->jsonRequest('POST', ['body' => '   ', 'community_id' => 1]);

        $response = $controller->createPost([], [], $this->authedAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Body must be', $response->content);
    }

    #[Test]
    public function createPost_rejects_oversized_body(): void
    {
        $controller = $this->makeController();
        $request = $this->jsonRequest('POST', ['body' => str_repeat('a', 5001), 'community_id' => 1]);

        $response = $controller->createPost([], [], $this->authedAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Body must be', $response->content);
    }

    #[Test]
    public function comment_rejects_oversized_body(): void
    {
        $controller = $this->makeController();
        $request = $this->jsonRequest('POST', [
            'body' => str_repeat('a', 2001),
            'target_type' => 'event',
            'target_id' => 1,
        ]);

        $response = $controller->comment([], [], $this->authedAccount(), $request);

        $this->assertSame(422, $response->statusCode);
        $this->assertStringContainsString('Body must be', $response->content);
    }
}

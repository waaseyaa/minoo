<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\FeedController;
use Minoo\Feed\FeedAssemblerInterface;
use Minoo\Feed\FeedItem;
use Minoo\Feed\FeedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(FeedController::class)]
final class FeedControllerTest extends TestCase
{
    #[Test]
    public function index_renders_feed_template(): void
    {
        $twig = $this->createMock(Environment::class);
        $assembler = $this->createMock(FeedAssemblerInterface::class);

        $assembler->method('assemble')->willReturn(
            new FeedResponse([], null, 'all')
        );
        $twig->expects($this->once())
            ->method('render')
            ->with('feed.html.twig', $this->anything())
            ->willReturn('<html>feed</html>');

        $controller = new FeedController($assembler, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/');

        $response = $controller->index([], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function api_returns_json(): void
    {
        $twig = $this->createMock(Environment::class);
        $assembler = $this->createMock(FeedAssemblerInterface::class);

        $item = new FeedItem(
            id: 'event:1', type: 'event', title: 'Test',
            url: '/events/test', badge: 'Event', weight: 0,
            createdAt: new \DateTimeImmutable(), sortKey: 'key',
        );

        $assembler->method('assemble')->willReturn(
            new FeedResponse([$item], 'cursor123', 'all')
        );

        // API needs Twig to render card HTML fragments
        $twig->method('render')->willReturn('<article>card</article>');

        $controller = new FeedController($assembler, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/api/feed?filter=all');

        $response = $controller->api([], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
        $json = json_decode($response->content, true);
        $this->assertArrayHasKey('items', $json);
        $this->assertArrayHasKey('nextCursor', $json);
        $this->assertArrayHasKey('activeFilter', $json);
        $this->assertSame('cursor123', $json['nextCursor']);
    }

    #[Test]
    public function explore_redirects(): void
    {
        $twig = $this->createMock(Environment::class);
        $assembler = $this->createMock(FeedAssemblerInterface::class);

        $controller = new FeedController($assembler, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/explore?type=events&q=pow+wow');

        $response = $controller->explore([], ['type' => 'events', 'q' => 'pow wow'], $account, $request);

        $this->assertSame(302, $response->statusCode);
    }
}

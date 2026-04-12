<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\FeedController;
use App\Feed\FeedAssemblerInterface;
use App\Feed\FeedItem;
use App\Feed\FeedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(FeedController::class)]
final class FeedControllerTest extends TestCase
{
    private function createEntityTypeManager(): EntityTypeManager
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(false);

        return $etm;
    }

    #[Test]
    public function index_renders_feed_template(): void
    {
        $twig = $this->createMock(Environment::class);
        $assembler = $this->createMock(FeedAssemblerInterface::class);
        $etm = $this->createEntityTypeManager();

        $assembler->method('assemble')->willReturn(
            new FeedResponse([], null, 'all')
        );
        $twig->expects($this->once())
            ->method('render')
            ->with('feed.html.twig', $this->anything())
            ->willReturn('<html>feed</html>');

        $controller = new FeedController($assembler, $twig, $etm);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/');

        $response = $controller->index([], [], $account, $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function index_passes_sidebar_data_to_template(): void
    {
        $twig = $this->createMock(Environment::class);
        $assembler = $this->createMock(FeedAssemblerInterface::class);
        $etm = $this->createEntityTypeManager();

        $item = new FeedItem(
            id: 'event:1', type: 'event', title: 'Pow Wow',
            url: '/events/pow-wow', badge: 'Event', weight: 0,
            createdAt: new \DateTimeImmutable(), sortKey: 'key',
        );

        $assembler->method('assemble')->willReturn(
            new FeedResponse([$item], null, 'all')
        );

        $capturedContext = null;
        $twig->expects($this->once())
            ->method('render')
            ->with('feed.html.twig', $this->callback(function (array $ctx) use (&$capturedContext): bool {
                $capturedContext = $ctx;

                return true;
            }))
            ->willReturn('<html>feed</html>');

        $controller = new FeedController($assembler, $twig, $etm);
        $account = $this->createMock(AccountInterface::class);
        $account->method('isAuthenticated')->willReturn(false);
        $request = HttpRequest::create('/');

        $controller->index([], [], $account, $request);

        $this->assertArrayHasKey('csrf_token', $capturedContext);
        $this->assertNotEmpty($capturedContext['csrf_token']);
        $this->assertArrayHasKey('trending', $capturedContext);
        $this->assertArrayHasKey('upcoming_events', $capturedContext);
        $this->assertArrayHasKey('suggested_communities', $capturedContext);
        $this->assertArrayHasKey('followed_communities', $capturedContext);
        $this->assertArrayHasKey('user_communities', $capturedContext);
        $this->assertArrayHasKey('account', $capturedContext);

        // Trending falls back to feed items when no reaction entity type exists
        $this->assertCount(1, $capturedContext['trending']);
        $this->assertSame('Pow Wow', $capturedContext['trending'][0]['title']);

        // No location cookie — suggested communities empty
        $this->assertSame([], $capturedContext['suggested_communities']);

        // Anonymous user — followed/user communities empty
        $this->assertSame([], $capturedContext['followed_communities']);
        $this->assertSame([], $capturedContext['user_communities']);
    }

    #[Test]
    public function api_returns_json(): void
    {
        $twig = $this->createMock(Environment::class);
        $assembler = $this->createMock(FeedAssemblerInterface::class);
        $etm = $this->createEntityTypeManager();

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

        $controller = new FeedController($assembler, $twig, $etm);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/api/feed?filter=all');

        $response = $controller->api([], [], $account, $request);

        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
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
        $etm = $this->createEntityTypeManager();

        $controller = new FeedController($assembler, $twig, $etm);
        $account = $this->createMock(AccountInterface::class);
        $request = HttpRequest::create('/explore?type=events&q=pow+wow');

        $response = $controller->explore([], ['type' => 'events', 'q' => 'pow wow'], $account, $request);

        $this->assertSame(302, $response->getStatusCode());
    }
}

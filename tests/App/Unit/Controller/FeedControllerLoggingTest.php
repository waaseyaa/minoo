<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\FeedController;
use App\Feed\FeedAssemblerInterface;
use App\Feed\FeedContext;
use App\Feed\FeedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(FeedController::class)]
final class FeedControllerLoggingTest extends TestCase
{
    #[Test]
    public function buildTrendingLogsAndReturnsEmptyOnPdoException(): void
    {
        $assembler = $this->createMock(FeedAssemblerInterface::class);
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);

        $etm->method('hasDefinition')->with('reaction')->willReturn(true);
        $etm->method('getStorage')->with('reaction')->willThrowException(
            new \PDOException('Connection refused')
        );

        $controller = new FeedController($assembler, $twig, $etm);

        $method = new \ReflectionMethod($controller, 'buildTrending');

        $response = new FeedResponse(items: [], nextCursor: null, activeFilter: 'all');

        $result = $method->invoke($controller, $response);

        $this->assertSame([], $result);
    }

    #[Test]
    public function buildUpcomingEventsLogsAndReturnsEmptyOnRuntimeException(): void
    {
        $assembler = $this->createMock(FeedAssemblerInterface::class);
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);

        $etm->method('hasDefinition')->with('event')->willReturn(true);
        $etm->method('getStorage')->with('event')->willThrowException(
            new \RuntimeException('Storage unavailable')
        );

        $controller = new FeedController($assembler, $twig, $etm);

        $method = new \ReflectionMethod($controller, 'buildUpcomingEvents');

        $result = $method->invoke($controller);

        $this->assertSame([], $result);
    }

    #[Test]
    public function buildSuggestedCommunitiesLogsAndReturnsEmptyOnPdoException(): void
    {
        $assembler = $this->createMock(FeedAssemblerInterface::class);
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);

        $etm->method('hasDefinition')->with('community')->willReturn(true);
        $etm->method('getStorage')->with('community')->willThrowException(
            new \PDOException('Disk full')
        );

        $controller = new FeedController($assembler, $twig, $etm);

        $method = new \ReflectionMethod($controller, 'buildSuggestedCommunities');

        $result = $method->invoke($controller, 46.0, -81.0);

        $this->assertSame([], $result);
    }

    #[Test]
    public function buildFollowedCommunitiesLogsAndReturnsEmptyOnRuntimeException(): void
    {
        $assembler = $this->createMock(FeedAssemblerInterface::class);
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);

        $account = $this->createMock(AccountInterface::class);
        $account->method('isAuthenticated')->willReturn(true);
        $account->method('id')->willReturn(1);

        $etm->method('hasDefinition')->with('follow')->willReturn(true);
        $etm->method('getStorage')->with('follow')->willThrowException(
            new \RuntimeException('Follow storage broken')
        );

        $controller = new FeedController($assembler, $twig, $etm);

        $method = new \ReflectionMethod($controller, 'buildFollowedCommunities');

        $result = $method->invoke($controller, $account);

        $this->assertSame([], $result);
    }
}

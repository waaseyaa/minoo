<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Home;

use App\Http\Controller\Home\HomeController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(HomeController::class)]
final class HomeControllerTest extends TestCase
{
    private function createAccount(bool $authenticated): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('isAuthenticated')->willReturn($authenticated);

        return $account;
    }

    #[Test]
    public function anonymous_user_sees_homepage(): void
    {
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willThrowException(new \RuntimeException('No table'));

        $twig->expects($this->once())
            ->method('render')
            ->with('pages/home/index.html.twig', $this->callback(function (array $ctx): bool {
                return $ctx['path'] === '/'
                    && array_key_exists('featured', $ctx)
                    && array_key_exists('entry_count', $ctx);
            }))
            ->willReturn('<html>homepage</html>');

        $controller = new HomeController($etm, $twig);
        $response = $controller->index([], [], $this->createAccount(false), HttpRequest::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('homepage', $response->getContent());
    }

    #[Test]
    public function authenticated_user_sees_homepage_too(): void
    {
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willThrowException(new \RuntimeException('No table'));

        $twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>homepage</html>');

        $controller = new HomeController($etm, $twig);
        $response = $controller->index([], [], $this->createAccount(true), HttpRequest::create('/'));

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function homepage_passes_empty_defaults_when_storage_unavailable(): void
    {
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willThrowException(new \RuntimeException('No table'));

        $capturedContext = null;
        $twig->expects($this->once())
            ->method('render')
            ->with('pages/home/index.html.twig', $this->callback(function (array $ctx) use (&$capturedContext): bool {
                $capturedContext = $ctx;

                return true;
            }))
            ->willReturn('<html>homepage</html>');

        $controller = new HomeController($etm, $twig);
        $response = $controller->index([], [], $this->createAccount(false), HttpRequest::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $capturedContext['featured']);
        $this->assertSame(0, $capturedContext['entry_count']);
    }
}

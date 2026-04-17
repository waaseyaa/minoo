<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\HomeController;
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
    private function createEntityTypeManager(): EntityTypeManager
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(false);

        return $etm;
    }

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
        $etm = $this->createEntityTypeManager();

        $twig->expects($this->once())
            ->method('render')
            ->with('pages/home/index.html.twig', $this->callback(function (array $ctx): bool {
                return $ctx['path'] === '/'
                    && array_key_exists('featured', $ctx)
                    && array_key_exists('events', $ctx)
                    && array_key_exists('teachings', $ctx);
            }))
            ->willReturn('<html>homepage</html>');

        $controller = new HomeController($etm, $twig);
        $response = $controller->index([], [], $this->createAccount(false), HttpRequest::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('homepage', $response->getContent());
    }

    #[Test]
    public function authenticated_user_is_redirected_to_feed(): void
    {
        $twig = $this->createMock(Environment::class);
        $etm = $this->createEntityTypeManager();

        $twig->expects($this->never())->method('render');

        $controller = new HomeController($etm, $twig);
        $response = $controller->index([], [], $this->createAccount(true), HttpRequest::create('/'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/feed', $response->headers->get('Location'));
    }

    #[Test]
    public function homepage_passes_empty_arrays_when_storage_unavailable(): void
    {
        $twig = $this->createMock(Environment::class);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('hasDefinition')->willReturn(false);
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
        $this->assertSame([], $capturedContext['events']);
        $this->assertSame([], $capturedContext['teachings']);
    }
}

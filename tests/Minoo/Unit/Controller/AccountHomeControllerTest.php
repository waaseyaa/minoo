<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\AccountHomeController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\User;

#[CoversClass(AccountHomeController::class)]
final class AccountHomeControllerTest extends TestCase
{
    private function createController(?Environment $twig = null, ?EntityTypeManager $etm = null): AccountHomeController
    {
        return new AccountHomeController(
            $twig ?? $this->createMock(Environment::class),
            $etm ?? $this->createMock(EntityTypeManager::class),
        );
    }

    #[Test]
    public function indexRendersAccountHomePage(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(1);
        $account->method('getRoles')->willReturn([]);
        $account->method('isAuthenticated')->willReturn(true);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('account/home.html.twig', $this->callback(function (array $vars) {
                return $vars['account'] instanceof AccountInterface
                    && $vars['roles'] === []
                    && $vars['path'] === '/account';
            }))
            ->willReturn('<html>account home</html>');

        $controller = $this->createController($twig);
        $response = $controller->index([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function indexPassesRolesToTemplate(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(2);
        $account->method('getRoles')->willReturn(['volunteer']);
        $account->method('isAuthenticated')->willReturn(true);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('account/home.html.twig', $this->callback(function (array $vars) {
                return $vars['roles'] === ['volunteer'];
            }))
            ->willReturn('<html>account home</html>');

        $controller = $this->createController($twig);
        $response = $controller->index([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function index_passes_is_elder_false_for_non_elder(): void
    {
        $twig = $this->createMock(Environment::class);

        $capturedContext = null;
        $twig->expects($this->once())
            ->method('render')
            ->with('account/home.html.twig', $this->callback(function (array $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;

                return true;
            }))
            ->willReturn('<html></html>');

        $controller = $this->createController($twig);
        $account = new User(['uid' => 1, 'name' => 'Test']);

        $controller->index([], [], $account, HttpRequest::create('/account'));

        $this->assertArrayHasKey('is_elder', $capturedContext);
        $this->assertFalse($capturedContext['is_elder']);
    }

    #[Test]
    public function index_passes_is_elder_true_for_elder(): void
    {
        $twig = $this->createMock(Environment::class);

        $capturedContext = null;
        $twig->expects($this->once())
            ->method('render')
            ->with('account/home.html.twig', $this->callback(function (array $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;

                return true;
            }))
            ->willReturn('<html></html>');

        $controller = $this->createController($twig);
        $account = new User(['uid' => 1, 'name' => 'Test', 'is_elder' => 1]);

        $controller->index([], [], $account, HttpRequest::create('/account'));

        $this->assertTrue($capturedContext['is_elder']);
    }

    #[Test]
    public function toggle_elder_sets_elder_and_redirects(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(EntityStorageInterface::class);

        $user = new User(['uid' => 1, 'name' => 'Test']);
        $this->assertFalse($user->isElder());

        $etm->method('getStorage')->with('user')->willReturn($storage);
        $storage->method('load')->with(1)->willReturn($user);
        $storage->expects($this->once())->method('save')->with($user);

        $controller = $this->createController(etm: $etm);
        $account = new User(['uid' => 1, 'name' => 'Test']);

        $_SESSION = [];
        $response = $controller->toggleElder([], [], $account, HttpRequest::create('/account/elder-toggle', 'POST'));

        $this->assertSame(302, $response->statusCode);
        $this->assertSame('/account', $response->headers['Location']);
        $this->assertTrue($user->isElder());
    }

    #[Test]
    public function toggle_elder_unsets_elder_when_already_elder(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $storage = $this->createMock(EntityStorageInterface::class);

        $user = new User(['uid' => 1, 'name' => 'Test', 'is_elder' => 1]);
        $this->assertTrue($user->isElder());

        $etm->method('getStorage')->with('user')->willReturn($storage);
        $storage->method('load')->with(1)->willReturn($user);
        $storage->expects($this->once())->method('save')->with($user);

        $controller = $this->createController(etm: $etm);
        $account = new User(['uid' => 1, 'name' => 'Test', 'is_elder' => 1]);

        $_SESSION = [];
        $response = $controller->toggleElder([], [], $account, HttpRequest::create('/account/elder-toggle', 'POST'));

        $this->assertSame(302, $response->statusCode);
        $this->assertFalse($user->isElder());
    }
}

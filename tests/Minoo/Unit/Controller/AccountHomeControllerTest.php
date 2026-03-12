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

#[CoversClass(AccountHomeController::class)]
final class AccountHomeControllerTest extends TestCase
{
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

        $controller = new AccountHomeController($twig);
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

        $controller = new AccountHomeController($twig);
        $response = $controller->index([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->statusCode);
    }
}

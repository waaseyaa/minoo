<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\AuthController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(AuthController::class)]
final class AuthControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private EntityStorageInterface $userStorage;
    private EntityQueryInterface $query;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->query = $this->createMock(EntityQueryInterface::class);
        $this->query->method('condition')->willReturnSelf();
        $this->query->method('sort')->willReturnSelf();
        $this->query->method('range')->willReturnSelf();

        $this->userStorage = $this->createMock(EntityStorageInterface::class);
        $this->userStorage->method('getQuery')->willReturn($this->query);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->with('user')
            ->willReturn($this->userStorage);

        $this->twig = new Environment(new ArrayLoader([
            'auth/login.html.twig' => '{{ errors.email|default("") }}',
            'auth/register.html.twig' => '{{ errors.name|default("") }}{{ errors.email|default("") }}{{ errors.password|default("") }}',
        ]));

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function login_form_returns_200(): void
    {
        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->loginForm([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function register_form_returns_200(): void
    {
        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->registerForm([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function submit_login_with_empty_email_shows_error(): void
    {
        $this->request = HttpRequest::create('/login', 'POST', ['email' => '', 'password' => '']);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitLogin([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Email is required', $response->content);
    }

    #[Test]
    public function submit_login_with_unknown_email_shows_error(): void
    {
        $this->query->method('execute')->willReturn([]);
        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'nobody@example.com',
            'password' => 'secret123',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitLogin([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Invalid email or password', $response->content);
    }

    #[Test]
    public function submit_register_with_empty_fields_shows_errors(): void
    {
        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => '',
            'email' => '',
            'password' => '',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitRegister([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Name is required', $response->content);
    }

    #[Test]
    public function submit_register_with_short_password_shows_error(): void
    {
        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => 'Mary',
            'email' => 'mary@example.com',
            'password' => 'short',
        ]);
        $this->query->method('execute')->willReturn([]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitRegister([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('at least 8 characters', $response->content);
    }

    #[Test]
    public function submit_register_with_duplicate_email_shows_error(): void
    {
        $this->query->method('execute')->willReturn([1]);
        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => 'Mary',
            'email' => 'mary@example.com',
            'password' => 'password123',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitRegister([], [], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('already registered', $response->content);
    }

    #[Test]
    public function logout_redirects_to_home(): void
    {
        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->logout([], [], $this->account, $this->request);

        $this->assertSame(302, $response->statusCode);
        $this->assertSame('/', $response->headers['Location']);
    }
}

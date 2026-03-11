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
use Waaseyaa\User\User;
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
        $_SESSION = [];

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

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function submit_login_redirects_to_redirect_param_after_success(): void
    {
        $user = $this->createSuccessfulUser();

        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'mary@example.com',
            'password' => 'password123',
            'redirect' => '/elders/request/42',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitLogin([], [], $this->account, $this->request);

        self::assertSame(302, $response->statusCode);
        self::assertSame('/elders/request/42', $response->headers['Location']);
    }

    #[Test]
    public function submit_login_falls_back_to_dashboard_when_no_redirect(): void
    {
        $user = $this->createSuccessfulUser();

        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'mary@example.com',
            'password' => 'password123',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitLogin([], [], $this->account, $this->request);

        self::assertSame(302, $response->statusCode);
        self::assertSame('/dashboard/volunteer', $response->headers['Location']);
    }

    #[Test]
    public function submit_login_rejects_absolute_url_redirect(): void
    {
        $user = $this->createSuccessfulUser();

        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'mary@example.com',
            'password' => 'password123',
            'redirect' => 'https://evil.com/phish',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitLogin([], [], $this->account, $this->request);

        self::assertSame(302, $response->statusCode);
        self::assertSame('/dashboard/volunteer', $response->headers['Location']);
    }

    #[Test]
    public function submit_login_rejects_protocol_relative_redirect(): void
    {
        $user = $this->createSuccessfulUser();

        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'mary@example.com',
            'password' => 'password123',
            'redirect' => '//evil.com/phish',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitLogin([], [], $this->account, $this->request);

        self::assertSame(302, $response->statusCode);
        self::assertSame('/dashboard/volunteer', $response->headers['Location']);
    }

    #[Test]
    public function submit_register_creates_volunteer_entity_for_new_account(): void
    {
        $this->query->method('execute')->willReturn([]);

        $savedUser = null;
        $this->userStorage->method('create')->willReturnCallback(function (array $values) use (&$savedUser) {
            $savedUser = new User($values + ['uid' => 42]);
            return $savedUser;
        });
        $this->userStorage->method('save')->willReturn(42);

        $volStorage = $this->createMock(EntityStorageInterface::class);
        $volEntity = null;
        $volStorage->method('create')->willReturnCallback(function (array $values) use (&$volEntity) {
            $volEntity = $values;
            $entity = $this->createMock(\Waaseyaa\Entity\ContentEntityBase::class);
            return $entity;
        });
        $volStorage->method('save')->willReturn(1);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')->willReturnMap([
            ['user', $this->userStorage],
            ['volunteer', $volStorage],
        ]);

        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => 'Mary',
            'email' => 'mary@example.com',
            'password' => 'password123',
            'phone' => '705-555-1234',
        ]);

        $controller = new AuthController($this->entityTypeManager, $this->twig);
        $response = $controller->submitRegister([], [], $this->account, $this->request);

        self::assertSame(302, $response->statusCode);
        self::assertNotNull($volEntity, 'Volunteer entity should be created');
        self::assertSame(42, $volEntity['account_id']);
        self::assertSame('Mary', $volEntity['name']);
        self::assertSame('705-555-1234', $volEntity['phone']);
    }

    private function createSuccessfulUser(): User
    {
        $user = new User([
            'uid' => 1,
            'name' => 'Mary',
            'mail' => 'mary@example.com',
            'roles' => ['volunteer'],
            'status' => 1,
        ]);
        $user->setRawPassword('password123');

        $this->query->method('execute')->willReturn([1]);
        $this->userStorage->method('load')->with(1)->willReturn($user);

        return $user;
    }
}

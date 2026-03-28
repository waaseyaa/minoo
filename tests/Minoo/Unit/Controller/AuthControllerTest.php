<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\AuthController;
use Waaseyaa\User\AuthMailer;
use Minoo\Support\EmailVerificationService;
use Waaseyaa\User\PasswordResetTokenRepository;
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
    private AuthMailer $authMailer;
    private PasswordResetTokenRepository $passwordResetService;
    private EmailVerificationService $emailVerificationService;
    private EntityStorageInterface $userStorage;
    private EntityQueryInterface $query;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        putenv('WAASEYAA_DB=:memory:');
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
            'auth/forgot-password.html.twig' => '{% if submitted|default(false) %}submitted{% endif %}',
            'auth/check-email.html.twig' => 'check-email',
            'auth/verify-email.html.twig' => '{% if verified %}verified{% else %}{{ error }}{% endif %}',
        ]));

        $this->authMailer = $this->createMock(AuthMailer::class);

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->passwordResetService = new PasswordResetTokenRepository($pdo);
        $this->emailVerificationService = new EmailVerificationService($pdo);

        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        putenv('WAASEYAA_DB');
    }

    private function createController(): AuthController
    {
        return new AuthController(
            $this->entityTypeManager,
            $this->twig,
            $this->authMailer,
            $this->passwordResetService,
            $this->emailVerificationService,
        );
    }

    #[Test]
    public function submit_login_redirects_to_redirect_param_after_success(): void
    {
        $this->createSuccessfulUser();

        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'mary@example.com',
            'password' => 'password123',
            'redirect' => '/elders/request/42',
        ]);

        $response = $this->createController()->submitLogin([], [], $this->account, $this->request);

        self::assertSame(302, $response->statusCode);
        self::assertSame('/elders/request/42', $response->headers['Location']);
    }

    #[Test]
    public function submit_login_falls_back_to_homepage_when_no_redirect(): void
    {
        $this->createSuccessfulUser();

        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'mary@example.com',
            'password' => 'password123',
        ]);

        $response = $this->createController()->submitLogin([], [], $this->account, $this->request);

        self::assertSame(302, $response->statusCode);
        self::assertSame('/', $response->headers['Location']);
    }

    #[Test]
    public function submit_login_rejects_absolute_url_redirect(): void
    {
        $this->createSuccessfulUser();

        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'mary@example.com',
            'password' => 'password123',
            'redirect' => 'https://evil.com/phish',
        ]);

        $response = $this->createController()->submitLogin([], [], $this->account, $this->request);

        self::assertSame(302, $response->statusCode);
        self::assertSame('/', $response->headers['Location']);
    }

    #[Test]
    public function submit_login_rejects_protocol_relative_redirect(): void
    {
        $this->createSuccessfulUser();

        $this->request = HttpRequest::create('/login', 'POST', [
            'email' => 'mary@example.com',
            'password' => 'password123',
            'redirect' => '//evil.com/phish',
        ]);

        $response = $this->createController()->submitLogin([], [], $this->account, $this->request);

        self::assertSame(302, $response->statusCode);
        self::assertSame('/', $response->headers['Location']);
    }

    #[Test]
    public function submit_register_creates_active_user_and_auto_logins(): void
    {
        $this->query->method('execute')->willReturn([]);

        $this->userStorage->method('create')->willReturnCallback(function (array $values) {
            return new User($values + ['uid' => 42]);
        });
        $this->userStorage->method('save')->willReturn(42);

        $this->authMailer->expects(self::once())
            ->method('sendEmailVerification');

        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => 'Mary',
            'email' => 'mary@example.com',
            'password' => 'password123',
            'phone' => '705-555-1234',
        ]);

        $response = $this->createController()->submitRegister([], [], $this->account, $this->request);

        // Should auto-login and redirect to home
        self::assertSame(302, $response->statusCode);
        self::assertSame('/', $response->headers['Location']);
        // Session should be set (auto-login)
        self::assertSame(42, $_SESSION['waaseyaa_uid']);
    }

    #[Test]
    public function submit_forgot_password_sends_reset_email_for_valid_user(): void
    {
        $user = new User(['uid' => 1, 'name' => 'Mary', 'mail' => 'mary@example.com', 'status' => 1]);

        $this->query->method('execute')->willReturn([1]);
        $this->userStorage->method('load')->willReturn($user);

        $this->authMailer->expects(self::once())
            ->method('sendPasswordReset');

        $this->request = HttpRequest::create('/forgot-password', 'POST', ['email' => 'mary@example.com']);

        $response = $this->createController()->submitForgotPassword([], [], $this->account, $this->request);

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('submitted', $response->content);
    }

    #[Test]
    public function submit_forgot_password_does_not_send_email_for_unknown_user(): void
    {
        $this->query->method('execute')->willReturn([]);

        $this->authMailer->expects(self::never())
            ->method('sendPasswordReset');

        $this->request = HttpRequest::create('/forgot-password', 'POST', ['email' => 'nobody@example.com']);

        $response = $this->createController()->submitForgotPassword([], [], $this->account, $this->request);

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('submitted', $response->content);
    }

    #[Test]
    public function verify_email_with_missing_token_returns_400(): void
    {
        $this->request = HttpRequest::create('/verify-email', 'GET');

        $response = $this->createController()->verifyEmail([], [], $this->account, $this->request);

        self::assertSame(400, $response->statusCode);
        self::assertStringContainsString('invalid or has expired', $response->content);
    }

    #[Test]
    public function verify_email_with_valid_token_activates_user_and_sends_welcome(): void
    {
        $user = new User([
            'uid' => 99,
            'name' => 'New User',
            'mail' => 'new@example.com',
            'status' => 0,
        ]);

        // Create a real token in the in-memory DB
        $token = $this->emailVerificationService->createToken('99');

        $this->query->method('execute')->willReturn([99]);
        $this->userStorage->method('load')->with('99')->willReturn($user);
        $this->userStorage->expects(self::once())->method('save');

        $this->authMailer->expects(self::once())
            ->method('sendWelcome')
            ->with($user);

        $this->request = HttpRequest::create('/verify-email?token=' . $token, 'GET', ['token' => $token]);

        $response = $this->createController()->verifyEmail([], [], $this->account, $this->request);

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('verified', $response->content);
        self::assertSame(99, $_SESSION['waaseyaa_uid']);
    }

    #[Test]
    public function register_with_existing_inactive_email_resends_verification(): void
    {
        $existingUser = new User([
            'uid' => 50,
            'name' => 'Inactive',
            'mail' => 'inactive@example.com',
            'status' => 0,
        ]);

        $this->query->method('execute')->willReturn([50]);
        $this->userStorage->method('load')->with(50)->willReturn($existingUser);

        $this->authMailer->expects(self::once())
            ->method('sendEmailVerification');

        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => 'Inactive',
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response = $this->createController()->submitRegister([], [], $this->account, $this->request);

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('check-email', $response->content);
    }

    #[Test]
    public function register_with_existing_active_email_shows_check_email_page(): void
    {
        $activeUser = new User([
            'uid' => 51,
            'name' => 'Active',
            'mail' => 'active@example.com',
            'status' => 1,
        ]);

        $this->query->method('execute')->willReturn([51]);
        $this->userStorage->method('load')->with(51)->willReturn($activeUser);

        $this->authMailer->expects(self::never())
            ->method('sendEmailVerification');

        $this->request = HttpRequest::create('/register', 'POST', [
            'name' => 'Active',
            'email' => 'active@example.com',
            'password' => 'password123',
        ]);

        $response = $this->createController()->submitRegister([], [], $this->account, $this->request);

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('check-email', $response->content);
    }

    private function createSuccessfulUser(): User
    {
        $user = new User([
            'uid' => 1,
            'name' => 'Mary',
            'mail' => 'mary@example.com',
            'roles' => [],
            'status' => 1,
        ]);
        $user->setRawPassword('password123');

        $this->query->method('execute')->willReturn([1]);
        $this->userStorage->method('load')->with(1)->willReturn($user);

        return $user;
    }
}

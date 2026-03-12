<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Minoo\Middleware\RateLimitMiddleware;
use Minoo\Support\PasswordResetService;
use Waaseyaa\SSR\SsrResponse;
use Waaseyaa\User\User;

final class AuthController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function loginForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('auth/login.html.twig', [
            'errors' => [],
            'values' => [],
            'redirect' => (string) $request->query->get('redirect', ''),
        ]);

        return new SsrResponse(content: $html);
    }

    public function submitLogin(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $limiter = new RateLimitMiddleware(
            getenv('WAASEYAA_DB') ?: dirname(__DIR__, 2) . '/waaseyaa.sqlite'
        );
        $ip = $request->getClientIp() ?? '0.0.0.0';

        if (!$limiter->check($ip, '/login', 5, 300)) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'Too many attempts. Please try again in 5 minutes.'],
                'values' => [],
            ]);
            return new SsrResponse(content: $html, statusCode: 429);
        }
        $limiter->record($ip, '/login');

        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');

        $errors = [];
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if ($errors !== []) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => $errors,
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $ids = $storage->getQuery()
            ->condition('mail', $email)
            ->execute();

        if ($ids === []) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'Invalid email or password.'],
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        /** @var User|null $user */
        $user = $storage->load(reset($ids));

        if ($user === null || !$user->checkPassword($password)) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'Invalid email or password.'],
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        if (!$user->isActive()) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'This account has been deactivated.'],
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        $_SESSION['waaseyaa_uid'] = $user->id();

        $redirect = $this->safeRedirect(
            (string) $request->request->get('redirect', ''),
            $this->dashboardRedirect($user),
        );

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $redirect]);
    }

    public function registerForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('auth/register.html.twig', [
            'errors' => [],
            'values' => [],
        ]);

        return new SsrResponse(content: $html);
    }

    public function submitRegister(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        $phone = trim((string) $request->request->get('phone', ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($errors !== []) {
            $html = $this->twig->render('auth/register.html.twig', [
                'errors' => $errors,
                'values' => compact('name', 'email', 'phone'),
            ]);
            return new SsrResponse(content: $html);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $existing = $storage->getQuery()
            ->condition('mail', $email)
            ->execute();

        if ($existing !== []) {
            $html = $this->twig->render('auth/register.html.twig', [
                'errors' => ['email' => 'This email is already registered.'],
                'values' => compact('name', 'email', 'phone'),
            ]);
            return new SsrResponse(content: $html);
        }

        /** @var User $user */
        $user = $storage->create([
            'name' => $name,
            'mail' => $email,
            'status' => true,
            'created' => time(),
            'roles' => ['volunteer'],
            'permissions' => ['view own assignments', 'update assignment status'],
        ]);
        $user->setRawPassword($password);

        if ($phone !== '') {
            $user->set('phone', $phone);
        }

        $storage->save($user);

        $volStorage = $this->entityTypeManager->getStorage('volunteer');
        $volunteer = $volStorage->create([
            'name' => $name,
            'phone' => $phone,
            'account_id' => $user->id(),
            'status' => 'active',
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $volStorage->save($volunteer);

        $_SESSION['waaseyaa_uid'] = $user->id();

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/volunteer']);
    }

    public function forgotPasswordForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('auth/forgot-password.html.twig', [
            'values' => [],
        ]);

        return new SsrResponse(content: $html);
    }

    public function submitForgotPassword(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $limiter = new RateLimitMiddleware(
            getenv('WAASEYAA_DB') ?: dirname(__DIR__, 2) . '/waaseyaa.sqlite'
        );
        $ip = $request->getClientIp() ?? '0.0.0.0';

        if (!$limiter->check($ip, '/forgot-password', 3, 300)) {
            $html = $this->twig->render('auth/forgot-password.html.twig', [
                'submitted' => true,
                'reset_url' => null,
                'values' => [],
            ]);
            return new SsrResponse(content: $html, statusCode: 429);
        }
        $limiter->record($ip, '/forgot-password');

        $email = trim((string) $request->request->get('email', ''));

        $resetUrl = null;
        if ($email !== '') {
            $storage = $this->entityTypeManager->getStorage('user');
            $ids = $storage->getQuery()
                ->condition('mail', $email)
                ->execute();

            if ($ids !== []) {
                $user = $storage->load(reset($ids));
                if ($user !== null) {
                    $resetService = $this->createPasswordResetService();
                    $token = $resetService->createToken($user->id());
                    $resetUrl = '/reset-password?token=' . $token;
                }
            }
        }

        $html = $this->twig->render('auth/forgot-password.html.twig', [
            'submitted' => true,
            'reset_url' => $resetUrl,
            'values' => compact('email'),
        ]);

        return new SsrResponse(content: $html);
    }

    public function resetPasswordForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $token = (string) $request->query->get('token', '');
        $tokenError = null;

        if ($token === '') {
            $tokenError = 'No reset token provided.';
        } else {
            $resetService = $this->createPasswordResetService();
            $userId = $resetService->validateToken($token);
            if ($userId === null) {
                $tokenError = 'This reset link is invalid or has expired.';
            }
        }

        $html = $this->twig->render('auth/reset-password.html.twig', [
            'token' => $token,
            'token_error' => $tokenError,
            'errors' => [],
        ]);

        return new SsrResponse(content: $html);
    }

    public function submitResetPassword(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $token = (string) $request->request->get('token', '');
        $password = (string) $request->request->get('password', '');
        $passwordConfirm = (string) $request->request->get('password_confirm', '');

        $resetService = $this->createPasswordResetService();
        $userId = $resetService->validateToken($token);

        if ($userId === null) {
            $html = $this->twig->render('auth/reset-password.html.twig', [
                'token' => $token,
                'token_error' => 'This reset link is invalid or has expired.',
                'errors' => [],
            ]);
            return new SsrResponse(content: $html);
        }

        $errors = [];
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            $html = $this->twig->render('auth/reset-password.html.twig', [
                'token' => $token,
                'token_error' => null,
                'errors' => $errors,
            ]);
            return new SsrResponse(content: $html);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        /** @var User|null $user */
        $user = $storage->load($userId);

        if ($user === null) {
            $html = $this->twig->render('auth/reset-password.html.twig', [
                'token' => $token,
                'token_error' => 'User account not found.',
                'errors' => [],
            ]);
            return new SsrResponse(content: $html);
        }

        $user->setRawPassword($password);
        $storage->save($user);
        $resetService->consumeToken($token);

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/login?reset=success']);
    }

    public function logout(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/']);
    }

    private function safeRedirect(string $target, string $fallback): string
    {
        if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return $fallback;
        }

        return $target;
    }

    private function createPasswordResetService(): PasswordResetService
    {
        $projectRoot = dirname(__DIR__, 2);
        $dbPath = getenv('WAASEYAA_DB') ?: $projectRoot . '/waaseyaa.sqlite';
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return new PasswordResetService($pdo);
    }

    private function dashboardRedirect(User $user): string
    {
        $roles = $user->getRoles();

        if (in_array('elder_coordinator', $roles, true)) {
            return '/dashboard/coordinator';
        }

        if (in_array('volunteer', $roles, true)) {
            return '/dashboard/volunteer';
        }

        return '/account';
    }
}

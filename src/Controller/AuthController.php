<?php

declare(strict_types=1);

namespace App\Controller;

use Waaseyaa\Auth\Config\AuthConfig;
use Waaseyaa\Auth\Token\AuthTokenRepositoryInterface;
use Waaseyaa\User\AuthMailer;
use App\Support\LayoutTwigContext;
use Waaseyaa\SSR\Flash\Flash;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use App\Middleware\RateLimitMiddleware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\User\User;

final class AuthController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly AuthMailer $authMailer,
        private readonly AuthTokenRepositoryInterface $tokenRepo,
        private readonly AuthConfig $authConfig,
    ) {}

    public function loginForm(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('auth/login.html.twig', LayoutTwigContext::withAccount($account, [
            'errors' => [],
            'values' => [],
            'redirect' => (string) $request->query->get('redirect', ''),
        ]));

        return new Response($html);
    }

    public function submitLogin(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $limiter = new RateLimitMiddleware(
            getenv('WAASEYAA_DB') ?: dirname(__DIR__, 2) . '/storage/waaseyaa.sqlite'
        );
        $ip = $request->getClientIp() ?? '0.0.0.0';

        if (!$limiter->check($ip, '/login', 5, 300)) {
            $html = $this->twig->render('auth/login.html.twig', LayoutTwigContext::withAccount($account, [
                'errors' => ['email' => 'Too many attempts. Please try again in 5 minutes.'],
                'values' => [],
            ]));
            return new Response($html, 429);
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
            $html = $this->twig->render('auth/login.html.twig', LayoutTwigContext::withAccount($account, [
                'errors' => $errors,
                'values' => compact('email'),
            ]));
            return new Response($html);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $ids = $storage->getQuery()
            ->condition('mail', $email)
            ->execute();

        if ($ids === []) {
            $html = $this->twig->render('auth/login.html.twig', LayoutTwigContext::withAccount($account, [
                'errors' => ['email' => 'Invalid email or password.'],
                'values' => compact('email'),
            ]));
            return new Response($html);
        }

        /** @var User|null $user */
        $user = $storage->load(reset($ids));

        if ($user === null || !$user->checkPassword($password)) {
            $html = $this->twig->render('auth/login.html.twig', LayoutTwigContext::withAccount($account, [
                'errors' => ['email' => 'Invalid email or password.'],
                'values' => compact('email'),
            ]));
            return new Response($html);
        }

        if (!$user->isActive()) {
            $html = $this->twig->render('auth/login.html.twig', LayoutTwigContext::withAccount($account, [
                'errors' => ['email' => 'This account has been deactivated.'],
                'values' => compact('email'),
            ]));
            return new Response($html);
        }

        $_SESSION['waaseyaa_uid'] = $user->id();

        Flash::success('Welcome back, ' . $user->get('name') . '.');

        $redirect = $this->safeRedirect(
            (string) $request->request->get('redirect', ''),
            '/',
        );

        return new RedirectResponse($redirect);
    }

    public function registerForm(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('auth/register.html.twig', LayoutTwigContext::withAccount($account, [
            'errors' => [],
            'values' => [],
        ]));

        return new Response($html);
    }

    public function submitRegister(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
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
            $html = $this->twig->render('auth/register.html.twig', LayoutTwigContext::withAccount($account, [
                'errors' => $errors,
                'values' => compact('name', 'email', 'phone'),
            ]));
            return new Response($html);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $existing = $storage->getQuery()
            ->condition('mail', $email)
            ->execute();

        if ($existing !== []) {
            /** @var User|null $existingUser */
            $existingUser = $storage->load(reset($existing));

            // If inactive (unverified), re-send verification email instead of revealing existence
            if ($existingUser !== null && !$existingUser->isActive()) {
                $token = $this->tokenRepo->createToken($existingUser->id(), 'email_verification', $this->authConfig->tokenTtl('email_verification'));
                $this->authMailer->sendEmailVerification($existingUser, $token);
                $html = $this->twig->render('auth/check-email.html.twig', LayoutTwigContext::withAccount($account, []));
                return new Response($html);
            }

            // Active account — show generic check-email page to prevent enumeration
            $html = $this->twig->render('auth/check-email.html.twig', LayoutTwigContext::withAccount($account, []));
            return new Response($html);
        }

        /** @var User $user */
        $user = $storage->create([
            'name' => $name,
            'mail' => $email,
            'status' => true,
            'created' => time(),
            'roles' => [],
            'permissions' => [],
        ]);
        $user->setRawPassword($password);

        if ($phone !== '') {
            $user->set('phone', $phone);
        }

        $storage->save($user);

        // Send welcome email (non-blocking — account is already active)
        $token = $this->tokenRepo->createToken($user->id(), 'email_verification', $this->authConfig->tokenTtl('email_verification'));
        $this->authMailer->sendEmailVerification($user, $token);

        // Auto-login
        $_SESSION['waaseyaa_uid'] = $user->id();

        Flash::success('Welcome to Minoo, ' . $name . '.');

        return new RedirectResponse('/');
    }

    public function forgotPasswordForm(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('auth/forgot-password.html.twig', LayoutTwigContext::withAccount($account, [
            'values' => [],
        ]));

        return new Response($html);
    }

    public function submitForgotPassword(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $limiter = new RateLimitMiddleware(
            getenv('WAASEYAA_DB') ?: dirname(__DIR__, 2) . '/storage/waaseyaa.sqlite'
        );
        $ip = $request->getClientIp() ?? '0.0.0.0';

        if (!$limiter->check($ip, '/forgot-password', 3, 300)) {
            // Return 200 even when rate-limited to prevent enumeration via status codes
            $html = $this->twig->render('auth/forgot-password.html.twig', LayoutTwigContext::withAccount($account, [
                'submitted' => true,
                'values' => [],
            ]));
            return new Response($html);
        }
        $limiter->record($ip, '/forgot-password');

        $email = trim((string) $request->request->get('email', ''));

        if ($email !== '') {
            $storage = $this->entityTypeManager->getStorage('user');
            $ids = $storage->getQuery()
                ->condition('mail', $email)
                ->execute();

            if ($ids !== []) {
                /** @var User|null $user */
                $user = $storage->load(reset($ids));
                if ($user !== null) {
                    $token = $this->tokenRepo->createToken($user->id(), 'password_reset', $this->authConfig->tokenTtl('password_reset'));
                    $this->authMailer->sendPasswordReset($user, $token);
                }
            }
        }

        // Always show the same message (prevents user enumeration)
        $html = $this->twig->render('auth/forgot-password.html.twig', LayoutTwigContext::withAccount($account, [
            'submitted' => true,
            'values' => compact('email'),
        ]));

        return new Response($html);
    }

    public function resetPasswordForm(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $token = (string) $request->query->get('token', '');
        $tokenError = null;

        if ($token === '') {
            $tokenError = 'No reset token provided.';
        } else {
            $result = $this->tokenRepo->validateToken($token, 'password_reset');
            if ($result === null) {
                $tokenError = 'This reset link is invalid or has expired.';
            }
        }

        $html = $this->twig->render('auth/reset-password.html.twig', LayoutTwigContext::withAccount($account, [
            'token' => $token,
            'token_error' => $tokenError,
            'errors' => [],
        ]));

        return new Response($html);
    }

    public function submitResetPassword(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $token = (string) $request->request->get('token', '');
        $password = (string) $request->request->get('password', '');
        $passwordConfirm = (string) $request->request->get('password_confirm', '');

        $result = $this->tokenRepo->validateToken($token, 'password_reset');

        if ($result === null) {
            $html = $this->twig->render('auth/reset-password.html.twig', LayoutTwigContext::withAccount($account, [
                'token' => $token,
                'token_error' => 'This reset link is invalid or has expired.',
                'errors' => [],
            ]));
            return new Response($html);
        }

        $userId = $result['user_id'];

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
            $html = $this->twig->render('auth/reset-password.html.twig', LayoutTwigContext::withAccount($account, [
                'token' => $token,
                'token_error' => null,
                'errors' => $errors,
            ]));
            return new Response($html);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        /** @var User|null $user */
        $user = $storage->load($userId);

        if ($user === null) {
            $html = $this->twig->render('auth/reset-password.html.twig', LayoutTwigContext::withAccount($account, [
                'token' => $token,
                'token_error' => 'User account not found.',
                'errors' => [],
            ]));
            return new Response($html);
        }

        $user->setRawPassword($password);
        $storage->save($user);
        $this->tokenRepo->consumeToken($result['id']);

        Flash::success('Your password has been reset. Please sign in.');

        return new RedirectResponse('/login');
    }

    public function verifyEmail(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $token = (string) $request->query->get('token', '');

        if ($token === '') {
            $html = $this->twig->render('auth/verify-email.html.twig', LayoutTwigContext::withAccount($account, [
                'verified' => false,
                'error' => 'This verification link is invalid or has expired.',
            ]));
            return new Response($html, 400);
        }

        $result = $this->tokenRepo->validateToken($token, 'email_verification');

        if ($result === null) {
            $html = $this->twig->render('auth/verify-email.html.twig', LayoutTwigContext::withAccount($account, [
                'verified' => false,
                'error' => 'This verification link is invalid or has expired.',
            ]));
            return new Response($html, 400);
        }

        $userId = $result['user_id'];
        $storage = $this->entityTypeManager->getStorage('user');
        /** @var User|null $user */
        $user = $storage->load($userId);

        if ($user === null) {
            $html = $this->twig->render('auth/verify-email.html.twig', LayoutTwigContext::withAccount($account, [
                'verified' => false,
                'error' => 'User account not found.',
            ]));
            return new Response($html, 404);
        }

        $user->set('status', true);
        $storage->save($user);
        $this->tokenRepo->consumeToken($result['id']);

        $this->authMailer->sendWelcome($user);

        $_SESSION['waaseyaa_uid'] = $user->id();

        Flash::success('Your email is verified and your account is active. Welcome to Minoo.');

        $html = $this->twig->render('auth/verify-email.html.twig', LayoutTwigContext::withAccount($account, [
            'verified' => true,
        ]));
        return new Response($html);
    }

    public function logout(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return new RedirectResponse('/');
    }

    private function safeRedirect(string $target, string $fallback): string
    {
        if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return $fallback;
        }

        return $target;
    }

}

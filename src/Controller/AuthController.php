<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
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

    private function dashboardRedirect(User $user): string
    {
        $roles = $user->getRoles();

        if (in_array('elder_coordinator', $roles, true)) {
            return '/dashboard/coordinator';
        }

        if (in_array('volunteer', $roles, true)) {
            return '/dashboard/volunteer';
        }

        return '/';
    }
}

<?php

declare(strict_types=1);

namespace Minoo\Provider;

use Minoo\Support\EmailVerificationService;
use Waaseyaa\User\PasswordResetTokenRepository;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(PasswordResetTokenRepository::class, function () {
            return new PasswordResetTokenRepository($this->createPdo());
        });

        $this->singleton(EmailVerificationService::class, function () {
            return new EmailVerificationService($this->createPdo());
        });
    }

    private function createPdo(): \PDO
    {
        $projectRoot = $this->config['app_root'] ?? dirname(__DIR__, 2);
        $dbPath = getenv('WAASEYAA_DB') ?: $projectRoot . '/storage/waaseyaa.sqlite';
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'auth.login_form',
            RouteBuilder::create('/login')
                ->controller('Minoo\Controller\AuthController::loginForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.login_submit',
            RouteBuilder::create('/login')
                ->controller('Minoo\Controller\AuthController::submitLogin')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.register_form',
            RouteBuilder::create('/register')
                ->controller('Minoo\Controller\AuthController::registerForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.register_submit',
            RouteBuilder::create('/register')
                ->controller('Minoo\Controller\AuthController::submitRegister')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.logout',
            RouteBuilder::create('/logout')
                ->controller('Minoo\Controller\AuthController::logout')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.forgot_password_form',
            RouteBuilder::create('/forgot-password')
                ->controller('Minoo\Controller\AuthController::forgotPasswordForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.forgot_password_submit',
            RouteBuilder::create('/forgot-password')
                ->controller('Minoo\Controller\AuthController::submitForgotPassword')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.reset_password_form',
            RouteBuilder::create('/reset-password')
                ->controller('Minoo\Controller\AuthController::resetPasswordForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.reset_password_submit',
            RouteBuilder::create('/reset-password')
                ->controller('Minoo\Controller\AuthController::submitResetPassword')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.verify_email',
            RouteBuilder::create('/verify-email')
                ->controller('Minoo\Controller\AuthController::verifyEmail')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}

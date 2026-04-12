<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Token repository and auth config are registered by the framework's
        // Waaseyaa\Auth\AuthServiceProvider. No custom bindings needed.
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'auth.login_form',
            RouteBuilder::create('/login')
                ->controller('App\Controller\AuthController::loginForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.login_submit',
            RouteBuilder::create('/login')
                ->controller('App\Controller\AuthController::submitLogin')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.register_form',
            RouteBuilder::create('/register')
                ->controller('App\Controller\AuthController::registerForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.register_submit',
            RouteBuilder::create('/register')
                ->controller('App\Controller\AuthController::submitRegister')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.logout',
            RouteBuilder::create('/logout')
                ->controller('App\Controller\AuthController::logout')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.forgot_password_form',
            RouteBuilder::create('/forgot-password')
                ->controller('App\Controller\AuthController::forgotPasswordForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.forgot_password_submit',
            RouteBuilder::create('/forgot-password')
                ->controller('App\Controller\AuthController::submitForgotPassword')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.reset_password_form',
            RouteBuilder::create('/reset-password')
                ->controller('App\Controller\AuthController::resetPasswordForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.reset_password_submit',
            RouteBuilder::create('/reset-password')
                ->controller('App\Controller\AuthController::submitResetPassword')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.verify_email',
            RouteBuilder::create('/verify-email')
                ->controller('App\Controller\AuthController::verifyEmail')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}

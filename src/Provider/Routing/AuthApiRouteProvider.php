<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AuthApiRouteProvider extends AppCoreServiceProvider
{
    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // =====================================================================
        // --- Auth ---
        // =====================================================================

        $router->addRoute(
            'auth.login_form',
            RouteBuilder::create('/login')
                ->controller('App\Http\Controller\AuthController::loginForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.login_submit',
            RouteBuilder::create('/login')
                ->controller('App\Http\Controller\AuthController::submitLogin')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.register_form',
            RouteBuilder::create('/register')
                ->controller('App\Http\Controller\AuthController::registerForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.register_submit',
            RouteBuilder::create('/register')
                ->controller('App\Http\Controller\AuthController::submitRegister')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.logout',
            RouteBuilder::create('/logout')
                ->controller('App\Http\Controller\AuthController::logout')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.forgot_password_form',
            RouteBuilder::create('/forgot-password')
                ->controller('App\Http\Controller\AuthController::forgotPasswordForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.forgot_password_submit',
            RouteBuilder::create('/forgot-password')
                ->controller('App\Http\Controller\AuthController::submitForgotPassword')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.reset_password_form',
            RouteBuilder::create('/reset-password')
                ->controller('App\Http\Controller\AuthController::resetPasswordForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.reset_password_submit',
            RouteBuilder::create('/reset-password')
                ->controller('App\Http\Controller\AuthController::submitResetPassword')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.verify_email',
            RouteBuilder::create('/verify-email')
                ->controller('App\Http\Controller\AuthController::verifyEmail')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );
    }
}

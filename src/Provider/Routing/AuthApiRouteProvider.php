<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\AppCoreServiceProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
                ->controller('App\Http\Controller\Auth\AuthController::loginForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.login_submit',
            RouteBuilder::create('/login')
                ->controller('App\Http\Controller\Auth\AuthController::submitLogin')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.register_form',
            RouteBuilder::create('/register')
                ->controller('App\Http\Controller\Auth\AuthController::registerForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.register_submit',
            RouteBuilder::create('/register')
                ->controller('App\Http\Controller\Auth\AuthController::submitRegister')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.logout',
            RouteBuilder::create('/logout')
                ->controller('App\Http\Controller\Auth\AuthController::logout')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.forgot_password_form',
            RouteBuilder::create('/forgot-password')
                ->controller('App\Http\Controller\Auth\AuthController::forgotPasswordForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.forgot_password_submit',
            RouteBuilder::create('/forgot-password')
                ->controller('App\Http\Controller\Auth\AuthController::submitForgotPassword')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.reset_password_form',
            RouteBuilder::create('/reset-password')
                ->controller('App\Http\Controller\Auth\AuthController::resetPasswordForm')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'auth.reset_password_submit',
            RouteBuilder::create('/reset-password')
                ->controller('App\Http\Controller\Auth\AuthController::submitResetPassword')
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'auth.verify_email',
            RouteBuilder::create('/verify-email')
                ->controller('App\Http\Controller\Auth\AuthController::verifyEmail')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        // =====================================================================
        // --- Admin SPA auth aliases ---
        // The Nuxt admin SPA is mounted at /admin/ and its $fetch baseURL is
        // '/admin/', so paths like '/api/auth/login' resolve client-side to
        // '/admin/api/auth/login'. The framework only registers the canonical
        // routes at '/api/auth/*'. These 307-redirect aliases let the SPA's
        // username/password flow reach the existing controllers without
        // duplicating their construction wiring.
        // =====================================================================

        $adminAuthAliases = [
            'login' => 'POST',
            'register' => 'POST',
            'logout' => 'POST',
            'forgot-password' => 'POST',
            'reset-password' => 'POST',
            'verify-email' => 'POST',
            'resend-verification' => 'POST',
        ];

        foreach ($adminAuthAliases as $segment => $method) {
            $target = '/api/auth/' . $segment;
            $router->addRoute(
                'admin.api.auth.' . str_replace('-', '_', $segment),
                RouteBuilder::create('/admin/api/auth/' . $segment)
                    ->controller(static fn () => new RedirectResponse($target, 307))
                    ->allowAll()
                    ->methods($method)
                    ->build(),
            );
        }

        $router->addRoute(
            'admin.api.user.me',
            RouteBuilder::create('/admin/api/user/me')
                ->controller(static fn () => new RedirectResponse('/api/user/me', 307))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}

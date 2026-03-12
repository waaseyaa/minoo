<?php

declare(strict_types=1);

namespace Minoo\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Middleware\HttpHandlerInterface;
use Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface;

final class SecurityHeadersMiddleware implements HttpMiddlewareInterface
{
    /** @return array<string, string> */
    public static function headers(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
    }

    public function process(Request $request, HttpHandlerInterface $next): Response
    {
        $response = $next->handle($request);
        foreach (self::headers() as $name => $value) {
            $response->headers->set($name, $value);
        }
        return $response;
    }
}

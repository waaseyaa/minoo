<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityHeadersMiddleware::class)]
final class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function returns_expected_headers(): void
    {
        $headers = SecurityHeadersMiddleware::headers();
        self::assertArrayHasKey('X-Content-Type-Options', $headers);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertArrayHasKey('X-Frame-Options', $headers);
        self::assertSame('DENY', $headers['X-Frame-Options']);
        self::assertArrayHasKey('Referrer-Policy', $headers);
        self::assertArrayHasKey('Permissions-Policy', $headers);
    }
}

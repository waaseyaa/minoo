<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Security;

use Minoo\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitMiddleware::class)]
final class RateLimitTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = ':memory:';
    }

    #[Test]
    public function allows_requests_under_limit(): void
    {
        $limiter = new RateLimitMiddleware($this->dbPath);
        self::assertTrue($limiter->check('127.0.0.1', '/login', 5, 60));
    }

    #[Test]
    public function blocks_requests_over_limit(): void
    {
        $limiter = new RateLimitMiddleware($this->dbPath);
        for ($i = 0; $i < 5; $i++) {
            $limiter->record('127.0.0.1', '/login');
        }
        self::assertFalse($limiter->check('127.0.0.1', '/login', 5, 60));
    }

    #[Test]
    public function different_ips_have_separate_limits(): void
    {
        $limiter = new RateLimitMiddleware($this->dbPath);
        for ($i = 0; $i < 5; $i++) {
            $limiter->record('127.0.0.1', '/login');
        }
        self::assertFalse($limiter->check('127.0.0.1', '/login', 5, 60));
        self::assertTrue($limiter->check('192.168.1.1', '/login', 5, 60));
    }
}

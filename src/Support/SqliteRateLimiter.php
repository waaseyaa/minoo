<?php

declare(strict_types=1);

namespace App\Support;

use App\Contract\RateLimiterInterface;
use App\Middleware\RateLimitMiddleware;

final class SqliteRateLimiter implements RateLimiterInterface
{
    private readonly RateLimitMiddleware $delegate;

    public function __construct(string $dbPath)
    {
        $this->delegate = new RateLimitMiddleware($dbPath);
    }

    public function check(string $ip, string $key, int $limit, int $window): bool
    {
        return $this->delegate->check($ip, $key, $limit, $window);
    }

    public function record(string $ip, string $key): void
    {
        $this->delegate->record($ip, $key);
    }
}

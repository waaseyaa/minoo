<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

final class NullRateLimiter implements RateLimiterInterface
{
    public function check(string $ip, string $key, int $limit, int $window): bool
    {
        return true;
    }

    public function record(string $ip, string $key): void
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Contract;

interface RateLimiterInterface
{
    public function check(string $ip, string $key, int $limit, int $window): bool;

    public function record(string $ip, string $key): void;
}

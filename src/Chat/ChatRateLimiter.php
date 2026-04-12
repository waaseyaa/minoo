<?php

declare(strict_types=1);

namespace App\Chat;

final class ChatRateLimiter
{
    private const SESSION_KEY = 'minoo_chat_requests';

    public function __construct(
        private readonly int $maxRequestsPerMinute,
    ) {}

    public function isAllowed(): bool
    {
        $this->pruneExpired();
        $timestamps = $_SESSION[self::SESSION_KEY] ?? [];

        return count($timestamps) < $this->maxRequestsPerMinute;
    }

    public function record(): void
    {
        $this->pruneExpired();
        $_SESSION[self::SESSION_KEY][] = time();
    }

    public function remainingRequests(): int
    {
        $this->pruneExpired();
        $timestamps = $_SESSION[self::SESSION_KEY] ?? [];

        return max(0, $this->maxRequestsPerMinute - count($timestamps));
    }

    private function pruneExpired(): void
    {
        $cutoff = time() - 60;
        $timestamps = $_SESSION[self::SESSION_KEY] ?? [];
        $_SESSION[self::SESSION_KEY] = array_values(
            array_filter($timestamps, static fn(int $t): bool => $t > $cutoff),
        );
    }
}

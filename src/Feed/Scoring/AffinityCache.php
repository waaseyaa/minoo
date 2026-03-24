<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

/**
 * Per-request memory cache for user affinity scores.
 *
 * Uses a simple in-memory array backend. Acceptable at current scale
 * (affinity is computed once per request). Can be swapped for Redis/Memcached later.
 */
final class AffinityCache
{
    /** @var array<string, array<string, float>> userId => [sourceKey => score] */
    private array $cache = [];

    /**
     * Get cached affinity scores for a user.
     *
     * @return array<string, float>|null null if not cached
     */
    public function get(int $userId): ?array
    {
        return $this->cache[(string) $userId] ?? null;
    }

    /**
     * Store affinity scores for a user.
     *
     * @param array<string, float> $scores sourceKey => affinity score
     */
    public function set(int $userId, array $scores): void
    {
        $this->cache[(string) $userId] = $scores;
    }

    /**
     * Invalidate all cached data for a user.
     */
    public function invalidate(int $userId): void
    {
        unset($this->cache[(string) $userId]);
    }

    /**
     * Clear the entire cache.
     */
    public function clear(): void
    {
        $this->cache = [];
    }
}

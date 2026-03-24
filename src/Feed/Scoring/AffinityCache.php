<?php

declare(strict_types=1);

namespace Minoo\Feed\Scoring;

use Waaseyaa\Cache\CacheBackendInterface;

final class AffinityCache
{
    private const int TTL = 900; // 15 minutes

    public function __construct(private readonly CacheBackendInterface $cache) {}

    /**
     * @return array<string, float>|null
     */
    public function get(int $userId): ?array
    {
        $item = $this->cache->get($this->cid($userId));

        return $item === false ? null : $item->data;
    }

    /**
     * @param array<string, float> $scores
     */
    public function set(int $userId, array $scores): void
    {
        $this->cache->set($this->cid($userId), $scores, time() + self::TTL);
    }

    public function invalidate(int $userId): void
    {
        $this->cache->delete($this->cid($userId));
    }

    private function cid(int $userId): string
    {
        return 'feed_affinity:' . $userId;
    }
}

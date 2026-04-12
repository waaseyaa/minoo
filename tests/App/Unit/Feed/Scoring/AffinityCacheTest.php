<?php

declare(strict_types=1);

namespace App\Tests\Unit\Feed\Scoring;

use App\Feed\Scoring\AffinityCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;

#[CoversClass(AffinityCache::class)]
final class AffinityCacheTest extends TestCase
{
    #[Test]
    public function cache_miss_returns_null(): void
    {
        $cache = new AffinityCache(new MemoryBackend());

        $this->assertNull($cache->get(42));
    }

    #[Test]
    public function set_and_get_round_trips(): void
    {
        $cache = new AffinityCache(new MemoryBackend());
        $scores = ['source_a' => 3.5, 'source_b' => 1.0];

        $cache->set(1, $scores);

        $this->assertSame($scores, $cache->get(1));
    }

    #[Test]
    public function invalidate_removes_cached_scores(): void
    {
        $cache = new AffinityCache(new MemoryBackend());
        $cache->set(1, ['source_a' => 2.0]);

        $cache->invalidate(1);

        $this->assertNull($cache->get(1));
    }

    #[Test]
    public function independent_users_do_not_interfere(): void
    {
        $cache = new AffinityCache(new MemoryBackend());
        $cache->set(1, ['source_a' => 1.0]);
        $cache->set(2, ['source_b' => 5.0]);

        $this->assertSame(['source_a' => 1.0], $cache->get(1));
        $this->assertSame(['source_b' => 5.0], $cache->get(2));

        $cache->invalidate(1);

        $this->assertNull($cache->get(1));
        $this->assertSame(['source_b' => 5.0], $cache->get(2));
    }
}

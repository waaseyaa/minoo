<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\NorthCloudCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NorthCloudCache::class)]
final class NorthCloudCacheTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    #[Test]
    public function get_returns_null_on_cache_miss(): void
    {
        $cache = new NorthCloudCache($this->pdo);

        self::assertNull($cache->get('nonexistent'));
    }

    #[Test]
    public function set_then_get_returns_cached_value(): void
    {
        $cache = new NorthCloudCache($this->pdo);

        $cache->set('people:123', '{"people":[]}');

        self::assertSame('{"people":[]}', $cache->get('people:123'));
    }

    #[Test]
    public function expired_entry_returns_null(): void
    {
        $cache = new NorthCloudCache($this->pdo, ttl: 0);

        $cache->set('people:123', '{"people":[]}');
        sleep(1);

        self::assertNull($cache->get('people:123'));
    }

    #[Test]
    public function clear_removes_all_entries(): void
    {
        $cache = new NorthCloudCache($this->pdo);

        $cache->set('people:1', '{"people":[]}');
        $cache->set('band-office:1', '{"band_office":{}}');
        $cache->clear();

        self::assertNull($cache->get('people:1'));
        self::assertNull($cache->get('band-office:1'));
    }

    #[Test]
    public function set_overwrites_existing_entry(): void
    {
        $cache = new NorthCloudCache($this->pdo);

        $cache->set('people:1', '{"people":["old"]}');
        $cache->set('people:1', '{"people":["new"]}');

        self::assertSame('{"people":["new"]}', $cache->get('people:1'));
    }
}

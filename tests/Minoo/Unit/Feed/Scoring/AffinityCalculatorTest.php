<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Feed\Scoring;

use Minoo\Feed\Scoring\AffinityCache;
use Minoo\Feed\Scoring\AffinityCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;
use Waaseyaa\Database\DBALDatabase;

#[CoversClass(AffinityCalculator::class)]
final class AffinityCalculatorTest extends TestCase
{
    private DBALDatabase $db;
    private AffinityCache $affinityCache;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite();
        $this->affinityCache = new AffinityCache(new MemoryBackend());

        $this->createTables();
    }

    private function createTables(): void
    {
        $schema = $this->db->schema();

        $schema->createTable('follow', [
            'fields' => [
                'id' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'target_key' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
            ],
            'primary key' => ['id'],
        ]);

        $schema->createTable('reaction', [
            'fields' => [
                'id' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'source_key' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
                'created_at' => ['type' => 'varchar', 'length' => 32, 'not null' => true],
            ],
            'primary key' => ['id'],
        ]);

        $schema->createTable('comment', [
            'fields' => [
                'id' => ['type' => 'serial', 'not null' => true],
                'user_id' => ['type' => 'int', 'not null' => true],
                'source_key' => ['type' => 'varchar', 'length' => 255, 'not null' => true],
                'created_at' => ['type' => 'varchar', 'length' => 32, 'not null' => true],
            ],
            'primary key' => ['id'],
        ]);
    }

    private function makeCalculator(): AffinityCalculator
    {
        return new AffinityCalculator($this->db, $this->affinityCache);
    }

    #[Test]
    public function anonymous_user_returns_null(): void
    {
        $calc = $this->makeCalculator();

        $result = $calc->computeBatch(null, ['source_a'], null, null);

        $this->assertNull($result);
    }

    #[Test]
    public function unknown_source_gets_base_affinity(): void
    {
        $calc = $this->makeCalculator();

        $result = $calc->computeBatch(1, ['source_a'], null, null);

        $this->assertNotNull($result);
        $this->assertSame(1.0, $result['source_a']);
    }

    #[Test]
    public function follow_adds_points(): void
    {
        $this->db->insert('follow')
            ->fields(['user_id', 'target_key'])
            ->values([1, 'source_a'])
            ->execute();

        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(1, ['source_a', 'source_b'], null, null);

        $this->assertNotNull($result);
        // source_a: base(1) + follow(4) = 5
        $this->assertSame(5.0, $result['source_a']);
        // source_b: base(1) only
        $this->assertSame(1.0, $result['source_b']);
    }

    #[Test]
    public function reactions_add_capped_points(): void
    {
        $now = date('Y-m-d H:i:s');

        // Add 7 reactions — should be capped at reactionMax (5.0)
        for ($i = 0; $i < 7; $i++) {
            $this->db->insert('reaction')
                ->fields(['user_id', 'source_key', 'created_at'])
                ->values([1, 'source_a', $now])
                ->execute();
        }

        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(1, ['source_a'], null, null);

        $this->assertNotNull($result);
        // base(1) + min(7*1, 5) = 6.0
        $this->assertSame(6.0, $result['source_a']);
    }

    #[Test]
    public function comments_add_capped_points(): void
    {
        $now = date('Y-m-d H:i:s');

        // Add 5 comments — should be capped at commentMax (6.0)
        for ($i = 0; $i < 5; $i++) {
            $this->db->insert('comment')
                ->fields(['user_id', 'source_key', 'created_at'])
                ->values([1, 'source_a', $now])
                ->execute();
        }

        $calc = $this->makeCalculator();
        $result = $calc->computeBatch(1, ['source_a'], null, null);

        $this->assertNotNull($result);
        // base(1) + min(5*2, 6) = 7.0
        $this->assertSame(7.0, $result['source_a']);
    }

    #[Test]
    public function same_community_adds_points(): void
    {
        $calc = $this->makeCalculator();

        $sourceLocations = [
            'source_a' => ['lat' => 46.0, 'lon' => -81.0, 'community_id' => 10],
            'source_b' => ['lat' => 46.0, 'lon' => -81.0, 'community_id' => 20],
        ];

        $result = $calc->computeBatch(1, ['source_a', 'source_b'], 10, null, $sourceLocations);

        $this->assertNotNull($result);
        // source_a: base(1) + sameCommunity(3) = 4
        $this->assertSame(4.0, $result['source_a']);
        // source_b: base(1) only (different community)
        $this->assertSame(1.0, $result['source_b']);
    }

    #[Test]
    public function geo_proximity_close_adds_points(): void
    {
        $calc = $this->makeCalculator();

        // Two points ~11 km apart (within 50 km)
        $userLocation = ['lat' => 46.0, 'lon' => -81.0];
        $sourceLocations = [
            'source_a' => ['lat' => 46.1, 'lon' => -81.0],
        ];

        $result = $calc->computeBatch(1, ['source_a'], null, $userLocation, $sourceLocations);

        $this->assertNotNull($result);
        // base(1) + geoClose(2) = 3
        $this->assertSame(3.0, $result['source_a']);
    }

    #[Test]
    public function geo_proximity_mid_adds_points(): void
    {
        $calc = $this->makeCalculator();

        // Two points ~111 km apart (between 50 and 150 km)
        $userLocation = ['lat' => 46.0, 'lon' => -81.0];
        $sourceLocations = [
            'source_a' => ['lat' => 47.0, 'lon' => -81.0],
        ];

        $result = $calc->computeBatch(1, ['source_a'], null, $userLocation, $sourceLocations);

        $this->assertNotNull($result);
        // base(1) + geoMid(1) = 2
        $this->assertSame(2.0, $result['source_a']);
    }

    #[Test]
    public function geo_beyond_mid_range_gets_no_bonus(): void
    {
        $calc = $this->makeCalculator();

        // Two points ~555 km apart (beyond 150 km)
        $userLocation = ['lat' => 46.0, 'lon' => -81.0];
        $sourceLocations = [
            'source_a' => ['lat' => 51.0, 'lon' => -81.0],
        ];

        $result = $calc->computeBatch(1, ['source_a'], null, $userLocation, $sourceLocations);

        $this->assertNotNull($result);
        // base(1) only
        $this->assertSame(1.0, $result['source_a']);
    }

    #[Test]
    public function cache_is_used_on_second_call(): void
    {
        $this->db->insert('follow')
            ->fields(['user_id', 'target_key'])
            ->values([1, 'source_a'])
            ->execute();

        $calc = $this->makeCalculator();

        // First call populates cache.
        $result1 = $calc->computeBatch(1, ['source_a'], null, null);
        $this->assertSame(5.0, $result1['source_a']);

        // Remove the follow row — second call should still return cached value.
        $this->db->delete('follow')
            ->condition('user_id', 1)
            ->execute();

        $result2 = $calc->computeBatch(1, ['source_a'], null, null);
        $this->assertSame(5.0, $result2['source_a']);
    }

    #[Test]
    public function empty_source_keys_returns_empty_array(): void
    {
        $calc = $this->makeCalculator();

        $result = $calc->computeBatch(1, [], null, null);

        $this->assertSame([], $result);
    }
}
